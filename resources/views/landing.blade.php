{{--
    Public landing page, served at `/`.

    Describes only what Release 1 actually delivers. In particular payroll is
    presented as records and payslips, never as paying money out: the system
    does not disburse funds, and this page must not imply that it does.

    The page is laid out as a record rather than a brochure. Content is left
    aligned on one axis, groups are drawn with hairlines instead of floating
    cards, and anything that is data (reference numbers, statuses, column
    heads, the step index) is set in the mono face. Numbering appears once, on
    the review sequence, because that is the only content here that is a
    sequence.
--}}
@extends('layouts.public')

@section('title', __('Organization management'))
@section('description', __('Run your organization in one place: staff reporting, projects and daily activities, a private document library, attendance and leave, and payroll records.'))

@section('content')
    {{-- Hero ------------------------------------------------------------- --}}
    <section class="band-ink">
        <span class="band-ink__wash" aria-hidden="true"></span>

        <div class="container">
            <div class="row align-items-center g-5 pt-5 pb-4">
                <div class="col-12 col-lg-6">
                    <p class="hero-eyebrow label-mono js-reveal">{{ __('Organization management') }}</p>

                    <h1 class="hero-title js-reveal" data-reveal-index="1">
                        {{ __('Know what your team actually did this week.') }}
                    </h1>

                    <p class="hero-lead js-reveal" data-reveal-index="2">
                        {{ __('Reports, projects, documents, attendance and payroll records in one place, so work is visible, every approval is traceable, and nothing depends on chasing people for updates.') }}
                    </p>

                    <div class="d-flex flex-wrap gap-2 mb-4 js-reveal" data-reveal-index="3">
                        <a class="btn btn-on-ink btn-lg px-4"
                           href="{{ Route::has('login') ? route('login') : url('/dev/auth/login') }}">
                            {{ __('Sign in') }}
                        </a>
                        <a class="btn btn-ghost-ink btn-lg px-4" href="#modules">
                            {{ __('See what it does') }}
                        </a>
                    </div>

                    <p class="hero-note js-reveal" data-reveal-index="3">
                        <i class="bi bi-shield-lock" aria-hidden="true"></i>
                        {{ __('Invitation only. Your administrator creates accounts; there is no public sign-up.') }}
                    </p>
                </div>

                {{-- Decorative impression of the application. The rows are
                     illustrative, not data, so the block is hidden from
                     assistive technology. --}}
                <div class="col-12 col-lg-6">
                    <div class="window js-reveal" data-reveal-index="2" aria-hidden="true">
                        <div class="window__bar">
                            <span class="window__path">{{ config('app.name') }} / Reports / Weekly</span>
                            <span class="window__period">W43 &middot; 2026</span>
                        </div>

                        <div class="window__body">
                            <div class="window__side">
                                @foreach ([
                                    ['speedometer2', 'Dashboard', false],
                                    ['file-earmark-text', 'Reports', true],
                                    ['kanban', 'Projects', false],
                                    ['calendar-check', 'Activities', false],
                                    ['folder2-open', 'Documents', false],
                                    ['clock', 'Attendance', false],
                                    ['receipt', 'Payroll', false],
                                ] as [$icon, $label, $active])
                                    <div class="window__nav @if ($active) is-active @endif">
                                        <i class="bi bi-{{ $icon }}"></i>{{ $label }}
                                    </div>
                                @endforeach
                            </div>

                            <div class="window__main">
                                <div class="window__head">
                                    <span>Ref</span>
                                    <span>Report</span>
                                    <span class="text-end">Status</span>
                                </div>

                                @foreach ([
                                    ['RPT-0431', 'Field operations', 'Operations, submitted Mon', 'Approved', 'approved'],
                                    ['RPT-0430', 'Clinic outreach', 'Health, submitted Tue', 'Under Review', 'review'],
                                    ['RPT-0429', 'Logistics and fleet', 'Logistics, returned Tue', 'Returned', 'returned'],
                                    ['RPT-0428', 'Community training', 'Programmes, submitted Wed', 'Approved', 'approved'],
                                    ['RPT-0427', 'Finance reconciliation', 'Finance, not submitted', 'Draft', 'draft'],
                                ] as [$ref, $name, $meta, $label, $modifier])
                                    <div class="window__row">
                                        <span class="window__ref">{{ $ref }}</span>
                                        <span>
                                            <span class="window__name">{{ $name }}</span>
                                            <span class="window__meta">{{ $meta }}</span>
                                        </span>
                                        <span class="status-badge status-badge--{{ $modifier }}">{{ $label }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="rule-ink">

            <div class="facts pb-5">
                @foreach ([
                    ['Six modules', 'People, reports, projects, documents, attendance and payroll'],
                    ['Roles you define', 'Build your own roles and assign permissions to them'],
                    ['Own, department, organization', 'Every list is scoped to what the viewer may see'],
                    ['Full audit trail', 'Who changed what, when and why, kept permanently'],
                ] as $index => [$value, $label])
                    <div class="facts__item js-reveal" data-reveal-index="{{ $index }}">
                        <span class="facts__value">{{ $value }}</span>
                        <span class="facts__label">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Modules ---------------------------------------------------------- --}}
    <section class="section" id="modules">
        <div class="container">
            <div class="section-head js-reveal">
                <p class="section-kicker label-mono">{{ __('What is inside') }}</p>
                <h2 class="section-title">{{ __('Six modules, one register of people') }}</h2>
                <p class="section-lead">
                    {{ __('Each module works on its own, and they share the same people, departments and permissions.') }}
                </p>
            </div>

            <div class="ledger js-reveal" data-reveal-index="1">
                @foreach ([
                    [
                        'people', 'People and access',
                        'Invite staff, place them in departments and teams, and build your own roles. Permissions are scoped, so staff see their own work, managers see their department, and administrators see everything.',
                    ],
                    [
                        'file-earmark-text', 'Reports',
                        'Weekly and periodic reports with attachments, a review workflow and immutable version history. A returned report carries the reviewer\'s reason, so revisions are never guesswork.',
                    ],
                    [
                        'kanban', 'Projects and activities',
                        'Projects, milestones and tasks, plus a daily activity log with hours against a project or task. Weekly reports draft themselves from what was already logged.',
                    ],
                    [
                        'folder2-open', 'Documents',
                        'A private library with nested folders and version history. Share with a person, a team, a department or the whole organization, and revoke instantly.',
                    ],
                    [
                        'clock', 'Attendance and leave',
                        'Clock in and out, with late, absent and incomplete handled honestly rather than invented. Leave runs through manager and HR approval against a balance ledger.',
                    ],
                    [
                        'receipt', 'Payroll records',
                        'Effective-dated compensation, monthly pay periods and payslips staff can view. Records and payslips only: the system does not move money.',
                    ],
                ] as [$icon, $title, $body])
                    <div class="ledger__cell">
                        <i class="bi bi-{{ $icon }} ledger__icon" aria-hidden="true"></i>
                        <h3 class="ledger__title">{{ $title }}</h3>
                        <p class="ledger__body">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Access and scope -------------------------------------------------- --}}
    <section class="section section--sunken" id="access">
        <div class="container">
            <div class="scope-layout">
                <div class="scope-intro js-reveal">
                    <p class="section-kicker label-mono">{{ __('Access') }}</p>
                    <h2 class="section-title">{{ __('Everyone sees exactly their own scope') }}</h2>
                    <p class="section-lead mb-4">
                        {{ __('One permission model runs through every module, so access behaves the same way in reports, documents, attendance and payroll alike.') }}
                    </p>
                    <p class="d-flex gap-2 text-secondary small mb-0">
                        <i class="bi bi-hdd-network mt-1" aria-hidden="true"></i>
                        <span>{{ __('Scope is enforced on the server for every request. Hiding a button is never what keeps a record private.') }}</span>
                    </p>
                </div>

                <div class="js-reveal" data-reveal-index="1">
                    @foreach ([
                        ['Own', 'Staff', [
                            'Submit reports',
                            'Log daily activities',
                            'Own attendance and payslips',
                            'Request leave',
                        ]],
                        ['Department', 'Managers and HR', [
                            'Review and approve reports',
                            'Approve attendance corrections',
                            'Approve leave',
                            'Team calendars and workload',
                        ]],
                        ['Organization', 'Administrators', [
                            'Invite staff and build roles',
                            'Manage departments',
                            'Run payroll and release payslips',
                            'Read the full audit trail',
                        ]],
                    ] as [$scope, $title, $points])
                        <div class="scope-row">
                            <p class="scope-row__key label-mono mb-0">{{ $scope }}</p>

                            <div>
                                <h3 class="scope-row__title">{{ $title }}</h3>
                                <ul class="scope-row__list">
                                    @foreach ($points as $point)
                                        <li>{{ $point }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Review sequence --------------------------------------------------- --}}
    <section class="section" id="workflow">
        <div class="container">
            <div class="section-head js-reveal">
                <p class="section-kicker label-mono">{{ __('Workflow') }}</p>
                <h2 class="section-title">{{ __('A report\'s life, end to end') }}</h2>
                <p class="section-lead">
                    {{ __('Every transition records who did it, when, and why, so a report\'s history can always be reconstructed.') }}
                </p>
            </div>

            <ol class="sequence js-reveal" data-reveal-index="1">
                @foreach ([
                    ['Draft', 'draft', 'Written by the author, or generated from the week\'s logged activities.'],
                    ['Submitted', 'submitted', 'Sent for review and snapshotted, so it cannot change underneath the reviewer.'],
                    ['Under Review', 'review', 'A reviewer in scope picks it up. Nobody can approve their own report.'],
                    ['Approved', 'approved', 'Accepted and locked, with the full version and status history retained.'],
                ] as $index => [$label, $modifier, $body])
                    <li class="sequence__step">
                        <span class="sequence__index label-mono">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <p class="sequence__title">
                            <span class="status-badge status-badge--{{ $modifier }}">{{ $label }}</span>
                        </p>
                        <p class="sequence__body">{{ $body }}</p>
                    </li>
                @endforeach
            </ol>

            <p class="sequence-note js-reveal">
                <i class="bi bi-arrow-counterclockwise mt-1" aria-hidden="true"></i>
                <span>{{ __('A reviewer can also return a report for changes, with a required reason. The author then revises and resubmits, and both versions are kept.') }}</span>
            </p>
        </div>
    </section>

    {{-- Closing ---------------------------------------------------------- --}}
    <section class="band-ink">
        <span class="band-ink__wash" aria-hidden="true"></span>

        <div class="container">
            <div class="cta">
                <h2 class="cta__title">{{ __('Already have an account?') }}</h2>
                <p class="cta__lead">
                    {{ __('Sign in to submit a report, log your week, or review your team\'s work.') }}
                </p>

                <a class="btn btn-on-ink btn-lg px-4"
                   href="{{ Route::has('login') ? route('login') : url('/dev/auth/login') }}">
                    {{ __('Sign in') }}
                </a>

                <p class="cta__note">
                    {{ __('Need access? Ask an administrator in your organization to send you an invitation.') }}
                </p>
            </div>
        </div>
    </section>
@endsection
