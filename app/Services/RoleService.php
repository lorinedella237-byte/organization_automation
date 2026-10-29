<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleService
{
    public function __construct(private WorkspaceAccess $access, private AuditRecorder $audit) {}

    public function seedTemplates(Organization $organization): void
    {
        foreach (config('permissions.abilities') as $ability => $scopes) {
            [$module, $action] = explode('.', $ability, 2);
            DB::table('permissions')->insertOrIgnore([
                'code' => $ability, 'module' => $module, 'action' => $action, 'resource' => $module,
                'name' => $ability, 'scope' => 'own', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (config('permissions.templates') as $code => $template) {
            $role = Role::firstOrCreate(['organization_id' => $organization->id, 'code' => $code], [
                'name' => $template['name'], 'is_system_role' => true, 'status' => 'Active',
            ]);
            // Never rewrite existing roles/grants during a repeated seed.
            if (! $role->wasRecentlyCreated) {
                continue;
            }
            $grants = $template['grants'] ?? [];
            if ($template['all'] ?? false) {
                foreach (config('permissions.abilities') as $ability => $scopes) {
                    $grants[$ability] = in_array('organization', $scopes, true) ? 'organization' : $scopes[0];
                }
            }
            foreach ($grants as $ability => $scope) {
                $this->insertGrant($role, $ability, $scope);
            }
        }
    }

    private function insertGrant(Role $role, string $ability, string $scope): void
    {
        DB::table('role_permissions')->insert([
            'organization_id' => $role->organization_id, 'role_id' => $role->id,
            'permission_id' => DB::table('permissions')->where('code', $ability)->value('id'), 'scope' => $scope,
        ]);
    }

    public function save(OrganizationMembership $actor, array $data, ?Role $role = null): Role
    {
        return DB::transaction(function () use ($actor, $data, $role) {
            Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            $this->access->authorize($actor, 'roles.manage');
            if ($role) {
                abort_unless($role->organization_id === $actor->organization_id, 404);
                abort_if($role->is_system_role, 403, 'System role templates cannot be edited.');
            }
            foreach ($data['grants'] as $grant) {
                $allowed = config('permissions.abilities')[$grant['ability']] ?? [];
                if (! in_array($grant['scope'], $allowed, true)) {
                    throw ValidationException::withMessages(['grants' => 'Invalid ability or scope.']);
                }
                $this->authorizeGrant($actor, $grant['ability'], $grant['scope']);
            }
            $role ??= new Role(['organization_id' => $actor->organization_id, 'code' => 'custom_'.\Illuminate\Support\Str::random(16), 'is_system_role' => false]);
            $role->name = $data['name'];
            $role->status = 'Active';
            $role->save();
            DB::table('role_permissions')->where('role_id', $role->id)->delete();
            foreach (collect($data['grants'])->unique(fn ($grant) => $grant['ability'].'|'.$grant['scope']) as $grant) {
                $this->insertGrant($role, $grant['ability'], $grant['scope']);
            }
            $this->audit->record($actor->organization_id, $actor, 'role.saved', 'role', $role->id);

            return $role;
        });
    }

    private function authorizeGrant(OrganizationMembership $actor, string $ability, string $scope): void
    {
        $held = $this->access->scopes($actor, $ability);
        abort_unless(in_array('organization', $held, true) || in_array($scope, $held, true), 403, 'Cannot delegate a permission you do not hold.');
    }

    public function assign(OrganizationMembership $actor, OrganizationMembership $subject, array $roleIds): void
    {
        DB::transaction(function () use ($actor, $subject, $roleIds) {
            Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            $this->access->authorize($actor, 'roles.manage', $subject);
            $roles = Role::where('organization_id', $actor->organization_id)->where('status', 'Active')->whereIn('id', $roleIds)->get();
            abort_unless($roles->count() === count(array_unique($roleIds)), 422, 'Invalid workspace roles.');
            foreach ($roles as $role) {
                foreach (DB::table('role_permissions as rp')->join('permissions as p', 'p.id', '=', 'rp.permission_id')->where('role_id', $role->id)->select('p.code', 'rp.scope')->get() as $grant) {
                    $this->authorizeGrant($actor, $grant->code, $grant->scope);
                }
            }
            if (! $roles->contains('code', 'owner')) {
                $this->protectLastOwner($subject);
            }
            DB::table('membership_roles')->where('organization_membership_id', $subject->id)->delete();
            foreach ($roles as $role) {
                DB::table('membership_roles')->insert([
                    'organization_id' => $actor->organization_id, 'organization_membership_id' => $subject->id,
                    'role_id' => $role->id, 'assigned_by_membership_id' => $actor->id, 'created_at' => now(),
                ]);
            }
            $this->audit->record($actor->organization_id, $actor, 'roles.assigned', 'membership', $subject->id, ['role_ids' => $roles->modelKeys()]);
        });
    }

    public function protectLastOwner(OrganizationMembership $subject): void
    {
        $owners = OrganizationMembership::where('organization_id', $subject->organization_id)->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('code', 'owner')->where('roles.status', 'Active'));
        if ((clone $owners)->whereKey($subject->id)->exists() && $owners->count() <= 1) {
            throw ValidationException::withMessages(['roles' => 'Assign another active owner before removing the last owner.']);
        }
    }
}
