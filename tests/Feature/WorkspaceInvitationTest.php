<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use App\Notifications\WorkspaceInvitationMail;
use App\Services\InvitationService;
use App\Services\WorkspaceProvisioner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WorkspaceInvitationTest extends TestCase
{
    private Organization $organization;
    private OrganizationMembership $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->organization = app(WorkspaceProvisioner::class)->bootstrap(User::factory()->create(), [
            'name' => 'Invitations', 'slug' => 'invitations', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN',
        ]);
        $this->owner = $this->organization->memberships()->sole();
        Notification::fake();
    }

    private function invite(string $email = 'invitee@example.test'): array
    {
        $role = Role::where('organization_id', $this->organization->id)->where('code', 'staff')->sole();
        $invitation = app(InvitationService::class)->issue($this->owner, ['email' => $email, 'role_id' => $role->id]);
        $token = null;
        Notification::assertSentOnDemand(WorkspaceInvitationMail::class, function ($notification) use ($invitation, &$token) {
            if ($notification->invitationId !== $invitation->id) {
                return false;
            }
            $token = $notification->token;

            return true;
        });

        return [$invitation, $token];
    }

    private function newAccount(string $token): array
    {
        return ['token' => $token, 'first_name' => 'New', 'last_name' => 'Staff', 'password' => 'NewAccountPass!2026', 'password_confirmation' => 'NewAccountPass!2026'];
    }

    public function test_invitation_creates_verified_membership_once_and_never_returns_raw_token(): void
    {
        [$invitation, $token] = $this->invite();
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertArrayNotHasKey('token_hash', $invitation->toArray());
        $this->get('/invitations/'.$token)->assertOk()->assertViewIs('auth.accept-invitation');
        $this->post('/invitations/accept', $this->newAccount($token))->assertRedirect('/login');
        $user = User::where('email', $invitation->email)->sole();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('NewAccountPass!2026', $user->password));
        $this->assertSame(1, $user->memberships()->count());
        $this->assertSame('staff', $user->memberships()->sole()->roles()->sole()->code);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->post('/invitations/accept', $this->newAccount($token))->assertStatus(410);
        $this->assertSame(1, $user->memberships()->count());
        $this->assertDatabaseCount('workspace_notifications', 1);
        $this->assertStringNotContainsString($token, DB::table('audit_logs')->get()->toJson());
    }

    public function test_expired_revoked_and_suspended_issuer_invitations_cannot_be_accepted(): void
    {
        [$expired, $expiredToken] = $this->invite('expired@example.test');
        $expired->update(['expires_at' => now()->subMinute()]);
        $this->post('/invitations/accept', $this->newAccount($expiredToken))->assertStatus(410);
        [$revoked, $revokedToken] = $this->invite('revoked@example.test');
        app(InvitationService::class)->revoke($this->owner, $revoked->id);
        $this->post('/invitations/accept', $this->newAccount($revokedToken))->assertStatus(410);
        [$pending, $pendingToken] = $this->invite('pending@example.test');
        $this->owner->update(['status' => 'suspended']);
        $this->post('/invitations/accept', $this->newAccount($pendingToken))->assertStatus(410);
        $this->assertFalse((new WorkspaceInvitationMail($pending->id, $pendingToken))->shouldSend(new \stdClass(), 'mail'));
        $this->assertSame(1, User::count());
    }

    public function test_existing_account_requires_its_password_and_preserves_identity(): void
    {
        $existing = User::factory()->create(['password' => 'ExistingPassword!2026']);
        $before = $existing->fresh()->getAttributes();
        [$invitation, $token] = $this->invite($existing->email);
        $this->get('/invitations/'.$token)->assertOk()->assertSee('Current password')->assertDontSee('Create a password');
        $this->postJson('/invitations/accept', ['token' => $token, 'password' => 'wrong'])->assertUnprocessable();
        $this->post('/invitations/accept', ['token' => $token, 'password' => 'ExistingPassword!2026', 'first_name' => 'Overwrite'])
            ->assertRedirect('/login');
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertSame(1, $existing->memberships()->count());
    }

    public function test_duplicate_invites_and_foreign_role_ids_are_rejected(): void
    {
        [$invitation] = $this->invite();
        $this->actingAs($this->owner->user)->withSession(['current_organization_id' => $this->organization->id]);
        $this->postJson('/workspace/invitations', ['email' => $invitation->email, 'role_id' => $invitation->role_id])->assertUnprocessable();
        $other = app(WorkspaceProvisioner::class)->bootstrap(User::factory()->create(), ['name' => 'Foreign', 'slug' => 'foreign', 'timezone' => 'UTC', 'currency' => 'USD']);
        $foreignRole = Role::where('organization_id', $other->id)->firstOrFail();
        $this->postJson('/workspace/invitations', ['email' => 'foreign@example.test', 'role_id' => $foreignRole->id])->assertNotFound();
        $this->getJson('/workspace/invitations')->assertOk()->assertJsonMissing(['token_hash' => $invitation->token_hash]);
    }

    public function test_organization_admin_can_invite_staff_but_cannot_invite_finance(): void
    {
        $admin = OrganizationMembership::create(['organization_id' => $this->organization->id, 'user_id' => User::factory()->create()->id]);
        $adminRole = Role::where('organization_id', $this->organization->id)->where('code', 'organization_admin')->sole();
        app(\App\Services\RoleService::class)->assign($this->owner, $admin, [$adminRole->id]);
        $this->actingAs($admin->user)->withSession(['current_organization_id' => $this->organization->id]);
        $staff = Role::where('organization_id', $this->organization->id)->where('code', 'staff')->sole();
        $finance = Role::where('organization_id', $this->organization->id)->where('code', 'finance_officer')->sole();
        $this->postJson('/workspace/invitations', ['email' => 'staff@example.test', 'role_id' => $staff->id])->assertCreated();
        $this->postJson('/workspace/invitations', ['email' => 'finance@example.test', 'role_id' => $finance->id])->assertForbidden();
    }

    public function test_notification_read_access_is_per_membership_and_deduplicated(): void
    {
        [$invitation, $token] = $this->invite();
        $member = app(InvitationService::class)->accept($token, $this->newAccount($token), null);
        app(\App\Services\WorkspaceNotifications::class)->record($member, 'invitation.accepted:'.$invitation->id, 'Duplicate');
        $this->assertDatabaseCount('workspace_notifications', 1);
        $id = DB::table('workspace_notifications')->value('id');
        $this->actingAs($this->owner->user)->withSession(['current_organization_id' => $this->organization->id]);
        $this->postJson('/workspace/notifications/'.$id.'/read')->assertNotFound();
        $this->actingAs($member->user)->withSession(['password_hash_web' => $member->user->getAuthPassword()]);
        $this->getJson('/workspace/notifications')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/workspace/notifications/'.$id.'/read')->assertOk();
        $this->assertNotNull(DB::table('workspace_notifications')->value('read_at'));
    }

    public function test_platform_provisioner_invites_owner_without_receiving_workspace_access(): void
    {
        $platform = User::factory()->create();
        $this->actingAs($platform);
        $payload = ['name' => 'Pilot', 'slug' => 'pilot', 'timezone' => 'UTC', 'currency' => 'USD', 'owner_email' => 'pilot-owner@example.test'];
        $this->postJson('/platform/organizations', $payload)->assertForbidden();
        $this->artisan('platform:grant', ['email' => $platform->email])->assertExitCode(0);
        $response = $this->postJson('/platform/organizations', $payload)->assertCreated();
        $this->assertSame(0, $platform->memberships()->count());
        $this->postJson('/workspace/switch', ['organization' => $response->json('data.id')])->assertNotFound();
        $invitation = OrganizationInvitation::where('email', 'pilot-owner@example.test')->sole();
        $this->assertTrue(app(InvitationService::class)->available($invitation));
        $this->assertSame($platform->id, DB::table('audit_logs')->where('entity_type', 'invitation')->where('entity_id', $invitation->id)->value('user_id'));
    }

    public function test_verification_requires_valid_signed_link_for_current_account(): void
    {
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified);
        $this->get('/workspace/staff')->assertRedirect('/verify-email');
        $this->post('/email/verification-notification')->assertSessionHas('status');
        Notification::assertSentTo($unverified, \Illuminate\Auth\Notifications\VerifyEmail::class);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $unverified->id, 'hash' => sha1($unverified->email)]);
        $this->get($url.'&tampered=1')->assertForbidden();
        $this->get($url)->assertRedirect('/home');
        $this->assertTrue($unverified->fresh()->hasVerifiedEmail());
    }

    public function test_changed_password_invalidates_previously_authenticated_session(): void
    {
        $user = $this->owner->user;
        $this->actingAs($user)->withSession(['password_hash_web' => $user->getAuthPassword()]);
        $user->update(['password' => 'ChangedPassword!2026']);
        $this->getJson('/home')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_invitation_mail_queue_payload_is_encrypted_and_dispatched_after_commit(): void
    {
        Notification::swap(new \Illuminate\Notifications\ChannelManager($this->app));
        Queue::fake();
        $role = Role::where('organization_id', $this->organization->id)->where('code', 'staff')->sole();
        app(InvitationService::class)->issue($this->owner, ['email' => 'queued@example.test', 'role_id' => $role->id]);
        Queue::assertPushed(\Illuminate\Notifications\SendQueuedNotifications::class, fn ($job) => $job->shouldBeEncrypted && $job->afterCommit && $job->tries === 3);
    }
}
