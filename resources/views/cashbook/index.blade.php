@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Cashbook'))

@section('content')
    @php
        $money = fn (float $value): string => Money::format($value);
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-journal-text"></i>{{ __('Accounts') }}</span>
            <h1 class="page-title">{{ __('Cashbook') }}</h1>
            <p class="page-subtitle">{{ __('Every posted collection, expense, salary and voucher, split by cash, bank and mobile wallet.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('cashbook.closing') }}" class="btn btn-light-soft"><i class="bi bi-clipboard-check"></i>{{ __('Daily closing') }}</a>
            @can('cash.view')
                <a href="{{ route('cashbook.export', array_filter($filters)) }}" class="btn btn-light-soft"><i class="bi bi-filetype-csv"></i>{{ __('Export CSV') }}</a>
            @endcan
        </div>
    </div>

    <section class="stats-grid mb-3" aria-label="{{ __('Cash position') }}">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Cash in hand') }}</p>
                    <p class="stat-value">{{ $money($balances['cash']) }}</p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('All cash ledger entries') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Bank balance') }}</p>
                    <p class="stat-value">{{ $money($balances['bank']) }}</p>
                </div>
                <span class="stat-icon" aria-hidden="true"><i class="bi bi-bank"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Opening :opening + ledger :ledger', ['opening' => $money($balances['bank_opening']), 'ledger' => $money($balances['bank_ledger'])]) }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Mobile wallets') }}</p>
                    <p class="stat-value">{{ $money($balances['mobile']) }}</p>
                </div>
                <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-phone"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('bKash, Nagad, Rocket collections') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Total liquid funds') }}</p>
                    <p class="stat-value">{{ $money($balances['total']) }}</p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-safe2"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Cash + bank + mobile') }}</span></div>
        </article>
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('cashbook.index') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="cash-search">{{ __('Search cashbook') }}</label>
                    <input id="cash-search" type="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Search description or reference') }}">
                </div>
                <select name="channel" class="form-select" data-auto-submit aria-label="{{ __('Channel') }}">
                    <option value="">{{ __('All channels') }}</option>
                    @foreach (\App\Models\CashTransaction::CHANNELS as $channel)
                        <option value="{{ $channel }}" @selected($filters['channel'] === $channel)>{{ __(ucfirst($channel)) }}</option>
                    @endforeach
                </select>
                <select name="type" class="form-select" data-auto-submit aria-label="{{ __('Type') }}">
                    <option value="">{{ __('All entries') }}</option>
                    <option value="income" @selected($filters['type'] === 'income')>{{ __('Income') }}</option>
                    <option value="expense" @selected($filters['type'] === 'expense')>{{ __('Expense') }}</option>
                </select>
                <select name="reconciled" class="form-select" data-auto-submit aria-label="{{ __('Reconciliation') }}">
                    <option value="">{{ __('Any status') }}</option>
                    <option value="yes" @selected($filters['reconciled'] === 'yes')>{{ __('Reconciled') }}</option>
                    <option value="no" @selected($filters['reconciled'] === 'no')>{{ __('Not reconciled') }}</option>
                </select>
                <input type="date" name="from" class="form-control" value="{{ $filters['from'] }}" aria-label="{{ __('From date') }}">
                <input type="date" name="to" class="form-control" value="{{ $filters['to'] }}" aria-label="{{ __('To date') }}">
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('cashbook.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count entry|:count entries', $summary['count'], ['count' => $summary['count']]) }}</span>
        </div>

        <div class="px-3 py-2 d-flex flex-wrap gap-3 border-bottom" style="font-size:.82rem;">
            <span>{{ __('Money in') }}: <strong class="text-success">{{ $money($summary['income']) }}</strong></span>
            <span>{{ __('Money out') }}: <strong class="text-danger">{{ $money($summary['expense']) }}</strong></span>
            <span>{{ __('Net movement') }}: <strong class="{{ $summary['net'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($summary['net']) }}</strong></span>
        </div>

        @if ($transactions->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-journal-text"></i></div>
                <h3>{{ __('No ledger entries') }}</h3>
                <p>{{ array_filter($filters) ? __('No entries match these filters.') : __('Collect a fee, record an expense or post a voucher to start the cashbook.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Date') }}</th>
                            <th scope="col">{{ __('Description') }}</th>
                            <th scope="col">{{ __('Channel') }}</th>
                            <th scope="col" class="text-end">{{ __('In') }}</th>
                            <th scope="col" class="text-end">{{ __('Out') }}</th>
                            <th scope="col">{{ __('Reconciled') }}</th>
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $transaction)
                            @php($isIncome = (float) $transaction->amount >= 0)
                            <tr>
                                <td class="is-date">{{ $transaction->transaction_date?->format('d M Y') }}</td>
                                <td>
                                    <span class="fw-600">{{ $transaction->description }}</span>
                                    @if ($transaction->reference_no)
                                        <div class="text-muted-2" style="font-size:.74rem;">{{ $transaction->reference_no }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ __(ucfirst($transaction->channel)) }}
                                    @if ($transaction->bankAccount)
                                        <div class="text-muted-2" style="font-size:.74rem;">{{ $transaction->bankAccount->name }}</div>
                                    @endif
                                </td>
                                <td class="text-end text-success">{{ $isIncome ? $money((float) $transaction->amount) : '' }}</td>
                                <td class="text-end text-danger">{{ $isIncome ? '' : $money(abs((float) $transaction->amount)) }}</td>
                                <td>
                                    @if ($transaction->reconciled_at)
                                        <span class="badge-soft is-success">{{ __('Yes') }}</span>
                                    @else
                                        <span class="badge-soft is-warning">{{ __('Pending') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if (! $transaction->reconciled_at)
                                        @can('cash.view')
                                            <form method="POST" action="{{ route('cashbook.reconcile', $transaction) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light-soft">{{ __('Reconcile') }}</button>
                                            </form>
                                        @endcan
                                    @else
                                        <span class="text-muted-2" style="font-size:.74rem;">{{ $transaction->reconciler?->name }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $transactions->firstItem(), 'to' => $transactions->lastItem(), 'total' => $transactions->total()]) }}</span>
                {{ $transactions->links() }}
            </div>
        @endif
    </section>

    @if ($balances['accounts']->isNotEmpty())
        <section class="ui-card table-card mt-3">
            <div class="table-toolbar">
                <h2 class="card-title mb-0">{{ __('Bank accounts') }}</h2>
                @can('bank.manage')
                    <a href="{{ route('bank.index') }}" class="card-link">{{ __('Manage accounts') }}</a>
                @endcan
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Account') }}</th>
                            <th scope="col" class="text-end">{{ __('Opening balance') }}</th>
                            <th scope="col" class="text-end">{{ __('Ledger movement') }}</th>
                            <th scope="col" class="text-end">{{ __('Current balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($balances['accounts'] as $account)
                            <tr>
                                <td>{{ $account['label'] }}</td>
                                <td class="text-end">{{ $money($account['opening']) }}</td>
                                <td class="text-end {{ $account['ledger'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($account['ledger']) }}</td>
                                <td class="text-end fw-600">{{ $money($account['balance']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
