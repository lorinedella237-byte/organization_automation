{{--
    Role editor with the ability and scope matrix. P1.05-FE.

    A role is a name plus a set of grants, and a grant is an ability paired with
    one scope. The catalogue comes from config/permissions.php by way of the
    controller, so this page can never offer an ability the server does not
    know, and the scopes offered for an ability are exactly the ones that
    ability supports.

    One role is edited at a time, chosen with ?role=<id>. Rendering every role's
    full matrix at once produced 435KB of HTML and 198 selects for six roles,
    growing linearly with the number of roles, which is both slow and harder to
    read. Editing one role also makes it impossible to submit a form that
    silently rewrites a role the editor was not looking at.

    Abilities are grouped by module so the matrix can be scanned rather than
    read end to end.

    Shape note: the endpoint takes `grants` as a list of {ability, scope}
    objects, and an ungranted ability must be absent from that list rather than
    present with an empty scope. A plain HTML form cannot omit a field
    conditionally, so resources/js/role-matrix.js serialises the selects into
    that shape on submit. Without JavaScript the form fails validation rather
    than submitting something wrong, which is the safe direction: it can never
    quietly strip a role's permissions. If Codex would rather accept a plain
    ability-to-scope map, this page gets simpler and the script goes away.
--}}
@extends('layouts.app')

@section('title', __('Roles'))

@php
    $editing = request('role')
        ? $roles->firstWhere('id', (int) request('role'))
        : null;

    $heldFor = function ($role) {
        $map = [];

        foreach ($role->grants as $grant) {
            $map[$grant->ability] = $grant->scope;
        }

        return $map;
    };

    // Group by the segment before the first dot, so the matrix reads as
    // sections rather than one long undifferentiated list.
    //
    // preserveKeys matters: groupBy renumbers each group by default, which
    // would throw away the ability code and leave the rows labelled 0, 1, 2.
    $grouped = collect($abilities)->groupBy(
        fn ($scopes, $ability) => explode('.', $ability)[0],
        preserveKeys: true
    );

    $scopeLabels = [
        'own' => __('Only their own'),
        'direct_reports' => __('Their direct reports'),
        'department' => __('Their department'),
        'organization' => __('The whole organization'),
        'assigned_project' => __('Projects they are on'),
    ];
@endphp

