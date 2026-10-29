<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\InvitationService;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformOrganizationController extends Controller
{
    public function create(Request $request)
    {
        abort_unless(DB::table('platform_administrators')->where('user_id', $request->user()->id)->exists(), 403);
        return $request->expectsJson() ? response()->json(['data' => ['currencies' => ['NGN', 'USD', 'GBP', 'EUR']]]) : view('platform.organizations.create', ['currencies' => ['NGN', 'USD', 'GBP', 'EUR']]);
    }

    public function store(Request $request, RoleService $roles, InvitationService $invitations)
    {
        abort_unless(DB::table('platform_administrators')->where('user_id', $request->user()->id)->exists(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('organizations')],
            'timezone' => ['required', 'timezone'], 'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'owner_email' => ['required', 'email', 'max:255'],
        ]);
        $organization = DB::transaction(function () use ($data, $request, $roles, $invitations) {
            $email = $data['owner_email'];
            unset($data['owner_email']);
            $organization = Organization::create($data + ['settings' => [
                'work_days' => [1, 2, 3, 4, 5], 'work_start' => '09:00', 'work_end' => '17:00', 'report_due_days_after_period' => 5,
            ]]);
            $roles->seedTemplates($organization);
            $invitations->issueOwner($request->user(), $organization, $email);

            return $organization;
        });

        return response()->json(['data' => $organization], 201);
    }
}
