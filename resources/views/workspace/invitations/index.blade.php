{{--
    Invitations. P1.04-FE.

    The whole lifecycle on one page, because an invitation is only ever in one
    of four states and an administrator needs to see all of them at once to
    know whether to chase, revoke or send another.

    The role list is filtered by the controller to roles the actor may actually
    delegate. Offering the rest would let someone fill in the form and then
    collect a 403 from InvitationService::issue(). A role you cannot grant is
    not a choice, so it is not shown.

    Revoke is the only destructive control here, and the server refuses it on an
    already-accepted invitation. The button is therefore absent, not disabled,
    on accepted and revoked rows: there is nothing left to revoke.
--}}
@extends('layouts.app')

@section('title', __('Invitations'))

@php
    /**
     * One invitation, four possible states, resolved in precedence order.
     * Accepted and revoked are facts already recorded; expiry is derived from
     * the clock, so it is only reached when neither of those happened.
     */
    $stateOf = function ($invitation) {
        if ($invitation->accepted_at) {
            return ['status' => 'Approved', 'label' => __('Accepted'), 'revocable' => false];
        }

        if ($invitation->revoked_at) {
            return ['status' => 'Cancelled', 'label' => __('Revoked'), 'revocable' => false];
        }

        // Already unusable: InvitationService::available() refuses an expired
        // token, so revoking adds nothing. Offering the button would imply the
        // link is still live and needs withdrawing.
        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            return ['status' => 'Failed', 'label' => __('Expired'), 'revocable' => false];
        }

        return ['status' => 'Pending', 'label' => __('Waiting'), 'revocable' => true];
    };
@endphp

@section('content')
    <x-page-header
        :title="__('Invitations')"
        :subtitle="__('Invite someone into this organization, and follow what became of each invitation.')"
        :breadcrumbs="[__('Workspace') => null, __('Invitations') => null]" />

    <div class="row g-4">
        {{-- Send ----------------------------------------------------------- --}}
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Invite someone') }}</h2>

                    @if ($roles->isEmpty())
                        <p class="text-secondary small mb-0">
                            {{ __('You hold no permissions you can pass on, so there is no role you can invite someone into yet.') }}
                        </p>
                    @else
                        <form method="POST" action="{{ route('invitations.store') }}" novalidate>
                            @csrf

                            <x-input
                                name="email"
                                type="email"
                                :label="__('Email address')"
                                autocomplete="off"
                                required
                                :help="__('They receive a link that works once and lasts seven days.')" />

                            <x-select
                                name="role_id"
                                :label="__('Joins as')"
                                :options="$roles->pluck('name', 'id')"
                                :placeholder="__('Choose a role')"
                                required
                                :help="__('Only roles you could grant yourself are listed.')" />

                            <button type="submit" class="btn btn-primary">{{ __('Send invitation') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Lifecycle ------------------------------------------------------ --}}
        <div class="col-12 col-lg-8">
            @if ($page->total() === 0)
                <x-empty-state
                    icon="envelope"
                    :title="__('No invitations yet')"
                    :description="__('Invitations appear here as soon as you send one, each with what became of it.')" />
            @else
                <div class="table-responsive">
                    <table class="table table-data align-middle">
                        <caption class="visually-hidden">{{ __('Invitations sent from this organization') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Email') }}</th>
                                <th scope="col">{{ __('Joins as') }}</th>
                                <th scope="col">{{ __('State') }}</th>
                                <th scope="col">{{ __('Expires') }}</th>
                                <th scope="col" class="text-end">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($page as $invitation)
                                @php($state = $stateOf($invitation))

                                <tr>
                                    <th scope="row" class="fw-normal font-monospace small">{{ $invitation->email }}</th>
                                    <td class="small">{{ $roleNames[$invitation->role_id] ?? __('Role removed') }}</td>
                                    <td><x-status-badge :status="$state['status']" :label="$state['label']" /></td>
                                    <td class="font-monospace small text-secondary text-nowrap">
                                        {{ $invitation->expires_at?->toDateString() ?? __('Not set') }}
                                    </td>
                                    <td class="text-end">
                                        @if ($state['revocable'])
                                            <form method="POST"
                                                  action="{{ route('invitations.revoke', $invitation->id) }}"
                                                  class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                    {{ __('Revoke') }}
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-secondary small">{{ __('Closed') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <p class="text-secondary small mb-0">
                        {{ __('Showing :from to :to of :total', [
                            'from' => $page->firstItem(),
                            'to' => $page->lastItem(),
                            'total' => $page->total(),
                        ]) }}
                    </p>
                    {{ $page->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
