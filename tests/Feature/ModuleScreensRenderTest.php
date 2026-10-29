<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\WorkspaceProvisioner;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Phase 3 to Phase 7 module screens.
 *
 * Four of these lists were unscoped before this session: attendance, leave,
 * reports and documents each returned the whole organization to anyone whose
 * permission reached the page at all. The rendering tests are the cheap half
 * of this file; the half that matters asserts what a least privileged member
 * is NOT shown.
 */
class ModuleScreensRenderTest extends TestCase
{
    private Organization $organization;

    private OrganizationMembership $owner;

    private OrganizationMembership $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->organization = app(WorkspaceProvisioner::class)->bootstrap(User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Rowe']), [
            'name' => 'Harbour Authority', 'slug' => 'harbour', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN',
        ]);
        $this->owner = $this->organization->memberships()->sole();
        $this->member = $this->memberWithRole('staff', 'Tolu', 'Adeyemi');
    }

    private function memberWithRole(string $code, string $first, string $last): OrganizationMembership
    {
        $membership = OrganizationMembership::create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create(['first_name' => $first, 'last_name' => $last])->id,
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

    /**
     * AuthenticateSession keeps the signed-in user's password hash in the
     * session and tears the session down when it stops matching. Swapping
     * identity inside one test has to start a clean session, the way a real
     * second sign-in does, or every request after the swap redirects.
     */
    private function actingFreshly(OrganizationMembership $membership): static
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $this->actingAs($membership->user);
    }

    private function paths(): array
    {
        return [
            '/workspace/tasks/my',
            '/workspace/activities',
            '/workspace/work-logs',
            '/workspace/reports',
            '/workspace/attendance',
            '/workspace/leave',
            '/workspace/payroll',
            '/workspace/documents',
            '/workspace/announcements',
        ];
    }

    public function test_every_module_screen_renders_html_for_a_browser(): void
    {
        $this->actingAs($this->owner->user);

        foreach ($this->paths() as $path) {
            $this->get($path)
                ->assertOk()
                ->assertHeader('content-type', 'text/html; charset=UTF-8');
        }
    }

    public function test_every_module_screen_still_answers_json(): void
    {
        $this->actingAs($this->owner->user);

        foreach ($this->paths() as $path) {
            $this->getJson($path)->assertOk();
        }
    }

    public function test_a_least_privileged_member_reaches_every_screen_their_role_allows(): void
    {
        $this->actingAs($this->member->user);

        // Staff holds own-scoped grants for all of these, so none should be a
        // 403: the pages narrow instead of refusing.
        foreach (['/workspace/tasks/my', '/workspace/activities', '/workspace/work-logs', '/workspace/reports', '/workspace/attendance', '/workspace/leave', '/workspace/payroll', '/workspace/documents', '/workspace/announcements'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_attendance_does_not_show_a_member_another_persons_sessions(): void
    {
        DB::table('attendance_sessions')->insert([
            'organization_id' => $this->organization->id,
            'membership_id' => $this->owner->id,
            'local_date' => now()->format('Y-m-d'),
            'clocked_in_at' => now()->subHours(3),
            'clocked_out_at' => now(),
            'duration_minutes' => 180,
            'status' => 'complete',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // The owner's session is the only one that exists, and the member's
        // attendance.view is scoped to their own record.
        $this->actingAs($this->member->user)
            ->get('/workspace/attendance')
            ->assertOk()
            ->assertDontSee('Ada Rowe')
            ->assertSee('No attendance recorded yet');

        $this->actingFreshly($this->owner)
            ->get('/workspace/attendance')
            ->assertOk()
            ->assertDontSee('No attendance recorded yet');
    }

    public function test_leave_does_not_show_a_member_another_persons_pending_request(): void
    {
        $type = DB::table('leave_types')->insertGetId([
            'organization_id' => $this->organization->id, 'name' => 'Annual', 'requires_reason' => false,
            'is_paid' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('leave_requests')->insert([
            'organization_id' => $this->organization->id,
            'membership_id' => $this->owner->id,
            'leave_type_id' => $type,
            'starts_on' => now()->addWeek()->format('Y-m-d'),
            'ends_on' => now()->addWeek()->addDays(2)->format('Y-m-d'),
            'days' => 3, 'reason' => 'Family matter', 'status' => 'pending_manager',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // A pending request used to be listed to everyone, name and reason.
        $this->actingAs($this->member->user)
            ->get('/workspace/leave')
            ->assertOk()
            ->assertDontSee('Ada Rowe')
            ->assertDontSee('Family matter');

        $this->actingFreshly($this->owner)
            ->get('/workspace/leave')
            ->assertOk()
            ->assertSee('Family matter');
    }

    public function test_reports_does_not_show_a_member_another_persons_submitted_report(): void
    {
        $template = DB::table('report_templates')->insertGetId([
            'organization_id' => $this->organization->id, 'name' => 'Weekly operations', 'code' => 'WEEKLY',
            'period' => 'weekly', 'requires_attachment' => false, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('reports')->insert([
            'organization_id' => $this->organization->id,
            'template_id' => $template,
            'membership_id' => $this->owner->id,
            'period_start' => now()->startOfWeek()->format('Y-m-d'),
            'period_end' => now()->endOfWeek()->format('Y-m-d'),
            'status' => 'submitted', 'submitted_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->member->user)
            ->get('/workspace/reports')
            ->assertOk()
            ->assertDontSee('Weekly operations')
            ->assertSee('No reports yet');

        $this->actingFreshly($this->owner)
            ->get('/workspace/reports')
            ->assertOk()
            ->assertSee('Weekly operations');
    }

    public function test_the_library_lists_only_files_the_reader_may_open(): void
    {
        $private = $this->document('Board minutes.pdf', $this->owner);
        $shared = $this->document('Staff handbook.pdf', $this->owner);

        // Shared with the whole organization: all three target columns null.
        DB::table('document_shares')->insert([
            'organization_id' => $this->organization->id, 'document_id' => $shared->id,
            'membership_id' => null, 'department_id' => null, 'team_id' => null,
            'permission' => 'download', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->member->user)
            ->get('/workspace/documents')
            ->assertOk()
            ->assertSee('Staff handbook.pdf')
            // The file list is itself confidential; naming it is the leak.
            ->assertDontSee('Board minutes.pdf');

        $this->assertNotNull($private->id);
    }

    public function test_a_file_shared_with_one_person_is_not_readable_by_someone_with_no_department(): void
    {
        $document = $this->document('Salary review.pdf', $this->owner);

        $other = $this->memberWithRole('staff', 'Kemi', 'Bello');

        DB::table('document_shares')->insert([
            'organization_id' => $this->organization->id, 'document_id' => $document->id,
            'membership_id' => $other->id, 'department_id' => null, 'team_id' => null,
            'permission' => 'download', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // The share targets one person. The reader below belongs to no
        // department and no team, which used to turn the unguarded
        // `department_id = null` comparison into a match on every such row.
        $this->assertNull($this->member->department_id);

        $this->actingAs($this->member->user)
            ->get('/workspace/documents')
            ->assertOk()
            ->assertDontSee('Salary review.pdf');

        $this->actingFreshly($this->member)
            ->get('/workspace/documents/'.$document->id.'/download')
            ->assertForbidden();

        // The person it was actually shared with still gets it.
        $this->actingFreshly($other)
            ->get('/workspace/documents')
            ->assertOk()
            ->assertSee('Salary review.pdf');
    }

    private function document(string $name, OrganizationMembership $uploader): Document
    {
        $document = Document::create([
            'organization_id' => $this->organization->id,
            'uploaded_by_membership_id' => $uploader->id,
            'name' => $name,
            'status' => 'available',
        ]);

        $document->versions()->create([
            'organization_id' => $this->organization->id,
            'version' => 1,
            'storage_key' => 'test/'.$document->id.'.bin',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'sha256' => str_repeat('a', 64),
            'scan_status' => 'clean',
        ]);

        return $document->load('versions');
    }
}
