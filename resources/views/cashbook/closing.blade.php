@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Daily closing'))

@section('content')
    @php
        $money = fn (float $value): string => Money::format($value);
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-clipboard-check"></i>{{ __('Accounts') }}</span>
            <h1 class="page-title">{{ __('Daily closing') }}</h1>
            <p class="page-subtitle">{{ __('Reconciliation sheet for :date', ['date' => $date->format('d M Y')]) }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('cashbook.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Cashbook') }}</a>
            <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i>{{ __('Print') }}</button>
        </div>
    </div>

    <section class="ui-card p-lg mb-3">
        <form method="GET" action="{{ route('cashbook.closing') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="closing-date">{{ __('Closing date') }}</label>
                <input id="closing-date" type="date" name="date" class="form-control" value="{{ $date->toDateString() }}" data-auto-submit>
            </div>
        </form>
    </section>

    <div class="row g-3">
        <div class="col-lg-7">
            <section class="ui-card table-card">
                <div class="table-toolbar">
                    <h2 class="card-title mb-0">{{ __('Movement by channel') }}</h2>
                    <span class="text-muted-2" style="font-size:.78rem;">{{ __('Unreconciled entries: :count', ['count' => $unreconciled]) }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Channel') }}</th>
                                <th scope="col" class="text-end">{{ __('In') }}</th>
                                <th scope="col" class="text-end">{{ __('Out') }}</th>
                                <th scope="col" class="text-end">{{ __('Net') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (\App\Models\CashTransaction::CHANNELS as $channel)
                                @php($row = $byChannel[$channel] ?? ['income' => 0.0, 'expense' => 0.0, 'net' => 0.0])
                                <tr>
                                    <td>{{ __(ucfirst($channel)) }}</td>
                                    <td class="text-end text-success">{{ $money($row['income']) }}</td>
                                    <td class="text-end text-danger">{{ $money($row['expense']) }}</td>
                                    <td class="text-end fw-600">{{ $money($row['net']) }}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <th>{{ __('Total for the day') }}</th>
                                <th class="text-end">{{ $money($totals['income']) }}</th>
                                <th class="text-end">{{ $money($totals['expense']) }}</th>
                                <th class="text-end">{{ $money($totals['net']) }}</th>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('Closing position') }}</h2>
                <dl class="detail-list mt-3 mb-0">
                    <div><dt>{{ __('Cash in hand') }}</dt><dd>{{ $money($balances['cash']) }}</dd></div>
                    <div><dt>{{ __('Bank accounts') }}</dt><dd>{{ $money($balances['bank']) }}</dd></div>
                    <div><dt>{{ __('Mobile wallets') }}</dt><dd>{{ $money($balances['mobile']) }}</dd></div>
                    <div><dt>{{ __('Total liquid funds') }}</dt><dd class="fw-700">{{ $money($balances['total']) }}</dd></div>
                </dl>
            </section>
        </div>
    </div>

    <section class="ui-card table-card mt-3">
        <div class="table-toolbar">
            <h2 class="card-title mb-0">{{ __('Entries on :date', ['date' => $date->format('d M Y')]) }}</h2>
        </div>

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-journal-x"></i></div>
                <h3>{{ __('No entries on this date') }}</h3>
                <p>{{ __('Choose another date or post today\'s collections first.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Description') }}</th>
                            <th scope="col">{{ __('Channel') }}</th>
                            <th scope="col" class="text-end">{{ __('Amount') }}</th>
                            <th scope="col">{{ __('Reconciled') }}</th>
                            <th scope="col">{{ __('Entered by') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $row->description }}</td>
                                <td>{{ __(ucfirst($row->channel)) }}</td>
                                <td class="text-end {{ (float) $row->amount < 0 ? 'text-danger' : 'text-success' }}">{{ $money(abs((float) $row->amount)) }}</td>
                                <td>{{ $row->reconciled_at ? __('Yes') : __('Pending') }}</td>
                                <td>{{ $row->creator?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection

@push('styles')
    <style>
        @media print {
            .page-actions, .sidebar, .topbar, .filter-form { display: none !important; }
        }
    </style>
@endpush
