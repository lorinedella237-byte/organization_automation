<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use App\Notifications\WorkspaceInvitationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function __construct(private WorkspaceAccess $access, private RoleService $roles, private AuditRecorder $audit, private WorkspaceNotifications $notifications) {}

    public function available(OrganizationInvitation $invitation): bool
    {
        if (! $invitation->isAvailable() || ! Organization::whereKey($invitation->organization_id)->where('status', 'active')->exists()) {
            return false;
        }
        $role = Role::where('organization_id', $invitation->organization_id)->whereKey($invitation->role_id)->where('status', 'Active')->first();
        if (! $role) {
            return false;
        }
        if ($invitation->platform_actor_id) {
            return DB::table('platform_administrators')->where('user_id', $invitation->platform_actor_id)->exists();
        }
        $actor = OrganizationMembership::where('organization_id', $invitation->organization_id)->find($invitation->invited_by_membership_id);

        return $actor && $this->access->allows($actor, 'staff.invite') && $this->roles->canDelegate($actor, $role);
    }

    public function issue(OrganizationMembership $actor, array $input): OrganizationInvitation
    {
        return DB::transaction(function () use ($actor, $input) {
            Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            $this->access->authorize($actor, 'staff.invite');
            $data = Validator::make($input, ['email' => ['required', 'email', 'max:255'], 'role_id' => ['required', 'integer']])->validate();
            $role = Role::where('organization_id', $actor->organization_id)->findOrFail($data['role_id']);
            abort_unless($this->roles->canDelegate($actor, $role), 403, 'Cannot invite someone into a role you cannot delegate.');

            return $this->create($actor->organization_id, $data['email'], $role, $actor);
        });
    }

    public function issueOwner(User $platformActor, Organization $organization, string $email): OrganizationInvitation
    {
        abort_unless(DB::table('platform_administrators')->where('user_id', $platformActor->id)->exists(), 403);
        $role = Role::where('organization_id', $organization->id)->where('code', 'owner')->firstOrFail();

        return $this->create($organization->id, $email, $role, null, $platformActor);
    }

    private function create(int $organizationId, string $email, Role $role, ?OrganizationMembership $actor, ?User $platformActor = null): OrganizationInvitation
    {
        $email = strtolower(trim($email));
        if (OrganizationMembership::where('organization_id', $organizationId)->whereHas('user', fn ($query) => $query->where('email', $email))->exists()) {
            throw ValidationException::withMessages(['email' => 'This address already has a membership. Manage its status instead.']);
        }
        if (OrganizationInvitation::where('organization_id', $organizationId)->where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists()) {
            throw ValidationException::withMessages(['email' => 'An active invitation already exists. Revoke it before sending another.']);
        }
        $token = Str::random(64);
        $invitation = OrganizationInvitation::create([
            'organization_id' => $organizationId, 'email' => $email, 'role_id' => $role->id,
            'token_hash' => hash('sha256', $token), 'invited_by_membership_id' => $actor?->id,
            'platform_actor_id' => $platformActor?->id, 'expires_at' => now()->addDays(7),
        ]);
        $this->audit->record($organizationId, $actor, 'invitation.created', 'invitation', $invitation->id, [], $platformActor);
        Notification::route('mail', $email)->notify(new WorkspaceInvitationMail($invitation->id, $token));

        return $invitation;
    }

    public function find(string $token): OrganizationInvitation
    {
        $invitation = OrganizationInvitation::where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_unless($this->available($invitation), 410, 'This invitation is expired or no longer available.');

        return $invitation;
    }

    public function accept(string $token, array $input, ?User $signedIn): OrganizationMembership
    {
        // Resolve without disclosure, then lock the workspace before its invitation and identity.
        $invitation = OrganizationInvitation::where('token_hash', hash('sha256', $token))->firstOrFail();

        return DB::transaction(function () use ($invitation, $input, $signedIn) {
            Organization::whereKey($invitation->organization_id)->lockForUpdate()->firstOrFail();
            $invitation = OrganizationInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->available($invitation), 410, 'This invitation is expired or no longer available.');
            $user = User::where('email', $invitation->email)->lockForUpdate()->first();
            abort_if($signedIn && (! $user || $signedIn->id !== $user->id), 403, 'Sign out before accepting an invitation for another account.');
            if ($user) {
                if (! $signedIn && (! is_string($input['password'] ?? null) || ! Hash::check($input['password'], $user->password))) {
                    throw ValidationException::withMessages(['password' => 'Enter your existing account password to accept this invitation.']);
                }
            } else {
                $data = Validator::make($input, [
                    'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
                    'middle_name' => ['nullable', 'string', 'max:100'], 'phone' => ['nullable', 'string', 'max:40'],
                    'password' => ['required', 'confirmed', Password::min(12), 'max:4096'],
                ])->validate();
                unset($data['password_confirmation']);
                $user = User::create($data + ['email' => $invitation->email]);
            }
            if (OrganizationMembership::where('organization_id', $invitation->organization_id)->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['token' => 'This account already has a membership.']);
            }
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
            $member = OrganizationMembership::create(['organization_id' => $invitation->organization_id, 'user_id' => $user->id]);
            DB::table('membership_roles')->insert([
                'organization_id' => $member->organization_id, 'organization_membership_id' => $member->id,
                'role_id' => $invitation->role_id, 'assigned_by_membership_id' => $invitation->invited_by_membership_id, 'created_at' => now(),
            ]);
            $invitation->update(['accepted_at' => now()]);
            $this->audit->record($member->organization_id, $member, 'invitation.accepted', 'invitation', $invitation->id);
            $this->notifications->record($member, 'invitation.accepted:'.$invitation->id, 'Your workspace membership is active.');

            return $member;
        });
    }

    public function revoke(OrganizationMembership $actor, int $invitationId): void
    {
        DB::transaction(function () use ($actor, $invitationId) {
            Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            $this->access->authorize($actor, 'staff.invite');
            $invitation = OrganizationInvitation::where('organization_id', $actor->organization_id)->findOrFail($invitationId);
            abort_if($invitation->accepted_at, 409, 'Accepted invitations cannot be revoked. Manage the membership instead.');
            $invitation->update(['revoked_at' => now()]);
            $this->audit->record($actor->organization_id, $actor, 'invitation.revoked', 'invitation', $invitation->id);
        });
    }
}
