<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkspaceNotificationController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->attributes->get('currentMembership');

        $page = DB::table('workspace_notifications')->where('organization_id', $actor->organization_id)
            ->where('organization_membership_id', $actor->id)->orderByDesc('created_at')->paginate(25);

        if ($request->expectsJson()) {
            return response()->json($page);
        }

        return view('workspace.notifications.index', ['page' => $page]);
    }

    public function read(Request $request, string $notification)
    {
        $actor = $request->attributes->get('currentMembership');
        $query = DB::table('workspace_notifications')->where('organization_id', $actor->organization_id)
            ->where('organization_membership_id', $actor->id)->where('id', $notification);
        abort_unless((clone $query)->exists(), 404);
        $query->whereNull('read_at')->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'read']);
        }

        return back()->with('success', __('Marked as read.'));
    }
}
