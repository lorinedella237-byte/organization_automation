<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\Team;
use App\Services\RoleService;
use App\Services\StaffService;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function __construct(private WorkspaceAccess $access, private StaffService $staff) {}

    private function actor(Request $request): OrganizationMembership
    {
        return $request->attributes->get('currentMembership');
    }

    private function target(Request $request, int $membership): OrganizationMembership
    {
        return OrganizationMembership::where('organization_id', $this->actor($request)->organization_id)->findOrFail($membership);
    }

    private function fields(OrganizationMembership $membership): array
    {
        return [
            'id' => $membership->id, 'name' => $membership->user->name, 'email' => $membership->user->email,
            'employee_number' => $membership->employee_number, 'title' => $membership->title,
            'department_id' => $membership->department_id, 'team_id' => $membership->team_id,
            'manager_membership_id' => $membership->manager_membership_id, 'status' => $membership->status,
            'start_date' => $membership->start_date?->format('Y-m-d'), 'end_date' => $membership->end_date?->format('Y-m-d'),
        ];
    }

    public function index(Request $request)
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'per_page' => ['nullable', 'integer', 'between:1,100']]);
        abort_if($this->access->scopes($this->actor($request), 'staff.view') === [], 403);
        $query = $this->access->visibleMembers($this->actor($request))->with('user');
        if (filled($data['search'] ?? null)) {
            $query->whereHas('user', fn ($query) => $query->where(function ($query) use ($data) {
                $term = '%'.addcslashes($data['search'], '%_\\').'%';
                $query->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('email', 'like', $term);
            }));
        }
        $page = $query->orderBy('id')->paginate($data['per_page'] ?? 25);
        $page->setCollection($page->getCollection()->map(fn ($membership) => $this->fields($membership)));

        // Claude, 2026-10-28: HTML requests get the server-rendered page the
        // workspace contract promised; asynchronous callers still get the same
        // JSON. The payload is identical either way.
        return $request->expectsJson()
            ? response()->json($page)
            : view('workspace.staff.index', ['page' => $page, 'search' => $data['search'] ?? '']);
    }

    public function show(Request $request, int $membership)
    {
        $target = $this->access->visibleMembers($this->actor($request))->findOrFail($membership);

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->fields($target)]);
        }

        // Option lists for the edit form. All three are scoped to the actor's
        // organization, so this cannot offer a department, team, manager or
        // role belonging to another tenant. The server revalidates every one of
        // them on write regardless of what this form submits.
        $organizationId = $this->actor($request)->organization_id;
        $canManage = $this->access->allows($this->actor($request), 'staff.manage', $target);
        $canManageRoles = $this->access->allows($this->actor($request), 'roles.manage', $target);

        return view('workspace.staff.show', [
            'member' => $this->fields($target),
            'membership' => $target,
            'departments' => $canManage ? Department::where('organization_id', $organizationId)->orderBy('name')->pluck('name', 'id') : collect(),
            'teams' => $canManage ? Team::where('organization_id', $organizationId)->orderBy('name')->pluck('name', 'id') : collect(),
            'managers' => $canManage ? OrganizationMembership::where('organization_id', $organizationId)
                ->where('status', 'active')->whereKeyNot($target->id)->with('user')->get()
                ->mapWithKeys(fn ($row) => [$row->id => $row->user->name]) : collect(),
            'roles' => $canManageRoles ? Role::where('organization_id', $organizationId)->orderBy('name')->pluck('name', 'id') : collect(),
            'assignedRoleIds' => $target->roles()->pluck('roles.id')->all(),
        ]);
    }

    public function update(Request $request, int $membership)
    {
        $updated = $this->staff->update($this->actor($request), $this->target($request, $membership), $request->all());

        return $request->expectsJson() ? response()->json(['data' => $this->fields($updated)])
            : redirect()->route('staff.show', $membership)->with('success', 'Staff profile updated.');
    }

    public function status(Request $request, int $membership)
    {
        $updated = $this->staff->transition($this->actor($request), $this->target($request, $membership), $request->all());

        return $request->expectsJson() ? response()->json(['data' => $this->fields($updated)])
            : redirect()->route('staff.show', $membership)->with('success', 'Staff status updated.');
    }

    public function roles(Request $request, int $membership, RoleService $roles)
    {
        $data = $request->validate(['role_ids' => ['present', 'array', 'max:50'], 'role_ids.*' => ['integer', 'distinct']]);
        $roles->assign($this->actor($request), $this->target($request, $membership), $data['role_ids']);

        return $request->expectsJson() ? response()->json(['status' => 'updated'])
            : redirect()->route('staff.show', $membership)->with('success', 'Staff roles updated.');
    }
}
