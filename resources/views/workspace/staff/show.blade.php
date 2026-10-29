{{--
    Staff member detail and administration. P1.04-FE.

    Three separate forms, deliberately not one. Editing someone's posting,
    changing their membership status and changing their roles are different
    decisions with different consequences, and the server exposes them as three
    endpoints. Merging them into a single Save would let a careless click
    suspend someone while correcting a job title.

    Every write requires a reason, because the audit trail records why as well
    as what. The reason field is therefore not optional decoration.

    The server revalidates ownership, scope and every option on write. Nothing
    here is load bearing for authorization.
--}}
@extends('layouts.app')

@section('title', $member['name'])

@section('content')
    <x-page-header
        :title="$member['name']"
        :subtitle="$member['email']"
        :breadcrumbs="[
            __('Workspace') => null,
            __('Staff') => route('staff.index'),
            $member['name'] => null,
        ]">
        <x-slot:actions>
            <x-status-badge :status="$member['status']" />
        </x-slot:actions>
    </x-page-header>

    <div class="row g-4">
        <div class="col-12 col-xl-7">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Posting') }}</h2>

                    <form method="POST" action="{{ route('staff.update', $member['id']) }}" novalidate>
                        @csrf
                        @method('PATCH')

                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <x-input
                                    name="employee_number"
                                    :label="__('Employee number')"
                                    :value="$member['employee_number']"
                                    :help="__('Unique within this organization. Leave blank if one has not been issued.')" />
                            </div>
                            <div class="col-12 col-sm-6">
                                <x-input name="title" :label="__('Job title')" :value="$member['title']" />
                            </div>
                            <div class="col-12 col-sm-6">
                                <x-select
                                    name="department_id"
                                    :label="__('Department')"
                                    :options="$departments"
                                    :selected="$member['department_id']"
                                    :placeholder="__('No department')" />
                            </div>
                            <div class="col-12 col-sm-6">
                                <x-select
                                    name="team_id"
                                    :label="__('Team')"
                                    :options="$teams"
                                    :selected="$member['team_id']"
                                    :placeholder="__('No team')" />
                            </div>
                            <div class="col-12">
                                <x-select
                                    name="manager_membership_id"
                                    :label="__('Reports to')"
                                    :options="$managers"
                                    :selected="$member['manager_membership_id']"
                                    :placeholder="__('No manager')"
                                    :help="__('Only active members of this organization can be selected.')" />
                            </div>
                            <div class="col-12 col-sm-6">
                                <x-input
                                    name="start_date"
                                    type="date"
                                    :label="__('Start date')"
                                    :value="$member['start_date']" />
                            </div>
                            <div class="col-12 col-sm-6">
                                <x-input
                                    name="end_date"
                                    type="date"
                                    :label="__('End date')"
                                    :value="$member['end_date']" />
                            </div>
                            <div class="col-12">
                                <x-input
                                    name="reason"
                                    :label="__('Reason for this change')"
                                    required
                                    :help="__('Recorded in the audit trail alongside what changed.')" />
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">{{ __('Save posting') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Roles') }}</h2>

                    @if ($roles->isEmpty())
                        <p class="text-secondary small mb-0">
                            {{ __('No roles exist in this organization yet. Create one before assigning it.') }}
                        </p>
                    @else
                        <form method="POST" action="{{ route('staff.roles', $member['id']) }}">
                            @csrf
                            @method('PUT')

                            {{-- Submitted even when nothing is ticked, because the
                                 endpoint expects `role_ids` to be present and
                                 treats an empty array as "revoke everything". --}}
                            <input type="hidden" name="role_ids[]" value="">

                            @foreach ($roles as $id => $name)
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="role_ids[]"
                                           value="{{ $id }}"
                                           id="role-{{ $id }}"
                                           @checked(in_array($id, $assignedRoleIds, true))>
                                    <label class="form-check-label" for="role-{{ $id }}">{{ $name }}</label>
                                </div>
                            @endforeach

                            <button type="submit" class="btn btn-outline-primary btn-sm mt-3">
                                {{ __('Update roles') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-1">{{ __('Membership status') }}</h2>
                    <p class="text-secondary small">
                        {{ __('Suspending removes this person\'s access immediately. Offboarding is final and keeps their history.') }}
                    </p>

                    <form method="POST" action="{{ route('staff.status', $member['id']) }}" novalidate>
                        @csrf

                        <x-select
                            name="status"
                            :label="__('Status')"
                            :options="[
                                'active' => __('Active'),
                                'suspended' => __('Suspended'),
                                'offboarded' => __('Offboarded'),
                            ]"
                            :selected="$member['status']"
                            required />

                        <x-input
                            name="reason"
                            :label="__('Reason for this change')"
                            required
                            :help="__('Required, and kept in the audit trail.')" />

                        <button type="submit" class="btn btn-outline-secondary">{{ __('Change status') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
