@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Payment verification'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-shield-check"></i>{{ __('Fees') }}</span>
            <h1 class="page-title">{{ __('Payment verification') }}</h1>
            <p class="page-subtitle">{{ __('Bank, card, cheque and wallet payments wait here until the money is confirmed in the account.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('fees.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Invoices') }}</a>
        </div>
    </div>

    <div class="alert alert-light border d-flex align-items-start gap-2 mb-3" role="status">
        <i class="bi bi-info-circle mt-1"></i>
        <span style="font-size:.82rem;">
            {{ __('No payment gateway is connected. Verify each entry against your own bank or wallet statement before confirming — verification posts the receipt, updates the invoice and writes the cashbook entry.') }}
        </span>
    </div>

    <section class="stats-grid mb-3">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Waiting for verification') }}</p>
                    <p class="stat-value">{{ number_format($summary['count']) }}</p>
                </div>
                <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
            </div>
            <div class="stat-foot"><span>{{ Money::format($summary['total']) }}</span></div>
        </article>
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('fees.verifications') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="verification-search">{{ __('Search pending payments') }}</label>
                    <input id="verification-search" type="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Student, ID or transaction reference') }}">
                </div>
                <select name="method" class="form-select" data-auto-submit aria-label="{{ __('Method') }}">
                    <option value="">{{ __('All methods') }}</option>
                    @foreach ($methods as $method)
                        <option value="{{ $method }}" @selected($filters['method'] === $method)>{{ __(ucfirst($method)) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('fees.verifications') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count payment|:count payments', $payments->total(), ['count' => $payments->total()]) }}</span>
        </div>

        @if ($payments->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-shield-check"></i></div>
                <h3>{{ __('Nothing to verify') }}</h3>
                <p>{{ array_filter($filters) ? __('No pending payments match these filters.') : __('Cash collections post instantly; only bank, wallet, card and cheque payments are held here.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Student') }}</th>
                            <th scope="col">{{ __('Invoice') }}</th>
                            <th scope="col">{{ __('Method / reference') }}</th>
                            <th scope="col">{{ __('Paid at') }}</th>
                            <th scope="col" class="text-end">{{ __('Amount') }}</th>
                            <th scope="col">{{ __('Held by') }}</th>
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            @php($invoice = $payment->allocations->first()?->invoice)
                            <tr>
                                <td>
                                    {{ $payment->student?->full_name ?? '—' }}
                                    <div class="text-muted-2" style="font-size:.74rem;">{{ $payment->student?->student_id }}</div>
                                </td>
                                <td>
                                    @if ($invoice)
                                        <a href="{{ route('fees.show', $invoice) }}" class="text-decoration-none fw-600">{{ $invoice->invoice_no }}</a>
                                        <div class="text-muted-2" style="font-size:.74rem;">{{ __('Balance :balance', ['balance' => Money::format((float) $invoice->due_total)]) }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    {{ __(ucfirst($payment->method)) }}
                                    <div class="text-muted-2" style="font-size:.74rem;">{{ $payment->transaction_ref ?: __('No reference') }}</div>
                                </td>
                                <td>{{ $payment->paid_at?->format('d M Y H:i') }}</td>
                                <td class="text-end fw-600">{{ Money::format((float) $payment->amount) }}</td>
                                <td>{{ $payment->receiver?->name ?? '—' }}</td>
                                <td class="text-end">
                                    <div class="row-actions">
                                        <form method="POST" action="{{ route('fees.payments.verify', $payment) }}" class="d-inline"
                                              onsubmit="return confirm(@json(__('Confirm this payment and issue the receipt?')))">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Verify') }}</button>
                                        </form>
                                        <button type="button" class="icon-action text-danger" data-bs-toggle="modal" data-bs-target="#paymentReject{{ $payment->id }}"
                                                title="{{ __('Reject') }}" aria-label="{{ __('Reject payment :id', ['id' => $payment->id]) }}">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </div>
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

    @foreach ($payments as $payment)
        <div class="modal fade" id="paymentReject{{ $payment->id }}" tabindex="-1" aria-labelledby="paymentRejectLabel{{ $payment->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('fees.payments.reject-verification', $payment) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="paymentRejectLabel{{ $payment->id }}">{{ __('Reject payment') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">{{ $payment->student?->full_name }} · {{ Money::format((float) $payment->amount) }}</p>
                        <p class="text-muted-2" style="font-size:.8rem;">{{ __('The invoice balance stays unchanged and no receipt is issued.') }}</p>
                        <label class="form-label" for="payment-reason-{{ $payment->id }}">{{ __('Reason') }}</label>
                        <textarea id="payment-reason-{{ $payment->id }}" name="reason" class="form-control" rows="3" maxlength="500" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('Reject payment') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
