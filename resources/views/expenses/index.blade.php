@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Expenses'))

@section('content')
    @php
        $statusClasses = [
            'pending' => 'is-warning',
            'paid' => 'is-success',
            'cancelled' => 'is-danger',
            'rejected' => 'is-danger',
        ];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-bag-dash"></i>{{ __('Accounts') }}</span>
            <h1 class="page-title">{{ __('Expenses') }}</h1>
            <p class="page-subtitle">{{ __('Record school expenses, approve them within limits and keep the cash ledger in step.') }}</p>
        </div>
        <div class="page-actions">
            @can('expenses.view')
                <a href="{{ route('expenses.categories') }}" class="btn btn-light-soft"><i class="bi bi-tags"></i>{{ __('Categories') }}</a>
                <a href="{{ route('expenses.export', array_filter($filters)) }}" class="btn btn-light-soft"><i class="bi bi-filetype-csv"></i>{{ __('Export CSV') }}</a>
            @endcan
            @can('expenses.manage')
                <a href="{{ route('expenses.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i>{{ __('Add expense') }}</a>
            @endcan
        </div>
    </div>

    @can('expenses.approve')
        <div class="alert alert-light border d-flex align-items-center gap-2 mb-3" role="status">
            <i class="bi bi-shield-check"></i>
            <span style="font-size:.84rem;">
                {{ __('You can approve expenses up to :limit. Higher amounts need an administrator.', ['limit' => Money::format($approvalLimit)]) }}
            </span>
        </div>
    @endcan

    <section class="stats-grid mb-3" aria-label="{{ __('Expense summary') }}">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Approved in this view') }}</p>
                    <p class="stat-value">৳ {{ number_format($summary['paid'], 2) }}</p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
            </div>
            <div class="stat-foot"><span>{{ trans_choice(':count expense|:count expenses', $summary['count'], ['count' => $summary['count']]) }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Pending approval') }}</p>
                    <p class="stat-value">{{ number_format($summary['pending_count']) }}</p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
            </div>
            <div class="stat-foot"><span>৳ {{ number_format($summary['pending_total'], 2) }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Approval limit') }}</p>
                    <p class="stat-value">৳ {{ number_format($approvalLimit, 2) }}</p>
                </div>
                <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-sliders"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Per expense for the accounts desk') }}</span></div>
        </article>
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('expenses.index') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="expense-search">{{ __('Search expenses') }}</label>
                    <input id="expense-search" type="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Search expense, title or payee') }}">
                </div>
                <label class="visually-hidden" for="expense-status">{{ __('Status') }}</label>
                <select id="expense-status" name="status" class="form-select" data-auto-submit>
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Models\Expense::STATUSES as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __(ucfirst($status)) }}</option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="expense-category">{{ __('Category') }}</label>
                <select id="expense-category" name="category_id" class="form-select" data-auto-submit>
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected($filters['category_id'] === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="expense-from">{{ __('From') }}</label>
                <input id="expense-from" type="date" name="from" class="form-control" value="{{ $filters['from'] }}" aria-label="{{ __('From date') }}">
                <label class="visually-hidden" for="expense-to">{{ __('To') }}</label>
                <input id="expense-to" type="date" name="to" class="form-control" value="{{ $filters['to'] }}" aria-label="{{ __('To date') }}">
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('expenses.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count expense|:count expenses', $expenses->total(), ['count' => $expenses->total()]) }}</span>
        </div>

        @if ($expenses->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-bag-dash"></i></div>
                <h3>{{ __('No expenses found') }}</h3>
                <p>{{ array_filter($filters) ? __('No expenses match these filters.') : __('Record the first expense to start the cashbook.') }}</p>
                @can('expenses.manage')
                    <a href="{{ route('expenses.create') }}" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i>{{ __('Add expense') }}</a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Expense') }}</th>
                            <th scope="col">{{ __('Category') }}</th>
                            <th scope="col">{{ __('Payee / method') }}</th>
                            <th scope="col" class="text-end">{{ __('Amount') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col">{{ __('Approved by') }}</th>
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($expenses as $expense)
                            <tr>
                                <td>
                                    <a href="{{ route('expenses.show', $expense) }}" class="fw-600 text-decoration-none">{{ $expense->expense_no }}</a>
                                    <div class="text-muted-2" style="font-size:.76rem;">{{ $expense->title }}</div>
                                    <div class="text-muted-2" style="font-size:.72rem;">{{ $expense->expense_date?->format('d M Y') }}</div>
                                </td>
                                <td>{{ $expense->category?->name ?? '—' }}</td>
                                <td>
                                    {{ $expense->paid_to ?: '—' }}
                                    <div class="text-muted-2" style="font-size:.76rem;">{{ __(ucfirst($expense->payment_method)) }}</div>
                                </td>
                                <td class="text-end fw-600">৳ {{ number_format((float) $expense->amount, 2) }}</td>
                                <td><span class="badge-soft {{ $statusClasses[$expense->status] ?? '' }}">{{ __(ucfirst($expense->status)) }}</span></td>
                                <td>{{ $expense->approver?->name ?? '—' }}</td>
                                <td class="text-end">
                                    <div class="row-actions">
                                        <a href="{{ route('expenses.show', $expense) }}" class="icon-action" title="{{ __('View expense') }}" aria-label="{{ __('View expense :no', ['no' => $expense->expense_no]) }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if ($expense->status === 'pending')
                                            @can('expenses.approve')
                                                <form method="POST" action="{{ route('expenses.approve', $expense) }}" class="d-inline"
                                                      onsubmit="return confirm(@json(__('Approve this expense?')))">
                                                    @csrf
                                                    <button type="submit" class="icon-action text-success" title="{{ __('Approve') }}" aria-label="{{ __('Approve expense :no', ['no' => $expense->expense_no]) }}">
                                                        <i class="bi bi-check2-circle"></i>
                                                    </button>
                                                </form>
                                                <button type="button" class="icon-action text-danger" data-bs-toggle="modal" data-bs-target="#expenseReject{{ $expense->id }}"
                                                        title="{{ __('Reject') }}" aria-label="{{ __('Reject expense :no', ['no' => $expense->expense_no]) }}">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            @endcan
                                        @elseif ($expense->status === 'paid')
                                            @can('expenses.manage')
                                                <button type="button" class="icon-action text-danger" data-bs-toggle="modal" data-bs-target="#expenseCancel{{ $expense->id }}"
                                                        title="{{ __('Cancel') }}" aria-label="{{ __('Cancel expense :no', ['no' => $expense->expense_no]) }}">
                                                    <i class="bi bi-slash-circle"></i>
                                                </button>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $expenses->firstItem(), 'to' => $expenses->lastItem(), 'total' => $expenses->total()]) }}</span>
                {{ $expenses->links() }}
            </div>
        @endif
    </section>

    @foreach ($expenses as $expense)
        @if ($expense->status === 'pending')
            @can('expenses.approve')
                <div class="modal fade" id="expenseReject{{ $expense->id }}" tabindex="-1" aria-labelledby="expenseRejectLabel{{ $expense->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <form method="POST" action="{{ route('expenses.reject', $expense) }}" class="modal-content">
                            @csrf
                            <div class="modal-header">
                                <h2 class="modal-title fs-5" id="expenseRejectLabel{{ $expense->id }}">{{ __('Reject expense') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-2">{{ $expense->expense_no }} · {{ $expense->title }}</p>
                                <label class="form-label" for="reject-reason-{{ $expense->id }}">{{ __('Reason') }}</label>
                                <textarea id="reject-reason-{{ $expense->id }}" name="reason" class="form-control" rows="3" maxlength="500" required></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-danger">{{ __('Reject expense') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        @elseif ($expense->status === 'paid')
            @can('expenses.manage')
                <div class="modal fade" id="expenseCancel{{ $expense->id }}" tabindex="-1" aria-labelledby="expenseCancelLabel{{ $expense->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <form method="POST" action="{{ route('expenses.cancel', $expense) }}" class="modal-content">
                            @csrf
                            <div class="modal-header">
                                <h2 class="modal-title fs-5" id="expenseCancelLabel{{ $expense->id }}">{{ __('Cancel expense') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-2">{{ $expense->expense_no }} · {{ $expense->title }}</p>
                                <label class="form-label" for="cancel-reason-{{ $expense->id }}">{{ __('Reason') }}</label>
                                <textarea id="cancel-reason-{{ $expense->id }}" name="reason" class="form-control" rows="3" maxlength="500" required></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Keep expense') }}</button>
                                <button type="submit" class="btn btn-danger">{{ __('Cancel and reverse ledger') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        @endif
    @endforeach
@endsection
