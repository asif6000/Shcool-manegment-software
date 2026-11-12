@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Payment method collections'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-credit-card"></i>{{ __('Reports') }}</span>
            <h1 class="page-title">{{ __('Payment method report') }}</h1>
            <p class="page-subtitle">{{ __('Cash, bKash, Nagad, bank and every other configured channel received between :from and :to', [
                'from' => $filters['from']->format('d M Y'),
                'to' => $filters['to']->format('d M Y'),
            ]) }}</p>
        </div>
        @include('reports._actions', ['export' => 'methods'])
    </div>

    @include('reports._filters')
    @include('reports._summary')

    <section class="ui-card table-card">
        <div class="table-toolbar"><h2 class="card-title mb-0">{{ __('By payment method') }}</h2></div>
        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-credit-card"></i></div>
                <h3>{{ __('No money through any method') }}</h3>
                <p>{{ __('Post a payment in this range to see how it arrived.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Method') }}</th>
                            <th scope="col">{{ __('Cashbook channel') }}</th>
                            <th scope="col" class="text-end">{{ __('Payments') }}</th>
                            <th scope="col" class="text-end">{{ __('Invoices') }}</th>
                            <th scope="col" class="text-end">{{ __('Standard fee') }}</th>
                            <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="fw-600">{{ $row['label'] }}</td>
                                <td><span class="badge-soft is-muted">{{ __(ucfirst($row['channel'])) }}</span></td>
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
                            <th></th>
                            <th class="text-end">{{ number_format($summary['payments']) }}</th>
                            <th class="text-end">{{ number_format($rows->sum('invoices')) }}</th>
                            <th class="text-end">{{ Money::format($rows->sum('standard_fee')) }}</th>
                            <th class="text-end">{{ Money::format($rows->sum('received')) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>

    <p class="text-muted-2 mt-3 mb-0" style="font-size:.78rem;">
        <i class="bi bi-info-circle"></i>
        {{ __('Cash received adds up across methods. The standard fee column counts an invoice in every method that paid part of it, so it can be larger than the school total when one invoice was settled by two channels.') }}
    </p>
@endsection

@include('reports._print')
