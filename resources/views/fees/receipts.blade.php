@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Payment receipts'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-receipt"></i>{{ __('Fees') }}</span>
            <h1 class="page-title">{{ __('Payment receipts') }}</h1>
            <p class="page-subtitle">{{ __('Issued receipts are frozen snapshots. Reversing a payment keeps the receipt for the audit trail.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('fees.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Invoices') }}</a>
        </div>
    </div>

    <section class="stats-grid mb-3">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Collected in view') }}</p>
                    <p class="stat-value">{{ Money::format($summary['total']) }}</p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-cash-stack"></i></span>
            </div>
            <div class="stat-foot"><span>{{ trans_choice(':count receipt|:count receipts', $summary['count'], ['count' => $summary['count']]) }}</span></div>
        </article>
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('fees.receipts') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="receipt-search">{{ __('Search receipts') }}</label>
                    <input id="receipt-search" type="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Receipt no, student or transaction reference') }}">
                </div>
                <select name="method" class="form-select" data-auto-submit aria-label="{{ __('Method') }}">
                    <option value="">{{ __('All methods') }}</option>
                    @foreach ($methods as $method)
                        <option value="{{ $method }}" @selected($filters['method'] === $method)>{{ __(ucfirst($method)) }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" class="form-control" value="{{ $filters['from'] }}" aria-label="{{ __('From date') }}">
                <input type="date" name="to" class="form-control" value="{{ $filters['to'] }}" aria-label="{{ __('To date') }}">
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('fees.receipts') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count receipt|:count receipts', $payments->total(), ['count' => $payments->total()]) }}</span>
        </div>

        @if ($payments->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-receipt"></i></div>
                <h3>{{ __('No receipts issued') }}</h3>
                <p>{{ array_filter($filters) ? __('No receipts match these filters.') : __('A receipt is created automatically when a payment is posted.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Receipt') }}</th>
                            <th scope="col">{{ __('Student') }}</th>
                            <th scope="col">{{ __('Invoice') }}</th>
                            <th scope="col">{{ __('Method') }}</th>
                            <th scope="col" class="text-end">{{ __('Amount') }}</th>
                            <th scope="col">{{ __('Received by') }}</th>
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td>
                                    <span class="fw-600">{{ $payment->receipt?->receipt_no }}</span>
                                    <div class="text-muted-2" style="font-size:.74rem;">{{ $payment->paid_at?->format('d M Y H:i') }}</div>
                                </td>
                                <td>
                                    {{ $payment->student?->full_name ?? '—' }}
                                    <div class="text-muted-2" style="font-size:.74rem;">{{ $payment->student?->student_id }}</div>
                                </td>
                                <td>{{ $payment->allocations->first()?->invoice?->invoice_no ?? '—' }}</td>
                                <td>
                                    {{ __(ucfirst($payment->method)) }}
                                    @if ($payment->transaction_ref)
                                        <div class="text-muted-2" style="font-size:.74rem;">{{ $payment->transaction_ref }}</div>
                                    @endif
                                </td>
                                <td class="text-end fw-600">{{ Money::format((float) $payment->amount) }}</td>
                                <td>{{ $payment->receiver?->name ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('fees.receipt', $payment) }}" class="btn btn-sm btn-light-soft">{{ __('Receipt') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $payments->firstItem(), 'to' => $payments->lastItem(), 'total' => $payments->total()]) }}</span>
                {{ $payments->links() }}
            </div>
        @endif
    </section>
@endsection
