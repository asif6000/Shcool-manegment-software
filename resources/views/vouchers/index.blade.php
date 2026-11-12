@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Vouchers'))

@section('content')
    @php
        $money = fn (float $value): string => Money::format($value);
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-journal-plus"></i>{{ __('Accounts') }}</span>
            <h1 class="page-title">{{ __('Vouchers') }}</h1>
            <p class="page-subtitle">{{ __('Income and expense vouchers outside fee invoices, each with its own numbered ledger entry.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('vouchers.export', array_filter($filters)) }}" class="btn btn-light-soft"><i class="bi bi-filetype-csv"></i>{{ __('Export CSV') }}</a>
            @can('vouchers.manage')
                <a href="{{ route('vouchers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i>{{ __('Create voucher') }}</a>
            @endcan
        </div>
    </div>

    <section class="stats-grid mb-3" aria-label="{{ __('Voucher summary') }}">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Income vouchers') }}</p>
                    <p class="stat-value">{{ $money($summary['income']) }}</p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-arrow-down-left-circle"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Posted in this view') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Expense vouchers') }}</p>
                    <p class="stat-value">{{ $money($summary['expense']) }}</p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-arrow-up-right-circle"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Posted in this view') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Posted') }}</p>
                    <p class="stat-value">{{ number_format($summary['posted']) }}</p>
                </div>
                <span class="stat-icon" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __(':count cancelled', ['count' => $summary['cancelled']]) }}</span></div>
        </article>
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('vouchers.index') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="voucher-search">{{ __('Search vouchers') }}</label>
                    <input id="voucher-search" type="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Search voucher, payee or reference') }}">
                </div>
                <select name="type" class="form-select" data-auto-submit aria-label="{{ __('Type') }}">
                    <option value="">{{ __('All types') }}</option>
                    <option value="income" @selected($filters['type'] === 'income')>{{ __('Income') }}</option>
                    <option value="expense" @selected($filters['type'] === 'expense')>{{ __('Expense') }}</option>
                </select>
                <select name="channel" class="form-select" data-auto-submit aria-label="{{ __('Channel') }}">
                    <option value="">{{ __('All channels') }}</option>
                    @foreach (\App\Models\Voucher::CHANNELS as $channel)
                        <option value="{{ $channel }}" @selected($filters['channel'] === $channel)>{{ __(ucfirst($channel)) }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-select" data-auto-submit aria-label="{{ __('Status') }}">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="posted" @selected($filters['status'] === 'posted')>{{ __('Posted') }}</option>
                    <option value="cancelled" @selected($filters['status'] === 'cancelled')>{{ __('Cancelled') }}</option>
                </select>
                <input type="date" name="from" class="form-control" value="{{ $filters['from'] }}" aria-label="{{ __('From date') }}">
                <input type="date" name="to" class="form-control" value="{{ $filters['to'] }}" aria-label="{{ __('To date') }}">
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('vouchers.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count voucher|:count vouchers', $vouchers->total(), ['count' => $vouchers->total()]) }}</span>
        </div>

        @if ($vouchers->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-journal-plus"></i></div>
                <h3>{{ __('No vouchers found') }}</h3>
                <p>{{ array_filter($filters) ? __('No vouchers match these filters.') : __('Create a voucher for income or expenses that are not handled by fee invoices.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Voucher') }}</th>
                            <th scope="col">{{ __('Type') }}</th>
                            <th scope="col">{{ __('Payee / category') }}</th>
                            <th scope="col">{{ __('Channel') }}</th>
                            <th scope="col" class="text-end">{{ __('Amount') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vouchers as $voucher)
                            <tr>
                                <td>
                                    <a href="{{ route('vouchers.show', $voucher) }}" class="fw-600 text-decoration-none">{{ $voucher->voucher_no }}</a>
                                    <div class="text-muted-2" style="font-size:.74rem;">{{ $voucher->voucher_date?->format('d M Y') }}</div>
                                </td>
                                <td>
                                    <span class="badge-soft {{ $voucher->type === 'income' ? 'is-success' : 'is-danger' }}">{{ __(ucfirst($voucher->type)) }}</span>
                                </td>
                                <td>
                                    {{ $voucher->payee ?: '—' }}
                                    @if ($voucher->category)
                                        <div class="text-muted-2" style="font-size:.74rem;">{{ $voucher->category }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ __(ucfirst($voucher->channel)) }}
                                    @if ($voucher->bankAccount)
                                        <div class="text-muted-2" style="font-size:.74rem;">{{ $voucher->bankAccount->name }}</div>
                                    @endif
                                </td>
                                <td class="text-end fw-600">{{ $money((float) $voucher->amount) }}</td>
                                <td><span class="badge-soft {{ $voucher->status === 'posted' ? 'is-success' : 'is-muted' }}">{{ __(ucfirst($voucher->status)) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $vouchers->firstItem(), 'to' => $vouchers->lastItem(), 'total' => $vouchers->total()]) }}</span>
                {{ $vouchers->links() }}
            </div>
        @endif
    </section>
@endsection
