@use('App\Support\Money')
@use('Illuminate\Support\Carbon')
@extends('layouts.app')

@section('title', __('Daily collection report'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-calendar-day"></i>{{ __('Accountant Management') }}</span>
            <h1 class="page-title">{{ __('Daily collection report') }}</h1>
            <p class="page-subtitle">{{ __('Cash received on each date between :from and :to, split by the accountant who posted it', [
                'from' => $filters['from']->format('d M Y'),
                'to' => $filters['to']->format('d M Y'),
            ]) }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('accountants.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Accountant list') }}</a>
            @can('reports.export')
                <a href="{{ route('accountants.export', array_merge(request()->query(), ['scope' => 'daily'])) }}" class="btn btn-light-soft">
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
            <h2 class="card-title mb-0">{{ __('Collection by day') }}</h2>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count day|:count days with money', $byDay->count(), ['count' => $byDay->count()]) }}</span>
        </div>
        @if ($byDay->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
                <h3>{{ __('No collections in this range') }}</h3>
                <p>{{ __('A date appears here once a payment is posted on it. Change the dates or clear the filters above.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Date') }}</th>
                            <th scope="col">{{ __('Accountants') }}</th>
                            <th scope="col" class="text-end">{{ __('Payments') }}</th>
                            <th scope="col" class="text-end">{{ __('Invoices') }}</th>
                            <th scope="col" class="text-end">{{ __('Standard fee') }}</th>
                            <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($byDay as $day)
                            <tr>
                                <td class="fw-600">{{ $day['label'] }}</td>
                                <td style="font-size:.8rem;">
                                    {{ $split->get((string) $day['key'], collect())->pluck('collector')->implode(', ') ?: '—' }}
                                </td>
                                <td class="text-end">{{ number_format($day['payments']) }}</td>
                                <td class="text-end">{{ number_format($day['invoices']) }}</td>
                                <td class="text-end">{{ Money::format($day['standard_fee']) }}</td>
                                <td class="text-end fw-600">{{ Money::format($day['received']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="2">{{ __('Total') }}</th>
                            <td class="text-end">{{ number_format($byDay->sum('payments')) }}</td>
                            <td class="text-end">{{ number_format($byDay->sum('invoices')) }}</td>
                            <td class="text-end">{{ Money::format($byDay->sum('standard_fee')) }}</td>
                            <td class="text-end fw-700">{{ Money::format($byDay->sum('received')) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>

    <section class="ui-card table-card mb-3">
        <div class="table-toolbar">
            <h2 class="card-title mb-0">{{ __('Day and accountant split') }}</h2>
            <span class="text-muted-2" style="font-size:.78rem;">{{ __('Each receipt is listed under the person who posted it') }}</span>
        </div>
        @if ($split->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-person-badge"></i></div>
                <h3>{{ __('Nothing to split') }}</h3>
                <p>{{ __('The split appears on the same dates as the table above.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Date') }}</th>
                            <th scope="col">{{ __('Accountant') }}</th>
                            <th scope="col" class="text-end">{{ __('Payments') }}</th>
                            <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($split as $day => $rows)
                            @php($label = $byDay->firstWhere('key', $day)['label'] ?? Carbon::parse((string) $day)->format('d M Y'))
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td>
                                        @if ($row['collector_id'])
                                            <a href="{{ route('accountants.show', $row['collector_id']) }}">{{ $row['collector'] }}</a>
                                        @else
                                            <span class="text-muted-2">{{ $row['collector'] }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($row['payments']) }}</td>
                                    <td class="text-end fw-600">{{ Money::format($row['received']) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <h2 class="card-title mb-0">{{ __('Accountant totals for the range') }}</h2>
        </div>
        @if ($byCollector->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-person-badge"></i></div>
                <h3>{{ __('No accountant posted in this range') }}</h3>
                <p>{{ __('Widening the dates above will show everyone who has.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Accountant') }}</th>
                            <th scope="col" class="text-end">{{ __('Payments') }}</th>
                            <th scope="col" class="text-end">{{ __('Invoices') }}</th>
                            <th scope="col" class="text-end">{{ __('Standard fee') }}</th>
                            <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                            <th scope="col" class="text-end">{{ __('Reversals signed') }}</th>
                            <th scope="col" class="text-end">{{ __('Waiting verification') }}</th>
                        </tr>
                    </thead>
                    <tbody>
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
                                <td class="text-end">{{ number_format($row['invoices']) }}</td>
                                <td class="text-end">{{ Money::format($row['standard_fee']) }}</td>
                                <td class="text-end fw-600">{{ Money::format($row['received']) }}</td>
                                <td class="text-end">{{ number_format($row['reversed']) }}</td>
                                <td class="text-end">{{ number_format($row['held']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <p class="text-muted-2 mt-3" style="font-size:.8rem;">
        <i class="bi bi-info-circle"></i>
        {{ __('Dates are payment dates. Reversed receipts are excluded from cash received and listed under their own column, so the original history is never lost.') }}
    </p>
@endsection

@include('reports._print')
