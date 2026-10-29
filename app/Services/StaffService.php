<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffService
{
    public function __construct(private WorkspaceAccess $access, private AuditRecorder $audit, private RoleService $roles) {}

    public function update(OrganizationMembership $actor, OrganizationMembership $subject, array $input): OrganizationMembership
    {
        return DB::transaction(function () use ($actor, $subject, $input) {
            // Serialize hierarchy edits and lifecycle/role changes inside a workspace.
            Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            $this->access->authorize($actor, 'staff.manage', $subject);
            $subject = $subject->fresh();
            $organizationId = $actor->organization_id;
            $data = Validator::make($input, [
                'employee_number' => ['sometimes', 'nullable', 'string', 'max:80', Rule::unique('organization_memberships')->where('organization_id', $organizationId)->ignore($subject->id)],
                'title' => ['sometimes', 'nullable', 'string', 'max:150'],
                'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists('departments', 'id')->where('organization_id', $organizationId)],
                'team_id' => ['sometimes', 'nullable', 'integer', Rule::exists('teams', 'id')->where('organization_id', $organizationId)],
                'manager_membership_id' => ['sometimes', 'nullable', 'integer', Rule::exists('organization_memberships', 'id')->where('organization_id', $organizationId)->where('status', 'active')],
                'start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
                'end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
                'reason' => ['required', 'string', 'max:500'],
            ])->validate();
            $managerId = $data['manager_membership_id'] ?? null;
            $visited = [$subject->id];
            while ($managerId !== null) {
                if (in_array((int) $managerId, $visited, true)) {
                    throw ValidationException::withMessages(['manager_membership_id' => 'A manager assignment cannot create a cycle.']);
                }
                $visited[] = (int) $managerId;
                $managerId = OrganizationMembership::where('organization_id', $organizationId)->whereKey($managerId)->value('manager_membership_id');
            }
            $reason = $data['reason'];
            unset($data['reason']);
            $subject->fill($data);
            if ($subject->start_date && $subject->end_date && $subject->end_date->lt($subject->start_date)) {
                throw ValidationException::withMessages(['end_date' => 'End date must not precede start date.']);
            }
            $changes = array_keys($subject->getDirty());
            $subject->save();
            $this->audit->record($organizationId, $actor, 'staff.updated', 'membership', $subject->id, ['changed_fields' => $changes, 'reason' => $reason]);

            return $subject;
        });
    }

    public function transition(OrganizationMembership $actor, OrganizationMembership $subject, array $input): OrganizationMembership
    {
        $data = Validator::make($input, ['status' => ['required', Rule::in(['active', 'suspended', 'offboarded'])], 'reason' => ['required', 'string', 'max:500']])->validate();

        return DB::transaction(function () use ($actor, $subject, $data) {
            Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            $this->access->authorize($actor, 'staff.manage', $subject);
            $subject = $subject->fresh();
            if ($data['status'] !== 'active') {
                $this->roles->protectLastOwner($subject);
            }
            if ($subject->status === 'offboarded' && $data['status'] !== 'offboarded') {
                throw ValidationException::withMessages(['status' => 'Offboarded memberships require a separate rehire process.']);
            }
            $before = $subject->status;
            $subject->status = $data['status'];
            $subject->save();
            $this->audit->record($actor->organization_id, $actor, 'staff.status_changed', 'membership', $subject->id, [
                'before_status' => $before, 'after_status' => $subject->status, 'reason' => $data['reason'],
            ]);

            return $subject;
        });
    }
}
