@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Financial reports'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-bar-chart-line"></i>{{ __('Accounts') }}</span>
            <h1 class="page-title">{{ __('Financial reports') }}</h1>
            <p class="page-subtitle">{{ __('Collections, cashbook and the yearly income and expense statement for :from – :to', [
                'from' => $filters['from']->format('d M Y'),
                'to' => $filters['to']->format('d M Y'),
            ]) }}</p>
        </div>
    </div>

    @include('reports._filters')

    <section class="stats-grid mb-3" aria-label="{{ __('Report totals') }}">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Standard fee') }}</p>
                    <p class="stat-value">{{ Money::format($summary['standard_fee']) }}</p>
                </div>
                <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-journal-text"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Fee as issued on the invoices collected') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Cash received') }}</p>
                    <p class="stat-value">{{ Money::format($summary['received']) }}</p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-cash-stack"></i></span>
            </div>
            <div class="stat-foot"><span>{{ trans_choice(':count payment|:count payments', $summary['payments'], ['count' => $summary['payments']]) }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Ledger income') }}</p>
                    <p class="stat-value">{{ Money::format($totals['income']) }}</p>
                </div>
                <span class="stat-icon" aria-hidden="true"><i class="bi bi-arrow-down-left-circle"></i></span>
            </div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Ledger expense') }}</p>
                    <p class="stat-value">{{ Money::format($totals['expense']) }}</p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-arrow-up-right-circle"></i></span>
            </div>
        </article>
    </section>

    <div class="row g-3">
        @foreach ([
            ['route' => 'reports.collection', 'key' => 'collection', 'icon' => 'bi-cash-coin', 'title' => __('Fee collection report'), 'copy' => __('Daily and monthly totals, the method split, plus reversals and unverified money kept apart.')],
            ['route' => 'reports.students', 'key' => 'students', 'icon' => 'bi-person-lines-fill', 'title' => __('Student payment history'), 'copy' => __('What each student paid, invoice by invoice, with the receipt behind every line.')],
            ['route' => 'reports.classes', 'key' => 'classes', 'icon' => 'bi-diagram-3', 'title' => __('Class and section summary'), 'copy' => __('Collections grouped by the class each fee record belonged to.')],
            ['route' => 'reports.fee-types', 'key' => 'fee-types', 'icon' => 'bi-list-task', 'title' => __('Fee type report'), 'copy' => __('Tuition, examination and other charges with the cash each one brought in.')],
            ['route' => 'reports.methods', 'key' => 'methods', 'icon' => 'bi-credit-card', 'title' => __('Payment method report'), 'copy' => __('Cash, bKash, Nagad, rocket, bank, card and cheque, mapped to the cashbook channel.')],
            ['route' => 'reports.collectors', 'key' => 'collectors', 'icon' => 'bi-person-badge', 'title' => __('Collector activity and audit'), 'copy' => __('Who took the money and every posting, hold, verification and reversal they signed off.')],
            ['route' => 'reports.expenses', 'key' => 'expenses', 'icon' => 'bi-bag-dash', 'title' => __('Expense report'), 'copy' => __('Approved spending grouped by category and payment channel.')],
            ['route' => 'reports.statement', 'key' => 'statement', 'icon' => 'bi-journal-check', 'title' => __('Income and expense statement'), 'copy' => __('Twelve months of income, expense and running balance from the cashbook.')],
        ] as $card)
            <div class="col-md-6 col-xl-4">
                <a class="ui-card p-lg d-block text-decoration-none h-100" href="{{ route($card['route'], array_merge(request()->query(), ['from' => $filters['from']->toDateString(), 'to' => $filters['to']->toDateString()])) }}">
                    <span class="stat-icon mb-2" aria-hidden="true"><i class="bi {{ $card['icon'] }}"></i></span>
                    <h2 class="card-title">{{ $card['title'] }}</h2>
                    <p class="text-muted-2 mb-0" style="font-size:.82rem;">{{ $card['copy'] }}</p>
                </a>
            </div>
        @endforeach
    </div>

    <p class="text-muted-2 mt-3 mb-0" style="font-size:.78rem;">
        <i class="bi bi-info-circle"></i>
        {{ __('These reports never show a balance owed. When a collector settles an invoice for less than the fee it was issued at, the difference is written off at that payment and is not carried forward into any report here.') }}
    </p>
@endsection

@include('reports._print')
