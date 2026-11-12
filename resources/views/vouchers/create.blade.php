@extends('layouts.app')

@section('title', __('Create voucher'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-journal-plus"></i>{{ __('Accounts') }}</span>
            <h1 class="page-title">{{ __('Create voucher') }}</h1>
            <p class="page-subtitle">{{ __('Post income or an expense that is not part of student fees. The cashbook updates immediately.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('vouchers.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Vouchers') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('vouchers.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-lg-7">
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('Voucher details') }}</h2>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label" for="voucher-type">{{ __('Type') }}</label>
                            <select id="voucher-type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                                <option value="expense" @selected(old('type', 'expense') === 'expense')>{{ __('Expense (money out)') }}</option>
                                <option value="income" @selected(old('type') === 'income')>{{ __('Income (money in)') }}</option>
                            </select>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="voucher-date">{{ __('Voucher date') }}</label>
                            <input id="voucher-date" type="date" name="voucher_date" class="form-control @error('voucher_date') is-invalid @enderror"
                                   value="{{ old('voucher_date', now()->toDateString()) }}" required>
                            @error('voucher_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="voucher-amount">{{ __('Amount (BDT)') }}</label>
                            <input id="voucher-amount" type="number" step="0.01" min="0.01" name="amount"
                                   class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="voucher-channel">{{ __('Channel') }}</label>
                            <select id="voucher-channel" name="channel" class="form-select @error('channel') is-invalid @enderror" data-bank-toggle required>
                                <option value="cash" @selected(old('channel', 'cash') === 'cash')>{{ __('Cash') }}</option>
                                <option value="bank" @selected(old('channel') === 'bank')>{{ __('Bank') }}</option>
                                <option value="mobile" @selected(old('channel') === 'mobile')>{{ __('Mobile wallet') }}</option>
                            </select>
                            @error('channel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 d-none" data-bank-field>
                            <label class="form-label" for="voucher-bank">{{ __('Bank account') }}</label>
                            <select id="voucher-bank" name="bank_account_id" class="form-select @error('bank_account_id') is-invalid @enderror">
                                <option value="">{{ __('Choose an account') }}</option>
                                @foreach ($bankAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('bank_account_id') === (string) $account->id)>{{ $account->label() }}</option>
                                @endforeach
                            </select>
                            @error('bank_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if ($bankAccounts->isEmpty())
                                <div class="text-muted-2 mt-1" style="font-size:.76rem;">
                                    {{ __('No active bank account yet.') }}
                                    @can('bank.manage')
                                        <a href="{{ route('bank.index') }}">{{ __('Add a bank account') }}</a>
                                    @endcan
                                </div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="voucher-category">{{ __('Category') }}</label>
                            <input id="voucher-category" type="text" name="category" class="form-control @error('category') is-invalid @enderror"
                                   value="{{ old('category') }}" maxlength="80" placeholder="{{ __('Utilities, transport, donation…') }}">
                            @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="voucher-payee">{{ __('Payee / received from') }}</label>
                            <input id="voucher-payee" type="text" name="payee" class="form-control @error('payee') is-invalid @enderror"
                                   value="{{ old('payee') }}" maxlength="120">
                            @error('payee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="voucher-reference">{{ __('Reference number') }}</label>
                            <input id="voucher-reference" type="text" name="reference_no" class="form-control @error('reference_no') is-invalid @enderror"
                                   value="{{ old('reference_no') }}" maxlength="100" placeholder="{{ __('Cheque or transaction id') }}">
                            @error('reference_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="voucher-description">{{ __('Description') }}</label>
                            <textarea id="voucher-description" name="description" class="form-control @error('description') is-invalid @enderror"
                                      rows="3" maxlength="255" required>{{ old('description') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-5">
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('Before posting') }}</h2>
                    <ul class="text-muted-2 mt-3 mb-0" style="font-size:.84rem;line-height:1.7;padding-left:1.1rem;">
                        <li>{{ __('The voucher number is issued sequentially inside this school.') }}</li>
                        <li>{{ __('A posted voucher writes one cashbook entry and cannot be deleted.') }}</li>
                        <li>{{ __('Cancel a wrong voucher with a reason so the ledger is reversed, not removed.') }}</li>
                        <li>{{ __('Use Expenses instead when the payment needs an approval limit.') }}</li>
                    </ul>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i>{{ __('Post voucher') }}
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            var channel = document.querySelector('[data-bank-toggle]');
            var field = document.querySelector('[data-bank-field]');
            if (!channel || !field) return;

            function sync() {
                field.classList.toggle('d-none', channel.value !== 'bank');
            }

            channel.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush
