<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\WorkspaceProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Every workspace screen is served as HTML to a browser and as JSON to an API
 * caller, from the same action. These tests assert the HTML branch, which the
 * backend tests do not cover: they all send Accept: application/json.
 *
 * The point is to catch a view that throws once real data reaches it, which is
 * how the breadcrumb and groupBy defects were found by hand earlier.
 */
class WorkspaceScreensRenderTest extends TestCase
{
    private Organization $organization;

    private OrganizationMembership $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->organization = app(WorkspaceProvisioner::class)->bootstrap(User::factory()->create(), [
            'name' => 'Screens', 'slug' => 'screens', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN',
        ]);
        $this->owner = $this->organization->memberships()->sole();
        Notification::fake();
    }

    public function test_every_workspace_screen_renders_html_for_a_browser(): void
    {
        $this->actingAs($this->owner->user);

        foreach ([
            '/workspace/staff',
            '/workspace/staff/'.$this->owner->id,
            '/workspace/roles',
            '/workspace/departments',
            '/workspace/teams',
            '/workspace/audit',
            '/workspace/settings',
            '/workspace/invitations',
            '/workspace/notifications',
        ] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertHeader('content-type', 'text/html; charset=UTF-8');
        }
    }

    public function test_role_editor_renders_the_matrix_for_a_chosen_role(): void
    {
        $role = Role::where('organization_id', $this->organization->id)->where('code', 'staff')->sole();

        $this->actingAs($this->owner->user)
            ->get('/workspace/roles?role='.$role->id)
            ->assertOk()
            // A protected role is readable but offers no Save control at all.
            ->assertSee('Supplied with the system')
            ->assertDontSee('Save '.$role->name)
            // The ability code itself must render, not the group index that the
            // earlier groupBy defect produced.
            ->assertSee('staff.view');
    }

    public function test_the_same_actions_still_answer_json_for_an_api_caller(): void
    {
        $this->actingAs($this->owner->user);

        foreach (['/workspace/staff', '/workspace/audit', '/workspace/invitations', '/workspace/notifications'] as $path) {
            $this->getJson($path)->assertOk()->assertJsonStructure(['data']);
        }
    }

    public function test_invitation_can_be_sent_and_revoked_from_the_browser_form(): void
    {
        $this->actingAs($this->owner->user);

        $role = Role::where('organization_id', $this->organization->id)->where('code', 'staff')->sole();

        // A browser form posts without Accept: application/json and must be
        // answered with a redirect, never a JSON body rendered as text.
        $this->post('/workspace/invitations', [
            'email' => 'Newcomer@Example.Test',
            'role_id' => $role->id,
        ])->assertRedirect('/workspace/invitations');

        $invitation = \App\Models\OrganizationInvitation::sole();
        $this->assertSame('newcomer@example.test', $invitation->email);

        $this->get('/workspace/invitations')->assertOk()->assertSee('newcomer@example.test');

        $this->delete('/workspace/invitations/'.$invitation->id)
            ->assertRedirect('/workspace/invitations');

        $this->assertNotNull($invitation->fresh()->revoked_at);
    }

    public function test_staff_detail_write_paths_accept_a_browser_submission(): void
    {
        $this->actingAs($this->owner->user);

        // Posting: the server requires a reason on every write, and the form
        // supplies one. These three paths were rendered but never submitted.
        $this->patch('/workspace/staff/'.$this->owner->id, [
            'title' => 'Head of Operations',
            'reason' => 'Correcting the recorded job title.',
        ])->assertRedirect();

        $this->assertSame('Head of Operations', $this->owner->fresh()->title);

        $role = Role::where('organization_id', $this->organization->id)->where('code', 'owner')->sole();

        $this->put('/workspace/staff/'.$this->owner->id.'/roles', [
            'roles' => [$role->id],
            'reason' => 'Confirming the owner role.',
        ])->assertRedirect();
    }

    public function test_status_change_accepts_a_browser_submission(): void
    {
        $this->actingAs($this->owner->user);

        // The last owner cannot be suspended, so the status path is exercised
        // on a second membership rather than on the actor.
        $subject = OrganizationMembership::create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->post('/workspace/staff/'.$subject->id.'/status', [
            'status' => 'suspended',
            'reason' => 'Suspended pending an access review.',
        ])->assertRedirect();

        $this->assertSame('suspended', $subject->fresh()->status);

        // The three options the form offers are exactly the three the server
        // accepts, so each must round trip.
        foreach (['active', 'offboarded'] as $status) {
            $this->post('/workspace/staff/'.$subject->id.'/status', [
                'status' => $status,
                'reason' => 'Exercising the '.$status.' transition.',
            ])->assertRedirect();

            $this->assertSame($status, $subject->fresh()->status);
        }
    }

    public function test_audit_viewer_separates_no_details_from_recorded_details(): void
    {
        $this->actingAs($this->owner->user);

        // Provisioning records an entry with no metadata, which the column
        // stores as the JSON string "[]". That is not blank, so a plain
        // emptiness check printed the two characters to the reader.
        $this->get('/workspace/audit')
            ->assertOk()
            ->assertSee('None')
            ->assertDontSee('[]');

        $subject = OrganizationMembership::create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->post('/workspace/staff/'.$subject->id.'/status', [
            'status' => 'suspended',
            'reason' => 'Suspended pending an access review.',
        ])->assertRedirect();

        $this->get('/workspace/audit')
            ->assertOk()
            ->assertSee('before status: active', false)
            ->assertSee('after status: suspended', false)
            ->assertSee('Suspended pending an access review.');
    }

    public function test_a_denied_page_returns_a_signed_in_reader_to_the_application(): void
    {
        $member = $this->memberWithRole('staff');

        // The shell is not rendered on an error page, so the only way back is
        // the link the page itself offers. Sending a signed-in reader to the
        // public landing page puts them outside the application they are in.
        $this->actingAs($member->user)
            ->get('/workspace/roles')
            ->assertForbidden()
            ->assertSee(url('/home'))
            // A suspended membership is denied the dashboard too, so the page
            // has to carry the sign out its own message tells them to use.
            ->assertSee('Sign out');
    }

    public function test_an_error_page_keeps_the_public_link_for_a_guest(): void
    {
        // No session has started for an unmatched URL, so the page must fall
        // back rather than try to read who the reader is.
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('Back to safety')
            ->assertDontSee('Sign out');
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
}
