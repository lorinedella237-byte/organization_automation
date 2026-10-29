@php
    /**
     * Topbar: sidebar trigger, workspace scope, appearance, notifications,
     * account menu.
     *
     * Nothing in this bar carries a border or a fill of its own. Apple's
     * toolbar guidance is that the bar is itself the container, so bordered
     * controls inside it are redundant chrome, and that a toolbar stays
     * monochrome so the accent keeps meaning one thing. The accent therefore
     * appears exactly once here, on the unread count.
     *
     * There is no view title. Every screen renders its own <h1> through
     * <x-page-header>, and a toolbar title that repeats it is the redundancy
     * Apple tells you to leave out.
     */
    $user = auth()->user();

    $currentOrganization = $currentOrganization ?? null;
    $availableOrganizations = $availableOrganizations ?? collect();

    $unreadNotificationCount = $unreadNotificationCount ?? 0;
@endphp

<header class="app-topbar d-flex align-items-center gap-2">
    <button type="button"
            class="app-bar-button app-bar-button-start d-lg-none"
            data-sidebar-toggle
            aria-expanded="false"
            aria-label="{{ __('Open navigation') }}">
        <i class="bi bi-list" aria-hidden="true"></i>
    </button>

    {{--
        Workspace scope. One presentation whether there is one organization or
        many: the same icon, the same name, in the same place. Only the chevron
        and the menu appear when there is somewhere to switch to.
    --}}
    @if ($availableOrganizations->count() > 1)
        <div class="dropdown">
            <button class="app-bar-scope"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="{{ __('Workspace: :name. Switch workspace', ['name' => $currentOrganization?->name ?? __('none selected')]) }}">
                <i class="bi bi-building" aria-hidden="true"></i>
                <span class="text-truncate">
                    {{ $currentOrganization?->name ?? __('Select workspace') }}
                </span>
                <i class="bi bi-chevron-down app-bar-chevron" aria-hidden="true"></i>
            </button>

            <ul class="dropdown-menu">
                @foreach ($availableOrganizations as $organization)
                    <li>
                        <form method="POST"
                              action="{{ Route::has('workspace.switch') ? route('workspace.switch') : '#' }}">
                            @csrf
                            <input type="hidden" name="organization" value="{{ $organization->getKey() }}">
                            <button type="submit"
                                    class="dropdown-item d-flex align-items-center justify-content-between gap-3">
                                <span class="text-truncate">{{ $organization->name }}</span>
                                @if ($currentOrganization?->is($organization))
                                    <i class="bi bi-check2" aria-hidden="true"></i>
                                    <span class="visually-hidden">{{ __('Current workspace') }}</span>
                                @endif
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @elseif ($currentOrganization)
        <span class="app-bar-scope">
            <i class="bi bi-building" aria-hidden="true"></i>
            <span class="text-truncate">{{ $currentOrganization->name }}</span>
        </span>
    @endif

    {{--
        Two groups on the trailing edge, with space between them: controls that
        act on the view, then the account menu. Apple asks for no more than
        three groups and for fixed space to separate them, so a symbol is never
        read as part of the label beside it.
    --}}
    <div class="ms-auto d-flex align-items-center gap-1">
        {{--
            A toggle button, so the name stays put and the pressed state
            carries the answer. The glyph shows the appearance in force; CSS
            swaps it, which means it is already right on first paint.
        --}}
        <button type="button"
                class="app-bar-button"
                data-theme-toggle
                aria-pressed="false"
                aria-label="{{ __('Dark appearance') }}"
                data-bs-toggle="tooltip"
                title="{{ __('Dark appearance') }}">
            <i class="bi bi-sun app-bar-icon-light" aria-hidden="true"></i>
            <i class="bi bi-moon app-bar-icon-dark" aria-hidden="true"></i>
        </button>

        <a class="app-bar-button"
           href="{{ Route::has('notifications.index') ? route('notifications.index') : '#' }}"
           aria-label="{{ trans_choice('{0}Notifications, none unread|{1}Notifications, 1 unread|[2,*]Notifications, :count unread', $unreadNotificationCount, ['count' => $unreadNotificationCount]) }}">
            <i class="bi bi-bell" aria-hidden="true"></i>

            {{-- The label above already says the count, so the chip is not read twice. --}}
            @if ($unreadNotificationCount > 0)
                <span class="app-bar-count" aria-hidden="true">
                    {{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}
                </span>
            @endif
        </a>

        @if ($user)
            <div class="dropdown ms-2">
                <button class="app-bar-account app-bar-button-end"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-label="{{ __('Account menu for :name', ['name' => $user->name]) }}">
                    <i class="bi bi-person d-sm-none" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline text-truncate">{{ $user->name }}</span>
                    <i class="bi bi-chevron-down app-bar-chevron" aria-hidden="true"></i>
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
