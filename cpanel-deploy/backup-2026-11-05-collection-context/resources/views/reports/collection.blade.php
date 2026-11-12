@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Fee collection report'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-cash-coin"></i>{{ __('Reports') }}</span>
            <h1 class="page-title">{{ __('Fee collection report') }}</h1>
            <p class="page-subtitle">{{ __('Money received between :from and :to, grouped by the day and month it was paid', [
                'from' => $filters['from']->format('d M Y'),
                'to' => $filters['to']->format('d M Y'),
            ]) }}</p>
        </div>
        @include('reports._actions', ['export' => 'collection'])
    </div>

    @include('reports._filters')
    @include('reports._summary')

    <div class="row g-3">
        <div class="col-lg-6">
            <section class="ui-card table-card h-100">
                <div class="table-toolbar"><h2 class="card-title mb-0">{{ __('Daily collection') }}</h2></div>
                @if ($byDay->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-calendar3"></i></div>
                        <h3>{{ __('Nothing collected') }}</h3>
                        <p>{{ __('No posted payment falls inside this range with these filters.') }}</p>
                    </div>
                @else
                    <div class="table-responsive" style="max-height:420px;overflow:auto;">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Paid on') }}</th>
                                    <th scope="col" class="text-end">{{ __('Payments') }}</th>
                                    <th scope="col" class="text-end">{{ __('Invoices') }}</th>
                                    <th scope="col" class="text-end">{{ __('Standard fee') }}</th>
                                    <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byDay as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td class="text-end">{{ number_format($row['payments']) }}</td>
                                        <td class="text-end">{{ number_format($row['invoices']) }}</td>
                                        <td class="text-end">{{ Money::format($row['standard_fee']) }}</td>
                                        <td class="text-end fw-600">{{ Money::format($row['received']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th scope="row">{{ __('Total') }}</th>
                                    <th class="text-end">{{ number_format($summary['payments']) }}</th>
                                    <th class="text-end">{{ number_format($summary['invoices']) }}</th>
                                    <th class="text-end">{{ Money::format($summary['standard_fee']) }}</th>
                                    <th class="text-end">{{ Money::format($summary['received']) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="text-muted-2 px-lg pb-2 mb-0" style="font-size:.78rem;">
                        {{ __('A day repeats an invoice only when it was paid again that day, never the same money twice.') }}
                    </p>
                @endif
            </section>
        </div>

        <div class="col-lg-6">
            <section class="ui-card table-card h-100">
                <div class="table-toolbar"><h2 class="card-title mb-0">{{ __('Monthly collection') }}</h2></div>
                @if ($byPaymentMonth->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-calendar-month"></i></div>
                        <h3>{{ __('No months with money') }}</h3>
                        <p>{{ __('Collection months appear once a payment is posted.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Payment month') }}</th>
                                    <th scope="col" class="text-end">{{ __('Payments') }}</th>
                                    <th scope="col" class="text-end">{{ __('Standard fee') }}</th>
                                    <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byPaymentMonth as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td class="text-end">{{ number_format($row['payments']) }}</td>
                                        <td class="text-end">{{ Money::format($row['standard_fee']) }}</td>
                                        <td class="text-end fw-600">{{ Money::format($row['received']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <div class="col-lg-6">
            <section class="ui-card table-card h-100">
                <div class="table-toolbar"><h2 class="card-title mb-0">{{ __('By payment method') }}</h2></div>
                @if ($byMethod->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-cash-coin"></i></div>
                        <h3>{{ __('No collections in range') }}</h3>
                        <p>{{ __('Record a fee payment inside this date range to see the split.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Method') }}</th>
                                    <th scope="col" class="text-end">{{ __('Payments') }}</th>
                                    <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byMethod as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td class="text-end">{{ number_format($row['payments']) }}</td>
                                        <td class="text-end fw-600">{{ Money::format($row['received']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted-2 px-lg pb-2 mb-0" style="font-size:.78rem;">
                        <a href="{{ route('reports.methods', request()->query()) }}" class="text-decoration-none">{{ __('Open the payment method report') }} <i class="bi bi-arrow-right"></i></a>
                    </p>
                @endif
            </section>
        </div>

        <div class="col-lg-6">
            <section class="ui-card table-card h-100">
                <div class="table-toolbar"><h2 class="card-title mb-0">{{ __('Reversals and unverified money') }}</h2></div>
                @if ($reversals->isEmpty() && $held->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-shield-check"></i></div>
                        <h3>{{ __('Nothing to reverse') }}</h3>
                        <p>{{ __('No payment in this range was reversed or is waiting for verification.') }}</p>
                    </div>
                @else
                    <div class="table-responsive" style="max-height:300px;overflow:auto;">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Student') }}</th>
                                    <th scope="col">{{ __('Status') }}</th>
                                    <th scope="col" class="text-end">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reversals as $row)
                                    <tr>
                                        <td>{{ $row['student'] }}<br><span class="text-muted-2" style="font-size:.75rem;">{{ $row['receipt_no'] }} · {{ $row['reversed_at']?->format('d M Y') }}</span></td>
                                        <td><span class="badge-soft is-danger">{{ __('Reversed') }}</span></td>
                                        <td class="text-end">{{ Money::format($row['amount']) }}</td>
                                    </tr>
                                @endforeach
                                @foreach ($held as $row)
                                    <tr>
                                        <td>{{ $row['student'] }}<br><span class="text-muted-2" style="font-size:.75rem;">{{ $row['method'] }} · {{ $row['paid_at']?->format('d M Y') }}</span></td>
                                        <td><span class="badge-soft is-warning">{{ __('Waiting verification') }}</span></td>
                                        <td class="text-end">{{ Money::format($row['amount']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted-2 px-lg pb-2 mb-0" style="font-size:.78rem;">
                        {{ __('These amounts are outside the cash received total: a reversal left the cashbook, an unverified payment has not entered it yet.') }}
                    </p>
                @endif
            </section>
        </div>
    </div>
@endsection

@include('reports._print')
