@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Monthly collection report'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-calendar-month"></i>{{ __('Accountant Management') }}</span>
            <h1 class="page-title">{{ __('Monthly collection report') }}</h1>
            <p class="page-subtitle">{{ __('Collection month by month between :from and :to, with the accountant split inside each month', [
                'from' => $filters['from']->format('d M Y'),
                'to' => $filters['to']->format('d M Y'),
            ]) }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('accountants.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Accountant list') }}</a>
            @can('reports.export')
                <a href="{{ route('accountants.export', array_merge(request()->query(), ['scope' => 'monthly'])) }}" class="btn btn-light-soft">
                    <i class="bi bi-filetype-csv"></i>{{ __('Export Excel') }}
                </a>
            @endcan
            <button type="button" class="btn btn-light-soft" onclick="window.print()"><i class="bi bi-printer"></i>{{ __('Print / Save as PDF') }}</button>
        </div>
    </div>

    @include('reports._filters')
    @include('reports._summary')

    <section class="ui-card table-card mb-3">
        <div class="table-toolbar">
            <h2 class="card-title mb-0">{{ __('Month and accountant split') }}</h2>
            <span class="text-muted-2" style="font-size:.78rem;">{{ __('The month the money arrived') }}</span>
        </div>
        @if ($byPaymentMonth->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
                <h3>{{ __('No collections in this range') }}</h3>
                <p>{{ __('A month appears here once a payment is posted in it.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Month') }}</th>
                            <th scope="col">{{ __('Accountants') }}</th>
                            <th scope="col" class="text-end">{{ __('Payments') }}</th>
                            <th scope="col" class="text-end">{{ __('Invoices') }}</th>
                            <th scope="col" class="text-end">{{ __('Standard fee') }}</th>
                            <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($byPaymentMonth as $month)
                            @php($split = $months[$month['key']]['collectors'] ?? collect())
                            <tr>
                                <td class="fw-600">{{ $month['label'] }}</td>
                                <td style="font-size:.8rem;">
                                    @if ($split->isEmpty())
                                        <span class="text-muted-2">—</span>
                                    @else
                                        @foreach ($split as $collectorId => $part)
                                            <a href="{{ route('accountants.show', (int) $collectorId) }}" class="d-block text-truncate" style="max-width:220px;">
                                                {{ $part['collector'] }} · {{ Money::format($part['received']) }}
                                            </a>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($month['payments']) }}</td>
                                <td class="text-end">{{ number_format($month['invoices']) }}</td>
                                <td class="text-end">{{ Money::format($month['standard_fee']) }}</td>
                                <td class="text-end fw-600">{{ Money::format($month['received']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="2">{{ __('Total') }}</th>
                            <td class="text-end">{{ number_format($byPaymentMonth->sum('payments')) }}</td>
                            <td class="text-end">{{ number_format($byPaymentMonth->sum('invoices')) }}</td>
                            <td class="text-end">{{ Money::format($byPaymentMonth->sum('standard_fee')) }}</td>
                            <td class="text-end fw-700">{{ Money::format($byPaymentMonth->sum('received')) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>

    <div class="row g-3">
        <div class="col-lg-6">
            <section class="ui-card table-card h-100">
                <div class="table-toolbar">
                    <h2 class="card-title mb-0">{{ __('By fee month') }}</h2>
                    <span class="text-muted-2" style="font-size:.78rem;">{{ __('What the money was for') }}</span>
                </div>
                @if ($byFeeMonth->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-calendar-3-range"></i></div>
                        <h3>{{ __('No billed months in range') }}</h3>
                        <p>{{ __('Collections applied to a monthly fee show the billing month here, which can differ from the date paid.') }}</p>
                    </div>
                @else
                    <div class="table-responsive" style="max-height:420px;overflow:auto;">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Fee month') }}</th>
                                    <th scope="col" class="text-end">{{ __('Payments') }}</th>
                                    <th scope="col" class="text-end">{{ __('Invoices') }}</th>
                                    <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byFeeMonth as $row)
                                    <tr>
                                        <td class="fw-600">{{ $row['label'] }}</td>
                                        <td class="text-end">{{ number_format($row['payments']) }}</td>
                                        <td class="text-end">{{ number_format($row['invoices']) }}</td>
                                        <td class="text-end">{{ Money::format($row['received']) }}</td>
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
                <div class="table-toolbar">
                    <h2 class="card-title mb-0">{{ __('By accountant') }}</h2>
                    <span class="text-muted-2" style="font-size:.78rem;">{{ __('Whole range') }}</span>
                </div>
                @if ($byCollector->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-person-badge"></i></div>
                        <h3>{{ __('No accountant posted in this range') }}</h3>
                        <p>{{ __('Widening the dates above will show everyone who has.') }}</p>
                    </div>
                @else
                    <div class="table-responsive" style="max-height:420px;overflow:auto;">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Accountant') }}</th>
                                    <th scope="col" class="text-end">{{ __('Payments') }}</th>
                                    <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                                    <th scope="col" class="text-end">{{ __('Share') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php($rangeTotal = max($byCollector->sum('received'), 0.01))
                                @foreach ($byCollector as $row)
                                    <tr>
                                        <td class="fw-600">
                                            @if ($row['key'])
                                                <a href="{{ route('accountants.show', $row['key']) }}">{{ $row['label'] }}</a>
                                            @else
                                                {{ $row['label'] }}
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format($row['payments']) }}</td>
                                        <td class="text-end fw-600">{{ Money::format($row['received']) }}</td>
                                        <td class="text-end">{{ number_format($row['received'] / $rangeTotal * 100, 1) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>

    <p class="text-muted-2 mt-3" style="font-size:.8rem;">
        <i class="bi bi-info-circle"></i>
        {{ __('Cash received counts posted payments only. Pending, held, cancelled or reversed receipts are never added to a month, and the original history stays on the payment record.') }}
    </p>
@endsection

@include('reports._print')
