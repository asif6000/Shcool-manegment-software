@use('App\Support\Money')
@extends('layouts.accountant')

@section('title', __('Accountant Dashboard'))

@section('content')
@php
    $money = fn (float $value): string => Money::format($value);
    $percent = fn (?float $value): string => $value === null ? '0.0%' : number_format($value, 1).'%';
@endphp

{{-- =========================================================
     TOP KPI CARDS (6 CARDS)
     ========================================================= --}}
<section class="kpi-grid" aria-label="{{ __('Financial KPIs') }}">
    {{-- 1. Today's Collection --}}
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-label">{{ __("Today's Collection") }}</span>
            <span class="kpi-icon-wrap is-emerald"><i class="bi bi-cash-stack"></i></span>
        </div>
        <p class="kpi-value">{{ $money($cards['today_collection']['value']) }}</p>
        <div class="kpi-subtext">
            <span class="badge-emerald"><i class="bi bi-receipt"></i> {{ $cards['today_collection']['count'] }} {{ __('receipts') }}</span>
            <span>{{ __('today') }}</span>
        </div>
    </div>

    {{-- 2. Monthly Collection --}}
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-label">{{ __('Monthly Collection') }}</span>
            <span class="kpi-icon-wrap is-navy"><i class="bi bi-graph-up-arrow"></i></span>
        </div>
        <p class="kpi-value">{{ $money($cards['month_collection']['value']) }}</p>
        <div class="kpi-subtext">
            <span class="{{ $cards['month_collection']['growth'] < 0 ? 'badge bg-danger-subtle text-danger' : 'badge-emerald' }}">
                {{ $cards['month_collection']['growth'] > 0 ? '+' : '' }}{{ $percent($cards['month_collection']['growth']) }}
            </span>
            <span>{{ __('vs last month') }}</span>
        </div>
    </div>

    {{-- 3. Total Expenses --}}
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-label">{{ __('Total Expenses') }}</span>
            <span class="kpi-icon-wrap is-rose"><i class="bi bi-bag-dash"></i></span>
        </div>
        <p class="kpi-value">{{ $money($cards['total_expenses']['value']) }}</p>
        <div class="kpi-subtext">
            <span class="badge bg-danger-subtle text-danger">{{ $cards['total_expenses']['count'] }} {{ __('approved entries') }}</span>
        </div>
    </div>

    {{-- 4. Cash Balance --}}
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-label">{{ __('Cash Balance') }}</span>
            <span class="kpi-icon-wrap is-emerald"><i class="bi bi-wallet2"></i></span>
        </div>
        <p class="kpi-value">{{ $money($cards['cash_balance']['value']) }}</p>
        <div class="kpi-subtext text-truncate">
            <span class="text-success fw-bold">{{ __('Hand:') }} {{ $money($cards['cash_balance']['cash']) }}</span>
            <span class="text-muted">· {{ __('Bank:') }} {{ $money($cards['cash_balance']['bank']) }}</span>
        </div>
    </div>

    {{-- 5. Total Discounts --}}
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-label">{{ __('Total Discounts') }}</span>
            <span class="kpi-icon-wrap is-amber"><i class="bi bi-percent"></i></span>
        </div>
        <p class="kpi-value">{{ $money($cards['total_discounts']['value']) }}</p>
        <div class="kpi-subtext">
            <span class="badge-amber">{{ $cards['total_discounts']['invoices_count'] }} {{ __('invoices waived') }}</span>
        </div>
    </div>

    {{-- 6. Online Payments --}}
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-label">{{ __('Online Payments') }}</span>
            <span class="kpi-icon-wrap is-purple"><i class="bi bi-credit-card-2-front"></i></span>
        </div>
        <p class="kpi-value">{{ $money($cards['online_payments']['value']) }}</p>
        <div class="kpi-subtext">
            <span class="badge bg-purple-subtle text-purple fw-semibold">{{ $cards['online_payments']['count'] }} {{ __('transactions') }}</span>
            <span>{{ __('bKash / Bank') }}</span>
        </div>
    </div>
</section>

{{-- =========================================================
     ACTION CARDS (9 CARDS)
     ========================================================= --}}
<div class="section-heading-bar">
    <div class="section-title-tag">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>{{ __('Accountant Actions & Operations') }}</span>
    </div>
    <span class="text-muted small">{{ __('Click any card to open modal/panel instantly') }}</span>
</div>

<section class="action-cards-grid mb-4" aria-label="{{ __('Accountant Action Cards') }}">
    {{-- Action 1: Fee Collection --}}
    <button type="button" class="action-card-btn" data-bs-toggle="modal" data-bs-target="#modalFeeCollection">
        <div class="action-card-top">
            <div class="action-icon-badge"><i class="bi bi-cash-coin"></i></div>
            <div class="action-card-chevron"><i class="bi bi-chevron-right"></i></div>
        </div>
        <div>
            <span class="action-card-title">{{ __('Fee Collection') }}</span>
            <p class="action-card-desc">{{ __('Receive tuition and monthly student fees across open invoices.') }}</p>
        </div>
    </button>

    {{-- Action 2: Student Fees --}}
    <button type="button" class="action-card-btn" data-bs-toggle="modal" data-bs-target="#modalStudentFees">
        <div class="action-card-top">
            <div class="action-icon-badge"><i class="bi bi-mortarboard"></i></div>
            <div class="action-card-chevron"><i class="bi bi-chevron-right"></i></div>
        </div>
        <div>
            <span class="action-card-title">{{ __('Student Fees') }}</span>
            <p class="action-card-desc">{{ __('Review student fee invoices, dues balances, and billing records.') }}</p>
        </div>
    </button>

    {{-- Action 3: Payment History --}}
    <button type="button" class="action-card-btn" data-bs-toggle="modal" data-bs-target="#modalPaymentHistory">
        <div class="action-card-top">
            <div class="action-icon-badge"><i class="bi bi-clock-history"></i></div>
            <div class="action-card-chevron"><i class="bi bi-chevron-right"></i></div>
        </div>
        <div>
            <span class="action-card-title">{{ __('Payment History') }}</span>
            <p class="action-card-desc">{{ __('Browse all verified receipts, counter collections, and audit logs.') }}</p>
        </div>
    </button>

    {{-- Action 4: Add Expense --}}
    <button type="button" class="action-card-btn" data-bs-toggle="modal" data-bs-target="#modalAddExpense">
        <div class="action-card-top">
            <div class="action-icon-badge"><i class="bi bi-plus-circle"></i></div>
            <div class="action-card-chevron"><i class="bi bi-chevron-right"></i></div>
        </div>
        <div>
            <span class="action-card-title">{{ __('Add Expense') }}</span>
            <p class="action-card-desc">{{ __('Record new school expenditure, utilities, maintenance, or petty cash.') }}</p>
        </div>
    </button>

    {{-- Action 5: Cashbook --}}
    <button type="button" class="action-card-btn" data-bs-toggle="modal" data-bs-target="#modalCashbook">
        <div class="action-card-top">
            <div class="action-icon-badge"><i class="bi bi-journal-bookmark"></i></div>
            <div class="action-card-chevron"><i class="bi bi-chevron-right"></i></div>
        </div>
        <div>
            <span class="action-card-title">{{ __('Cashbook') }}</span>
            <p class="action-card-desc">{{ __('Track daily cash ledger, bank accounts, and mobile wallet balances.') }}</p>
        </div>
    </button>

    {{-- Action 6: Discounts --}}
    <button type="button" class="action-card-btn" data-bs-toggle="modal" data-bs-target="#modalDiscounts">
        <div class="action-card-top">
            <div class="action-icon-badge"><i class="bi bi-percent"></i></div>
            <div class="action-card-chevron"><i class="bi bi-chevron-right"></i></div>
        </div>
        <div>
            <span class="action-card-title">{{ __('Discounts') }}</span>
            <p class="action-card-desc">{{ __('Inspect student fee waivers, special concession policies, and grants.') }}</p>
        </div>
    </button>

    {{-- Action 7: Salary Payment --}}
    <button type="button" class="action-card-btn" data-bs-toggle="modal" data-bs-target="#modalSalaryPayment">
        <div class="action-card-top">
            <div class="action-icon-badge"><i class="bi bi-wallet2"></i></div>
            <div class="action-card-chevron"><i class="bi bi-chevron-right"></i></div>
        </div>
        <div>
            <span class="action-card-title">{{ __('Salary Payment') }}</span>
            <p class="action-card-desc">{{ __('Staff payroll structures, monthly disbursement register, and payouts.') }}</p>
        </div>
    </button>

    {{-- Action 8: Reports --}}
    <button type="button" class="action-card-btn" data-bs-toggle="modal" data-bs-target="#modalReports">
        <div class="action-card-top">
            <div class="action-icon-badge"><i class="bi bi-file-earmark-bar-graph"></i></div>
            <div class="action-card-chevron"><i class="bi bi-chevron-right"></i></div>
        </div>
        <div>
            <span class="action-card-title">{{ __('Reports') }}</span>
            <p class="action-card-desc">{{ __('Generate comprehensive financial statements, daily digests, and exports.') }}</p>
        </div>
    </button>

    {{-- Action 9: My Profile --}}
    <button type="button" class="action-card-btn" data-bs-toggle="modal" data-bs-target="#modalMyProfile">
        <div class="action-card-top">
            <div class="action-icon-badge"><i class="bi bi-person-circle"></i></div>
            <div class="action-card-chevron"><i class="bi bi-chevron-right"></i></div>
        </div>
        <div>
            <span class="action-card-title">{{ __('My Profile') }}</span>
            <p class="action-card-desc">{{ __('Manage your accountant profile, credentials, and security password.') }}</p>
        </div>
    </button>
</section>

{{-- =========================================================
     BELOW ACTION CARDS SECTION
     1. Recent Payments
     2. Recent Expenses
     3. Monthly Income vs Expense Chart
     4. Today's Transactions
     ========================================================= --}}
<div class="bottom-grid">
    {{-- Left Column: Recent Payments & Today's Transactions --}}
    <div class="d-flex flex-column gap-4">
        {{-- Recent Payments --}}
        <div class="dashboard-panel">
            <div class="panel-head">
                <h3 class="panel-title"><i class="bi bi-receipt-cutoff"></i> {{ __('Recent Payments') }}</h3>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalPaymentHistory">
                    <i class="bi bi-eye"></i> {{ __('View All') }}
                </button>
            </div>
            <div class="table-responsive">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>{{ __('Receipt') }}</th>
                            <th>{{ __('Student') }}</th>
                            <th>{{ __('Method') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentPayments->take(6) as $payment)
                            <tr>
                                <td>
                                    <span class="fw-bold text-primary">#{{ $payment->id }}</span>
                                </td>
                                <td>
                                    <span class="fw-semibold d-block">{{ $payment->student?->full_name ?? __('Direct Collection') }}</span>
                                    <span class="text-muted small">{{ $payment->student?->student_id }}</span>
                                </td>
                                <td>
                                    <span class="badge-navy text-capitalize"><i class="bi bi-credit-card"></i> {{ $payment->method }}</span>
                                </td>
                                <td>
                                    <span class="fw-bold text-success">{{ $money($payment->amount) }}</span>
                                </td>
                                <td>
                                    <span class="text-muted small">{{ $payment->paid_at?->format('d M, h:i A') ?? $payment->created_at->format('d M') }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                    {{ __('No payment records found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Today's Transactions --}}
        <div class="dashboard-panel">
            <div class="panel-head">
                <h3 class="panel-title"><i class="bi bi-journal-text"></i> {{ __("Today's Transactions & Day-Book") }}</h3>
                <span class="badge bg-light text-dark border">{{ $today->format('d M Y') }}</span>
            </div>
            <div class="table-responsive">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>{{ __('Reference') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('Channel') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($todayTransactions as $tx)
                            @php
                                $isIn = (float) $tx->amount >= 0;
                            @endphp
                            <tr>
                                <td><span class="text-muted small font-monospace">{{ $tx->reference_no ?: '#'.$tx->id }}</span></td>
                                <td><span class="fw-medium text-truncate d-block" style="max-width: 220px;">{{ $tx->description ?: __('Daybook ledger entry') }}</span></td>
                                <td><span class="badge bg-secondary-subtle text-secondary text-uppercase" style="font-size: 0.65rem;">{{ $tx->channel }}</span></td>
                                <td>
                                    <span class="{{ $isIn ? 'badge-emerald' : 'badge bg-danger-subtle text-danger' }}">
                                        {{ $isIn ? __('In (Credit)') : __('Out (Debit)') }}
                                    </span>
                                </td>
                                <td class="text-end fw-bold {{ $isIn ? 'text-success' : 'text-danger' }}">
                                    {{ $isIn ? '+' : '-' }}{{ $money(abs((float) $tx->amount)) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="bi bi-clipboard2-check fs-3 d-block mb-1"></i>
                                    {{ __('No transactions logged for today yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Right Column: Monthly Income vs Expense Chart & Recent Expenses --}}
    <div class="d-flex flex-column gap-4">
        {{-- Monthly Income vs Expense Chart --}}
        <div class="dashboard-panel">
            <div class="panel-head">
                <h3 class="panel-title"><i class="bi bi-bar-chart-fill"></i> {{ __('Monthly Income vs Expense') }}</h3>
                <div class="d-flex align-items-center gap-3 font-monospace small">
                    <span class="d-flex align-items-center gap-1 text-success"><span style="width: 10px; height: 10px; background: #10b981; border-radius: 2px;"></span> {{ __('Income') }}</span>
                    <span class="d-flex align-items-center gap-1 text-primary"><span style="width: 10px; height: 10px; background: #0a1d3d; border-radius: 2px;"></span> {{ __('Expense') }}</span>
                </div>
            </div>
            <div style="height: 250px; position: relative;">
                <canvas id="monthlyFinancialChart"></canvas>
            </div>
        </div>

        {{-- Recent Expenses --}}
        <div class="dashboard-panel">
            <div class="panel-head">
                <h3 class="panel-title"><i class="bi bi-wallet"></i> {{ __('Recent Expenses') }}</h3>
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalAddExpense">
                    <i class="bi bi-plus-lg"></i> {{ __('Add Expense') }}
                </button>
            </div>
            <div class="table-responsive">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>{{ __('Expense') }}</th>
                            <th>{{ __('Category') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentExpenses->take(5) as $exp)
                            <tr>
                                <td>
                                    <span class="fw-semibold d-block text-truncate" style="max-width: 170px;">{{ $exp->title }}</span>
                                    <span class="text-muted small">{{ $exp->expense_date?->format('d M Y') }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $exp->category?->name ?? __('General') }}</span>
                                </td>
                                <td>
                                    <span class="fw-bold text-danger">{{ $money($exp->amount) }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $exp->status === 'paid' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }} text-capitalize">
                                        {{ $exp->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="bi bi-check2-circle fs-3 d-block mb-1 text-success"></i>
                                    {{ __('No expense entries logged.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- =========================================================
     INTERACTION MODALS (CORRESPONDING TO 9 ACTION CARDS)
     Opened in the same page without navigating away.
     ========================================================= --}}

{{-- 1. Fee Collection Modal --}}
<div class="modal fade" id="modalFeeCollection" tabindex="-1" aria-labelledby="modalFeeCollectionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0a1d3d 0%, #162f5d 100%);">
                <h5 class="modal-title fw-bold" id="modalFeeCollectionLabel">
                    <i class="bi bi-cash-coin text-emerald me-2"></i> {{ __('Collect Student Fee') }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('fees.collect.store') }}" id="feeCollectionForm">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <input type="hidden" name="academic_session_id" value="{{ $currentSession?->id }}">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        {{-- Select Student --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted">{{ __('Select Student') }} <span class="text-danger">*</span></label>
                            <select name="student_id" id="feeStudentSelect" class="form-select" required>
                                <option value="">-- {{ __('Choose student') }} --</option>
                                @foreach ($students as $st)
                                    <option value="{{ $st->id }}">
                                        {{ $st->full_name }} ({{ $st->student_id }})
                                        @if($st->enrollments->isNotEmpty())
                                            · {{ $st->enrollments->first()->schoolClass?->name }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Payment Method --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted">{{ __('Payment Method') }} <span class="text-danger">*</span></label>
                            <select name="method" id="feeMethodSelect" class="form-select" required>
                                @foreach ($paymentMethods as $m)
                                    <option value="{{ $m }}" {{ $m === 'cash' ? 'selected' : '' }}>{{ __(ucfirst($m)) }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Open Invoices / Fee Allocation Table --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted">{{ __('Select Invoices to Bill') }} <span class="text-danger">*</span></label>
                            <div class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                @forelse ($openInvoices as $inv)
                                    <div class="d-flex align-items-center justify-content-between py-2 border-bottom invoice-row" data-student-id="{{ $inv->student_id }}">
                                        <div class="form-check">
                                            <input class="form-check-input invoice-check" type="checkbox" name="picked[]" value="{{ $inv->id }}" id="inv_{{ $inv->id }}" data-due="{{ $inv->due_total }}">
                                            <label class="form-check-label small" for="inv_{{ $inv->id }}">
                                                <strong>#{{ $inv->invoice_no }}</strong> - {{ $inv->student?->full_name }}
                                                <span class="text-muted">({{ $inv->billing_period }})</span>
                                            </label>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-danger-subtle text-danger small">{{ __('Due:') }} {{ $money($inv->due_total) }}</span>
                                            <input type="number" step="0.01" min="0.01" max="{{ $inv->due_total }}" class="form-control form-control-sm text-end invoice-amount" name="amounts[{{ $inv->id }}]" value="{{ $inv->due_total }}" style="width: 110px;" placeholder="Amount">
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted small text-center my-2">{{ __('No pending invoices found in database.') }}</p>
                                @endforelse
                            </div>
                        </div>

                        {{-- Reference No --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted">{{ __('Transaction Reference / ID') }}</label>
                            <input type="text" name="transaction_ref" class="form-control" placeholder="{{ __('e.g. bKash TrxID or Cheque #') }}">
                        </div>

                        {{-- Date --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted">{{ __('Payment Date') }} <span class="text-danger">*</span></label>
                            <input type="date" name="paid_at" class="form-control" value="{{ $today->toDateString() }}" max="{{ $today->toDateString() }}" required>
                        </div>

                        {{-- Bank Account (if applicable) --}}
                        @if ($bankAccounts->isNotEmpty())
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">{{ __('Deposit To Bank Account') }}</label>
                                <select name="bank_account_id" class="form-select">
                                    <option value="">-- {{ __('None / Cash Ledger') }} --</option>
                                    @foreach ($bankAccounts as $ba)
                                        <option value="{{ $ba->id }}">{{ $ba->name }} ({{ $ba->account_number }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        {{-- Notes --}}
                        <div class="col-md-{{ $bankAccounts->isNotEmpty() ? '6' : '12' }}">
                            <label class="form-label fw-semibold small text-muted">{{ __('Remarks / Notes') }}</label>
                            <input type="text" name="notes" class="form-control" placeholder="{{ __('Optional payment remarks') }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-success rounded-pill px-5 fw-bold" style="background: #10b981; border-color: #10b981;">
                        <i class="bi bi-check2-circle me-1"></i> {{ __('Post Collection & Receipt') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 2. Student Fees Modal --}}
<div class="modal fade" id="modalStudentFees" tabindex="-1" aria-labelledby="modalStudentFeesLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0a1d3d 0%, #162f5d 100%);">
                <h5 class="modal-title fw-bold" id="modalStudentFeesLabel">
                    <i class="bi bi-mortarboard text-emerald me-2"></i> {{ __('Student Fee Invoices & Dues') }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted small">{{ __('Listing all recent student fee records from the database') }}</span>
                    <span class="badge bg-primary rounded-pill">{{ $openInvoices->count() }} {{ __('active dues') }}</span>
                </div>
                <div class="table-responsive" style="max-height: 480px;">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>{{ __('Invoice #') }}</th>
                                <th>{{ __('Student Name') }}</th>
                                <th>{{ __('Billing Period') }}</th>
                                <th class="text-end">{{ __('Total') }}</th>
                                <th class="text-end">{{ __('Paid') }}</th>
                                <th class="text-end">{{ __('Due') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-center">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($openInvoices as $inv)
                                <tr>
                                    <td><span class="fw-bold text-primary">{{ $inv->invoice_no }}</span></td>
                                    <td>
                                        <span class="fw-semibold">{{ $inv->student?->full_name }}</span>
                                        <span class="text-muted small d-block">{{ $inv->student?->student_id }}</span>
                                    </td>
                                    <td>{{ $inv->billing_period }}</td>
                                    <td class="text-end fw-semibold">{{ $money($inv->total) }}</td>
                                    <td class="text-end text-success">{{ $money($inv->paid_total) }}</td>
                                    <td class="text-end fw-bold text-danger">{{ $money($inv->due_total) }}</td>
                                    <td>
                                        <span class="badge {{ $inv->status === 'paid' ? 'bg-success' : ($inv->status === 'partial' ? 'bg-warning text-dark' : 'bg-danger') }} text-capitalize">
                                            {{ $inv->status }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-success rounded-pill px-3" onclick="openFeeModalWithInvoice({{ $inv->student_id }}, {{ $inv->id }}, {{ $inv->due_total }})">
                                            <i class="bi bi-cash"></i> {{ __('Collect') }}
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">{{ __('No open invoices found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light p-3">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- 3. Payment History Modal --}}
<div class="modal fade" id="modalPaymentHistory" tabindex="-1" aria-labelledby="modalPaymentHistoryLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0a1d3d 0%, #162f5d 100%);">
                <h5 class="modal-title fw-bold" id="modalPaymentHistoryLabel">
                    <i class="bi bi-clock-history text-emerald me-2"></i> {{ __('Payment Receipts & Collection History') }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive" style="max-height: 480px;">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>{{ __('Receipt ID') }}</th>
                                <th>{{ __('Paid Date') }}</th>
                                <th>{{ __('Student') }}</th>
                                <th>{{ __('Method') }}</th>
                                <th>{{ __('Reference') }}</th>
                                <th>{{ __('Collected By') }}</th>
                                <th class="text-end">{{ __('Amount') }}</th>
                                <th class="text-center">{{ __('Receipt') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentPayments as $p)
                                <tr>
                                    <td><span class="fw-bold font-monospace text-primary">#{{ $p->id }}</span></td>
                                    <td>{{ $p->paid_at?->format('d M Y, h:i A') ?? $p->created_at->format('d M Y') }}</td>
                                    <td>
                                        <span class="fw-semibold">{{ $p->student?->full_name ?? __('Direct Collection') }}</span>
                                        <span class="text-muted small d-block">{{ $p->student?->student_id }}</span>
                                    </td>
                                    <td><span class="badge-navy text-capitalize">{{ $p->method }}</span></td>
                                    <td><span class="text-muted font-monospace small">{{ $p->transaction_ref ?: '—' }}</span></td>
                                    <td><span class="text-muted small">{{ $p->receiver?->name ?? __('System') }}</span></td>
                                    <td class="text-end fw-bold text-success">{{ $money($p->amount) }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('fees.receipt', $p) }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                            <i class="bi bi-printer"></i> {{ __('Receipt') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">{{ __('No payments recorded yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light p-3">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- 4. Add Expense Modal --}}
<div class="modal fade" id="modalAddExpense" tabindex="-1" aria-labelledby="modalAddExpenseLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0a1d3d 0%, #162f5d 100%);">
                <h5 class="modal-title fw-bold" id="modalAddExpenseLabel">
                    <i class="bi bi-plus-circle text-emerald me-2"></i> {{ __('Record School Expense') }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('expenses.store') }}">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small text-muted">{{ __('Expense Title / Purpose') }} <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="{{ __('e.g. Electricity Bill or Office Paper Purchase') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted">{{ __('Amount (BDT)') }} <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" name="amount" class="form-control fw-bold" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted">{{ __('Category') }} <span class="text-danger">*</span></label>
                            <select name="expense_category_id" class="form-select" required>
                                <option value="">-- {{ __('Select category') }} --</option>
                                @foreach ($expenseCategories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted">{{ __('Expense Date') }} <span class="text-danger">*</span></label>
                            <input type="date" name="expense_date" class="form-control" value="{{ $today->toDateString() }}" max="{{ $today->toDateString() }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted">{{ __('Payment Method') }} <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" required>
                                @foreach ($expenseMethods as $em)
                                    <option value="{{ $em }}" {{ $em === 'cash' ? 'selected' : '' }}>{{ __(ucfirst($em)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted">{{ __('Paid To (Vendor / Person)') }}</label>
                            <input type="text" name="paid_to" class="form-control" placeholder="{{ __('Vendor or supplier name') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted">{{ __('Invoice / Voucher Ref #') }}</label>
                            <input type="text" name="reference_no" class="form-control" placeholder="{{ __('Voucher reference number') }}">
                        </div>
                        @if ($bankAccounts->isNotEmpty())
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-muted">{{ __('Bank Account') }}</label>
                                <select name="bank_account_id" class="form-select">
                                    <option value="">-- {{ __('None / Cash Ledger') }} --</option>
                                    @foreach ($bankAccounts as $ba)
                                        <option value="{{ $ba->id }}">{{ $ba->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted">{{ __('Description / Notes') }}</label>
                            <textarea name="description" rows="2" class="form-control" placeholder="{{ __('Optional expenditure details') }}"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold" style="background: #0a1d3d; border-color: #0a1d3d;">
                        <i class="bi bi-save me-1"></i> {{ __('Save & Record Expense') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 5. Cashbook Modal --}}
<div class="modal fade" id="modalCashbook" tabindex="-1" aria-labelledby="modalCashbookLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0a1d3d 0%, #162f5d 100%);">
                <h5 class="modal-title fw-bold" id="modalCashbookLabel">
                    <i class="bi bi-journal-bookmark text-emerald me-2"></i> {{ __('Cashbook & Liquid Position') }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                {{-- Three channels breakdown --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 bg-light border text-center">
                            <span class="text-muted small fw-semibold text-uppercase">{{ __('Cash in Hand') }}</span>
                            <h4 class="fw-bold text-success my-1">{{ $money($cards['cash_balance']['cash']) }}</h4>
                            <span class="text-muted small">{{ __('Counter Cash Drawer') }}</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 bg-light border text-center">
                            <span class="text-muted small fw-semibold text-uppercase">{{ __('Bank Accounts') }}</span>
                            <h4 class="fw-bold text-primary my-1">{{ $money($cards['cash_balance']['bank']) }}</h4>
                            <span class="text-muted small">{{ __('Ledger + Opening Balances') }}</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 bg-light border text-center">
                            <span class="text-muted small fw-semibold text-uppercase">{{ __('Mobile Wallets') }}</span>
                            <h4 class="fw-bold text-purple my-1">{{ $money($cards['cash_balance']['mobile']) }}</h4>
                            <span class="text-muted small">{{ __('bKash / Nagad / Rocket') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Transactions Table --}}
                <h6 class="fw-bold mb-2">{{ __('Day-Book Cash Entries') }}</h6>
                <div class="table-responsive" style="max-height: 380px;">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Reference') }}</th>
                                <th>{{ __('Description') }}</th>
                                <th>{{ __('Channel') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th class="text-end">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($todayTransactions as $tx)
                                @php $isIn = (float) $tx->amount >= 0; @endphp
                                <tr>
                                    <td>{{ $tx->transaction_date ? \Carbon\Carbon::parse($tx->transaction_date)->format('d M Y') : '—' }}</td>
                                    <td><span class="font-monospace small text-primary">{{ $tx->reference_no ?: '#'.$tx->id }}</span></td>
                                    <td>{{ $tx->description ?: __('Cash transaction') }}</td>
                                    <td><span class="badge bg-secondary-subtle text-secondary text-uppercase">{{ $tx->channel }}</span></td>
                                    <td>
                                        <span class="{{ $isIn ? 'badge-emerald' : 'badge bg-danger-subtle text-danger' }}">
                                            {{ $isIn ? __('In (Credit)') : __('Out (Debit)') }}
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold {{ $isIn ? 'text-success' : 'text-danger' }}">
                                        {{ $isIn ? '+' : '-' }}{{ $money(abs((float) $tx->amount)) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">{{ __('No transactions found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light p-3">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- 6. Discounts Modal --}}
<div class="modal fade" id="modalDiscounts" tabindex="-1" aria-labelledby="modalDiscountsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0a1d3d 0%, #162f5d 100%);">
                <h5 class="modal-title fw-bold" id="modalDiscountsLabel">
                    <i class="bi bi-percent text-emerald me-2"></i> {{ __('Fee Discounts & Waivers') }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border text-center">
                            <span class="text-muted small fw-semibold">{{ __('Total Discounts Given') }}</span>
                            <h3 class="fw-bold text-primary my-1">{{ $money($cards['total_discounts']['value']) }}</h3>
                            <span class="text-muted small">{{ $cards['total_discounts']['invoices_count'] }} {{ __('invoices with concession') }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border text-center">
                            <span class="text-muted small fw-semibold">{{ __('Active Discount Policies') }}</span>
                            <h3 class="fw-bold text-success my-1">{{ $cards['total_discounts']['schemes_count'] }}</h3>
                            <span class="text-muted small">{{ __('Configured concession schemes') }}</span>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mb-2">{{ __('Active Discount Policies') }}</h6>
                <div class="table-responsive">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>{{ __('Scheme Name') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Value') }}</th>
                                <th>{{ __('Scope') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($feeDiscounts as $d)
                                <tr>
                                    <td><span class="fw-semibold">{{ $d->name }}</span></td>
                                    <td><span class="badge-navy text-capitalize">{{ $d->type }}</span></td>
                                    <td class="fw-bold text-success">{{ in_array($d->type, ['percent', 'percentage'], true) ? $d->value.'%' : $money($d->value) }}</td>
                                    <td><span class="text-muted small">{{ $d->scope }}</span></td>
                                    <td>
                                        <span class="badge {{ $d->is_active ? 'badge-emerald' : 'bg-secondary' }}">
                                            {{ $d->is_active ? __('Active') : __('Inactive') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">{{ __('No discount policies configured yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light p-3">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- 7. Salary Payment Modal --}}
<div class="modal fade" id="modalSalaryPayment" tabindex="-1" aria-labelledby="modalSalaryPaymentLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0a1d3d 0%, #162f5d 100%);">
                <h5 class="modal-title fw-bold" id="modalSalaryPaymentLabel">
                    <i class="bi bi-wallet2 text-emerald me-2"></i> {{ __('Staff Salary & Payroll Register') }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted small">{{ __('Teaching and non-teaching personnel salary register') }}</span>
                    <span class="badge bg-primary rounded-pill">{{ $teachers->count() + $employees->count() }} {{ __('Staff Members') }}</span>
                </div>
                <div class="table-responsive" style="max-height: 480px;">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>{{ __('Staff Member') }}</th>
                                <th>{{ __('Role / Type') }}</th>
                                <th>{{ __('Phone / Contact') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($teachers as $t)
                                <tr>
                                    <td>
                                        <span class="fw-semibold d-block">{{ $t->full_name }}</span>
                                        <span class="text-muted small">{{ $t->email }}</span>
                                    </td>
                                    <td><span class="badge bg-primary-subtle text-primary">{{ __('Teacher') }}</span></td>
                                    <td>{{ $t->phone ?: '—' }}</td>
                                    <td><span class="badge bg-success-subtle text-success">{{ __('Active') }}</span></td>
                                </tr>
                            @endforeach
                            @foreach ($employees as $e)
                                <tr>
                                    <td>
                                        <span class="fw-semibold d-block">{{ $e->full_name }}</span>
                                        <span class="text-muted small">{{ $e->email }}</span>
                                    </td>
                                    <td><span class="badge bg-info-subtle text-info">{{ __('Employee / Staff') }}</span></td>
                                    <td>{{ $e->phone ?: '—' }}</td>
                                    <td><span class="badge bg-success-subtle text-success">{{ __('Active') }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light p-3">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- 8. Reports Modal --}}
<div class="modal fade" id="modalReports" tabindex="-1" aria-labelledby="modalReportsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0a1d3d 0%, #162f5d 100%);">
                <h5 class="modal-title fw-bold" id="modalReportsLabel">
                    <i class="bi bi-file-earmark-bar-graph text-emerald me-2"></i> {{ __('Financial Reports & Statement Center') }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <a href="{{ route('reports.collection') }}" class="p-3 border rounded-3 d-flex align-items-center gap-3 text-decoration-none text-dark bg-light hover-shadow">
                            <span class="kpi-icon-wrap is-emerald"><i class="bi bi-cash-stack"></i></span>
                            <div>
                                <strong class="d-block">{{ __('Collection Report') }}</strong>
                                <span class="text-muted small">{{ __('Itemized fee receipts breakdown') }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="{{ route('reports.statement') }}" class="p-3 border rounded-3 d-flex align-items-center gap-3 text-decoration-none text-dark bg-light hover-shadow">
                            <span class="kpi-icon-wrap is-navy"><i class="bi bi-file-text"></i></span>
                            <div>
                                <strong class="d-block">{{ __('Account Statement') }}</strong>
                                <span class="text-muted small">{{ __('Combined ledger statement') }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="{{ route('reports.students') }}" class="p-3 border rounded-3 d-flex align-items-center gap-3 text-decoration-none text-dark bg-light hover-shadow">
                            <span class="kpi-icon-wrap is-purple"><i class="bi bi-mortarboard"></i></span>
                            <div>
                                <strong class="d-block">{{ __('Student Collection') }}</strong>
                                <span class="text-muted small">{{ __('Per-student payment ledger') }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="{{ route('reports.classes') }}" class="p-3 border rounded-3 d-flex align-items-center gap-3 text-decoration-none text-dark bg-light hover-shadow">
                            <span class="kpi-icon-wrap is-amber"><i class="bi bi-layers"></i></span>
                            <div>
                                <strong class="d-block">{{ __('Class-wise Collection') }}</strong>
                                <span class="text-muted small">{{ __('Section and class performance') }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="{{ route('reports.expenses') }}" class="p-3 border rounded-3 d-flex align-items-center gap-3 text-decoration-none text-dark bg-light hover-shadow">
                            <span class="kpi-icon-wrap is-rose"><i class="bi bi-bag-dash"></i></span>
                            <div>
                                <strong class="d-block">{{ __('Expense Report') }}</strong>
                                <span class="text-muted small">{{ __('Categorized expenditures digest') }}</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="{{ route('reports.methods') }}" class="p-3 border rounded-3 d-flex align-items-center gap-3 text-decoration-none text-dark bg-light hover-shadow">
                            <span class="kpi-icon-wrap is-emerald"><i class="bi bi-credit-card-2-front"></i></span>
                            <div>
                                <strong class="d-block">{{ __('Payment Methods') }}</strong>
                                <span class="text-muted small">{{ __('Cash, bKash, and Bank shares') }}</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light p-3">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- 9. My Profile Modal --}}
<div class="modal fade" id="modalMyProfile" tabindex="-1" aria-labelledby="modalMyProfileLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0a1d3d 0%, #162f5d 100%);">
                <h5 class="modal-title fw-bold" id="modalMyProfileLabel">
                    <i class="bi bi-person-circle text-emerald me-2"></i> {{ __('Accountant Profile') }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="accountant-avatar mx-auto mb-3" style="width: 72px; height: 72px; font-size: 1.8rem;">
                    @if ($actor->avatarUrl())
                        <img src="{{ $actor->avatarUrl() }}" alt="">
                    @else
                        {{ $actor->initials() }}
                    @endif
                </div>
                <h4 class="fw-bold mb-1">{{ $actor->name }}</h4>
                <p class="text-muted small mb-3">{{ $actor->email }}</p>
                <div class="d-inline-flex align-items-center gap-2 mb-4">
                    <span class="badge-emerald"><i class="bi bi-shield-check"></i> {{ __('Authorized Accountant') }}</span>
                    <span class="badge bg-light text-dark border">{{ $school?->name ?? 'Dream Roots Millennium School' }}</span>
                </div>

                {{-- Security Update Notice --}}
                <div class="p-3 rounded-3 bg-light border text-start">
                    <h6 class="fw-bold mb-2">{{ __('Security & Credentials') }}</h6>
                    <p class="text-muted small mb-3">{{ __('Your account is secured with role-based finance desk credentials.') }}</p>
                    <a href="{{ route('profile.edit') }}" class="btn btn-outline-primary btn-sm rounded-pill w-100">
                        <i class="bi bi-key me-1"></i> {{ __('Update Password & Profile Settings') }}
                    </a>
                </div>
            </div>
            <div class="modal-footer bg-light p-3">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('assets/vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
    (function () {
        // Render Monthly Income vs Expense Chart
        const canvas = document.getElementById('monthlyFinancialChart');
        if (canvas && window.Chart) {
            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: @json($chart['labels']),
                    datasets: [
                        {
                            label: @json(__('Income / Fees')),
                            data: @json($chart['collection']),
                            backgroundColor: '#10b981',
                            borderRadius: 6,
                            maxBarThickness: 20
                        },
                        {
                            label: @json(__('Expenses')),
                            data: @json($chart['expense']),
                            backgroundColor: '#0a1d3d',
                            borderRadius: 6,
                            maxBarThickness: 20
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#061327',
                            padding: 12,
                            cornerRadius: 10,
                            callbacks: {
                                label: function (context) {
                                    return context.dataset.label + ': ৳' + Number(context.parsed.y).toLocaleString('en-IN');
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: { color: '#64748b', font: { size: 11 } }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            border: { display: false },
                            ticks: {
                                color: '#64748b',
                                font: { size: 11 },
                                callback: function (val) {
                                    return '৳' + Number(val).toLocaleString('en-IN');
                                }
                            }
                        }
                    }
                }
            });
        }

        // Student filter in Fee Collection Modal
        const studentSelect = document.getElementById('feeStudentSelect');
        const invoiceRows = document.querySelectorAll('.invoice-row');

        if (studentSelect && invoiceRows.length > 0) {
            studentSelect.addEventListener('change', function () {
                const selectedStudentId = this.value;
                invoiceRows.forEach(function (row) {
                    const rowStudentId = row.getAttribute('data-student-id');
                    if (!selectedStudentId || rowStudentId === selectedStudentId) {
                        row.style.display = 'flex';
                    } else {
                        row.style.display = 'none';
                        const check = row.querySelector('.invoice-check');
                        if (check) check.checked = false;
                    }
                });
            });
        }
    })();

    function openFeeModalWithInvoice(studentId, invoiceId, dueAmount) {
        // Close current modal
        const feesModal = bootstrap.Modal.getInstance(document.getElementById('modalStudentFees'));
        if (feesModal) feesModal.hide();

        // Open Fee Collection Modal
        const collectModalEl = document.getElementById('modalFeeCollection');
        const collectModal = bootstrap.Modal.getOrCreateInstance(collectModalEl);
        
        const studentSelect = document.getElementById('feeStudentSelect');
        if (studentSelect) {
            studentSelect.value = studentId;
            studentSelect.dispatchEvent(new Event('change'));
        }

        const checkbox = document.getElementById('inv_' + invoiceId);
        if (checkbox) {
            checkbox.checked = true;
        }

        collectModal.show();
    }
</script>
@endpush