@section('content')
    <x-page-header
        :title="__('Roles')"
        :subtitle="__('Build your own roles and choose exactly what each one may do, and over whom.')"
        :breadcrumbs="[__('Workspace') => null, __('Roles') => null]" />

    <div class="row g-4">
        {{-- Role list and creation ---------------------------------------- --}}
        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Roles in this organization') }}</h2>

                    @if ($roles->isEmpty())
                        <p class="text-secondary small mb-0">
                            {{ __('None yet. Create the first one below.') }}
                        </p>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach ($roles as $role)
                                @php($count = count($heldFor($role)))

                                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2 px-0 @if ($editing && $editing->id === $role->id) fw-semibold @endif"
                                   href="{{ route('roles.index', ['role' => $role->id]) }}"
                                   @if ($editing && $editing->id === $role->id) aria-current="true" @endif>
                                    <span>
                                        {{ $role->name }}
                                        @if ($role->is_system_role)
                                            {{-- Said here, not only on the editor, so the
                                                 list answers "which of these can I change?"
                                                 without clicking through every one. --}}
                                            <i class="bi bi-lock-fill text-secondary small ms-1" aria-hidden="true"></i>
                                            <span class="visually-hidden">{{ __('(protected)') }}</span>
                                        @endif
                                    </span>
                                    <span class="text-secondary small font-monospace">{{ $count }}</span>
                                </a>
                            @endforeach
                        </div>
                        <p class="form-text mb-0">
                            {{ __('The number is how many abilities the role grants.') }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Create a role') }}</h2>

                    <form method="POST" action="{{ route('roles.store') }}" novalidate>
                        @csrf

                        <x-input name="name" :label="__('Role name')" required />

                        {{-- A new role starts with nothing granted. The key must
                             still be present, so an empty list is sent. --}}
                        <input type="hidden" name="grants" value="">

                        <button type="submit" class="btn btn-primary">{{ __('Create role') }}</button>

                        <p class="form-text mb-0">
                            {{ __('It starts with no permissions. Grant them once it exists.') }}
                        </p>
                    </form>
                </div>
            </div>
        </div>

        {{-- Matrix --------------------------------------------------------- --}}
        <div class="col-12 col-lg-8">
            @if (! $editing)
                <x-alert type="info" :title="__('How scope works')">
                    {{ __('An ability answers what someone may do. A scope answers who they may do it to: only themselves, the people reporting to them, their department, or everyone. Scope is enforced on the server for every request.') }}
                </x-alert>

                <x-empty-state
                    icon="shield-check"
                    :title="__('Choose a role to inspect')"
                    :description="__('Select a role on the left to see exactly what it permits. Roles marked with a lock are supplied with the system and can be read but not changed; create your own role to choose a different set.')" />
            @else
                @php($held = $heldFor($editing))

                <div class="card">
                    <div class="card-body">
                        <form method="POST"
                              action="{{ route('roles.update', $editing->id) }}"
                              data-role-matrix
                              novalidate>
                            @csrf
                            @method('PUT')

                            {{-- RoleService::save() aborts 403 on a system role, so
                                 the whole matrix is disabled rather than offered and
                                 then refused. The reason is stated before the form,
                                 because a reader meets the explanation before the
                                 controls it applies to. --}}
                            @if ($editing->is_system_role)
                                <x-alert type="info" :title="__('Supplied with the system')">
                                    {{ __('This role is protected and cannot be changed. You can read every permission it grants below. To grant a different set, create your own role.') }}
                                </x-alert>
                            @endif

                            <fieldset @disabled($editing->is_system_role)>

                            <div class="row g-2 align-items-end mb-4">
                                <div class="col-12 col-sm-7">
                                    <x-input
                                        name="name"
                                        :label="__('Role name')"
                                        :value="$editing->name"
                                        required />
                                </div>
                                <div class="col-auto mb-3">
                                    <span class="text-secondary small">
                                        {{ trans_choice('{0}Nothing granted|{1}:count ability granted|[2,*]:count abilities granted', count($held), ['count' => count($held)]) }}
                                    </span>
                                </div>
                            </div>

                            @foreach ($grouped as $module => $moduleAbilities)
                                <h3 class="label-mono text-secondary mt-4 mb-2">{{ $module }}</h3>

                                <div class="table-responsive">
                                    <table class="table table-data align-middle mb-0">
                                        <caption class="visually-hidden">
                                            {{ __(':module permissions for :role', ['module' => $module, 'role' => $editing->name]) }}
                                        </caption>
                                        <thead>
                                            <tr>
                                                <th scope="col" style="width: 45%">{{ __('Ability') }}</th>
                                                <th scope="col">{{ __('Applies to') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($moduleAbilities as $ability => $scopes)
                                                @php($current = $held[$ability] ?? null)
                                                @php($fieldId = 'ability-'.str_replace('.', '-', $ability))

                                                <tr>
                                                    <th scope="row" class="fw-normal">
                                                        <label class="form-label mb-0" for="{{ $fieldId }}">
                                                            <code class="text-body">{{ $ability }}</code>
                                                        </label>
                                                    </th>
                                                    <td>
                                                        <select class="form-select form-select-sm"
                                                                id="{{ $fieldId }}"
                                                                data-ability="{{ $ability }}">
                                                            <option value="" @selected($current === null)>
                                                                {{ __('Not granted') }}
                                                            </option>
                                                            @foreach ($scopes as $scope)
                                                                <option value="{{ $scope }}" @selected($current === $scope)>
                                                                    {{ $scopeLabels[$scope] ?? $scope }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endforeach

                            @if (! $editing->is_system_role)
                                <button type="submit" class="btn btn-primary mt-4">
                                    {{ __('Save :role', ['role' => $editing->name]) }}
                                </button>
                            @endif
                            </fieldset>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
