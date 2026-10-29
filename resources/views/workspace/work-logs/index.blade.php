{{--
    Work logs. P3.03-FE.

    A work log is hours claimed against a day, and it is deliberately not the
    same thing as an activity note or an attendance session. The master plan
    keeps the three apart because only this one is approved, and payroll must
    never be derived from the other two by accident. The copy on this page says
    so rather than leaving the reader to infer it.

    A pending log can still be edited; an approved one cannot, and the server
    refuses it with a 404 rather than a message, so no edit control is rendered
    for an approved row.

    The server returns the reader's own logs only. There is no approver queue
    here because no endpoint lists anyone else's logs, and a member cannot
    approve their own. Recorded as a handoff in the tracker.
--}}
@extends('layouts.app')

@section('title', __('Work Logs'))

@section('content')
    <x-page-header
        :title="__('Work Logs')"
        :subtitle="__('Hours you have claimed, and where each one stands.')"
        :breadcrumbs="[__('Workspace') => null, __('Work Logs') => null]" />

    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Log hours') }}</h2>

                    <form method="POST" action="{{ route('work-logs.store') }}" novalidate>
                        @csrf

                        <x-input
                            name="work_date"
                            type="date"
                            :label="__('Date worked')"
                            :value="old('work_date', now()->format('Y-m-d'))"
                            required />

                        <x-input
                            name="hours"
                            type="number"
                            step="0.25"
                            min="0.25"
                            max="24"
                            :label="__('Hours')"
                            :value="old('hours')"
                            required
                            :help="__('More than zero and no more than 24.')" />

                        <x-select
                            name="project_id"
                            :label="__('Project')"
                            :options="$projects->all()"
                            :selected="old('project_id')"
                            :placeholder="__('Not tied to a project')" />

                        <x-textarea name="comment" :label="__('What the time went on')" :value="old('comment')" rows="3" />

                        <button type="submit" class="btn btn-primary">{{ __('Log hours') }}</button>
                    </form>

                    <p class="text-secondary small mb-0 mt-3">
                        {{ __('A work log is reviewed and approved. It is not your attendance record and it is not a daily activity note.') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            @if ($page->total() === 0)
                <x-empty-state
                    icon="clock-history"
                    :title="__('No hours logged yet')"
                    :description="__('Logged hours go to a reviewer. Until one is approved it stays pending and can still be changed.')" />
            @else
                <div class="table-responsive">
                    <table class="table table-data align-middle">
                        <caption class="visually-hidden">{{ __('Hours you have logged') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Date') }}</th>
                                <th scope="col">{{ __('Hours') }}</th>
                                <th scope="col">{{ __('Against') }}</th>
                                <th scope="col">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($page as $log)
                                <tr>
                                    <td class="font-monospace small text-nowrap">
                                        {{ optional($log->work_date)->format('Y-m-d') }}
                                    </td>
                                    <th scope="row" class="fw-semibold font-monospace">{{ rtrim(rtrim(number_format((float) $log->hours, 2), '0'), '.') }}</th>
                                    <td>
                                        @if ($log->project)
                                            <a class="text-body" href="{{ route('projects.show', $log->project_id) }}">{{ $log->project->name }}</a>
                                        @else
                                            <span class="text-secondary">{{ __('No project') }}</span>
                                        @endif

                                        @if ($log->comment)
                                            <span class="d-block text-secondary small">{{ Str::limit($log->comment, 120) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <x-status-badge :status="$log->status" />

                                        @if ($log->status === 'approved' && $log->approved_at)
                                            <span class="d-block text-secondary small font-monospace">{{ $log->approved_at->format('Y-m-d') }}</span>
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
