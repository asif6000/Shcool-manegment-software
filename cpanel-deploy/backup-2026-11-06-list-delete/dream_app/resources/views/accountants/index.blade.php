@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Accountant list'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-badge"></i>{{ __('Accountant Management') }}</span>
            <h1 class="page-title">{{ __('Accountant list') }}</h1>
            <p class="page-subtitle">{{ __('Every account holder who collects fees, with the money that actually passed through their hands.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('accountants.daily') }}" class="btn btn-light-soft"><i class="bi bi-calendar-day"></i>{{ __('Daily report') }}</a>
            <a href="{{ route('accountants.monthly') }}" class="btn btn-light-soft"><i class="bi bi-calendar-month"></i>{{ __('Monthly report') }}</a>
            @if ($canExport)
                <a href="{{ route('accountants.export', array_filter(['q' => $search, 'status' => $status])) }}" class="btn btn-light-soft">
                    <i class="bi bi-filetype-csv"></i>{{ __('Export CSV') }}
                </a>
            @endif
            @if ($canCreate)
                <a href="{{ route('accountants.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i>{{ __('Add accountant') }}</a>
            @endif
        </div>
    </div>

    <section class="stats-grid mb-3" aria-label="{{ __('Roster totals') }}">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Accountants') }}</p>
                    <p class="stat-value">{{ number_format($totals['accountants']) }}</p>
                </div>
                <span class="stat-icon" aria-hidden="true"><i class="bi bi-person-badge"></i></span>
            </div>
            <div class="stat-foot"><span>{{ trans_choice(':count active|:count active', $totals['active'], ['count' => $totals['active']]) }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Today collection') }}</p>
                    <p class="stat-value">{{ Money::format($totals['today']) }}</p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-sunrise"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Posted payments taken today') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('This month') }}</p>
                    <p class="stat-value">{{ Money::format($totals['month']) }}</p>
                </div>
                <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-calendar-month"></i></span>
            </div>
            <div class="stat-foot"><span>{{ now()->translatedFormat('F Y') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Total collected') }}</p>
                    <p class="stat-value">{{ Money::format($totals['total']) }}</p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-cash-stack"></i></span>
            </div>
            <div class="stat-foot">
                <span>{{ trans_choice(':count payment|:count payments', $totals['payments'], ['count' => $totals['payments']]) }}</span>
            </div>
        </article>
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('accountants.index') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="accountant-search">{{ __('Search accountants') }}</label>
                    <input id="accountant-search" type="search" name="q" class="form-control" value="{{ $search }}" placeholder="{{ __('Name, mobile number, phone or employee ID') }}">
                </div>
                <label class="visually-hidden" for="filter-status">{{ __('Account status') }}</label>
                <select id="filter-status" name="status" class="form-select" data-auto-submit>
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="active" @selected($status === 'active')>{{ __('Active') }}</option>
                    <option value="inactive" @selected($status === 'inactive')>{{ __('Inactive') }}</option>
                </select>
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if ($search !== '' || $status !== '')
                    <a href="{{ route('accountants.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count collector|:count collectors', $accountants->total(), ['count' => $accountants->total()]) }}</span>
        </div>

        @if ($accountants->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-person-lines-fill"></i></div>
                <h3>{{ __('No collectors found') }}</h3>
                <p>{{ __('Someone appears here once they hold the accountant role or a fee payment has been posted under their name.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Accountant') }}</th>
                            <th scope="col">{{ __('Employee ID') }}</th>
                            <th scope="col">{{ __('Phone') }}</th>
                            <th scope="col">{{ __('Login') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col" class="text-end">{{ __('Total collection') }}</th>
                            <th scope="col" class="text-end">{{ __('Today') }}</th>
                            <th scope="col" class="text-end">{{ __('This month') }}</th>
                            <th scope="col" class="text-end">{{ __('Students') }}</th>
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($accountants as $accountant)
                            <tr>
                                <td>
                                    <div class="cell-person">
                                        <span class="avatar">
                                            @if ($accountant->avatarUrl())
                                                <img src="{{ $accountant->avatarUrl() }}" alt="">
                                            @else
                                                {{ $accountant->initials() }}
                                            @endif
                                        </span>
                                        <span>
                                            <span class="name">
                                                <a href="{{ route('accountants.show', $accountant) }}">{{ $accountant->name }}</a>
                                            </span>
                                            <span class="email">{{ $accountant->getAttribute('employee_designation') ?: $accountant->primaryRoleLabel() }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @if ($accountant->getAttribute('employee_code'))
                                        <span class="fw-600">{{ $accountant->getAttribute('employee_code') }}</span>
                                    @else
                                        <span class="text-muted-2">{{ __('Not linked') }}</span>
                                    @endif
                                </td>
                                <td>{{ $accountant->getAttribute('employee_phone') ?: ($accountant->phone ?: '—') }}</td>
                                <td style="font-size:.8rem;">{{ $accountant->mobile_number ?: ($accountant->email ?: '—') }}</td>
                                <td>
                                    @if ($accountant->is_active)
                                        <span class="badge-soft is-success"><span class="dot"></span>{{ __('Active') }}</span>
                                    @else
                                        <span class="badge-soft is-danger"><span class="dot"></span>{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-end fw-600">{{ Money::format($accountant->collection_stats['total']) }}</td>
                                <td class="text-end">{{ Money::format($accountant->collection_stats['today']) }}</td>
                                <td class="text-end">{{ Money::format($accountant->collection_stats['month']) }}</td>
                                <td class="text-end">
                                    {{ number_format($accountant->collection_stats['students']) }}
                                    <span class="d-block text-muted-2" style="font-size:.72rem;">{{ trans_choice(':count payment|:count payments', $accountant->collection_stats['payments'], ['count' => $accountant->collection_stats['payments']]) }}</span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('accountants.show', $accountant) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('View details') }}" aria-label="{{ __('View :name', ['name' => $accountant->name]) }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @can('update', $accountant)
                                            <a href="{{ route('accountants.edit', $accountant) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('Edit') }}" aria-label="{{ __('Edit :name', ['name' => $accountant->name]) }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="{{ route('accountants.edit', $accountant) }}#password" class="icon-action" data-bs-toggle="tooltip" title="{{ __('Reset password') }}" aria-label="{{ __('Reset the password for :name', ['name' => $accountant->name]) }}">
                                                <i class="bi bi-key"></i>
                                            </a>
                                        @endcan
                                        @can('changeStatus', $accountant)
                                            <form method="POST" action="{{ route('accountants.status', $accountant) }}" data-confirm="{{ $accountant->is_active ? __('Deactivate :name? They will be signed out immediately.', ['name' => $accountant->name]) : __('Activate :name?', ['name' => $accountant->name]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="icon-action" data-bs-toggle="tooltip" title="{{ $accountant->is_active ? __('Deactivate') : __('Activate') }}" aria-label="{{ $accountant->is_active ? __('Deactivate :name', ['name' => $accountant->name]) : __('Activate :name', ['name' => $accountant->name]) }}">
                                                    <i class="bi {{ $accountant->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $accountants->firstItem(), 'to' => $accountants->lastItem(), 'total' => $accountants->total()]) }}</span>
                {{ $accountants->links() }}
            </div>
        @endif
    </section>

    <p class="text-muted-2 mt-3" style="font-size:.8rem;">
        <i class="bi bi-info-circle"></i>
        {{ __('Collections are posted payments only. A pending, held, cancelled or reversed receipt is never counted as money collected, so these totals match the cashbook and Collector activity line for line.') }}
    </p>
@endsection
