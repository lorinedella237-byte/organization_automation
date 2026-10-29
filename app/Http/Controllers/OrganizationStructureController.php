<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Organization;
use App\Models\Team;
use App\Services\AuditRecorder;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrganizationStructureController extends Controller
{
    public function handle(Request $request, WorkspaceAccess $access, AuditRecorder $audit)
    {
        $actor = $request->attributes->get('currentMembership');
        $access->authorize($actor, 'departments.manage');
        $isDepartment = $request->route('kind') === 'departments';
        $class = $isDepartment ? Department::class : Team::class;
        $table = $isDepartment ? 'departments' : 'teams';
        if ($request->isMethod('GET')) {
            $rows = $class::where('organization_id', $actor->organization_id)->orderBy('name')->get();

            // Claude, 2026-10-28: HTML requests get the server-rendered page;
            // asynchronous callers still get the same JSON.
            return $request->expectsJson()
                ? response()->json(['data' => $rows])
                : view('workspace.structure.index', ['rows' => $rows, 'kind' => $request->route('kind')]);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique($table)->where('organization_id', $actor->organization_id)]]);
        $entity = DB::transaction(function () use ($actor, $access, $class, $data, $audit, $table) {
            Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            $access->authorize($actor, 'departments.manage');
            $entity = $class::create($data + ['organization_id' => $actor->organization_id]);
            $audit->record($actor->organization_id, $actor, $table.'.created', $table, $entity->id);

            return $entity;
        });

        return response()->json(['data' => $entity], 201);
    }
}
