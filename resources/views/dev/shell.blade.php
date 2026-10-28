{{--
    ============================================================
    P0.05 VERIFICATION FIXTURE. NOT A PRODUCT PAGE
    ============================================================

    Exists only so the shared shell and component kit can be rendered and
    reviewed in a browser before real pages exist. Every value below is
    hard-coded sample content; nothing here reads the database or reflects real
    organization data.

    Delete this file (and its route) once real pages extend `layouts.app`.
    It must never be reachable in a non-local environment.
--}}
@extends('layouts.app')

@section('title', 'Shell preview')

@section('content')
    <x-page-header
        title="Weekly Reports"
        subtitle="Sample content for the P0.05 shell verification fixture"
        :breadcrumbs="['Home' => url('/'), 'Reports' => null]">
        <x-slot:actions>
            <button type="button" class="btn btn-outline-secondary">
                <i class="bi bi-download me-1" aria-hidden="true"></i>Export
            </button>
            <button type="button" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New report
            </button>
        </x-slot:actions>
    </x-page-header>

    <x-alert type="warning" class="mb-4">
        This is a hard-coded preview fixture. No data on this page is real.
    </x-alert>

    {{-- Alerts ------------------------------------------------------------ --}}
    <div class="card mb-4">
        <div class="card-header fw-semibold">Feedback states</div>
        <div class="card-body">
            <x-alert type="success" dismissible>Report approved and recorded.</x-alert>
            <x-alert type="info">Your weekly draft was generated from 5 activity entries.</x-alert>
            <x-alert type="warning" title="Deadline approaching">Week 43 closes on Sunday at 23:59.</x-alert>
            <x-alert type="danger" title="Report returned for changes" class="mb-0">
                The attachment was missing. Add it and resubmit.
            </x-alert>
        </div>
    </div>

    {{-- Status vocabulary -------------------------------------------------- --}}
    <div class="card mb-4">
        <div class="card-header fw-semibold">Status vocabulary</div>
        <div class="card-body d-flex flex-wrap gap-2">
            @foreach ([
                'Draft', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Returned',
                'Pending', 'Active', 'Inactive', 'Suspended',
                'Present', 'Late', 'Absent', 'On Leave', 'Holiday', 'Non-Working Day',
                'Queued', 'Sending', 'Sent', 'Failed',
                'Draft Payslip', 'Paid', 'Cancelled', 'Completed', 'Blocked',
                'An Unmapped Status',
            ] as $status)
                <x-status-badge :status="$status" />
            @endforeach
        </div>
    </div>

    {{-- Data table -------------------------------------------------------- --}}
    <div class="card mb-4">
        <div class="card-header fw-semibold">Data table</div>
        <div class="table-responsive">
            <table class="table table-hover table-data align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">Staff</th>
                        <th scope="col">Period</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="numeric">Hours</th>
                        <th scope="col" class="numeric">Net pay</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ([
                        ['RPT-2026-0411', 'A. Mensah',  'Week 43', 'Approved',     38.50, 1240.00],
                        ['RPT-2026-0412', 'K. Boateng', 'Week 43', 'Under Review', 41.25, 1385.50],
                        ['RPT-2026-0413', 'J. Owusu',   'Week 43', 'Returned',      9.00,  302.75],
                        ['RPT-2026-0414', 'S. Adjei',   'Week 43', 'Draft',         0.00,    0.00],
                    ] as [$ref, $staff, $period, $status, $hours, $pay])
                        <tr>
                            <td><a href="#" class="text-decoration-none">{{ $ref }}</a></td>
                            <td>{{ $staff }}</td>
                            <td>{{ $period }}</td>
                            <td><x-status-badge :status="$status" /></td>
                            <td class="numeric">{{ number_format($hours, 2) }}</td>
                            <td class="numeric">{{ number_format($pay, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Form controls ----------------------------------------------------- --}}
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Form controls</div>
                <div class="card-body">
                    <x-input name="preview_title" label="Report title" required
                             help="Shown to your reviewer." />

                    <x-select name="preview_category" label="Category" placeholder="Choose a category…"
                              :options="['monthly' => 'Monthly Accomplishment', 'incident' => 'Incident', 'activity' => 'Activity']" />

                    <x-textarea name="preview_notes" label="Notes" :rows="3"
                                help="Optional context for the reviewer." />

                    <button type="button" class="btn btn-primary">Submit</button>
                    <button type="button" class="btn btn-link">Cancel</button>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Empty state</div>
                <div class="card-body d-flex align-items-center">
                    <x-empty-state
                        icon="file-earmark-text"
                        title="No reports match these filters"
                        description="Try widening the date range, or clear the department filter to see all reports you have access to.">
                        <x-slot:actions>
                            <button type="button" class="btn btn-outline-secondary btn-sm">Clear filters</button>
                        </x-slot:actions>
                    </x-empty-state>
                </div>
            </div>
        </div>
    </div>

    <p class="text-secondary small">
        Resize the window below 992&nbsp;px to check the off-canvas sidebar, and press
        <kbd>Tab</kbd> from the top of the page to check the skip link and focus ring.
    </p>
@endsection
