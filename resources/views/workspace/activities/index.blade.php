{{--
    Daily activities. P3.01-FE.

    An activity is what one person did on one day, in their own words. The
    server returns the reader's own entries only, so there is no scope question
    on this page and no filter that could widen it.

    Recording and the record sit on one page because the question a person has
    when they open it is almost always "did I write today's up yet", and the
    answer is the first row of the list.
--}}
@extends('layouts.app')

@section('title', __('Daily Activities'))

@php
    $formFailed = $errors->any();
@endphp

@section('content')
    <x-page-header
        :title="__('Daily Activities')"
        :subtitle="__('What you did, day by day. Only you see your entries here.')"
        :breadcrumbs="[__('Workspace') => null, __('Daily Activities') => null]" />

    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Record an activity') }}</h2>

                    <form method="POST" action="{{ route('activities.store') }}" novalidate>
                        @csrf

                        <x-input
                            name="activity_date"
                            type="date"
                            :label="__('Date')"
                            :value="old('activity_date', now()->format('Y-m-d'))"
                            required />

                        <x-input name="title" :label="__('What you worked on')" :value="old('title')" required />

                        <x-select
                            name="project_id"
                            :label="__('Project')"
                            :options="$projects->all()"
                            :selected="old('project_id')"
                            :placeholder="__('Not tied to a project')"
                            :help="__('Only projects you can see are listed.')" />

                        <x-textarea name="narrative" :label="__('Detail')" :value="old('narrative')" rows="3" />

                        <x-textarea
                            name="blockers"
                            :label="__('Anything blocking you')"
                            :value="old('blockers')"
                            rows="2"
                            :help="__('Leave empty if nothing is in your way.')" />

                        <div class="row">
                            <div class="col-6">
                                <x-input
                                    name="progress"
                                    type="number"
                                    :label="__('Progress')"
                                    :value="old('progress')"
                                    min="0"
                                    max="100"
                                    :help="__('Per cent, 0 to 100.')" />
                            </div>
                            <div class="col-6">
                                <x-input
                                    name="duration_minutes"
                                    type="number"
                                    :label="__('Time spent')"
                                    :value="old('duration_minutes')"
                                    min="0"
                                    max="1440"
                                    :help="__('Minutes. This is a note, not an approved work log.')" />
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">{{ __('Save activity') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            @if ($page->total() === 0)
                <x-empty-state
                    icon="journal-text"
                    :title="__('No activities recorded yet')"
                    :description="__('An activity is a short note about one day of work. It is separate from attendance and from an approved work log.')" />
            @else
                <div class="table-responsive">
                    <table class="table table-data align-middle">
                        <caption class="visually-hidden">{{ __('Your recorded activities') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Date') }}</th>
                                <th scope="col">{{ __('Activity') }}</th>
                                <th scope="col">{{ __('Progress') }}</th>
                                <th scope="col">{{ __('Time') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($page as $activity)
                                <tr>
                                    <td class="font-monospace small text-nowrap">
                                        {{ optional($activity->activity_date)->format('Y-m-d') }}
                                    </td>
                                    <th scope="row" class="fw-semibold">
                                        {{ $activity->title }}

                                        @if ($activity->narrative)
                                            <span class="d-block text-secondary small fw-normal">{{ Str::limit($activity->narrative, 140) }}</span>
                                        @endif

                                        @if ($activity->blockers)
                                            {{-- A blocker is the reason to read this
                                                 row at all, so it is labelled rather
                                                 than folded into the detail. --}}
                                            <span class="d-block small fw-normal mt-1">
                                                <x-status-badge status="blocked" :label="__('Blocked')" />
                                                <span class="ms-1">{{ Str::limit($activity->blockers, 120) }}</span>
                                            </span>
                                        @endif
                                    </th>
                                    <td class="font-monospace small">
                                        {{ $activity->progress === null ? __('Not set') : $activity->progress.'%' }}
                                    </td>
                                    <td class="font-monospace small text-nowrap">
                                        @if ($activity->duration_minutes)
                                            {{ intdiv($activity->duration_minutes, 60) }}h {{ $activity->duration_minutes % 60 }}m
                                        @else
                                            <span class="text-secondary">{{ __('Not set') }}</span>
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
