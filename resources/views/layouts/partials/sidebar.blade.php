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

    $withoutScreen = [];

    $sections = [
        null => [
            ['label' => __('Dashboard'), 'route' => 'dashboard', 'icon' => 'grid', 'can' => null],
        ],
        __('Work') => [
            ['label' => __('My Tasks'), 'route' => 'tasks.my', 'icon' => 'check2-square', 'can' => 'tasks.view.own'],
            ['label' => __('Projects'), 'route' => 'projects.index', 'icon' => 'kanban', 'can' => 'projects.view'],
            ['label' => __('Daily Activities'), 'route' => 'activities.index', 'icon' => 'journal-text', 'can' => 'activities.view.own'],
            ['label' => __('Work Logs'), 'route' => 'work-logs.index', 'icon' => 'stopwatch', 'can' => 'activities.create'],
            ['label' => __('Reports'), 'route' => 'reports.index', 'icon' => 'file-earmark-text', 'can' => 'reports.view.own'],
        ],
        /*
         * Attendance, leave and payslips answer one question between them:
         * what am I owed for the time I have put in. Payroll sat alone under
         * a Finance heading of its own, which spent a whole row of chrome to
         * label a single item.
         */
        __('Time and pay') => [
            ['label' => __('Attendance'), 'route' => 'attendance.index', 'icon' => 'clock', 'can' => 'attendance.view.own'],
            ['label' => __('Leave'), 'route' => 'leave.index', 'icon' => 'calendar-event', 'can' => 'leave.view.own'],
            ['label' => __('Payroll'), 'route' => 'payroll.index', 'icon' => 'wallet2', 'can' => 'payslips.view.own'],
        ],
        __('Documents') => [
            ['label' => __('Library'), 'route' => 'documents.index', 'icon' => 'folder2-open', 'can' => 'documents.view'],
            ['label' => __('Announcements'), 'route' => 'announcements.index', 'icon' => 'megaphone', 'can' => 'announcements.view'],
        ],
        __('People') => [
            ['label' => __('Staff'), 'route' => 'staff.index', 'icon' => 'people', 'can' => 'staff.view'],
            ['label' => __('Invitations'), 'route' => 'invitations.index', 'icon' => 'envelope', 'can' => 'staff.invite'],
            ['label' => __('Departments & Teams'), 'route' => 'departments.index', 'icon' => 'diagram-2', 'can' => 'departments.manage'],
            ['label' => __('Roles'), 'route' => 'roles.index', 'icon' => 'shield-check', 'can' => 'roles.manage'],
        ],
        __('Administration') => [
            ['label' => __('Organization Settings'), 'route' => 'settings.organization', 'icon' => 'gear', 'can' => 'organization.manage'],
            ['label' => __('Audit Log'), 'route' => 'audit.index', 'icon' => 'activity', 'can' => 'audit.view'],
        ],
    ];
@endphp

<aside class="app-sidebar" data-sidebar aria-label="{{ __('Main navigation') }}">
    <div class="app-sidebar-brand">
        <span class="text-truncate">{{ config('app.name') }}</span>

        {{-- The same control the topbar uses, so the two bands of the shell
             answer the pointer identically. --}}
        <button type="button"
                class="app-bar-button d-lg-none"
                data-sidebar-toggle
                aria-expanded="false"
                aria-label="{{ __('Close navigation') }}">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    <nav class="px-2 py-2">
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
                {{-- The first group carries no heading, so it needs no space
                     above it either. --}}
                <div @class(['app-nav-group' => ! $loop->first])>
                    @if ($heading)
                        <p class="app-nav-heading">{{ $heading }}</p>
                    @endif

                    <ul class="nav flex-column">
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
