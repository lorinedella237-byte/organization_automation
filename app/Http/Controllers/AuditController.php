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

        $page = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')->where('audit_logs.organization_id', $actor->organization_id)
            ->select('audit_logs.id', 'audit_logs.actor_membership_id', 'audit_logs.action', 'audit_logs.entity_type', 'audit_logs.entity_id', 'audit_logs.metadata', 'audit_logs.created_at', 'users.first_name as actor_first_name', 'users.last_name as actor_last_name')->orderByDesc('audit_logs.id')->paginate(25);

        // Claude, 2026-10-28: HTML requests get the server-rendered viewer;
        // asynchronous callers still get the same JSON.
        return $request->expectsJson()
            ? response()->json($page)
            : view('workspace.audit.index', ['page' => $page]);
    }
}
