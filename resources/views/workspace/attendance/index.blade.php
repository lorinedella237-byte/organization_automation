{{--
    Attendance. P5.01-FE to P5.03-FE.

    Clocking in and out is the whole point of the page, so it is the first
    thing on it and it is one button, not a form to fill in. Which button
    appears is decided by whether a session is open, because offering both and
    letting the server reject one teaches people to guess.

    The session list is scoped by the server to the members the reader's
    attendance.view covers, so a person scoped to their own record sees only
    their own rows and the employee column is not shown to them at all.
--}}
@extends('layouts.app')

@section('title', __('Attendance'))

@php
    // An open session has no clock-out, so a duration cannot be shown for it.
    $sessionStatuses = [
        'open' => __('Open'),
        'complete' => __('Complete'),
        'incomplete' => __('Incomplete'),
    ];

    $ownRowsOnly = $page->getCollection()->every(fn ($session) => $session->membership_id === $actor->id);
    $correctionFailed = $errors->hasAny(['reason', 'requested_clocked_in_at', 'requested_clocked_out_at', 'attendance_session_id']);
@endphp

@section('content')
    <x-page-header
        :title="__('Attendance')"
        :subtitle="__('Your clock, and the sessions you are allowed to see.')"
        :breadcrumbs="[__('Workspace') => null, __('Attendance') => null]">
        <x-slot:actions>
            @can('attendance.export')
                <a class="btn btn-outline-secondary btn-sm"
                   href="{{ route('attendance.export', ['from' => now()->startOfMonth()->format('Y-m-d'), 'to' => now()->format('Y-m-d')]) }}">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('Export this month') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h2 class="h6 mb-1">{{ __('Your clock') }}</h2>

                @if ($open)
                    <p class="mb-0">
                        <x-status-badge status="present" :label="__('Clocked in')" />
                        <span class="ms-2 font-monospace small">{{ $open->clocked_in_at?->format('Y-m-d H:i') }}</span>
                    </p>
                @else
                    <p class="mb-0">
                        <x-status-badge status="inactive" :label="__('Not clocked in')" />
                        <span class="ms-2 text-secondary small">{{ __('No session is open.') }}</span>
                    </p>
                @endif
            </div>

            {{-- One control, chosen by the state. The server refuses a second
                 clock-in while a session is open, so offering both would be
                 offering a button that fails. --}}
            @if ($open)
                @can('attendance.clock_out')
                    <form method="POST" action="{{ route('attendance.clock-out') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">{{ __('Clock out') }}</button>
                    </form>
                @endcan
            @else
                @can('attendance.clock_in')
                    <form method="POST" action="{{ route('attendance.clock-in') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">{{ __('Clock in') }}</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    <h2 class="h6 mb-2">{{ __('Sessions') }}</h2>

    @if ($page->total() === 0)
        <x-empty-state
            icon="clock"
            :title="__('No attendance recorded yet')"
            :description="__('Clocking in starts a session. It closes when you clock out, and the day is counted from the pair.')" />
    @else
        <div class="table-responsive">
            <table class="table table-data align-middle">
                <caption class="visually-hidden">{{ __('Attendance sessions you can see') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('Date') }}</th>
                        @unless ($ownRowsOnly)
                            <th scope="col">{{ __('Employee') }}</th>
                        @endunless
                        <th scope="col">{{ __('In') }}</th>
                        <th scope="col">{{ __('Out') }}</th>
                        <th scope="col">{{ __('Time') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($page as $session)
                        <tr>
                            <th scope="row" class="fw-semibold font-monospace">
                                {{ optional($session->local_date)->format('Y-m-d') }}
                            </th>
                            @unless ($ownRowsOnly)
                                <td>{{ $session->membership?->user?->name ?: __('Unknown') }}</td>
                            @endunless
                            <td class="font-monospace small">{{ $session->clocked_in_at?->format('H:i') ?: __('Not set') }}</td>
                            <td class="font-monospace small">
                                {{ $session->clocked_out_at?->format('H:i') ?: __('Still open') }}
                            </td>
                            <td class="font-monospace small text-nowrap">
                                @if ($session->duration_minutes)
                                    {{ intdiv($session->duration_minutes, 60) }}h {{ $session->duration_minutes % 60 }}m
                                @else
                                    <span class="text-secondary">{{ __('Not counted') }}</span>
                                @endif
                            </td>
                            <td>
                                <x-status-badge
                                    :status="$session->status"
                                    :label="$sessionStatuses[$session->status] ?? $session->status" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
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

    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Ask for a correction') }}</h2>

                    <p class="text-secondary small">
                        {{ __('A recorded session is never edited in place. A correction is requested, decided by someone else, and kept with what it changed.') }}
                    </p>

                    <form method="POST" action="{{ route('attendance.corrections.store') }}" novalidate>
                        @csrf

                        <x-select
                            name="attendance_session_id"
                            :label="__('Which session')"
                            :options="$page->getCollection()
                                ->where('membership_id', $actor->id)
                                ->mapWithKeys(fn ($session) => [
                                    $session->id => optional($session->local_date)->format('Y-m-d').' '.($session->clocked_in_at?->format('H:i') ?: '')
                                ])->all()"
                            :selected="old('attendance_session_id')"
                            :placeholder="__('Not about one session')" />

                        <div class="row">
                            <div class="col-12 col-xl-6">
                                <x-input
                                    name="requested_clocked_in_at"
                                    type="datetime-local"
                                    :label="__('Should have clocked in')"
                                    :value="old('requested_clocked_in_at')" />
                            </div>
                            <div class="col-12 col-xl-6">
                                <x-input
                                    name="requested_clocked_out_at"
                                    type="datetime-local"
                                    :label="__('Should have clocked out')"
                                    :value="old('requested_clocked_out_at')" />
                            </div>
                        </div>

                        <x-textarea name="reason" :label="__('Why')" :value="old('reason')" rows="3" required />

                        <button type="submit" class="btn btn-primary">{{ __('Request correction') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Corrections') }}</h2>

                    @if ($corrections->isEmpty())
                        <p class="text-secondary small mb-0">{{ __('Nothing has been asked for.') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-data align-middle mb-0">
                                <caption class="visually-hidden">{{ __('Correction requests') }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Raised') }}</th>
                                        <th scope="col">{{ __('Who') }}</th>
                                        <th scope="col">{{ __('Reason') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($corrections as $correction)
                                        <tr>
                                            <td class="font-monospace small text-nowrap">
                                                {{ optional($correction->created_at)->format('Y-m-d') }}
                                            </td>
                                            <td class="small">
                                                {{ $correction->membership_id === $actor->id
                                                    ? __('You')
                                                    : ($correction->membership?->user?->name ?: __('Unknown')) }}
                                            </td>
                                            <th scope="row" class="fw-normal small">{{ Str::limit($correction->reason, 90) }}</th>
                                            <td>
                                                <x-status-badge :status="$correction->status" />

                                                {{-- Deciding needs attendance.correct, and the
                                                     server refuses a decision on anything already
                                                     decided, so the control follows both. --}}
                                                @if ($correction->status === 'pending' && $correction->membership_id !== $actor->id)
                                                    @can('attendance.correct')
                                                        <span class="d-flex gap-1 mt-1">
                                                            @foreach (['approved' => __('Approve'), 'rejected' => __('Reject')] as $decision => $label)
                                                                <form method="POST" action="{{ route('attendance.corrections.decision', $correction) }}">
                                                                    @csrf
                                                                    <input type="hidden" name="decision" value="{{ $decision }}">
                                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $label }}</button>
                                                                </form>
                                                            @endforeach
                                                        </span>
                                                    @endcan
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
