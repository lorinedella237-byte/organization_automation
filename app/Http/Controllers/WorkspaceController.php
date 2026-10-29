<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\AuditRecorder;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkspaceController extends Controller
{
    public function switch(Request $request)
    {
        $data = $request->validate(['organization' => ['required', 'integer']]);
        $membership = $request->user()->memberships()->where('organization_id', $data['organization'])->where('status', 'active')
            ->whereHas('organization', fn ($query) => $query->where('status', 'active'))->firstOrFail();
        $request->session()->put('current_organization_id', $membership->organization_id);
        $request->session()->forget('url.intended');

        return $request->expectsJson() ? response()->json(['organization_id' => $membership->organization_id]) : redirect()->route('dashboard');
    }

    public function show(Request $request, WorkspaceAccess $access)
    {
        $actor = $request->attributes->get('currentMembership');
        $access->authorize($actor, 'organization.manage');

        // Claude, 2026-10-28: HTML requests get the server-rendered settings
        // form; asynchronous callers still get the same JSON.
        return $request->expectsJson()
            ? response()->json(['data' => $actor->organization])
            : view('workspace.settings.index', ['organization' => $actor->organization]);
    }

    public function update(Request $request, WorkspaceAccess $access, AuditRecorder $audit)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'timezone' => ['sometimes', 'required', 'timezone'],
            'currency' => ['sometimes', 'required', 'regex:/^[A-Z]{3}$/'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'settings' => ['sometimes', 'array:work_days,work_start,work_end,report_due_days_after_period'],
            'settings.work_days' => ['sometimes', 'array', 'min:1', 'max:7'],
            'settings.work_days.*' => ['integer', 'between:1,7', 'distinct'],
            'settings.work_start' => ['sometimes', 'date_format:H:i'],
            'settings.work_end' => ['sometimes', 'date_format:H:i'],
            'settings.report_due_days_after_period' => ['sometimes', 'integer', 'between:0,14'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $actor = $request->attributes->get('currentMembership');
        $organization = DB::transaction(function () use ($data, $actor, $access, $audit) {
            $organization = Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            $access->authorize($actor, 'organization.manage');
            $reason = $data['reason'];
            unset($data['reason']);
            if (isset($data['settings'])) {
                $data['settings'] = array_replace($organization->settings ?? [], $data['settings']);
            }
            $organization->fill($data);
            $fields = array_keys($organization->getDirty());
            $organization->save();
            $audit->record($organization->id, $actor, 'organization.updated', 'organization', $organization->id, ['changed_fields' => $fields, 'reason' => $reason]);

            return $organization;
        });

        return $request->expectsJson() ? response()->json(['data' => $organization])
            : redirect()->route('settings.organization')->with('success', 'Workspace settings updated.');
    }
}
