{{--
    My Tasks. P2.04-FE.

    The server returns only tasks assigned to the reader that are neither done
    nor cancelled, so this is a working list rather than an archive. Nothing is
    filtered here that the server has not already filtered.

    Changing a status needs tasks.assign, which most assignees do not hold, so
    the control appears only for those who do. Everyone else sees the status as
    a badge, which is the truth rather than a disabled control implying they
    are one permission away from using it.
--}}
@extends('layouts.app')

@section('title', __('My Tasks'))

@php
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

    $today = now()->startOfDay();
@endphp

@section('content')
    <x-page-header
        :title="__('My Tasks')"
        :subtitle="__('Work assigned to you that is still open.')"
        :breadcrumbs="[__('Workspace') => null, __('My Tasks') => null]" />

    @if ($page->total() === 0)
        <x-empty-state
            icon="check2-square"
            :title="__('Nothing is assigned to you')"
            :description="__('Tasks appear here as soon as someone assigns one to you. Finished and cancelled work drops off the list.')" />
    @else
        <div class="table-responsive">
            <table class="table table-data align-middle">
                <caption class="visually-hidden">{{ __('Open tasks assigned to you') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('Task') }}</th>
                        <th scope="col">{{ __('Project') }}</th>
                        <th scope="col">{{ __('Priority') }}</th>
                        <th scope="col">{{ __('Due') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($page as $task)
                        @php
                            $isOverdue = $task->due_on && $task->due_on->lt($today);
                        @endphp

                        <tr>
                            <th scope="row" class="fw-semibold">{{ $task->title }}</th>
                            <td>
                                @if ($task->project)
                                    <a class="text-body" href="{{ route('projects.show', $task->project_id) }}">{{ $task->project->name }}</a>
                                @else
                                    <span class="text-secondary">{{ __('Not set') }}</span>
                                @endif
                            </td>
                            <td>{{ $priorities[$task->priority] ?? $task->priority }}</td>
                            <td class="font-monospace small text-nowrap">
                                @if (! $task->due_on)
                                    <span class="text-secondary">{{ __('Not set') }}</span>
                                @elseif ($isOverdue)
                                    {{-- Named in words as well as weighted, so it
                                         survives a monochrome screen. --}}
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
                                        <select class="form-select form-select-sm w-auto" id="status-{{ $task->id }}" name="status">
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
