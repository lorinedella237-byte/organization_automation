<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\WorkspaceProvisioner;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The workspace home.
 *
 * Two of the figures DashboardService returns are organization wide rather than
 * scoped to the viewer: draft payroll periods, and leave awaiting a decision.
 * The page gates both behind the permission that owns them, so the tests that
 * matter most here are the ones asserting what a least privileged member is
 * NOT told.
 */
class DashboardScreenRenderTest extends TestCase
{
    private Organization $organization;

    private OrganizationMembership $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->organization = app(WorkspaceProvisioner::class)->bootstrap(User::factory()->create(), [
            'name' => 'Harbour Authority', 'slug' => 'harbour', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN',
        ]);
        $this->owner = $this->organization->memberships()->sole();
    }

    private function memberWithRole(string $code): OrganizationMembership
    {
        $membership = OrganizationMembership::create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
        ]);

        DB::table('membership_roles')->insert([
            'organization_id' => $this->organization->id,
            'organization_membership_id' => $membership->id,
            'role_id' => Role::where('organization_id', $this->organization->id)->where('code', $code)->value('id'),
            'assigned_by_membership_id' => $this->owner->id,
            'created_at' => now(),
        ]);

        return $membership;
    }

    private function projectWithTasks(): Project
    {
        $project = Project::create([
            'organization_id' => $this->organization->id,
            'owner_membership_id' => $this->owner->id,
            'name' => 'Quay Rebuild',
            'status' => 'active',
        ]);

        foreach ([['todo', '-3 days'], ['in_progress', '+5 days'], ['done', '-9 days']] as [$status, $due]) {
            $project->tasks()->create([
                'organization_id' => $this->organization->id,
                'title' => 'Task '.$status,
                'status' => $status,
                'due_on' => now()->modify($due)->format('Y-m-d'),
            ]);
        }

        return $project;
    }

    public function test_the_home_page_reports_the_counts_the_service_supplies(): void
    {
        $this->projectWithTasks();

        $this->actingAs($this->owner->user)
            ->get('/home')
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('Active projects')
            ->assertSee('Open tasks')
            ->assertSee('Overdue tasks')
            // Two tasks are open and only the one in the past is overdue; the
            // finished task with a past due date is neither.
            ->assertSee('Past their due date')
            ->assertSee('Harbour Authority');
    }

    public function test_a_quiet_count_is_not_dressed_up_as_a_problem(): void
    {
        $this->actingAs($this->owner->user)
            ->get('/home')
            ->assertOk()
            ->assertSee('Nothing past its due date')
            ->assertDontSee('Past their due date');
    }

    public function test_a_least_privileged_member_is_not_told_the_organizations_finance_or_leave_position(): void
    {
        $member = $this->memberWithRole('staff');

        $this->actingAs($member->user)
            ->get('/home')
            ->assertOk()
            // Scoped to them, so these are theirs to see.
            ->assertSee('Open tasks')
            // Organization wide in the service, so the page must withhold them.
            ->assertDontSee('Draft payroll periods')
            ->assertDontSee('Leave awaiting a decision')
            // staff.view at own scope would report a staff count of one, which
            // is not a workspace figure and is not shown.
            ->assertDontSee('Active staff');
    }

    public function test_an_owner_is_told_all_of_it(): void
    {
        $this->actingAs($this->owner->user)
            ->get('/home')
            ->assertOk()
            ->assertSee('Draft payroll periods')
            ->assertSee('Leave awaiting a decision')
            ->assertSee('Active staff');
    }

    public function test_the_attendance_line_follows_the_viewers_own_open_session(): void
    {
        $this->actingAs($this->owner->user)->get('/home')->assertOk()->assertSee('Not clocked in');

        DB::table('attendance_sessions')->insert([
            'organization_id' => $this->organization->id,
            'membership_id' => $this->owner->id,
            'local_date' => now()->format('Y-m-d'),
            'clocked_in_at' => now(),
            'status' => 'present',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get('/home')->assertOk()->assertSee('Clocked in');
    }

    public function test_someone_with_no_membership_gets_an_explanation_rather_than_an_error(): void
    {
        // The home route allows a missing workspace, so this page is reachable
        // before anyone has been invited anywhere.
        $this->actingAs(User::factory()->create())
            ->get('/home')
            ->assertOk()
            ->assertSee('You are not in a workspace yet')
            ->assertDontSee('Active projects');
    }
}
