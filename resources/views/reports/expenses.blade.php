@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Expense report'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-bag-dash"></i>{{ __('Reports') }}</span>
            <h1 class="page-title">{{ __('Expense report') }}</h1>
            <p class="page-subtitle">{{ __('Approved spending between :from and :to', ['from' => $from->format('d M Y'), 'to' => $to->format('d M Y')]) }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('reports.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Reports') }}</a>
            @can('reports.export')
                <a href="{{ route('reports.export', ['report' => 'expenses', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-light-soft">
                    <i class="bi bi-filetype-csv"></i>{{ __('Export CSV') }}
                </a>
            @endcan
            <button type="button" class="btn btn-light-soft" onclick="window.print()"><i class="bi bi-printer"></i>{{ __('Print / Save as PDF') }}</button>
        </div>
    </div>

    <section class="ui-card p-lg mb-3">
        <form method="GET" action="{{ route('reports.expenses') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="expense-from">{{ __('From') }}</label>
                <input id="expense-from" type="date" name="from" class="form-control" value="{{ $from->toDateString() }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="expense-to">{{ __('To') }}</label>
                <input id="expense-to" type="date" name="to" class="form-control" value="{{ $to->toDateString() }}">
            </div>
            <div class="col-md-4"><button type="submit" class="btn btn-light-soft w-100">{{ __('Apply range') }}</button></div>
        </form>
    </section>

    <section class="stats-grid mb-3">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Approved spend') }}</p>
                    <p class="stat-value">{{ Money::format($total) }}</p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-bag-dash"></i></span>
            </div>
            <div class="stat-foot"><span>{{ trans_choice(':count expense|:count expenses', $count, ['count' => $count]) }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Still pending') }}</p>
                    <p class="stat-value">{{ Money::format($pending) }}</p>
                </div>
                <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('In range, not in totals') }}</span></div>
        </article>
    </section>

    <div class="row g-3">
        <div class="col-lg-7">
            <section class="ui-card table-card h-100">
                <div class="table-toolbar"><h2 class="card-title mb-0">{{ __('By category') }}</h2></div>
                @if ($byCategory->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-bag-dash"></i></div>
                        <h3>{{ __('No approved expenses') }}</h3>
                        <p>{{ __('Approve expenses in this range to build the report.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Category') }}</th>
                                    <th scope="col" class="text-end">{{ __('Count') }}</th>
                                    <th scope="col" class="text-end">{{ __('Amount') }}</th>
                                    <th scope="col" class="text-end">{{ __('Share') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byCategory as $name => $row)
                                    <tr>
                                        <td>{{ $name }}</td>
                                        <td class="text-end">{{ number_format($row['count']) }}</td>
                                        <td class="text-end fw-600">{{ Money::format($row['amount']) }}</td>
                                        <td class="text-end">{{ $total > 0 ? number_format(($row['amount'] / $total) * 100, 1) : '0.0' }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <div class="col-lg-5">
            <section class="ui-card table-card h-100">
                <div class="table-toolbar"><h2 class="card-title mb-0">{{ __('By channel') }}</h2></div>
                @if ($byChannel->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-diagram-3"></i></div>
                        <h3>{{ __('Nothing to split') }}</h3>
                        <p>{{ __('Channel totals appear once expenses are approved.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Channel') }}</th>
                                    <th scope="col" class="text-end">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byChannel as $channel => $amount)
                                    <tr>
                                        <td>{{ __(ucfirst($channel)) }}</td>
                                        <td class="text-end fw-600">{{ Money::format((float) $amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection

@include('reports._print')
