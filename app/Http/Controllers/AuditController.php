<?php

namespace App\Http\Controllers;

use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access)
    {
        $actor = $request->attributes->get('currentMembership');
        $access->authorize($actor, 'audit.view');

        $page = DB::table('audit_logs')->where('organization_id', $actor->organization_id)
            ->select('id', 'actor_membership_id', 'action', 'entity_type', 'entity_id', 'metadata', 'created_at')->orderByDesc('id')->paginate(25);

        // Claude, 2026-10-28: HTML requests get the server-rendered viewer;
        // asynchronous callers still get the same JSON.
        return $request->expectsJson()
            ? response()->json($page)
            : view('workspace.audit.index', ['page' => $page]);
    }
}
