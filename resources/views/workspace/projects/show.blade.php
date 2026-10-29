{{--
    Project detail. P2.01-FE, P2.02-FE and P2.04-FE.

    The project arrives already filtered through ProjectService::visibleProjects,
    so reaching this page is itself the authorization decision. Controls that
    write are gated separately, because seeing a project does not imply being
    allowed to change it.

    Progress deliberately counts only tasks that still exist as work. A
    cancelled task is not an achievement and is not a burden, so it leaves the
    denominator entirely; a project whose every task was cancelled reads as not
    started rather than as complete.
--}}
@extends('layouts.app')

@section('title', $project->name)

@php
    // One query each for the people on the project and the two memberships that
    // own and lead it, instead of one per row while rendering. The controller
    // eager loads tasks and milestones only.
    $project->loadMissing(['members.user', 'owner.user', 'lead.user']);

    // The projects table carries department_id but the Project model declares
    // no department relation, so it cannot be eager loaded. One lookup, only
    // when the project actually has a department. Handed to Codex to replace
    // with a relation.
    $department = $project->department_id
        ? \App\Models\Department::find($project->department_id)
        : null;

    $projectStatuses = [
        'planned' => __('Planned'),
        'active' => __('Active'),
        'on_hold' => __('On hold'),
        'completed' => __('Completed'),
        'archived' => __('Archived'),
    ];

    $taskStatuses = [
        'todo' => __('To do'),
        'in_progress' => __('In progress'),
        'blocked' => __('Blocked'),
        'done' => __('Done'),
        'cancelled' => __('Cancelled'),
    ];

    $priorities = [
        'low' => __('Low'),
        'normal' => __('Normal'),
        'high' => __('High'),
        'urgent' => __('Urgent'),
    ];

    $tasks = $project->tasks;
    $live = $tasks->where('status', '!=', 'cancelled');
    $done = $live->where('status', 'done');
    $percent = $live->count() > 0 ? (int) round($done->count() / $live->count() * 100) : null;

    $today = now()->startOfDay();
    $overdue = $tasks->filter(fn ($task) => $task->due_on
        && $task->due_on->lt($today)
        && ! in_array($task->status, ['done', 'cancelled'], true));
    $blocked = $tasks->where('status', 'blocked');

    $assignees = $project->members
        ->sortBy(fn ($member) => $member->user?->name)
        ->mapWithKeys(fn ($member) => [$member->id => $member->user?->name ?? __('Unnamed member')])
        ->all();

    $milestones = $project->milestones->sortBy('due_on');

    // Both write forms on this page are disclosures, and a rejected submission
    // has to reopen the one it came from or the reader loses what they typed.
    $taskFormFailed = $errors->hasAny(['title', 'priority', 'due_on', 'assignee_membership_id', 'milestone_id']);
    $projectFormFailed = $errors->hasAny(['name', 'starts_on', 'ends_on']);
@endphp

