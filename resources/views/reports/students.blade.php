@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Student payment history'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-lines-fill"></i>{{ __('Reports') }}</span>
            <h1 class="page-title">{{ __('Student-wise collections') }}</h1>
            <p class="page-subtitle">{{ __('What each student paid between :from and :to, with the fee it was paid against', [
                'from' => $filters['from']->format('d M Y'),
                'to' => $filters['to']->format('d M Y'),
            ]) }}</p>
        </div>
        @include('reports._actions', ['export' => 'students'])
    </div>

    @include('reports._filters')
    @include('reports._summary')

    <section class="ui-card table-card mb-3">
        <div class="table-toolbar"><h2 class="card-title mb-0">{{ __('Students') }}</h2></div>
        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-people"></i></div>
                <h3>{{ __('No student paid in this range') }}</h3>
                <p>{{ __('Widen the dates or clear a filter to see collections.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Student') }}</th>
                            <th scope="col">{{ __('Class') }}</th>
                            <th scope="col" class="text-end">{{ __('Invoices') }}</th>
                            <th scope="col" class="text-end">{{ __('Payments') }}</th>
                            <th scope="col" class="text-end">{{ __('Standard fee') }}</th>
                            <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>
                                    {{ $row['label'] }}
                                    @if ($row['code'])
                                        <span class="text-muted-2 d-block" style="font-size:.75rem;">{{ $row['code'] }}</span>
                                    @endif
                                </td>
                                <td>{{ $row['class'] ? ($row['class'].' · '.($row['section'] ?? __('No section'))) : __('Not recorded') }}</td>
                                <td class="text-end">{{ number_format($row['invoices']) }}</td>
                                <td class="text-end">{{ number_format($row['payments']) }}</td>
                                <td class="text-end">{{ Money::format($row['standard_fee']) }}</td>
                                <td class="text-end fw-600">{{ Money::format($row['received']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="table-toolbar border-top-0">{{ $rows->links() }}</div>
        @endif
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <h2 class="card-title mb-0">{{ __('Payment history') }}</h2>
            <span class="text-muted-2" style="font-size:.78rem;">{{ __('One line per fee record a payment was applied to') }}</span>
        </div>
        @if ($payments->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-cash-stack"></i></div>
                <h3>{{ __('No posted payments') }}</h3>
                <p>{{ __('Money shows here the moment a payment is posted to the cashbook.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Paid on') }}</th>
                            <th scope="col">{{ __('Receipt') }}</th>
                            <th scope="col">{{ __('Student') }}</th>
                            <th scope="col">{{ __('Invoice') }}</th>
                            <th scope="col">{{ __('Fee month') }}</th>
                            <th scope="col">{{ __('Method') }}</th>
                            <th scope="col">{{ __('Collector') }}</th>
                            <th scope="col" class="text-end">{{ __('Standard fee') }}</th>
                            <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $allocation)
                            @php $payment = $allocation->payment; $invoice = $allocation->invoice; @endphp
                            <tr>
                                <td>{{ $payment?->paid_at?->format('d M Y') }}</td>
                                <td>
                                    @if ($payment)
                                        <a href="{{ route('fees.receipt', $payment) }}" class="text-decoration-none">{{ $payment->receipt?->receipt_no ?? $payment->id }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $payment?->student?->full_name }}</td>
                                <td>{{ $invoice?->invoice_no }}</td>
                                <td>{{ $invoice?->billing_period ?? __('Not monthed') }}</td>
                                <td>{{ __(ucfirst((string) $payment?->method)) }}</td>
                                <td>{{ $payment?->receiver?->name }}</td>
                                <td class="text-end">{{ Money::format((float) ($invoice?->total ?? 0)) }}</td>
                                <td class="text-end fw-600">{{ Money::format((float) $allocation->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="table-toolbar border-top-0">{{ $payments->links() }}</div>
        @endif
    </section>
@endsection

@include('reports._print')
