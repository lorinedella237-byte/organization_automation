<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WorkspaceProvisioner
{
    public function __construct(private RoleService $roles, private AuditRecorder $audit) {}

    // Trusted console/demo bootstrap. HTTP callers must authorize platform access before invoking.
    public function bootstrap(User $owner, array $data): Organization
    {
        $data = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('organizations')],
            'timezone' => ['required', 'timezone'], 'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
        ])->validate();

        return DB::transaction(function () use ($owner, $data) {
            $organization = Organization::create($data + ['settings' => [
                'work_days' => [1, 2, 3, 4, 5], 'work_start' => '09:00', 'work_end' => '17:00',
                'report_due_days_after_period' => 5,
            ]]);
            $this->roles->seedTemplates($organization);
            $membership = OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'status' => 'active']);
            $role = Role::where('organization_id', $organization->id)->where('code', 'owner')->firstOrFail();
            DB::table('membership_roles')->insert([
                'organization_id' => $organization->id, 'organization_membership_id' => $membership->id,
                'role_id' => $role->id, 'assigned_by_membership_id' => $membership->id, 'created_at' => now(),
            ]);
            $this->audit->record($organization->id, $membership, 'organization.provisioned', 'organization', $organization->id);

            return $organization;
        });
    }
}
