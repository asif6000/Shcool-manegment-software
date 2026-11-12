@extends('layouts.app')

@section('title', __('Invoice :no', ['no' => $receipt->receipt_no]))

@push('styles')
    <style>
        /* The paper choice is a print frame only; the receipt itself never changes. */
        @page {
            size: {{ $paperSize }};
            margin: {{ $paperMargin }};
        }
    </style>
@endpush

@section('content')
    @php
        $snapshot = $receipt->snapshot;
        $schoolInfo = $snapshot['school'] ?? [];
        $studentInfo = $snapshot['student'] ?? [];
        $invoiceInfo = $snapshot['invoice'] ?? [];
        $paymentInfo = $snapshot['payment'] ?? [];
        $invoice = $payment->allocations->first()?->invoice;
        $lines = $snapshot['lines'] ?? [];
        $schoolName = $schoolInfo['name'] ?? $school->name;
        $logo = $school->logoUrl();
        $studentSearch = $studentInfo['student_id'] ?? $payment->student?->student_id;
    @endphp

    <div class="page-header no-print">
        <div>
            <span class="page-eyebrow"><i class="bi bi-receipt-cutoff"></i>{{ __('Invoice preview') }}</span>
            <h1 class="page-title">{{ $receipt->receipt_no }}</h1>
            <p class="page-subtitle">
                {{ __('Issued :date', ['date' => $receipt->issued_at?->format('d M Y, h:i A') ?? '—']) }}
                · {{ __('paper') }} {{ $paper === '80mm' ? '80mm' : \Illuminate\Support\Str::upper($paper) }}
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('fees.receipts') }}" class="btn btn-light-soft">
                <i class="bi bi-search"></i>{{ __('Search invoices') }}
            </a>
            @if ($studentSearch)
                <a href="{{ route('fees.receipts', ['search' => $studentSearch]) }}" class="btn btn-light-soft">
                    <i class="bi bi-clock-history"></i>{{ __('Payment history') }}
                </a>
            @endif

            <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Print paper size') }}">
                @foreach (['a4' => 'A4', 'a5' => 'A5', '80mm' => '80mm'] as $value => $label)
                    <a href="{{ route('fees.receipt', [$payment, 'paper' => $value]) }}"
                       class="btn {{ $paper === $value ? 'btn-primary' : 'btn-light-soft' }}"
                       aria-pressed="{{ $paper === $value ? 'true' : 'false' }}">{{ $label }}</a>
                @endforeach
            </div>

            <a href="{{ route('fees.receipt', [$payment, 'paper' => $paper, 'print' => 1]) }}" class="btn btn-light-soft">
                <i class="bi bi-arrow-clockwise"></i>{{ __('Reprint') }}
            </a>
            <button type="button" class="btn btn-light-soft" onclick="window.print()">
                <i class="bi bi-printer"></i>{{ __('Print') }}
            </button>
            <button type="button" class="btn btn-primary" onclick="window.print()"
                    title="{{ __('In the print dialog choose “Save as PDF” as the destination.') }}">
                <i class="bi bi-file-earmark-pdf"></i>{{ __('Download PDF') }}
            </button>
        </div>
    </div>

    @if ($payment->status === 'reversed')
        <div class="alert alert-warning no-print">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>{{ __('This payment has been reversed.') }}</strong>
                <div style="font-size:.8rem;">
                    {{ $payment->reversal_reason }}
                    @if ($payment->reversed_at)
                        · {{ $payment->reversed_at->format('d M Y, h:i A') }}
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="receipt-sheet receipt-paper-{{ $paperClass }}">
        <div class="receipt-head">
            <div class="d-flex align-items-center gap-2">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $schoolName }}" class="receipt-logo">
                @else
                    <span class="portal-brand-mark" style="width:42px;height:42px;border-radius:12px;font-size:.85rem;">
                        {{ $school->initials() }}
                    </span>
                @endif
                <div>
                    <div class="fw-700">{{ $schoolName }}</div>
                    <div class="text-muted-2" style="font-size:.76rem;">{{ $schoolInfo['address'] ?? $school->address }}</div>
                    <div class="text-muted-2" style="font-size:.76rem;">
                        @if (! empty($schoolInfo['phone'] ?? $school->phone))
                            {{ __('Phone') }}: {{ $schoolInfo['phone'] ?? $school->phone }}
                        @endif
                        @if (! empty($school->email))
                            · {{ $school->email }}
                        @endif
                        @if (! empty($schoolInfo['eiin'] ?? $school->eiin))
                            · EIIN {{ $schoolInfo['eiin'] ?? $school->eiin }}
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-lg-end">
                <span class="badge-soft is-success">{{ __('Official payment receipt') }}</span>
                <div class="text-muted-2 mt-2" style="font-size:.76rem;">{{ __('Receipt no') }}</div>
                <div class="fw-700">{{ $receipt->receipt_no }}</div>
                <div class="text-muted-2" style="font-size:.72rem;">
                    {{ $receipt->issued_at?->format('d M Y, h:i A') }}
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-sm-7">
                <div class="text-muted-2" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;">{{ __('Received from') }}</div>
                <div class="fw-700 mt-1">{{ $studentInfo['name'] ?? $payment->student?->full_name }}</div>
                <div class="text-muted-2" style="font-size:.8rem;">
                    {{ __('Student ID') }}: {{ $studentInfo['student_id'] ?? $payment->student?->student_id }}
                    @if (! empty($studentInfo['class']))
                        · {{ $studentInfo['class'] }}
                    @endif
                    @if (! empty($studentInfo['section']))
                        ({{ __('Section') }} {{ $studentInfo['section'] }})
                    @endif
                    @if (! empty($studentInfo['roll']))
                        · {{ __('Roll') }} {{ $studentInfo['roll'] }}
                    @endif
                </div>
            </div>
            <div class="col-sm-5 text-sm-end">
                <div class="text-muted-2" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;">{{ __('Amount received') }}</div>
                <div class="receipt-amount mt-1">৳ {{ number_format((float) ($paymentInfo['amount'] ?? $payment->amount), 2) }}</div>
            </div>
        </div>

        @if (count($lines) > 1)
            <table class="receipt-table w-100 mt-4">
                <thead>
                    <tr>
                        <th>{{ __('Invoice') }}</th>
                        <th>{{ __('Period') }}</th>
                        <th class="text-end">{{ __('Received') }}</th>
                        <th class="text-end">{{ __('Balance after') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td>{{ $line['invoice']['invoice_no'] }}</td>
                            <td>{{ $line['invoice']['billing_period'] }}</td>
                            <td class="text-end">৳ {{ number_format((float) $line['received'], 2) }}</td>
                            <td class="text-end">৳ {{ number_format((float) $line['invoice']['due_after'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <table class="receipt-table w-100 mt-4">
                <tbody>
                    <tr>
                        <th>{{ __('Invoice') }}</th>
                        <td class="text-end">{{ $invoiceInfo['invoice_no'] ?? $invoice?->invoice_no ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('Billing period') }}</th>
                        <td class="text-end">{{ $invoiceInfo['billing_period'] ?? $invoice?->billing_period ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('Invoice total') }}</th>
                        <td class="text-end">৳ {{ number_format((float) ($invoiceInfo['total'] ?? $invoice?->total ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('Discounts') }}</th>
                        <td class="text-end">৳ {{ number_format((float) ($invoiceInfo['discount_total'] ?? $invoice?->discount_total ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('Paid after this receipt') }}</th>
                        <td class="text-end">৳ {{ number_format((float) ($invoiceInfo['paid_after'] ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('Balance after this receipt') }}</th>
                        <td class="text-end fw-700">৳ {{ number_format((float) ($invoiceInfo['due_after'] ?? 0), 2) }}</td>
                    </tr>
                </tbody>
            </table>
        @endif

        <table class="receipt-table w-100 mt-3">
            <tbody>
                <tr>
                    <th>{{ __('Payment method') }}</th>
                    <td class="text-end">{{ __(ucfirst($paymentInfo['method'] ?? $payment->method)) }}</td>
                </tr>
                @if (! empty($paymentInfo['reference']))
                    <tr>
                        <th>{{ __('Transaction ID') }}</th>
                        <td class="text-end">{{ $paymentInfo['reference'] }}</td>
                    </tr>
                @endif
                <tr>
                    <th>{{ __('Paid on') }}</th>
                    <td class="text-end">{{ $paymentInfo['paid_at'] ?? $payment->paid_at?->format('Y-m-d') }}</td>
                </tr>
                <tr>
                    <th>{{ __('Received by') }}</th>
                    <td class="text-end">{{ $paymentInfo['received_by'] ?? $payment->receiver?->name ?? '—' }}</td>
                </tr>
                <tr>
                    <th>{{ __('Status') }}</th>
                    <td class="text-end">
                        <span class="badge-soft {{ $payment->status === 'reversed' ? 'is-danger' : 'is-success' }}">
                            {{ __(ucfirst($payment->status)) }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="text-muted-2 mt-4 mb-0" style="font-size:.76rem;">
            {{ __('This receipt is an immutable snapshot issued at the time of payment. Reprinting it never records another payment.') }}
        </p>
        <p class="text-muted-2 mt-1 mb-0" style="font-size:.72rem;">
            {{ __('Issued') }}: {{ $receipt->issued_at?->format('d M Y, h:i A') }}
            · {{ __('Snapshot') }}: {{ \Illuminate\Support\Str::substr($receipt->snapshot_hash, 0, 16) }}
        </p>
    </div>
@endsection

@if ($printNow)
    @push('scripts')
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    @endpush
@endif