@section('content')
    <x-page-header
        :title="$project->name"
        :subtitle="$project->description ?: null"
        :breadcrumbs="[__('Workspace') => null, __('Projects') => route('projects.index'), $project->name => null]">
        <x-slot:actions>
            @can('projects.manage')
                <button class="btn btn-outline-secondary btn-sm"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#edit-project"
                        aria-expanded="{{ $projectFormFailed ? 'true' : 'false' }}"
                        aria-controls="edit-project">
                    {{ __('Edit project') }}
                </button>
            @endcan

            @can('tasks.assign')
                <button class="btn btn-primary btn-sm"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#new-task"
                        aria-expanded="{{ $taskFormFailed ? 'true' : 'false' }}"
                        aria-controls="new-task">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Add task') }}
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @can('projects.manage')
        <div class="collapse @if ($projectFormFailed) show @endif mb-4" id="edit-project">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Edit project') }}</h2>

                    <form method="POST" action="{{ route('projects.update', $project) }}" novalidate>
                        @csrf
                        @method('PATCH')

                        <div class="row">
                            <div class="col-12 col-md-6">
                                <x-input name="name" :label="__('Name')" :value="old('name', $project->name)" required />
                            </div>
                            <div class="col-12 col-md-6">
                                <x-select
                                    name="status"
                                    :label="__('Status')"
                                    :options="$projectStatuses"
                                    :selected="old('status', $project->status)" />
                            </div>
                            <div class="col-12 col-md-6">
                                <x-input
                                    name="starts_on"
                                    type="date"
                                    :label="__('Starts')"
                                    :value="old('starts_on', optional($project->starts_on)->format('Y-m-d'))" />
                            </div>
                            <div class="col-12 col-md-6">
                                <x-input
                                    name="ends_on"
                                    type="date"
                                    :label="__('Ends')"
                                    :value="old('ends_on', optional($project->ends_on)->format('Y-m-d'))" />
                            </div>
                            <div class="col-12">
                                <x-textarea
                                    name="description"
                                    :label="__('Description')"
                                    :value="old('description', $project->description)"
                                    rows="3" />
                            </div>
                        </div>

                        {{-- The short code is fixed after creation, because tasks,
                             reports and exports already refer to the project by it. --}}
                        <button type="submit" class="btn btn-primary">{{ __('Save project') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    @can('tasks.assign')
        <div class="collapse @if ($taskFormFailed) show @endif mb-4" id="new-task">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Add task') }}</h2>

                    <form method="POST" action="{{ route('projects.tasks.store', $project) }}" novalidate>
                        @csrf

                        <div class="row">
                            <div class="col-12">
                                <x-input name="title" :label="__('Title')" :value="old('title')" required />
                            </div>
                            <div class="col-12 col-md-6">
                                <x-select
                                    name="assignee_membership_id"
                                    :label="__('Assignee')"
                                    :options="$assignees"
                                    :selected="old('assignee_membership_id')"
                                    :placeholder="__('Nobody yet')"
                                    :help="__('People on this project. Add someone to the project first to assign them.')" />
                            </div>
                            @if ($milestones->isNotEmpty())
                                <div class="col-12 col-md-6">
                                    <x-select
                                        name="milestone_id"
                                        :label="__('Milestone')"
                                        :options="$milestones->pluck('name', 'id')->all()"
                                        :selected="old('milestone_id')"
                                        :placeholder="__('No milestone')" />
                                </div>
                            @endif
                            <div class="col-12 col-md-4">
                                <x-select
                                    name="status"
                                    :label="__('Status')"
                                    :options="$taskStatuses"
                                    :selected="old('status', 'todo')" />
                            </div>
                            <div class="col-12 col-md-4">
                                <x-select
                                    name="priority"
                                    :label="__('Priority')"
                                    :options="$priorities"
                                    :selected="old('priority', 'normal')" />
                            </div>
                            <div class="col-12 col-md-4">
                                <x-input name="due_on" type="date" :label="__('Due')" :value="old('due_on')" />
                            </div>
                            <div class="col-12">
                                <x-textarea name="description" :label="__('Description')" :value="old('description')" rows="3" />
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">{{ __('Add task') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-7">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Progress') }}</h2>

                    @if ($live->isEmpty())
                        <p class="mb-1 fw-semibold">{{ __('Not started') }}</p>
                        <p class="text-secondary small mb-0">
                            {{ $tasks->isEmpty()
                                ? __('No tasks have been added to this project.')
                                : __('Every task on this project is cancelled, so there is no work left to measure.') }}
                        </p>
                    @else
                        <div class="d-flex align-items-baseline justify-content-between mb-2">
                            <span class="fw-semibold">{{ $percent }}%</span>
                            <span class="text-secondary small">
                                {{ __(':done of :total tasks done', ['done' => $done->count(), 'total' => $live->count()]) }}
                            </span>
                        </div>

                        <div class="progress" role="img"
                             aria-label="{{ __(':percent per cent of tasks done', ['percent' => $percent]) }}">
                            <div class="progress-bar" style="width: {{ $percent }}%"></div>
                        </div>

                        @if ($tasks->count() > $live->count())
                            <p class="text-secondary small mt-2 mb-0">
                                {{ trans_choice(
                                    '{1}:count cancelled task is not counted|[2,*]:count cancelled tasks are not counted',
                                    $tasks->count() - $live->count(),
                                    ['count' => $tasks->count() - $live->count()],
                                ) }}
                            </p>
                        @endif
                    @endif

                    {{-- Overdue and blocked work stays visible whatever the
                         percentage says, because a project can read as nearly
                         finished while the remainder cannot move. --}}
                    @if ($overdue->isNotEmpty() || $blocked->isNotEmpty())
                        <ul class="list-unstyled mb-0 mt-3 d-flex flex-wrap gap-3">
                            @if ($overdue->isNotEmpty())
                                <li>
                                    <x-status-badge
                                        status="late"
                                        :label="trans_choice('{1}:count overdue task|[2,*]:count overdue tasks', $overdue->count(), ['count' => $overdue->count()])" />
                                </li>
                            @endif
                            @if ($blocked->isNotEmpty())
                                <li>
                                    <x-status-badge
                                        status="blocked"
                                        :label="trans_choice('{1}:count blocked task|[2,*]:count blocked tasks', $blocked->count(), ['count' => $blocked->count()])" />
                                </li>
                            @endif
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Details') }}</h2>

                    <dl class="row mb-0 small">
                        <dt class="col-5 fw-normal text-secondary">{{ __('Status') }}</dt>
                        <dd class="col-7">
                            <x-status-badge
                                :status="$project->status"
                                :label="$projectStatuses[$project->status] ?? $project->status" />
                        </dd>

                        <dt class="col-5 fw-normal text-secondary">{{ __('Short code') }}</dt>
                        <dd class="col-7 font-monospace">{{ $project->code ?: __('Not set') }}</dd>

                        <dt class="col-5 fw-normal text-secondary">{{ __('Department') }}</dt>
                        <dd class="col-7">{{ $department?->name ?: __('Not set') }}</dd>

                        <dt class="col-5 fw-normal text-secondary">{{ __('Owner') }}</dt>
                        <dd class="col-7">{{ $project->owner?->user?->name ?: __('Not set') }}</dd>

                        <dt class="col-5 fw-normal text-secondary">{{ __('Lead') }}</dt>
                        <dd class="col-7">{{ $project->lead?->user?->name ?: __('Not set') }}</dd>

                        <dt class="col-5 fw-normal text-secondary">{{ __('Dates') }}</dt>
                        <dd class="col-7 font-monospace">
                            @if ($project->starts_on || $project->ends_on)
                                {{ optional($project->starts_on)->format('Y-m-d') ?: __('Open') }}
                                {{ __('to') }}
                                {{ optional($project->ends_on)->format('Y-m-d') ?: __('Open') }}
                            @else
                                {{ __('Not set') }}
                            @endif
                        </dd>

                        <dt class="col-5 fw-normal text-secondary">{{ __('People') }}</dt>
                        <dd class="col-7 mb-0">
                            @if ($project->members->isEmpty())
                                {{ __('Nobody yet') }}
                            @else
                                {{ $project->members->map(fn ($member) => $member->user?->name ?? __('Unnamed member'))->join(', ') }}
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    @if ($milestones->isNotEmpty())
        <h2 class="h6 mb-2">{{ __('Milestones') }}</h2>

        <div class="table-responsive mb-4">
            <table class="table table-data align-middle">
                <caption class="visually-hidden">{{ __('Milestones on this project') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('Milestone') }}</th>
                        <th scope="col">{{ __('Due') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($milestones as $milestone)
                        <tr>
                            <th scope="row" class="fw-semibold">{{ $milestone->name }}</th>
                            <td class="font-monospace small">
                                {{ optional($milestone->due_on)->format('Y-m-d') ?: __('Not set') }}
                            </td>
                            <td><x-status-badge :status="$milestone->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 class="h6 mb-2">{{ __('Tasks') }}</h2>

    @if ($tasks->isEmpty())
        <x-empty-state
            icon="check2-square"
            :title="__('No tasks yet')"
            :description="__('Tasks are the unit of work people are assigned and report against.')" />
    @else
        <div class="table-responsive">
            <table class="table table-data align-middle">
                <caption class="visually-hidden">{{ __('Tasks on this project') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('Task') }}</th>
                        <th scope="col">{{ __('Assignee') }}</th>
                        <th scope="col">{{ __('Priority') }}</th>
                        <th scope="col">{{ __('Due') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tasks->sortBy('position') as $task)
                        @php
                            $isOverdue = $task->due_on
                                && $task->due_on->lt($today)
                                && ! in_array($task->status, ['done', 'cancelled'], true);
                        @endphp

                        <tr>
                            <th scope="row" class="fw-semibold">
                                {{ $task->title }}

                                @if ($task->description)
                                    <span class="d-block text-secondary small fw-normal">{{ Str::limit($task->description, 120) }}</span>
                                @endif
                            </th>
                            <td>{{ $task->assignee?->user?->name ?: __('Unassigned') }}</td>
                            <td>{{ $priorities[$task->priority] ?? $task->priority }}</td>
                            <td class="font-monospace small text-nowrap">
                                @if (! $task->due_on)
                                    <span class="text-secondary">{{ __('Not set') }}</span>
                                @elseif ($isOverdue)
                                    {{-- Overdue is carried by a word as well as by
                                         weight, so it survives a monochrome screen. --}}
                                    <span class="fw-semibold">{{ $task->due_on->format('Y-m-d') }}</span>
                                    <span class="d-block fw-normal">{{ __('Overdue') }}</span>
                                @else
                                    {{ $task->due_on->format('Y-m-d') }}
                                @endif
                            </td>
                            <td>
                                @can('tasks.assign')
                                    <form method="POST" action="{{ route('tasks.update', $task) }}" class="d-flex gap-1">
                                        @csrf
                                        @method('PATCH')

                                        <label class="visually-hidden" for="status-{{ $task->id }}">
                                            {{ __('Status of :task', ['task' => $task->title]) }}
                                        </label>
                                        <select class="form-select form-select-sm w-auto"
                                                id="status-{{ $task->id }}"
                                                name="status">
                                            @foreach ($taskStatuses as $value => $label)
                                                <option value="{{ $value }}" @selected($task->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>

                                        <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Save') }}</button>
                                    </form>
                                @else
                                    <x-status-badge
                                        :status="$task->status"
                                        :label="$taskStatuses[$task->status] ?? $task->status" />
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-secondary small mb-0">
            {{ trans_choice('{1}:count task|[2,*]:count tasks', $tasks->count(), ['count' => $tasks->count()]) }}
        </p>
    @endif
@endsection
