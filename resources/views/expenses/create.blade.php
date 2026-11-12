@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Add expense'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-bag-plus"></i>{{ __('Accounts') }}</span>
            <h1 class="page-title">{{ __('Add expense') }}</h1>
            <p class="page-subtitle">{{ __('Expenses up to :limit are posted immediately; larger amounts wait for an administrator.', ['limit' => Money::format($approvalLimit)]) }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('expenses.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Expenses') }}</a>
        </div>
    </div>

    @if ($categories->isEmpty())
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-tags"></i></div>
                <h3>{{ __('No expense categories yet') }}</h3>
                <p>{{ __('Create a category such as Utilities, Transport or Maintenance before recording an expense.') }}</p>
                @can('expenses.manage')
                    <a href="{{ route('expenses.categories') }}" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i>{{ __('Manage categories') }}</a>
                @endcan
            </div>
        </section>
    @else
        <form method="POST" action="{{ route('expenses.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-lg-8">
                    <section class="ui-card p-lg mb-3">
                        <h2 class="card-title">{{ __('Expense details') }}</h2>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label" for="expense-category">{{ __('Category') }}</label>
                                <select id="expense-category" name="expense_category_id" class="form-select" required>
                                    <option value="">{{ __('Choose a category') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected((string) old('expense_category_id') === (string) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('expense_category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="expense-date">{{ __('Expense date') }}</label>
                                <input id="expense-date" type="date" name="expense_date" class="form-control" value="{{ old('expense_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                                @error('expense_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="expense-title">{{ __('Title') }}</label>
                                <input id="expense-title" type="text" name="title" class="form-control" value="{{ old('title') }}" maxlength="160" required>
                                @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="expense-amount">{{ __('Amount (BDT)') }}</label>
                                <input id="expense-amount" type="number" name="amount" class="form-control" value="{{ old('amount') }}" min="0.01" step="0.01" required>
                                @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="expense-method">{{ __('Payment method') }}</label>
                                <select id="expense-method" name="payment_method" class="form-select" required>
                                    @foreach ($methods as $method)
                                        <option value="{{ $method }}" @selected(old('payment_method', 'cash') === $method)>{{ __(ucfirst($method)) }}</option>
                                    @endforeach
                                </select>
                                @error('payment_method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6" data-bank-field>
                                <label class="form-label" for="expense-bank">{{ __('Bank account') }}</label>
                                <select id="expense-bank" name="bank_account_id" class="form-select">
                                    <option value="">{{ __('Choose a bank account') }}</option>
                                    @foreach ($bankAccounts as $account)
                                        <option value="{{ $account->id }}" @selected((string) old('bank_account_id') === (string) $account->id)>{{ $account->label() }}</option>
                                    @endforeach
                                </select>
                                @error('bank_account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="expense-paid-to">{{ __('Paid to') }}</label>
                                <input id="expense-paid-to" type="text" name="paid_to" class="form-control" value="{{ old('paid_to') }}" maxlength="120">
                                @error('paid_to')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="expense-reference">{{ __('Reference no') }}</label>
                                <input id="expense-reference" type="text" name="reference_no" class="form-control" value="{{ old('reference_no') }}" maxlength="100">
                                @error('reference_no')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="expense-description">{{ __('Description') }}</label>
                                <textarea id="expense-description" name="description" class="form-control" rows="3" maxlength="1000">{{ old('description') }}</textarea>
                                @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-lg-4">
                    <section class="ui-card p-lg">
                        <h2 class="card-title">{{ __('Before you save') }}</h2>
                        <ul class="text-muted-2 mt-3 mb-0" style="font-size:.84rem;line-height:1.7;padding-left:1.1rem;">
                            <li>{{ __('The expense number is issued sequentially inside this school.') }}</li>
                            <li>{{ __('Cash, bank and mobile entries appear in the cashbook by channel.') }}</li>
                            <li>{{ __('Within-limit expenses post to the ledger immediately.') }}</li>
                            <li>{{ __('Above :limit the expense stays pending until an administrator approves it.', ['limit' => Money::format($approvalLimit)]) }}</li>
                        </ul>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i>{{ __('Save expense') }}</button>
                        </div>
                    </section>
                </div>
            </div>
        </form>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            var method = document.getElementById('expense-method');
            var bankField = document.querySelector('[data-bank-field]');
            var bankSelect = document.getElementById('expense-bank');
            if (!method || !bankField || !bankSelect) return;

            function sync() {
                var isBank = method.value === 'bank';
                bankField.style.display = isBank ? '' : 'none';
                bankSelect.required = isBank;
                if (!isBank) bankSelect.value = '';
            }

            method.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush
