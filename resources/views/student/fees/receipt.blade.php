@extends('layouts.student')

@section('title', __('Receipt :no', ['no' => $receipt->receipt_no]))

@section('content')
    @php
        $snapshot = $receipt->snapshot;
        $school = $snapshot['school'] ?? [];
        $studentInfo = $snapshot['student'] ?? [];
        $invoiceInfo = $snapshot['invoice'] ?? [];
        $paymentInfo = $snapshot['payment'] ?? [];
    @endphp

    <div class="page-header no-print">
        <div>
            <span class="page-eyebrow"><i class="bi bi-receipt-cutoff"></i>{{ __('Payment receipt') }}</span>
            <h1 class="page-title">{{ $receipt->receipt_no }}</h1>
            <p class="page-subtitle">{{ __('Issued :date', ['date' => $receipt->issued_at?->format('d M Y, h:i A') ?? '—']) }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('student.fees.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Fees') }}</a>
            <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i>{{ __('Print') }}</button>
        </div>
    </div>

    <div class="receipt-sheet">
        <div class="receipt-head">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="portal-brand-mark" style="width:40px;height:40px;border-radius:12px;font-size:.85rem;">
                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($school['name'] ?? 'EN', 0, 2)) }}
                    </span>
                    <div>
                        <div class="fw-700">{{ $school['name'] ?? config('app.name') }}</div>
                        <div class="text-muted-2" style="font-size:.76rem;">{{ $school['address'] ?? '' }}</div>
                    </div>
                </div>
                <div class="text-muted-2" style="font-size:.76rem;">
                    @if (! empty($school['phone']))
                        {{ __('Phone') }}: {{ $school['phone'] }}
                    @endif
                    @if (! empty($school['eiin']))
                        · EIIN {{ $school['eiin'] }}
                    @endif
                </div>
            </div>
            <div class="text-lg-end">
                <span class="badge-soft is-success">{{ __('Official receipt') }}</span>
                <div class="text-muted-2 mt-2" style="font-size:.76rem;">{{ __('Receipt no') }}</div>
                <div class="fw-700">{{ $receipt->receipt_no }}</div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-sm-7">
                <div class="text-muted-2" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;">{{ __('Received from') }}</div>
                <div class="fw-700 mt-1">{{ $studentInfo['name'] ?? $student->full_name }}</div>
                <div class="text-muted-2" style="font-size:.8rem;">
                    {{ __('Student ID') }}: {{ $studentInfo['student_id'] ?? $student->student_id }}
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

        <table class="receipt-table w-100 mt-4">
            <tbody>
                <tr>
                    <th>{{ __('Invoice') }}</th>
                    <td class="text-end">{{ $invoiceInfo['invoice_no'] ?? '—' }}</td>
                </tr>
                <tr>
                    <th>{{ __('Billing period') }}</th>
                    <td class="text-end">{{ $invoiceInfo['billing_period'] ?? '—' }}</td>
                </tr>
                <tr>
                    <th>{{ __('Invoice total') }}</th>
                    <td class="text-end">৳ {{ number_format((float) ($invoiceInfo['total'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <th>{{ __('Discounts') }}</th>
                    <td class="text-end">৳ {{ number_format((float) ($invoiceInfo['discount_total'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <th>{{ __('Paid after this receipt') }}</th>
                    <td class="text-end">৳ {{ number_format((float) ($invoiceInfo['paid_after'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <th>{{ __('Balance after this receipt') }}</th>
                    <td class="text-end fw-700">৳ {{ number_format((float) ($invoiceInfo['due_after'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <th>{{ __('Payment method') }}</th>
                    <td class="text-end">{{ __(ucfirst($paymentInfo['method'] ?? $payment->method)) }}</td>
                </tr>
                @if (! empty($paymentInfo['reference']))
                    <tr>
                        <th>{{ __('Reference') }}</th>
                        <td class="text-end">{{ $paymentInfo['reference'] }}</td>
                    </tr>
                @endif
                <tr>
                    <th>{{ __('Paid on') }}</th>
                    <td class="text-end">{{ $paymentInfo['paid_at'] ?? $payment->paid_at?->format('Y-m-d') }}</td>
                </tr>
                <tr>
                    <th>{{ __('Received by') }}</th>
                    <td class="text-end">{{ $paymentInfo['received_by'] ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        <p class="text-muted-2 mt-4 mb-0" style="font-size:.76rem;">
            {{ __('This receipt is an immutable snapshot issued at the time of payment. Keep it for your records.') }}
        </p>
        <p class="text-muted-2 mt-1 mb-0" style="font-size:.72rem;">
            {{ __('Issued') }}: {{ $receipt->issued_at?->format('d M Y, h:i A') }}
        </p>
    </div>
@endsection
