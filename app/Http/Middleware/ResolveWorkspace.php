<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    public function handle(Request $request, Closure $next, bool $required = true): Response
    {
        $memberships = $request->user()->memberships()->with(['organization', 'roles'])->where('status', 'active')
            ->whereHas('organization', fn ($query) => $query->where('status', 'active'))->orderBy('id')->get();
        $selected = $request->session()->get('current_organization_id');
        $membership = $selected ? $memberships->firstWhere('organization_id', (int) $selected) : $memberships->first();
        if ($selected && ! $membership) {
            // Do not silently move a request to a different tenant after access is revoked.
            abort(403, 'Your selected workspace is no longer available. Switch workspace or sign out.');
        }
        if ($required && ! $membership) {
            abort(403, 'No active workspace membership is available.');
        }
        if ($membership) {
            $request->session()->put('current_organization_id', $membership->organization_id);
        }
        $request->attributes->set('currentMembership', $membership);
        View::share([
            'currentMembership' => $membership,
            'currentOrganization' => $membership?->organization,
            'availableOrganizations' => $memberships->pluck('organization'),
            'unreadNotificationCount' => $membership ? \Illuminate\Support\Facades\DB::table('workspace_notifications')
                ->where('organization_id', $membership->organization_id)->where('organization_membership_id', $membership->id)->whereNull('read_at')->count() : 0,
        ]);

        return $next($request);
    }
}
