@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Income and expense statement'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-journal-check"></i>{{ __('Reports') }}</span>
            <h1 class="page-title">{{ __('Income and expense statement') }}</h1>
            <p class="page-subtitle">{{ __('Built from the cashbook ledger, so every line is a posted transaction.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('reports.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Reports') }}</a>
            @can('reports.export')
                <a href="{{ route('reports.export', ['report' => 'statement', 'year' => $year]) }}" class="btn btn-light-soft">
                    <i class="bi bi-filetype-csv"></i>{{ __('Export CSV') }}
                </a>
            @endcan
            <button type="button" class="btn btn-light-soft" onclick="window.print()"><i class="bi bi-printer"></i>{{ __('Print / Save as PDF') }}</button>
        </div>
    </div>

    <section class="ui-card p-lg mb-3">
        <form method="GET" action="{{ route('reports.statement') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="statement-year">{{ __('Year') }}</label>
                <input id="statement-year" type="number" name="year" class="form-control" value="{{ $year }}" min="2000" max="{{ now()->year + 1 }}" data-auto-submit>
            </div>
            <div class="col-md-4"><button type="submit" class="btn btn-light-soft w-100">{{ __('Load year') }}</button></div>
        </form>
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <h2 class="card-title mb-0">{{ __('Monthly movement · :year', ['year' => $year]) }}</h2>
            <span class="text-muted-2" style="font-size:.78rem;">{{ __('Opening balance') }}: {{ Money::format($totals['opening']) }}</span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Month') }}</th>
                        <th scope="col" class="text-end">{{ __('Income') }}</th>
                        <th scope="col" class="text-end">{{ __('Expense') }}</th>
                        <th scope="col" class="text-end">{{ __('Net') }}</th>
                        <th scope="col" class="text-end">{{ __('Running balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['month'] }}</td>
                            <td class="text-end text-success">{{ Money::format($row['income']) }}</td>
                            <td class="text-end text-danger">{{ Money::format($row['expense']) }}</td>
                            <td class="text-end fw-600 {{ $row['net'] < 0 ? 'text-danger' : '' }}">{{ Money::format($row['net']) }}</td>
                            <td class="text-end">{{ Money::format($row['running']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th>{{ __('Year total') }}</th>
                        <th class="text-end">{{ Money::format($totals['income']) }}</th>
                        <th class="text-end">{{ Money::format($totals['expense']) }}</th>
                        <th class="text-end">{{ Money::format($totals['net']) }}</th>
                        <th class="text-end">{{ Money::format($closing) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
@endsection

@include('reports._print')
