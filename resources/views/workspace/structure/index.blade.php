{{--
    Departments and teams. P1.04-FE.

    One template serves both, because the server serves both from one endpoint
    with a `kind` route default and they are the same shape: a named grouping
    inside the organization. The wording changes, the structure does not.

    Only creation and listing exist on the server today. Rename and delete are
    not offered here, because offering a control that has no endpoint behind it
    is worse than not offering it.
--}}
@extends('layouts.app')

@php
    $isDepartment = $kind === 'departments';

    $copy = $isDepartment
        ? [
            'title' => __('Departments'),
            'subtitle' => __('The permanent divisions of the organization. Staff belong to one, and department scope is measured against it.'),
            'label' => __('Department name'),
            'action' => __('Add department'),
            'emptyTitle' => __('No departments yet'),
            'emptyBody' => __('Departments are how reporting and approval scope is decided. Add the first one to start placing staff.'),
            'icon' => 'diagram-3',
        ]
        : [
            'title' => __('Teams'),
            'subtitle' => __('Working groups inside the organization. A person can sit in a team without changing which department they belong to.'),
            'label' => __('Team name'),
            'action' => __('Add team'),
            'emptyTitle' => __('No teams yet'),
            'emptyBody' => __('Teams are optional. Add one when a group of people work together across or within departments.'),
            'icon' => 'people',
        ];
@endphp

@section('title', $copy['title'])

@section('content')
    <x-page-header
        :title="$copy['title']"
        :subtitle="$copy['subtitle']"
        :breadcrumbs="[__('Workspace') => null, $copy['title'] => null]" />

    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ $copy['action'] }}</h2>

                    <form method="POST" action="{{ route($kind.'.store') }}" novalidate>
                        @csrf

                        <x-input
                            name="name"
                            :label="$copy['label']"
                            required
                            :help="__('Must be unique within this organization.')" />

                        <button type="submit" class="btn btn-primary">{{ $copy['action'] }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            @if ($rows->isEmpty())
                <x-empty-state
                    :icon="$copy['icon']"
                    :title="$copy['emptyTitle']"
                    :description="$copy['emptyBody']" />
            @else
                <div class="table-responsive">
                    <table class="table table-data align-middle">
                        <caption class="visually-hidden">{{ $copy['title'] }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Name') }}</th>
                                <th scope="col">{{ __('Added') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <th scope="row" class="fw-semibold">{{ $row->name }}</th>
                                    <td class="font-monospace small text-secondary">
                                        {{ optional($row->created_at)->format('Y-m-d') ?: __('Not recorded') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-secondary small mb-0">
                    {{ trans_choice('{1}:count entry|[2,*]:count entries', $rows->count(), ['count' => $rows->count()]) }}
                </p>
            @endif
        </div>
    </div>
@endsection
