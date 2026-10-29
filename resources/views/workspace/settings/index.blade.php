{{--
    Organization settings. P1.01-FE.

    Only the fields the update endpoint actually validates are offered, so this
    form cannot submit something the server will silently ignore. Identity
    fields it does not accept, such as the slug, are shown read only rather than
    hidden, because an administrator looking for them should find them and see
    that they are fixed.

    Every save requires a reason, which goes to the audit trail.
--}}
@extends('layouts.app')

@section('title', __('Organization settings'))

@php
    $settings = (array) ($organization->settings ?? []);

    $workDays = (array) ($settings['work_days'] ?? []);

    $dayNames = [
        1 => __('Monday'),
        2 => __('Tuesday'),
        3 => __('Wednesday'),
        4 => __('Thursday'),
        5 => __('Friday'),
        6 => __('Saturday'),
        7 => __('Sunday'),
    ];

    $timezones = collect(timezone_identifiers_list())->mapWithKeys(fn ($zone) => [$zone => $zone]);
@endphp

@section('content')
    <x-page-header
        :title="__('Organization settings')"
        :subtitle="__('How this organization is named, where it works and when its week runs.')"
        :breadcrumbs="[__('Workspace') => null, __('Settings') => null]" />

    <form method="POST" action="{{ route('settings.organization.update') }}" novalidate>
        @csrf
        @method('PATCH')

        <div class="row g-4">
            <div class="col-12 col-xl-7">
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('Identity') }}</h2>

                        <x-input name="name" :label="__('Organization name')" :value="$organization->name" required />

                        <div class="mb-3">
                            <label class="form-label" for="slug">{{ __('Short code') }}</label>
                            <input class="form-control" id="slug" value="{{ $organization->slug }}" readonly disabled>
                            <div class="form-text">
                                {{ __('Fixed once the organization is created, because other records refer to it.') }}
                            </div>
                        </div>

                        <x-input
                            name="contact_email"
                            type="email"
                            :label="__('Contact email')"
                            :value="$organization->contact_email"
                            :help="__('Used as the reply address on notifications this organization sends.')" />

                        <div class="mb-3">
                            <label class="form-label" for="address">{{ __('Address') }}</label>
                            <textarea class="form-control" id="address" name="address" rows="3">{{ old('address', $organization->address) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('Working week') }}</h2>
                        <p class="text-secondary small">
                            {{ __('Attendance and reporting use these to decide what counts as a working day and when a report is late.') }}
                        </p>

                        <fieldset class="mb-3">
                            <legend class="form-label">{{ __('Working days') }}</legend>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach ($dayNames as $number => $label)
                                    <div class="form-check">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="settings[work_days][]"
                                               value="{{ $number }}"
                                               id="work-day-{{ $number }}"
                                               @checked(in_array($number, $workDays))>
                                        <label class="form-check-label" for="work-day-{{ $number }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="row g-3">
                            <div class="col-12 col-sm-4">
                                <label class="form-label" for="work-start">{{ __('Day starts') }}</label>
                                <input class="form-control"
                                       type="time"
                                       id="work-start"
                                       name="settings[work_start]"
                                       value="{{ old('settings.work_start', $settings['work_start'] ?? '') }}">
                            </div>
                            <div class="col-12 col-sm-4">
                                <label class="form-label" for="work-end">{{ __('Day ends') }}</label>
                                <input class="form-control"
                                       type="time"
                                       id="work-end"
                                       name="settings[work_end]"
                                       value="{{ old('settings.work_end', $settings['work_end'] ?? '') }}">
                            </div>
                            <div class="col-12 col-sm-4">
                                <label class="form-label" for="report-due">{{ __('Report due after') }}</label>
                                <div class="input-group">
                                    <input class="form-control"
                                           type="number"
                                           id="report-due"
                                           name="settings[report_due_days_after_period]"
                                           min="0"
                                           max="14"
                                           value="{{ old('settings.report_due_days_after_period', $settings['report_due_days_after_period'] ?? '') }}">
                                    <span class="input-group-text">{{ __('days') }}</span>
                                </div>
                                <div class="form-text">{{ __('Counted from the end of the period, 0 to 14.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('Locale') }}</h2>

                        <x-select
                            name="timezone"
                            :label="__('Time zone')"
                            :options="$timezones"
                            :selected="$organization->timezone"
                            :help="__('Decides the day boundary for attendance and for reporting periods.')" />

                        <x-input
                            name="currency"
                            :label="__('Currency')"
                            :value="$organization->currency"
                            :help="__('Three letter code, such as GHS or USD. Used to label payroll records, which this system stores but does not pay out.')" />
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('Record this change') }}</h2>

                        <x-input
                            name="reason"
                            :label="__('Reason')"
                            required
                            :help="__('Required. Kept in the audit trail next to what changed.')" />

                        <button type="submit" class="btn btn-primary">{{ __('Save settings') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
