{{--
    Reports. P3.04-FE and P3.05-FE.

    The server returns the reader's own reports plus any that are submitted or
    returned, which is the reviewer's queue. Both live in one list because the
    same person is often in both positions, and splitting them would hide a
    report from the only person who can move it along.

    A submitted report is snapshotted by the server, so reviewing one never
    reads later edits. Nothing on this page re-reads the live record either:
    what a reviewer sees is what was submitted.

    The report templates carry no field definitions yet, so the shape of a
    submission is decided here: a summary, what was achieved, and what is in
    the way. When the template defines its own fields this form follows them.
--}}
@extends('layouts.app')

@section('title', __('Reports'))

@php
    $statuses = [
        'draft' => __('Draft'),
        'submitted' => __('Submitted'),
        'returned' => __('Returned for changes'),
        'approved' => __('Approved'),
    ];
@endphp

@section('content')
    <x-page-header
        :title="__('Reports')"
        :subtitle="__('Your reports, and any waiting on your review.')"
        :breadcrumbs="[__('Workspace') => null, __('Reports') => null]">
        <x-slot:actions>
            @if ($page->total() > 0)
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('reports.export') }}">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('Export CSV') }}
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($page->total() === 0)
        <x-empty-state
            icon="file-earmark-text"
            :title="__('No reports yet')"
            :description="__('A report is raised against a template and a period. Nothing on this screen creates one, so they appear here once the schedule opens them.')" />
    @else
        <div class="d-flex flex-column gap-3">
            @foreach ($page as $report)
                @php
                    $isAuthor = $report->membership_id === $actor->id;
                    $latest = $report->versions->sortByDesc('version')->first();
                    // The author may still work on anything not yet approved.
                    $canSubmit = $isAuthor && $report->status !== 'approved';
                    // A reviewer is never the author, which the server enforces too.
                    $canReview = ! $isAuthor
                        && in_array($report->status, ['submitted', 'returned'], true)
                        && Gate::allows('reports.review');
                @endphp

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                            <div class="min-w-0">
                                <h2 class="h6 mb-1">{{ $report->template?->name ?: __('Report') }}</h2>
                                <p class="text-secondary small mb-0 font-monospace">
                                    {{ optional($report->period_start)->format('Y-m-d') }}
                                    {{ __('to') }}
                                    {{ optional($report->period_end)->format('Y-m-d') }}
                                </p>
                                <p class="text-secondary small mb-0">
                                    {{ $isAuthor ? __('Yours') : $report->membership?->user?->name }}
                                </p>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <x-status-badge
                                    :status="$report->status"
                                    :label="$statuses[$report->status] ?? $report->status" />

                                @if ($latest)
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('reports.pdf', $report) }}">
                                        {{ __('PDF') }}
                                    </a>
                                @endif
                            </div>
                        </div>

                        @if ($latest)
                            <p class="text-secondary small mb-3">
                                {{ __('Version :version, submitted :when', [
                                    'version' => $latest->version,
                                    'when' => optional($latest->submitted_at)->format('Y-m-d H:i') ?: __('not recorded'),
                                ]) }}
                            </p>
                        @endif

                        @if ($canSubmit)
                            <details @if ($errors->has('snapshot')) open @endif>
                                <summary class="small">{{ $latest ? __('Submit a new version') : __('Submit this report') }}</summary>

                                <form method="POST" action="{{ route('reports.submit', $report) }}" class="mt-3" novalidate>
                                    @csrf

                                    <x-textarea
                                        name="snapshot[summary]"
                                        :label="__('Summary')"
                                        :value="old('snapshot.summary')"
                                        rows="3"
                                        required />

                                    <x-textarea
                                        name="snapshot[achieved]"
                                        :label="__('What was achieved')"
                                        :value="old('snapshot.achieved')"
                                        rows="3" />

                                    <x-textarea
                                        name="snapshot[blockers]"
                                        :label="__('What is in the way')"
                                        :value="old('snapshot.blockers')"
                                        rows="2" />

                                    <p class="text-secondary small">
                                        {{ __('Submitting stores a copy. Later edits to anything referenced here do not change what the reviewer reads.') }}
                                    </p>

                                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Submit report') }}</button>
                                </form>
                            </details>
                        @endif

                        @if ($canReview)
                            <details class="mt-2" @if ($errors->has('decision')) open @endif>
                                <summary class="small">{{ __('Review this report') }}</summary>

                                @if ($latest && is_array($latest->snapshot))
                                    <dl class="row small mt-3 mb-3">
                                        @foreach ($latest->snapshot as $key => $value)
                                            <dt class="col-4 col-md-3 fw-normal text-secondary">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                                            <dd class="col-8 col-md-9">{{ is_array($value) ? implode(', ', $value) : $value }}</dd>
                                        @endforeach
                                    </dl>
                                @endif

                                <form method="POST" action="{{ route('reports.review', $report) }}" novalidate>
                                    @csrf

                                    <x-select
                                        name="decision"
                                        :label="__('Decision')"
                                        :options="['approved' => __('Approve'), 'returned' => __('Return for changes')]"
                                        :selected="old('decision')"
                                        :placeholder="__('Choose a decision')"
                                        required />

                                    <x-textarea
                                        name="comment"
                                        :label="__('Comment')"
                                        :value="old('comment')"
                                        rows="2"
                                        :help="__('Required in practice when returning a report, so the author knows what to change.')" />

                                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Record decision') }}</button>
                                </form>
                            </details>
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
@endsection
