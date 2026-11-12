@extends('layouts.student')

@section('title', __('Fees'))

@section('content')
    @php
        $statusClasses = [
            'issued' => 'is-warning',
            'partial' => 'is-info',
            'paid' => 'is-success',
            'settled' => 'is-brand',
            'cancelled' => 'is-danger',
        ];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-receipt"></i>{{ __('Fees') }}</span>
            <h1 class="page-title">{{ __('My fees') }}</h1>
            <p class="page-subtitle">{{ __('Invoices, balances and receipts for :name.', ['name' => $student->full_name]) }}</p>
        </div>
    </div>

    <section class="stats-grid mb-3" aria-label="{{ __('Fee summary') }}">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Total billed') }}</p>
                    <p class="stat-value">৳ {{ number_format($summary['total'], 2) }}</p>
                </div>
                <span class="stat-icon" aria-hidden="true"><i class="bi bi-journal-text"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Excludes cancelled invoices') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Paid') }}</p>
                    <p class="stat-value">৳ {{ number_format($summary['paid'], 2) }}</p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Posted against invoices') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Outstanding') }}</p>
                    <p class="stat-value">৳ {{ number_format($summary['due'], 2) }}</p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Across all invoices') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Overdue invoices') }}</p>
                    <p class="stat-value">{{ number_format($summary['overdue']) }}</p>
                </div>
                <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-exclamation-circle"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Past their due date') }}</span></div>
        </article>
    </section>

    <section class="ui-card table-card mb-3">
        <div class="table-toolbar">
            <div>
                <h2 class="card-title mb-1">{{ __('Invoices') }}</h2>
                <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Open an invoice to see its items and payments.') }}</p>
            </div>
            <form method="GET" action="{{ route('student.fees.index') }}" class="filter-form">
                <label class="visually-hidden" for="status">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Models\FeeInvoice::STATUSES as $value)
                        <option value="{{ $value }}" @selected($status === $value)>{{ __(ucfirst($value)) }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if ($invoices->isEmpty())
            <div class="empty-state py-5">
                <div class="empty-icon"><i class="bi bi-receipt"></i></div>
                <h3>{{ __('No invoices found') }}</h3>
                <p>{{ $status !== '' ? __('No invoices match this status.') : __('The school office has not issued any fee invoices yet.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Invoice') }}</th>
                            <th>{{ __('Session / period') }}</th>
                            <th>{{ __('Issued') }}</th>
                            <th>{{ __('Due date') }}</th>
                            <th class="text-end">{{ __('Total') }}</th>
                            <th class="text-end">{{ __('Due') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td class="fw-600">{{ $invoice->invoice_no }}</td>
                                <td>
                                    {{ $invoice->academicSession?->name ?? '—' }}
                                    @if ($invoice->billing_period)
                                        <div class="text-muted-2" style="font-size:.76rem;">{{ $invoice->billing_period }}</div>
                                    @endif
                                </td>
                                <td>{{ $invoice->issue_date?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td>
                                <td class="text-end">৳ {{ number_format((float) $invoice->total, 2) }}</td>
                                <td class="text-end fw-600">৳ {{ number_format((float) $invoice->due_total, 2) }}</td>
                                <td>
                                    <span class="badge-soft {{ $statusClasses[$invoice->status] ?? '' }}">
                                        {{ __(ucfirst($invoice->status)) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('student.fees.show', $invoice->id) }}" class="btn btn-sm btn-light-soft">
                                        <i class="bi bi-eye"></i>{{ __('View') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                {{ $invoices->links() }}
            </div>
        @endif
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <div>
                <h2 class="card-title mb-1">{{ __('Payment history') }}</h2>
                <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Receipts are immutable snapshots issued at the time of payment.') }}</p>
            </div>
        </div>

        @if ($payments->isEmpty())
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-wallet2"></i></div>
                <h3>{{ __('No payments yet') }}</h3>
                <p>{{ __('Payments recorded by the school office will appear here with their receipts.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Receipt') }}</th>
                            <th>{{ __('Paid on') }}</th>
                            <th>{{ __('Method') }}</th>
                            <th>{{ __('Invoice') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="fw-600">{{ $payment->receipt?->receipt_no ?? '—' }}</td>
                                <td>{{ $payment->paid_at?->format('d M Y') }}</td>
                                <td>{{ __(ucfirst($payment->method)) }}</td>
                                <td>
                                    @foreach ($payment->allocations as $allocation)
                                        <div style="font-size:.8rem;">{{ $allocation->invoice?->invoice_no ?? '—' }}</div>
                                    @endforeach
                                </td>
                                <td class="text-end">৳ {{ number_format((float) $payment->amount, 2) }}</td>
                                <td class="text-end">
                                    @if ($payment->receipt)
                                        <a href="{{ route('student.fees.receipt', $payment->id) }}" class="btn btn-sm btn-light-soft">
                                            <i class="bi bi-receipt-cutoff"></i>{{ __('Receipt') }}
                                        </a>
                                    @else
                                        <span class="text-muted-2">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <p class="text-muted-2 mt-3 mb-0" style="font-size:.8rem;">
        <i class="bi bi-info-circle"></i>
        {{ __('Online payment is not enabled. Payments are recorded by the school office and each one issues a receipt.') }}
    </p>
@endsection
