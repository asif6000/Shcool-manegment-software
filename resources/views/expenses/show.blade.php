@use('App\Support\Money')
@extends('layouts.app')

@section('title', $expense->expense_no)

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
            <span class="page-eyebrow"><i class="bi bi-bag-dash"></i>{{ __('Expense') }}</span>
            <h1 class="page-title">{{ $expense->expense_no }}</h1>
            <p class="page-subtitle">
                {{ $expense->expense_date?->format('d M Y') }}
                <span class="badge-soft {{ $statusClasses[$expense->status] ?? '' }} ms-1">{{ __(ucfirst($expense->status)) }}</span>
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('expenses.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Expenses') }}</a>
            @if ($expense->status === 'pending')
                @can('expenses.approve')
                    @if ($canApproveAmount)
                        <form method="POST" action="{{ route('expenses.approve', $expense) }}" onsubmit="return confirm(@json(__('Approve this expense?')))">
                            @csrf
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i>{{ __('Approve') }}</button>
                        </form>
                    @endif
                    <button type="button" class="btn btn-light-soft text-danger" data-bs-toggle="modal" data-bs-target="#expenseReject">
                        <i class="bi bi-x-circle"></i>{{ __('Reject') }}
                    </button>
                @endcan
            @elseif ($expense->status === 'paid')
                @can('expenses.manage')
                    <button type="button" class="btn btn-light-soft text-danger" data-bs-toggle="modal" data-bs-target="#expenseCancel">
                        <i class="bi bi-slash-circle"></i>{{ __('Cancel') }}
                    </button>
                @endcan
            @endif
        </div>
    </div>

    @if ($expense->status === 'pending' && ! $canApproveAmount)
        <div class="alert alert-warning d-flex gap-2 align-items-start" role="status">
            <i class="bi bi-info-circle"></i>
            <div style="font-size:.84rem;">
                {{ __('This expense is above your :limit approval limit, so it needs an administrator.', ['limit' => Money::format($approvalLimit)]) }}
            </div>
        </div>
    @endif

    @if ($expense->rejection_reason)
        <div class="alert alert-danger"> <strong>{{ __('Rejected') }}:</strong> {{ $expense->rejection_reason }}</div>
    @endif
    @if ($expense->cancel_reason)
        <div class="alert alert-warning"> <strong>{{ __('Cancelled') }}:</strong> {{ $expense->cancel_reason }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <section class="ui-card p-lg mb-3">
                <h2 class="card-title">{{ __('Expense details') }}</h2>
                <dl class="detail-list mb-0 mt-3">
                    <div><dt>{{ __('Title') }}</dt><dd>{{ $expense->title }}</dd></div>
                    <div><dt>{{ __('Category') }}</dt><dd>{{ $expense->category?->name ?? '—' }}</dd></div>
                    <div><dt>{{ __('Amount') }}</dt><dd class="fw-700">{{ Money::format((float) $expense->amount) }}</dd></div>
                    <div><dt>{{ __('Payment method') }}</dt><dd>{{ __(ucfirst($expense->payment_method)) }}</dd></div>
                    <div><dt>{{ __('Bank account') }}</dt><dd>{{ $expense->bankAccount?->label() ?? '—' }}</dd></div>
                    <div><dt>{{ __('Paid to') }}</dt><dd>{{ $expense->paid_to ?: '—' }}</dd></div>
                    <div><dt>{{ __('Reference') }}</dt><dd>{{ $expense->reference_no ?: '—' }}</dd></div>
                    <div><dt>{{ __('Recorded by') }}</dt><dd>{{ $expense->creator?->name ?? '—' }}</dd></div>
                    <div><dt>{{ __('Approved by') }}</dt><dd>{{ $expense->approver?->name ?? '—' }} @if ($expense->approved_at) · {{ $expense->approved_at->format('d M Y, h:i A') }} @endif</dd></div>
                    @if ($expense->cancelled_at)
                        <div><dt>{{ __('Cancelled by') }}</dt><dd>{{ $expense->canceller?->name ?? '—' }} · {{ $expense->cancelled_at->format('d M Y, h:i A') }}</dd></div>
                    @endif
                    @if ($expense->description)
                        <div><dt>{{ __('Description') }}</dt><dd>{{ $expense->description }}</dd></div>
                    @endif
                </dl>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="ui-card table-card">
                <div class="table-toolbar">
                    <h2 class="card-title mb-0">{{ __('Ledger entries') }}</h2>
                </div>
                @if ($expense->cashTransactions->isEmpty())
                    <p class="dash-empty p-lg mb-0">{{ __('No ledger entry has been posted for this expense yet.') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Channel') }}</th>
                                    <th class="text-end">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($expense->cashTransactions as $transaction)
                                    <tr>
                                        <td>{{ $transaction->transaction_date?->format('d M Y') }}</td>
                                        <td>
                                            {{ __(ucfirst($transaction->channel)) }}
                                            @if ($transaction->reversal_of_id)
                                                <div class="text-muted-2" style="font-size:.72rem;">{{ __('Reversal') }}</div>
                                            @endif
                                        </td>
                                        <td class="text-end fw-600 {{ (float) $transaction->amount < 0 ? 'text-danger' : 'text-success' }}">
                                            {{ (float) $transaction->amount < 0 ? '−' : '+' }} {{ Money::format(abs((float) $transaction->amount)) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>

    @if ($expense->status === 'pending')
        @can('expenses.approve')
            <div class="modal fade" id="expenseReject" tabindex="-1" aria-labelledby="expenseRejectLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('expenses.reject', $expense) }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="expenseRejectLabel">{{ __('Reject expense') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label" for="reject-reason">{{ __('Reason') }}</label>
                            <textarea id="reject-reason" name="reason" class="form-control" rows="3" maxlength="500" required></textarea>
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
            <div class="modal fade" id="expenseCancel" tabindex="-1" aria-labelledby="expenseCancelLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('expenses.cancel', $expense) }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="expenseCancelLabel">{{ __('Cancel expense') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">{{ __('The posted ledger entry will be reversed. This cannot be undone.') }}</p>
                            <label class="form-label" for="cancel-reason">{{ __('Reason') }}</label>
                            <textarea id="cancel-reason" name="reason" class="form-control" rows="3" maxlength="500" required></textarea>
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
@endsection
