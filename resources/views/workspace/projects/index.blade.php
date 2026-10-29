{{--
    Project list. P2.01-FE and P2.04-FE.

    Rows come from ProjectController@index, which has already applied tenancy
    and the viewer's projects.view scope through ProjectService::visibleProjects.
    A project absent from this list is one the server decided the viewer may not
    see, so the template never widens the set and never queries for more.

    Create is a disclosure rather than a separate page: naming a project is four
    fields, and a round trip to a form page for four fields is a round trip for
    nothing. It reopens on a validation error so the typed values stay visible.
--}}
@extends('layouts.app')

@section('title', __('Projects'))

@php
    $statuses = [
        'planned' => __('Planned'),
        'active' => __('Active'),
        'on_hold' => __('On hold'),
        'completed' => __('Completed'),
        'archived' => __('Archived'),
    ];

    $search = request('search', '');
    $status = request('status', '');
    $filtered = filled($search) || filled($status);

    // Any validation error here came from the create form, which is the only
    // thing on this page that writes.
    $createFailed = $errors->any();
@endphp

@section('content')
    <x-page-header
        :title="__('Projects')"
        :subtitle="__('Work you have permission to see in this workspace.')"
        :breadcrumbs="[__('Workspace') => null, __('Projects') => null]">
        <x-slot:actions>
            @can('projects.manage')
                <button class="btn btn-primary btn-sm"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#new-project"
                        aria-expanded="{{ $createFailed ? 'true' : 'false' }}"
                        aria-controls="new-project">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('New project') }}
                </button>
            @endcan

            @if ($page->total() > 0)
                {{-- The export runs the same scoped query as the list, so it can
                     never contain a project the page does not show. --}}
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('projects.export') }}">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('Export CSV') }}
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @can('projects.manage')
        <div class="collapse @if ($createFailed) show @endif mb-4" id="new-project">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('New project') }}</h2>

                    <form method="POST" action="{{ route('projects.store') }}" novalidate>
                        @csrf

                        <div class="row">
                            <div class="col-12 col-md-7">
                                <x-input name="name" :label="__('Name')" :value="old('name')" required />
                            </div>
                            <div class="col-12 col-md-5">
                                <x-input
                                    name="code"
                                    :label="__('Short code')"
                                    :value="old('code')"
                                    :help="__('Optional, and unique within this organization.')" />
                            </div>
                            <div class="col-12 col-md-4">
                                <x-select
                                    name="status"
                                    :label="__('Status')"
                                    :options="$statuses"
                                    :selected="old('status', 'planned')" />
                            </div>
                            <div class="col-12 col-md-4">
                                <x-input name="starts_on" type="date" :label="__('Starts')" :value="old('starts_on')" />
                            </div>
                            <div class="col-12 col-md-4">
                                <x-input name="ends_on" type="date" :label="__('Ends')" :value="old('ends_on')" />
                            </div>
                            <div class="col-12">
                                <x-textarea name="description" :label="__('Description')" :value="old('description')" rows="3" />
                            </div>
                        </div>

                        {{--
                            Lead and department are accepted by the server but no
                            option list reaches this page, and a free text id field
                            is not a control anyone can use. Both are set after
                            creation instead. Recorded as a handoff in the tracker.
                        --}}
                        <button type="submit" class="btn btn-primary">{{ __('Create project') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    <form method="GET" action="{{ route('projects.index') }}" class="row g-2 align-items-end mb-3">
        <div class="col-12 col-sm-6 col-lg-4">
            <x-input
                name="search"
                :label="__('Search')"
                :value="$search"
                :help="__('Matches the project name or its short code.')" />
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <x-select
                name="status"
                :label="__('Status')"
                :options="$statuses"
                :selected="$status"
                :placeholder="__('Any status')" />
        </div>
        <div class="col-auto mb-3">
            <button type="submit" class="btn btn-outline-secondary">{{ __('Filter') }}</button>
        </div>
        @if ($filtered)
            <div class="col-auto mb-3">
                <a class="btn btn-link px-1" href="{{ route('projects.index') }}">{{ __('Clear') }}</a>
            </div>
        @endif
    </form>

    @if ($page->total() === 0)
        {{-- A filter that matched nothing and a workspace with no projects are
             different situations and must not share one message. --}}
        @if ($filtered)
            <x-empty-state
                icon="search"
                :title="__('No projects match those filters')"
                :description="__('Try a different name or status, or clear the filters to see everything in your scope.')" />
        @else
            <x-empty-state
                icon="kanban"
                :title="__('No projects yet')"
                :description="__('A project groups the tasks, activities and reports that belong to one piece of work.')" />
        @endif
    @else
        <div class="table-responsive">
            <table class="table table-data align-middle">
                <caption class="visually-hidden">{{ __('Projects visible to you in this workspace') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('Project') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Tasks') }}</th>
                        <th scope="col">{{ __('People') }}</th>
                        <th scope="col">{{ __('Dates') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($page as $project)
                        <tr>
                            <th scope="row" class="fw-semibold">
                                <a class="text-body" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>

                                @if ($project->code)
                                    <span class="d-block text-secondary font-monospace small fw-normal">{{ $project->code }}</span>
                                @endif
                            </th>
                            <td>
                                <x-status-badge
                                    :status="$project->status"
                                    :label="$statuses[$project->status] ?? $project->status" />
                            </td>
                            <td class="font-monospace small">{{ $project->tasks_count }}</td>
                            <td class="font-monospace small">{{ $project->members_count }}</td>
                            <td class="font-monospace small text-nowrap">
                                @if ($project->starts_on || $project->ends_on)
                                    {{ optional($project->starts_on)->format('Y-m-d') ?: __('Open') }}
                                    <span class="text-secondary">{{ __('to') }}</span>
                                    {{ optional($project->ends_on)->format('Y-m-d') ?: __('Open') }}
                                @else
                                    <span class="text-secondary">{{ __('Not set') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="{{ route('projects.show', $project) }}">{{ __('Open') }}</a>
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
@endsection
