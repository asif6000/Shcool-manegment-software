<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $invoice->invoice_no }} · {{ __('Fee invoice') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Segoe UI", Roboto, Arial, sans-serif;
            color: #1c2430;
            background: #f3f5f8;
            padding: 24px 12px;
        }
        .sheet {
            max-width: 820px;
            margin: 0 auto;
            background: #fff;
            padding: 40px 44px;
            border-radius: 10px;
            box-shadow: 0 6px 24px rgba(15, 30, 60, .08);
        }
        .head { display: flex; justify-content: space-between; gap: 16px; border-bottom: 3px solid #1c2430; padding-bottom: 16px; }
        .school-name { font-size: 1.35rem; font-weight: 700; }
        .school-meta { font-size: .8rem; color: #5b6676; margin-top: 4px; line-height: 1.5; }
        .doc-label { font-size: .72rem; letter-spacing: .18em; color: #5b6676; text-transform: uppercase; text-align: right; }
        .doc-no { font-size: 1.25rem; font-weight: 700; text-align: right; }
        .status { display: inline-block; margin-top: 6px; padding: 3px 12px; border-radius: 999px; font-size: .72rem; font-weight: 600; border: 1px solid; }
        .status-issued { color: #8a5a00; background: #fff6df; border-color: #eecd8a; }
        .status-partial { color: #0b5f8e; background: #e5f4fd; border-color: #9fd5ee; }
        .status-paid, .status-settled { color: #17663c; background: #e6f6ec; border-color: #9bd8b4; }
        .status-cancelled { color: #8e1c2b; background: #fde9ec; border-color: #efb0bb; }
        .gate { display: flex; gap: 32px; margin: 22px 0; }
        .gate div { flex: 1; }
        .gate h3 { font-size: .72rem; text-transform: uppercase; letter-spacing: .12em; color: #5b6676; margin-bottom: 6px; }
        .gate p { font-size: .92rem; line-height: 1.55; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 9px 10px; font-size: .86rem; text-align: left; }
        thead th { border-bottom: 2px solid #1c2430; font-size: .72rem; text-transform: uppercase; letter-spacing: .1em; color: #5b6676; }
        tbody tr { border-bottom: 1px solid #e3e7ee; }
        td.amount, th.amount { text-align: right; }
        tfoot td { padding: 7px 10px; font-size: .86rem; }
        tfoot tr.total td { border-top: 2px solid #1c2430; font-weight: 700; font-size: .95rem; }
        .paid { color: #17663c; }
        .payments { margin-top: 26px; }
        .payments h3 { font-size: .82rem; margin-bottom: 8px; }
        .note { margin-top: 26px; padding: 12px 14px; background: #f6f8fb; border-radius: 8px; font-size: .78rem; color: #5b6676; line-height: 1.55; }
        .sign { display: flex; justify-content: space-between; margin-top: 48px; }
        .sign div { width: 210px; text-align: center; font-size: .78rem; color: #5b6676; }
        .sign span { display: block; border-top: 1px solid #98a2b3; padding-top: 6px; margin-top: 42px; }
        .toolbar { max-width: 820px; margin: 0 auto 14px; text-align: right; }
        .toolbar button, .toolbar a {
            display: inline-block; padding: 8px 18px; margin-left: 8px; font-size: .82rem; cursor: pointer;
            border: 1px solid #c6cdd8; border-radius: 8px; background: #fff; color: #1c2430; text-decoration: none;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; border-radius: 0; max-width: none; padding: 12px 6px; }
            .toolbar { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('fees.show', $invoice) }}">{{ __('Back to the invoice') }}</a>
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
        <a href="{{ route('fees.print', ['invoice' => $invoice, 'download' => 1]) }}">{{ __('Download') }}</a>
    </div>

    <div class="sheet">
        <div class="head">
            <div>
                <div class="school-name">{{ $school?->name ?? __('Dream Roots Millennium School') }}</div>
                <div class="school-meta">
                    {{ $school?->address }}
                    @if ($school?->phone)<br>{{ __('Phone') }}: {{ $school->phone }}@endif
                </div>
            </div>
            <div>
                <div class="doc-label">{{ __('Fee invoice') }}</div>
                <div class="doc-no">{{ $invoice->invoice_no }}</div>
                <span class="status status-{{ $invoice->status }}">{{ __(ucfirst($invoice->status)) }}</span>
            </div>
        </div>

        <div class="gate">
            <div>
                <h3>{{ __('Billed to') }}</h3>
                <p>
                    <strong>{{ $invoice->student?->full_name ?? '—' }}</strong><br>
                    {{ __('Student ID') }}: {{ $invoice->student?->student_id ?? '—' }}<br>
                    {{ __('Class') }}: {{ $invoice->enrollment?->schoolClass?->name ?? '—' }}
                    @if ($invoice->enrollment?->section) — {{ $invoice->enrollment->section->name }}@endif
                    @if ($invoice->enrollment?->roll_no)<br>{{ __('Roll') }}: {{ $invoice->enrollment->roll_no }}@endif
                </p>
            </div>
            <div>
                <h3>{{ __('Details') }}</h3>
                <p>
                    {{ __('Session') }}: {{ $invoice->academicSession?->name ?? '—' }}<br>
                    {{ __('Billing period') }}: {{ $invoice->billing_period ?? __('One-off') }}<br>
                    {{ __('Issued') }}: {{ $invoice->issue_date?->format('d M Y') ?? '—' }}<br>
                    {{ __('Due date') }}: {{ $invoice->due_date?->format('d M Y') ?? '—' }}
                </p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>{{ __('Description') }}</th>
                    <th class="amount">{{ __('Qty') }}</th>
                    <th class="amount">{{ __('Unit') }}</th>
                    <th class="amount">{{ __('Line total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}@if($item->feeType)<span class="school-meta"> · {{ $item->feeType->name }}</span>@endif</td>
                        <td class="amount">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                        <td class="amount">৳ {{ number_format((float) $item->unit_amount, 2) }}</td>
                        <td class="amount">৳ {{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">{{ __('Subtotal') }}</td>
                    <td class="amount">৳ {{ number_format((float) $invoice->subtotal, 2) }}</td>
                </tr>
                @foreach ($invoice->discounts as $discount)
                    <tr>
                        <td colspan="3">{{ __('Discount') }} · {{ $discount->name }}</td>
                        <td class="amount paid">− ৳ {{ number_format((float) $discount->amount, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="3">{{ __('Total payable') }}</td>
                    <td class="amount">৳ {{ number_format((float) $invoice->total, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="3">{{ __('Amount received') }}</td>
                    <td class="amount paid">৳ {{ number_format((float) $invoice->paid_total, 2) }}</td>
                </tr>
                <tr class="total">
                    <td colspan="3">{{ __('Balance due') }}</td>
                    <td class="amount">৳ {{ number_format((float) $invoice->due_total, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <section class="payments">
            <h3>{{ __('Payment history') }}</h3>
            @forelse ($invoice->allocations as $allocation)
                @if ($allocation->payment?->status === 'posted')
                    <table>
                        <thead>
                            <tr>
                                <th>{{ __('Receipt') }}</th>
                                <th>{{ __('Paid on') }}</th>
                                <th>{{ __('Method') }}</th>
                                <th class="amount">{{ __('Applied') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ $allocation->payment->receipt?->receipt_no ?? '—' }}</td>
                                <td>{{ $allocation->payment->paid_at?->format('d M Y') ?? '—' }}</td>
                                <td>{{ __(ucfirst($allocation->payment->method)) }}@if($allocation->payment->transaction_ref) · {{ $allocation->payment->transaction_ref }}@endif</td>
                                <td class="amount">৳ {{ number_format((float) $allocation->amount, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            @empty
                <p class="school-meta">{{ __('No payments have been recorded against this invoice yet.') }}</p>
            @endforelse
        </section>

        <div class="note">
            {{ __('Receipts are issued at the office for every payment and cannot be altered. This invoice reflects the records of :school as of :date.', ['school' => $school?->name ?? __('the school'), 'date' => now()->format('d M Y')]) }}
        </div>

        <div class="sign">
            <div><span>{{ __('Accountant') }}</span></div>
            <div><span>{{ __('Authorised signature') }}</span></div>
        </div>
    </div>
</body>
</html>
