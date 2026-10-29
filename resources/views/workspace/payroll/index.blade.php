{{--
    Payroll. P6.01-FE to P6.04-FE.

    This screen serves two very different readers. Someone with payroll.manage
    runs the cycle; everyone else arrives only for their own payslip, and the
    server has already narrowed the lines to theirs. The page is built around
    that split rather than hiding a manager's controls behind disabled states.

    A payroll period moves draft, submitted, approved, locked, with return for
    correction off submitted. Each control is offered only at the status its
    own service method accepts, because a payroll button that throws a 422 is
    worse here than almost anywhere else in the product.

    Payments are recorded against an approved period only, and the server
    refuses a payment that would exceed the line. The remaining balance is
    shown next to the field so the number is not a guess.
--}}
@extends('layouts.app')

@section('title', __('Payroll'))

@php
    $periodStatuses = [
        'draft' => __('Draft'),
        'submitted' => __('Submitted'),
        'returned' => __('Returned for correction'),
        'approved' => __('Approved'),
        'locked' => __('Locked'),
    ];

    $generateFailed = $errors->hasAny(['period', 'starts_on', 'ends_on']);
@endphp

@section('content')
    <x-page-header
        :title="__('Payroll')"
        :subtitle="$manages
            ? __('Periods, lines and payments for this organization.')
            : __('Your pay records.')"
        :breadcrumbs="[__('Workspace') => null, __('Payroll') => null]">
        <x-slot:actions>
            @if ($manages)
                <button class="btn btn-primary btn-sm"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#generate-payroll"
                        aria-expanded="{{ $generateFailed ? 'true' : 'false' }}"
                        aria-controls="generate-payroll">
                    {{ __('Generate a period') }}
                </button>

                <a class="btn btn-outline-secondary btn-sm" href="{{ route('payroll.export') }}">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('Export CSV') }}
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($manages)
        <div class="collapse @if ($generateFailed) show @endif mb-4" id="generate-payroll">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Generate a payroll period') }}</h2>

                    <form method="POST" action="{{ route('payroll.generate') }}" novalidate>
                        @csrf

                        <div class="row">
                            <div class="col-12 col-md-4">
                                <x-input
                                    name="period"
                                    type="month"
                                    :label="__('Period')"
                                    :value="old('period', now()->format('Y-m'))"
                                    required
                                    :help="__('One period per month.')" />
                            </div>
                            <div class="col-12 col-md-4">
                                <x-input
                                    name="starts_on"
                                    type="date"
                                    :label="__('Covers from')"
                                    :value="old('starts_on', now()->startOfMonth()->format('Y-m-d'))"
                                    required />
                            </div>
                            <div class="col-12 col-md-4">
                                <x-input
                                    name="ends_on"
                                    type="date"
                                    :label="__('Covers to')"
                                    :value="old('ends_on', now()->endOfMonth()->format('Y-m-d'))"
                                    required />
                            </div>
                        </div>

                        <p class="text-secondary small">
                            {{ __('Generating writes a line for every active member from the compensation on record. It can only be done while the period is a draft.') }}
                        </p>

                        <button type="submit" class="btn btn-primary">{{ __('Generate') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <h2 class="h6 mb-2">{{ __('Periods') }}</h2>

        @if ($page->total() === 0)
            <x-empty-state
                icon="cash-stack"
                :title="__('No payroll periods yet')"
                :description="__('A period is generated for a month, checked, submitted, approved and then locked.')" />
        @else
            <div class="table-responsive">
                <table class="table table-data align-middle">
                    <caption class="visually-hidden">{{ __('Payroll periods') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Period') }}</th>
                            <th scope="col">{{ __('Covers') }}</th>
                            <th scope="col">{{ __('Lines') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col"><span class="visually-hidden">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($page as $period)
                            <tr>
                                <th scope="row" class="fw-semibold font-monospace">{{ $period->period }}</th>
                                <td class="font-monospace small text-nowrap">
                                    {{ optional($period->starts_on)->format('Y-m-d') }}
                                    {{ __('to') }}
                                    {{ optional($period->ends_on)->format('Y-m-d') }}
                                </td>
                                <td class="font-monospace small">{{ $period->lines_count }}</td>
                                <td>
                                    <x-status-badge
                                        :status="$period->status"
                                        :label="$periodStatuses[$period->status] ?? $period->status" />
                                </td>
                                <td>
                                    {{-- Each control appears only at the status its
                                         service method accepts. --}}
                                    <div class="d-flex flex-wrap gap-1 justify-content-end">
                                        @if ($period->status === 'draft' && $period->lines_count > 0)
                                            @can('payroll.prepare')
                                                <form method="POST" action="{{ route('payroll.submit', $period) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Submit') }}</button>
                                                </form>
                                            @endcan
                                        @endif

                                        @if ($period->status === 'submitted')
                                            @can('payroll.approve')
                                                <form method="POST" action="{{ route('payroll.approve', $period) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Approve') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('payroll.return', $period) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Return') }}</button>
                                                </form>
                                            @endcan
                                        @endif

                                        @if ($period->status === 'approved')
                                            @can('payroll.approve')
                                                <form method="POST" action="{{ route('payroll.lock', $period) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Lock') }}</button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <p class="text-secondary small mb-0">
                    {{ __('Showing :from to :to of :total', [
                        'from' => $page->firstItem(),
                        'to' => $page->lastItem(),
                        'total' => $page->total(),
                    ]) }}
                </p>
                {{ $page->withQueryString()->links() }}
            </div>
        @endif
    @endif

    <h2 class="h6 mb-2">{{ $manages ? __('Lines') : __('Your payslips') }}</h2>

    @if ($lines->isEmpty())
        <x-empty-state
            icon="receipt"
            :title="$manages ? __('No payroll lines yet') : __('You have no payslips yet')"
            :description="$manages
                ? __('Lines are written when a period is generated.')
                : __('A payslip appears here once payroll for a period has been generated and approved.')" />
    @else
        <div class="table-responsive">
            <table class="table table-data align-middle">
                <caption class="visually-hidden">{{ __('Payroll lines') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('Period') }}</th>
                        @if ($manages)
                            <th scope="col">{{ __('Employee') }}</th>
                        @endif
                        <th scope="col">{{ __('Gross') }}</th>
                        <th scope="col">{{ __('Adjustments') }}</th>
                        <th scope="col">{{ __('Net') }}</th>
                        <th scope="col">{{ __('Paid') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $line)
                        @php
                            $paid = (float) $line->payments->sum('amount');
                            $remaining = (float) $line->net_amount - $paid;
                            $payable = $manages && in_array($line->period?->status, ['approved', 'locked'], true) && $remaining > 0;
                        @endphp

                        <tr>
                            <th scope="row" class="fw-semibold font-monospace">{{ $line->period?->period }}</th>
                            @if ($manages)
                                <td>{{ $line->membership?->user?->name ?: __('Unknown') }}</td>
                            @endif
                            <td class="font-monospace small text-nowrap">{{ $line->currency }} {{ number_format((float) $line->gross_amount, 2) }}</td>
                            <td class="font-monospace small text-nowrap">{{ number_format((float) $line->adjustment_amount, 2) }}</td>
                            <td class="font-monospace small text-nowrap fw-semibold">{{ $line->currency }} {{ number_format((float) $line->net_amount, 2) }}</td>
                            <td class="font-monospace small text-nowrap">
                                {{ number_format($paid, 2) }}
                                @if ($remaining > 0.004)
                                    <span class="d-block text-secondary">{{ __('Outstanding :amount', ['amount' => number_format($remaining, 2)]) }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('payroll.payslip', $line) }}">{{ __('Payslip') }}</a>

                                @if ($payable)
                                    <details class="mt-1 text-start">
                                        <summary class="small">{{ __('Record a payment') }}</summary>

                                        <form method="POST" action="{{ route('payroll.payments.store', $line) }}" class="mt-2" novalidate>
                                            @csrf

                                            <x-input
                                                name="amount"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                :max="number_format($remaining, 2, '.', '')"
                                                :label="__('Amount')"
                                                :value="old('amount')"
                                                required
                                                :help="__('Up to :amount remaining.', ['amount' => number_format($remaining, 2)])" />

                                            <x-input
                                                name="paid_on"
                                                type="date"
                                                :label="__('Paid on')"
                                                :value="old('paid_on', now()->format('Y-m-d'))"
                                                required />

                                            <x-input
                                                name="reference"
                                                :label="__('Reference')"
                                                :value="old('reference')"
                                                :help="__('Optional, and unique across this organization.')" />

                                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Record payment') }}</button>
                                        </form>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
