<?php

namespace App\Services;

use App\Models\OrganizationMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class WorkspaceAccess
{
    public function active(OrganizationMembership $actor): ?OrganizationMembership
    {
        return OrganizationMembership::whereKey($actor->id)
            ->where('organization_id', $actor->organization_id)->where('user_id', $actor->user_id)
            ->where('status', 'active')->whereHas('organization', fn (Builder $q) => $q->where('status', 'active'))->first();
    }

    public function scopes(OrganizationMembership $actor, string $ability): array
    {
        if (! $this->active($actor) || ! array_key_exists($ability, config('permissions.abilities'))) {
            return [];
        }

        return DB::table('membership_roles as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->join('role_permissions as rp', 'rp.role_id', '=', 'r.id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('mr.organization_membership_id', $actor->id)
            ->where('mr.organization_id', $actor->organization_id)
            ->where('r.organization_id', $actor->organization_id)
            ->where('rp.organization_id', $actor->organization_id)
            ->where('r.status', 'Active')->where('p.status', 'Active')->where('p.code', $ability)
            ->whereIn('rp.scope', config('permissions.abilities')[$ability] ?? [])
            ->distinct()->pluck('rp.scope')->all();
    }

    public function allows(OrganizationMembership $actor, string $ability, ?OrganizationMembership $subject = null): bool
    {
        $scopes = $this->scopes($actor, $ability);
        if ($subject && $subject->organization_id !== $actor->organization_id) {
            return false;
        }
        if (in_array('organization', $scopes, true)) {
            return true;
        }
        if (! $subject) {
            return false;
        }
        $actor = $actor->fresh();
        $subject = $subject->fresh();

        return $actor && $subject && (
            (in_array('own', $scopes, true) && $subject->id === $actor->id)
            || (in_array('direct_reports', $scopes, true) && $subject->manager_membership_id === $actor->id)
            || (in_array('department', $scopes, true) && $actor->department_id !== null && $actor->department_id === $subject->department_id)
        );
    }

    public function authorize(OrganizationMembership $actor, string $ability, ?OrganizationMembership $subject = null): void
    {
        abort_unless($this->allows($actor, $ability, $subject), 403);
    }

    public function visibleMembers(OrganizationMembership $actor, string $ability = 'staff.view'): Builder
    {
        $scopes = $this->scopes($actor, $ability);
        $fresh = $actor->fresh();
        $query = OrganizationMembership::where('organization_id', $actor->organization_id);
        if (in_array('organization', $scopes, true)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($scopes, $fresh) {
            $query->whereRaw('1 = 0');
            if ($fresh && in_array('own', $scopes, true)) {
                $query->orWhere('id', $fresh->id);
            }
            if ($fresh && in_array('direct_reports', $scopes, true)) {
                $query->orWhere('manager_membership_id', $fresh->id);
            }
            if ($fresh?->department_id && in_array('department', $scopes, true)) {
                $query->orWhere('department_id', $fresh->department_id);
            }
        });
    }
}
