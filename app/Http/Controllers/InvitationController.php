<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Role;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\RoleService;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class InvitationController extends Controller
{
    public function __construct(private RoleService $roles) {}

    public function index(Request $request, WorkspaceAccess $access)
    {
        $actor = $request->attributes->get('currentMembership');
        $access->authorize($actor, 'staff.invite');

        $page = OrganizationInvitation::where('organization_id', $actor->organization_id)->orderByDesc('id')->paginate(25);

        if ($request->expectsJson()) {
            return response()->json($page);
        }

        // Only roles the actor may actually delegate are offered. Listing the
        // rest would invite a 403 from InvitationService::issue() after the
        // form was filled in.
        $roles = Role::where('organization_id', $actor->organization_id)
            ->where('status', 'Active')
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role) => $this->roles->canDelegate($actor, $role))
            ->values();

        return view('workspace.invitations.index', [
            'page' => $page,
            'roles' => $roles,
            // Every role in the organization, not just the delegatable ones:
            // an existing invitation may name a role this actor cannot grant,
            // and the row must still say which role it was.
            'roleNames' => Role::where('organization_id', $actor->organization_id)->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, InvitationService $invitations)
    {
        $input = $request->all();
        if (is_string($input['email'] ?? null)) {
            $input['email'] = strtolower(trim($input['email']));
        }

        $invitation = $invitations->issue($request->attributes->get('currentMembership'), $input);

        if ($request->expectsJson()) {
            return response()->json(['data' => $invitation], 201);
        }

        return redirect()->route('invitations.index')
            ->with('success', __('Invitation sent to :email.', ['email' => $invitation->email]));
    }

    public function revoke(Request $request, int $invitation, InvitationService $invitations)
    {
        $invitations->revoke($request->attributes->get('currentMembership'), $invitation);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'revoked']);
        }

        return redirect()->route('invitations.index')->with('success', __('Invitation revoked.'));
    }

    public function show(string $token, InvitationService $invitations)
    {
        try {
            $invitation = $invitations->find($token);
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 410) throw $exception;
            return response()->view('auth.invitation-unavailable', ['state' => $invitations->unavailableReason($token)], 410)
                ->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
        }

        return response()->view('auth.accept-invitation', [
            'token' => $token,
            'existingAccount' => User::where('email', $invitation->email)->exists(),
            'invitation' => (object) [
                'organization_name' => Organization::findOrFail($invitation->organization_id)->name,
                'role_name' => Role::findOrFail($invitation->role_id)->name,
                'email' => $invitation->email, 'expires_at' => $invitation->expires_at,
            ],
        ])->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function accept(Request $request, InvitationService $invitations)
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64'], 'password' => ['nullable', 'string', 'max:4096']]);
        $member = $invitations->accept($data['token'], $request->all(), $request->user());
        if ($request->user()) {
            $request->session()->put('current_organization_id', $member->organization_id);

            return redirect()->route('dashboard')->with('success', 'Invitation accepted.');
        }

        return redirect()->route('login')->with('status', 'Invitation accepted. Sign in to access your workspace.');
    }
}
