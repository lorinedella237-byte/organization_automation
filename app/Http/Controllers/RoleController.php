<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\RoleService;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access)
    {
        $actor = $request->attributes->get('currentMembership');
        $access->authorize($actor, 'roles.manage');
        $roles = Role::where('organization_id', $actor->organization_id)->orderBy('name')->get();
        foreach ($roles as $role) {
            $role->setAttribute('grants', DB::table('role_permissions as rp')->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                ->where('rp.organization_id', $actor->organization_id)->where('rp.role_id', $role->id)->select('p.code as ability', 'rp.scope')->get());
        }

        // Claude, 2026-10-28: HTML requests get the server-rendered editor;
        // asynchronous callers still get the same JSON.
        return $request->expectsJson()
            ? response()->json(['data' => $roles, 'abilities' => config('permissions.abilities')])
            : view('workspace.roles.index', ['roles' => $roles, 'abilities' => config('permissions.abilities')]);
    }

    public function save(Request $request, RoleService $roles, ?int $role = null)
    {
        $actor = $request->attributes->get('currentMembership');
        $target = $role ? Role::where('organization_id', $actor->organization_id)->findOrFail($role) : null;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'grants' => ['present', 'array', 'max:150'],
            'grants.*' => ['array:ability,scope'], 'grants.*.ability' => ['required', 'string'], 'grants.*.scope' => ['required', 'string'],
        ]);

        return response()->json(['data' => $roles->save($actor, $data, $target)], $target ? 200 : 201);
    }
}
