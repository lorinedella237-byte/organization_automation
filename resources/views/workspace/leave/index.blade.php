{{--
    Leave. P5.10-FE to P5.12-FE.

    Two things share this page: the reader's own requests, and the requests
    waiting on them. The server decides which of the second kind exist, from
    the reader's leave.view scope, so this template never asks for more.

    Approval is two stages, manager then HR, and a request sitting at
    pending_hr is not the manager's to decide. The decision control therefore
    follows the stage the request is actually at, not merely the fact that the
    reader can approve something.

    A request cannot be made without a balance for the year, so when there is
    no balance the page says that instead of rendering a form whose every
    submission would be refused.
--}}
@extends('layouts.app')

@section('title', __('Leave'))

@php
    $statuses = [
        'pending_manager' => __('With the manager'),
        'pending_hr' => __('With HR'),
        'approved' => __('Approved'),
        'rejected' => __('Rejected'),
        'cancelled' => __('Cancelled'),
    ];

    $canRequest = $types->isNotEmpty() && $balances->isNotEmpty();
@endphp

@section('content')
    <x-page-header
        :title="__('Leave')"
        :subtitle="__('Your requests, and any waiting on your decision.')"
        :breadcrumbs="[__('Workspace') => null, __('Leave') => null]" />

    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Your balance this year') }}</h2>

                    @if ($balances->isEmpty())
                        <p class="text-secondary small mb-0">
                            {{ __('No balance has been set for you this year, so you cannot request leave yet. An administrator allocates it.') }}
                        </p>
                    @else
                        <table class="table table-data align-middle mb-0">
                            <caption class="visually-hidden">{{ __('Your leave balance') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Type') }}</th>
                                    <th scope="col">{{ __('Left') }}</th>
                                    <th scope="col">{{ __('Allocated') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($balances as $balance)
                                    @php
                                        // Reserved days belong to requests that are
                                        // still in flight. They are already spoken
                                        // for, so they are not days left.
                                        $left = (float) $balance->allocated_days - (float) $balance->reserved_days - (float) $balance->used_days;
                                    @endphp
                                    <tr>
                                        <th scope="row" class="fw-semibold">{{ $balance->leaveType?->name ?: __('Leave') }}</th>
                                        <td class="font-monospace">{{ rtrim(rtrim(number_format($left, 2), '0'), '.') }}</td>
                                        <td class="font-monospace text-secondary">{{ rtrim(rtrim(number_format((float) $balance->allocated_days, 2), '0'), '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            @if ($canRequest)
                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('Request leave') }}</h2>

                        <form method="POST" action="{{ route('leave.store') }}" novalidate>
                            @csrf

                            <x-select
                                name="leave_type_id"
                                :label="__('Type')"
                                :options="$types->all()"
                                :selected="old('leave_type_id')"
                                :placeholder="__('Choose a type')"
                                required />

                            <div class="row">
                                <div class="col-6">
                                    <x-input name="starts_on" type="date" :label="__('From')" :value="old('starts_on')" required />
                                </div>
                                <div class="col-6">
                                    <x-input name="ends_on" type="date" :label="__('To')" :value="old('ends_on')" required />
                                </div>
                            </div>

                            <x-input
                                name="days"
                                type="number"
                                step="0.5"
                                min="0.5"
                                :label="__('Days')"
                                :value="old('days')"
                                required
                                :help="__('Working days counted against your balance, which can be fewer than the span of dates.')" />

                            <x-textarea name="reason" :label="__('Reason')" :value="old('reason')" rows="2" />

                            <button type="submit" class="btn btn-primary">{{ __('Request leave') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-12 col-lg-7">
            @if ($page->total() === 0)
                <x-empty-state
                    icon="calendar-event"
                    :title="__('No leave requests')"
                    :description="__('Your own requests appear here, along with any that are waiting on your decision.')" />
            @else
                <div class="d-flex flex-column gap-3">
                    @foreach ($page as $request)
                        @php
                            $isMine = $request->membership_id === $actor->id;
                            // A member never decides their own, and a stage is
                            // decided by whoever holds that stage's ability.
                            $stageAbility = $request->status === 'pending_manager' ? 'leave.approve_manager' : 'leave.approve_hr';
                            $canDecide = ! $isMine
                                && in_array($request->status, ['pending_manager', 'pending_hr'], true)
                                && Gate::allows($stageAbility);
                            $canCancel = $isMine && in_array($request->status, ['pending_manager', 'pending_hr', 'approved'], true);
                        @endphp

                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                    <div class="min-w-0">
                                        <h2 class="h6 mb-1">
                                            {{ $request->leaveType?->name ?: __('Leave') }}
                                            <span class="text-secondary fw-normal">
                                                {{ $isMine ? __('(yours)') : $request->membership?->user?->name }}
                                            </span>
                                        </h2>

                                        <p class="text-secondary small mb-0 font-monospace">
                                            {{ optional($request->starts_on)->format('Y-m-d') }}
                                            {{ __('to') }}
                                            {{ optional($request->ends_on)->format('Y-m-d') }}
                                            <span class="ms-2">{{ trans_choice('{1}:count day|[2,*]:count days', (float) $request->days, ['count' => rtrim(rtrim(number_format((float) $request->days, 2), '0'), '.')]) }}</span>
                                        </p>

                                        @if ($request->reason)
                                            <p class="small mb-0 mt-1">{{ $request->reason }}</p>
                                        @endif
                                    </div>

                                    <x-status-badge
                                        :status="$request->status"
                                        :label="$statuses[$request->status] ?? $request->status" />
                                </div>

                                @if ($canDecide || $canCancel)
                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        @if ($canDecide)
                                            @foreach (['approved' => __('Approve'), 'rejected' => __('Reject')] as $decision => $label)
                                                <form method="POST" action="{{ route('leave.decision', $request) }}">
                                                    @csrf
                                                    <input type="hidden" name="decision" value="{{ $decision }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $label }}</button>
                                                </form>
                                            @endforeach
                                        @endif

                                        @if ($canCancel)
                                            <form method="POST" action="{{ route('leave.cancel', $request) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Cancel request') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
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
