{{--
    Workspace home. P7.03-FE.

    Every number comes from DashboardService, which scopes projects, tasks and
    staff to what the viewer may see. Two of its figures are not scoped that
    way, so they are gated here by the permission that owns them: draft payroll
    periods behind payroll.manage, and leave awaiting a decision behind
    leave.approve_manager. Without those gates a staff member would be told how
    many payroll periods are open and how many leave requests the organization
    is sitting on. Both are recorded as backend scoping work in the tracker.

    These are counts, not series: there is no history behind any of them, so the
    right form is a stat tile and not a chart. A tile links to its list only
    where that list has a screen; the rest would lead to a page of raw JSON.
--}}
@extends('layouts.app')

@section('title', __('Home'))

@php
    $hasWorkspace = filled($data ?? []);

    $projects = $data['projects'] ?? null;
    $tasks = $data['tasks'] ?? null;
    $staff = $data['staff'] ?? null;
    $overdue = $tasks['overdue'] ?? 0;

    // Only this one is already scoped to the person reading the page.
    $clockedIn = ($data['attendance']['open'] ?? 0) > 0;
@endphp

@section('content')
    <x-page-header
        :title="__('Welcome, :name', ['name' => auth()->user()->name])"
        :subtitle="$hasWorkspace
            ? __('Where :workspace stands today.', ['workspace' => $currentOrganization?->name])
            : null" />

    @unless ($hasWorkspace)
        {{-- Reachable: the dashboard does not require a workspace, so a person
             with no active membership lands here rather than on a 403. --}}
        <x-empty-state
            icon="building"
            :title="__('You are not in a workspace yet')"
            :description="__('An administrator invites you to an organization, and everything you are given access to appears here.')" />
    @else
        @php
            /*
                The tiles are built as data first so the grid can be sized from
                how many survive their permission gate. An owner sees six and a
                staff member sees two, and a fixed column width leaves one of
                those two roles with a ragged half row.
            */
            $tiles = array_values(array_filter([
                Gate::allows('projects.view') ? [
                    'label' => __('Active projects'),
                    'value' => $projects['active'],
                    'caption' => trans_choice(
                        '{1}:count project you can see|[2,*]:count projects you can see',
                        $projects['visible'],
                        ['count' => number_format($projects['visible'])],
                    ),
                ] : null,
                Gate::allows('tasks.view') ? [
                    'label' => __('Open tasks'),
                    'value' => $tasks['open'],
                    'caption' => __('Across the projects you can see'),
                ] : null,
                Gate::allows('tasks.view') ? [
                    'label' => __('Overdue tasks'),
                    'value' => $overdue,
                    // The state is named in words as well as coloured, so it
                    // survives a monochrome screen. Nothing is flagged at zero:
                    // a quiet number is the good outcome and needs no badge.
                    'badge' => $overdue > 0 ? ['late', __('Past their due date')] : null,
                    'caption' => $overdue > 0 ? null : __('Nothing past its due date'),
                ] : null,
                Gate::allows('staff.view.direct_reports') ? [
                    'label' => __('Active staff'),
                    'value' => $staff['active'],
                    'caption' => trans_choice(
                        '{1}:count person in your scope|[2,*]:count people in your scope',
                        $staff['visible'],
                        ['count' => number_format($staff['visible'])],
                    ),
                ] : null,
                Gate::allows('leave.approve_manager') ? [
                    'label' => __('Leave awaiting a decision'),
                    'value' => $data['leave']['pending'],
                    'caption' => __('Requests not yet approved or declined'),
                ] : null,
                Gate::allows('payroll.manage') ? [
                    'label' => __('Draft payroll periods'),
                    'value' => $data['payroll']['drafts'],
                    'caption' => __('Prepared but not yet submitted'),
                ] : null,
            ]));

            // Three across divides five and six evenly and keeps four from
            // stranding a single tile on its own line.
            $column = count($tiles) === 4 ? 'col-6 col-lg-3' : 'col-6 col-lg-4';
        @endphp

        <div class="row g-3 mb-4">
            @foreach ($tiles as $tile)
                <div class="{{ $column }}">
                    <div class="card h-100">
                        <div class="card-body">
                            <p class="text-secondary small mb-1">{{ $tile['label'] }}</p>
                            <p class="fs-3 fw-semibold mb-1 lh-1">{{ number_format($tile['value']) }}</p>

                            @if ($tile['badge'] ?? null)
                                <x-status-badge :status="$tile['badge'][0]" :label="$tile['badge'][1]" />
                            @elseif ($tile['caption'] ?? null)
                                <p class="text-secondary small mb-0">{{ $tile['caption'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('You right now') }}</h2>

                        <p class="mb-2">
                            @if ($clockedIn)
                                <x-status-badge status="present" :label="__('Clocked in')" />
                                <span class="ms-2">{{ __('You have an attendance session still open.') }}</span>
                            @else
                                <x-status-badge status="inactive" :label="__('Not clocked in')" />
                                <span class="ms-2">{{ __('You have no attendance session open.') }}</span>
                            @endif
                        </p>

                        <p class="text-secondary small mb-0">
                            {{ __('Clocking in and out needs the attendance screen, which is not built yet.') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('Go to') }}</h2>

                        {{-- Only destinations that have a screen are offered. The
                             other modules answer with JSON today, and a link that
                             hands someone raw JSON is worse than no link. --}}
                        <ul class="list-unstyled mb-0 d-flex flex-wrap gap-2">
                            @can('projects.view')
                                <li>
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('projects.index') }}">
                                        {{ __('Projects') }}
                                    </a>
                                </li>
                            @endcan
                            @can('staff.view.direct_reports')
                                <li>
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('staff.index') }}">
                                        {{ __('Staff') }}
                                    </a>
                                </li>
                            @endcan
                            @can('staff.invite')
                                <li>
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('invitations.index') }}">
                                        {{ __('Invitations') }}
                                    </a>
                                </li>
                            @endcan
                            @can('audit.view')
                                <li>
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('audit.index') }}">
                                        {{ __('Audit trail') }}
                                    </a>
                                </li>
                            @endcan
                            <li>
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('notifications.index') }}">
                                    {{ __('Notifications') }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endunless
@endsection
