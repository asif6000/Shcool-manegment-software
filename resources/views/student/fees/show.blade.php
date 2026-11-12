@extends('layouts.student')

@section('title', $invoice->invoice_no)

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
            <span class="page-eyebrow"><i class="bi bi-receipt"></i>{{ __('Fee invoice') }}</span>
            <h1 class="page-title mb-1">{{ $invoice->invoice_no }}</h1>
            <p class="page-subtitle mb-0">
                {{ $invoice->academicSession?->name ?? '—' }}
                @if ($invoice->billing_period)
                    · {{ $invoice->billing_period }}
                @endif
                <span class="badge-soft {{ $statusClasses[$invoice->status] ?? '' }} ms-1">{{ __(ucfirst($invoice->status)) }}</span>
            </p>
        </div>
        <div class="page-actions no-print">
            <a href="{{ route('student.fees.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Fees') }}</a>
            <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i>{{ __('Print') }}</button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <section class="ui-card table-card mb-3">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Invoice items') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Amounts recorded by the school office.') }}</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Description') }}</th>
                                <th class="text-end">{{ __('Qty') }}</th>
                                <th class="text-end">{{ __('Unit') }}</th>
                                <th class="text-end">{{ __('Line total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td>
                                        {{ $item->description }}
                                        @if ($item->feeType)
                                            <div class="text-muted-2" style="font-size:.76rem;">{{ $item->feeType->name }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                                    <td class="text-end">৳ {{ number_format((float) $item->unit_amount, 2) }}</td>
                                    <td class="text-end">৳ {{ number_format((float) $item->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-lg">
                    <dl class="detail-list">
                        <div><dt>{{ __('Subtotal') }}</dt><dd>৳ {{ number_format((float) $invoice->subtotal, 2) }}</dd></div>
                        @foreach ($invoice->discounts as $discount)
                            <div>
                                <dt>{{ __('Discount') }} · {{ $discount->name }}</dt>
                                <dd class="text-success">− ৳ {{ number_format((float) $discount->amount, 2) }}</dd>
                            </div>
                        @endforeach
                        <div><dt>{{ __('Total') }}</dt><dd class="fw-700">৳ {{ number_format((float) $invoice->total, 2) }}</dd></div>
                        <div><dt>{{ __('Paid') }}</dt><dd>৳ {{ number_format((float) $invoice->paid_total, 2) }}</dd></div>
                        <div><dt>{{ __('Balance due') }}</dt><dd class="fw-700">৳ {{ number_format((float) $invoice->due_total, 2) }}</dd></div>
                    </dl>
                </div>
            </section>

            <section class="ui-card table-card">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Payments on this invoice') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Every payment has a receipt.') }}</p>
                    </div>
                </div>
                @if ($invoice->allocations->isEmpty())
                    <div class="empty-state py-4">
                        <div class="empty-icon"><i class="bi bi-wallet2"></i></div>
                        <h3>{{ __('No payments recorded') }}</h3>
                        <p>{{ __('Pay at the school office; the receipt will appear here.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Receipt') }}</th>
                                    <th>{{ __('Paid on') }}</th>
                                    <th>{{ __('Method') }}</th>
                                    <th class="text-end">{{ __('Applied') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice->allocations as $allocation)
                                    <tr>
                                        <td>
                                            @if ($allocation->payment?->receipt)
                                                <a href="{{ route('student.fees.receipt', $allocation->payment->id) }}" class="fw-600">
                                                    {{ $allocation->payment->receipt->receipt_no }}
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $allocation->payment?->paid_at?->format('d M Y') ?? '—' }}</td>
                                        <td>{{ $allocation->payment ? __(ucfirst($allocation->payment->method)) : '—' }}</td>
                                        <td class="text-end">৳ {{ number_format((float) $allocation->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <div class="col-lg-4">
            <section class="ui-card p-lg mb-3">
                <h2 class="card-title">{{ __('Invoice details') }}</h2>
                <dl class="detail-list mt-3 mb-0">
                    <div><dt>{{ __('Student') }}</dt><dd>{{ $student->full_name }}</dd></div>
                    <div><dt>{{ __('Student ID') }}</dt><dd>{{ $student->student_id }}</dd></div>
                    <div><dt>{{ __('Session') }}</dt><dd>{{ $invoice->academicSession?->name ?? '—' }}</dd></div>
                    <div><dt>{{ __('Billing period') }}</dt><dd>{{ $invoice->billing_period ?? '—' }}</dd></div>
                    <div><dt>{{ __('Issued') }}</dt><dd>{{ $invoice->issue_date?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>{{ __('Due date') }}</dt><dd>{{ $invoice->due_date?->format('d M Y') ?? '—' }}</dd></div>
                </dl>
            </section>

            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('Balance') }}</h2>
                <div class="d-flex align-items-baseline gap-2 mt-2">
                    <strong style="font-size:1.9rem;letter-spacing:-.02em;">৳ {{ number_format((float) $invoice->due_total, 2) }}</strong>
                    <span class="text-muted-2" style="font-size:.78rem;">{{ __('remaining') }}</span>
                </div>
                <p class="text-muted-2 mt-3 mb-0" style="font-size:.78rem;">
                    {{ __('Payments are recorded by the school office. Ask for a receipt for every payment.') }}
                </p>
            </section>
        </div>
    </div>
@endsection
