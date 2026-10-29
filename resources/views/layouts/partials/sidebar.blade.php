@php
    /**
     * Sidebar navigation.
     *
     * Each item carries the Gate ability that must pass for it to render. Those
     * ability keys are PROVISIONAL: the authoritative catalogue is settled by
     * Codex in P1.05 (Workspace/staff/RBAC contract). When it lands, the `can`
     * values below are reconciled against it. They are the frontend's input to
     * that contract, not a parallel permission system.
     *
     * `route` names are resolved defensively with Route::has() so the shell
     * renders before Codex has registered them. An unresolved route renders as
     * a disabled item rather than throwing.
     *
     * $withoutScreen does the same for the opposite case, which arrived with
     * the Phase 2 to Phase 7 backend: the route exists and answers, but only
     * with JSON, because no Blade screen has been built for it yet. Linking to
     * it would hand a person a page of raw JSON, which reads as a broken
     * product rather than as unfinished one. Remove a name from this list in
     * the same change that adds its screen.
     *
     * Hiding an item is a usability affordance only. Every destination must
     * enforce its own authorization server-side.
     *
     * $navPreview renders every item regardless of ability, so the navigation
     * design can be reviewed before P1.05 defines any Gate ability. It is
     * opt-in per view AND hard-gated to the local environment, so it can never
     * widen what a real user sees. It affects nav visibility only, never
     * access.
     */
    $navPreview = ($navPreview ?? false) && app()->isLocal();

    $withoutScreen = [
        'tasks.my', 'activities.index', 'reports.index', 'attendance.index',
        'leave.index', 'documents.index', 'announcements.index', 'payroll.index',
    ];

    $sections = [
        null => [
            ['label' => __('Dashboard'), 'route' => 'dashboard', 'icon' => 'grid', 'can' => null],
        ],
        __('Work') => [
            ['label' => __('My Tasks'), 'route' => 'tasks.my', 'icon' => 'check2-square', 'can' => 'tasks.view.own'],
            ['label' => __('Projects'), 'route' => 'projects.index', 'icon' => 'kanban', 'can' => 'projects.view'],
            ['label' => __('Daily Activities'), 'route' => 'activities.index', 'icon' => 'journal-text', 'can' => 'activities.view.own'],
            ['label' => __('Reports'), 'route' => 'reports.index', 'icon' => 'file-earmark-text', 'can' => 'reports.view.own'],
        ],
        __('Time') => [
            ['label' => __('Attendance'), 'route' => 'attendance.index', 'icon' => 'clock', 'can' => 'attendance.view.own'],
            ['label' => __('Leave'), 'route' => 'leave.index', 'icon' => 'calendar-event', 'can' => 'leave.view.own'],
        ],
        __('Documents') => [
            ['label' => __('Library'), 'route' => 'documents.index', 'icon' => 'folder2-open', 'can' => 'documents.view'],
            ['label' => __('Announcements'), 'route' => 'announcements.index', 'icon' => 'megaphone', 'can' => 'announcements.view'],
        ],
        __('People') => [
            ['label' => __('Staff'), 'route' => 'staff.index', 'icon' => 'people', 'can' => 'staff.view'],
            ['label' => __('Invitations'), 'route' => 'invitations.index', 'icon' => 'envelope', 'can' => 'staff.invite'],
            ['label' => __('Departments & Teams'), 'route' => 'departments.index', 'icon' => 'diagram-3', 'can' => 'departments.manage'],
            ['label' => __('Roles'), 'route' => 'roles.index', 'icon' => 'shield-check', 'can' => 'roles.manage'],
        ],
        __('Finance') => [
            ['label' => __('Payroll'), 'route' => 'payroll.index', 'icon' => 'cash-stack', 'can' => 'payroll.manage'],
            ['label' => __('My Payslips'), 'route' => 'payslips.mine', 'icon' => 'receipt', 'can' => 'payslips.view.own'],
        ],
        __('Administration') => [
            ['label' => __('Organization Settings'), 'route' => 'settings.organization', 'icon' => 'gear', 'can' => 'organization.manage'],
            ['label' => __('Audit Log'), 'route' => 'audit.index', 'icon' => 'list-columns', 'can' => 'audit.view'],
        ],
    ];
@endphp

<aside class="app-sidebar" data-sidebar aria-label="{{ __('Main navigation') }}">
    <div class="d-flex align-items-center justify-content-between px-3" style="height: var(--lorine-topbar-height)">
        <span class="fw-semibold text-truncate">{{ config('app.name') }}</span>

        <button type="button"
                class="btn btn-sm btn-link text-body d-lg-none p-1"
                data-sidebar-toggle
                aria-expanded="false"
                aria-label="{{ __('Close navigation') }}">
            <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
            </svg>
        </button>
    </div>

    <nav class="px-2 pb-4">
        @foreach ($sections as $heading => $items)
            @php
                // Resolve each item once so an entirely hidden section prints no heading.
                $visible = collect($items)
                    ->filter(fn ($item) => $navPreview
                        || $item['can'] === null
                        || Gate::allows($item['can']))
                    ->all();
            @endphp

            @if ($visible)
                <div class="mt-3">
                    @if ($heading)
                        <p class="app-nav-heading">{{ $heading }}</p>
                    @endif

                    <ul class="nav flex-column gap-1">
                        @foreach ($visible as $item)
                            @php
                                $exists = Route::has($item['route']) && ! in_array($item['route'], $withoutScreen, true);
                                $isActive = $exists && request()->routeIs($item['route'] . '*');
                            @endphp

                            <li class="nav-item">
                                {{-- @class emits the whole class attribute, so it
                                     cannot sit inside one. Nesting it produced
                                     class="app-nav-link class="disabled ...""
                                     and neither state ever reached the element. --}}
                                <a @class(['app-nav-link', 'active' => $isActive, 'disabled opacity-50' => ! $exists])
                                   href="{{ $exists ? route($item['route']) : '#' }}"
                                   @if ($isActive) aria-current="page" @endif
                                   @unless ($exists)
                                       aria-disabled="true"
                                       tabindex="-1"
                                       title="{{ __('Not available yet') }}"
                                   @endunless>
                                    <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>
                                    <span class="text-truncate">{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    </nav>
</aside>
