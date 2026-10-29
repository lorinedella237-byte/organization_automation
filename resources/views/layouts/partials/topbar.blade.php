@php
    /**
     * Topbar: sidebar trigger, workspace switcher, theme toggle, notifications,
     * account menu.
     *
     * The workspace switcher and notification bell are rendered from view data
     * that Codex supplies under the P1.06 and P1.07 contracts. Until then they
     * degrade: the switcher shows only the resolved organization name, and the
     * bell shows no count. No placeholder data is invented here.
     */
    $user = auth()->user();

    // Supplied by the P1.06 organization-resolution contract once available.
    $currentOrganization = $currentOrganization ?? null;
    $availableOrganizations = $availableOrganizations ?? collect();

    // Supplied by the P1.07 notifications contract once available.
    $unreadNotificationCount = $unreadNotificationCount ?? 0;
@endphp

<header class="app-topbar d-flex align-items-center gap-2 px-3">
    <button type="button"
            class="btn btn-sm btn-link text-body d-lg-none p-1"
            data-sidebar-toggle
            aria-expanded="false"
            aria-label="{{ __('Open navigation') }}">
        <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
        </svg>
    </button>

    {{-- Workspace switcher --}}
    @if ($availableOrganizations->count() > 1)
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false">
                <i class="bi bi-building me-1" aria-hidden="true"></i>
                <span class="text-truncate d-inline-block align-bottom" style="max-width: 11rem">
                    {{ $currentOrganization?->name ?? __('Select workspace') }}
                </span>
            </button>

            <ul class="dropdown-menu">
                <li><h6 class="dropdown-header">{{ __('Switch workspace') }}</h6></li>
                @foreach ($availableOrganizations as $organization)
                    <li>
                        <form method="POST"
                              action="{{ Route::has('workspace.switch') ? route('workspace.switch') : '#' }}">
                            @csrf
                            <input type="hidden" name="organization" value="{{ $organization->getKey() }}">
                            <button type="submit"
                                    class="dropdown-item d-flex align-items-center justify-content-between">
                                <span class="text-truncate">{{ $organization->name }}</span>
                                @if ($currentOrganization?->is($organization))
                                    <i class="bi bi-check2 ms-2" aria-hidden="true"></i>
                                    <span class="visually-hidden">{{ __('Current workspace') }}</span>
                                @endif
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @elseif ($currentOrganization)
        <span class="small fw-medium text-truncate">
            <i class="bi bi-building me-1 text-secondary" aria-hidden="true"></i>
            {{ $currentOrganization->name }}
        </span>
    @endif

    <div class="ms-auto d-flex align-items-center gap-1">
        {{-- Theme --}}
        <button type="button"
                class="btn btn-sm btn-link text-body p-1"
                data-theme-toggle
                aria-pressed="false"
                aria-label="{{ __('Toggle dark mode') }}"
                data-bs-toggle="tooltip"
                title="{{ __('Toggle dark mode') }}">
            <i class="bi bi-circle-half fs-5" aria-hidden="true"></i>
        </button>

        {{-- Notifications --}}
        <a class="btn btn-sm btn-link text-body p-1 position-relative"
           href="{{ Route::has('notifications.index') ? route('notifications.index') : '#' }}"
           aria-label="{{ trans_choice('{0}Notifications, none unread|{1}Notifications, 1 unread|[2,*]Notifications, :count unread', $unreadNotificationCount, ['count' => $unreadNotificationCount]) }}">
            <i class="bi bi-bell fs-5" aria-hidden="true"></i>

            @if ($unreadNotificationCount > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    {{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}
                </span>
            @endif
        </a>

        {{-- Account --}}
        @if ($user)
            <div class="dropdown">
                <button class="btn btn-sm btn-link text-body d-flex align-items-center gap-2 text-decoration-none p-1"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                    <span class="d-none d-sm-inline small fw-medium text-truncate" style="max-width: 10rem">
                        {{ $user->name }}
                    </span>
                    <i class="bi bi-person-circle fs-5" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('Account menu') }}</span>
                </button>

                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="dropdown-header text-truncate">{{ $user->email }}</li>
                    <li><hr class="dropdown-divider"></li>
                    @if (Route::has('profile.edit'))
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}">{{ __('My profile') }}</a></li>
                    @endif
                    <li>
                        <form method="POST" action="{{ Route::has('logout') ? route('logout') : '#' }}">
                            @csrf
                            <button type="submit" class="dropdown-item">{{ __('Sign out') }}</button>
                        </form>
                    </li>
                </ul>
            </div>
        @endif
    </div>
</header>
