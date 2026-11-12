@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Fee type collections'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-list-task"></i>{{ __('Reports') }}</span>
            <h1 class="page-title">{{ __('Fee type report') }}</h1>
            <p class="page-subtitle">{{ __('Money received between :from and :to, split across the fee types on each invoice', [
                'from' => $filters['from']->format('d M Y'),
                'to' => $filters['to']->format('d M Y'),
            ]) }}</p>
        </div>
        @include('reports._actions', ['export' => 'fee-types'])
    </div>

    @include('reports._filters')
    @include('reports._summary')

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <h2 class="card-title mb-0">{{ __('By fee type') }}</h2>
            <span class="text-muted-2" style="font-size:.78rem;">{{ __('Issued line totals are the fee as billed for that type') }}</span>
        </div>
        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-list-task"></i></div>
                <h3>{{ __('No fee lines collected') }}</h3>
                <p>{{ __('Post a payment against an invoice in this range to split it by fee type.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Fee type') }}</th>
                            <th scope="col" class="text-end">{{ __('Fee lines') }}</th>
                            <th scope="col" class="text-end">{{ __('Issued line total') }}</th>
                            <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="text-end">{{ number_format($row['lines']) }}</td>
                                <td class="text-end">{{ Money::format($row['billed']) }}</td>
                                <td class="text-end fw-600">{{ Money::format($row['received']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">{{ __('Total') }}</th>
                            <th class="text-end">{{ number_format($rows->sum('lines')) }}</th>
                            <th class="text-end">{{ Money::format($rows->sum('billed')) }}</th>
                            <th class="text-end">{{ Money::format($rows->sum('received')) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>

    <p class="text-muted-2 mt-3 mb-0" style="font-size:.78rem;">
        <i class="bi bi-info-circle"></i>
        {{ __('A payment is shared across the lines of the invoice it settled in proportion to each line, and the last line takes the rounding difference, so these rows add back to the cash received total. Invoice discounts stay on the invoice and are not pushed onto one fee type.') }}
    </p>
@endsection

@include('reports._print')
