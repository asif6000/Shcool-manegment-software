@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Bank accounts'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-bank"></i>{{ __('Accounts') }}</span>
            <h1 class="page-title">{{ __('Bank accounts') }}</h1>
            <p class="page-subtitle">{{ __('Opening balances and the bank channel of the ledger, per account.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('cashbook.index') }}" class="btn btn-light-soft"><i class="bi bi-journal-text"></i>{{ __('Cashbook') }}</a>
        </div>
    </div>

    <section class="stats-grid mb-3" aria-label="{{ __('Bank position') }}">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Opening balances') }}</p>
                    <p class="stat-value">{{ Money::format($summary['opening']) }}</p>
                </div>
                <span class="stat-icon" aria-hidden="true"><i class="bi bi-flag"></i></span>
            </div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Ledger movement') }}</p>
                    <p class="stat-value">{{ Money::format($summary['ledger']) }}</p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-arrow-left-right"></i></span>
            </div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Current balance') }}</p>
                    <p class="stat-value">{{ Money::format($summary['balance']) }}</p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-safe2"></i></span>
            </div>
        </article>
    </section>

    <div class="row g-3">
        <div class="col-lg-7">
            <section class="ui-card table-card">
                <div class="table-toolbar">
                    <h2 class="card-title mb-0">{{ __('Accounts') }}</h2>
                    <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count account|:count accounts', $accounts->count(), ['count' => $accounts->count()]) }}</span>
                </div>

                @if ($accounts->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-bank"></i></div>
                        <h3>{{ __('No bank account yet') }}</h3>
                        <p>{{ __('Add the school bank accounts so bank collections, expenses and vouchers land in the right ledger.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Account') }}</th>
                                    <th scope="col" class="text-end">{{ __('Opening') }}</th>
                                    <th scope="col" class="text-end">{{ __('Movement') }}</th>
                                    <th scope="col" class="text-end">{{ __('Balance') }}</th>
                                    <th scope="col">{{ __('Status') }}</th>
                                    <th scope="col" class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($accounts as $account)
                                    @php
                                        $ledger = (float) ($account->ledger_total ?? 0);
                                        $balance = (float) $account->opening_balance + $ledger;
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="fw-600">{{ $account->name }}</span>
                                            <div class="text-muted-2" style="font-size:.74rem;">{{ $account->bank_name }} · {{ $account->account_no }}@if ($account->branch) · {{ $account->branch }}@endif</div>
                                        </td>
                                        <td class="text-end">{{ Money::format((float) $account->opening_balance) }}</td>
                                        <td class="text-end {{ $ledger < 0 ? 'text-danger' : 'text-success' }}">{{ Money::format($ledger) }}</td>
                                        <td class="text-end fw-600">{{ Money::format($balance) }}</td>
                                        <td><span class="badge-soft {{ $account->is_active ? 'is-success' : 'is-muted' }}">{{ $account->is_active ? __('Active') : __('Inactive') }}</span></td>
                                        <td class="text-end">
                                            @can('bank.manage')
                                                <button type="button" class="icon-action" data-bs-toggle="modal" data-bs-target="#bankEdit{{ $account->id }}"
                                                        title="{{ __('Edit account') }}" aria-label="{{ __('Edit :name', ['name' => $account->name]) }}">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        @can('bank.manage')
            <div class="col-lg-5">
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('Add a bank account') }}</h2>
                    <form method="POST" action="{{ route('bank.store') }}" class="mt-3">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="bank-name">{{ __('Account label') }}</label>
                            <input id="bank-name" type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" maxlength="80" placeholder="{{ __('Main account') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="bank-institution">{{ __('Bank name') }}</label>
                            <input id="bank-institution" type="text" name="bank_name" class="form-control @error('bank_name') is-invalid @enderror"
                                   value="{{ old('bank_name') }}" maxlength="80" required>
                            @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="bank-account-no">{{ __('Account number') }}</label>
                            <input id="bank-account-no" type="text" name="account_no" class="form-control @error('account_no') is-invalid @enderror"
                                   value="{{ old('account_no') }}" maxlength="40" required>
                            @error('account_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="bank-branch">{{ __('Branch') }}</label>
                            <input id="bank-branch" type="text" name="branch" class="form-control @error('branch') is-invalid @enderror"
                                   value="{{ old('branch') }}" maxlength="80">
                            @error('branch')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="bank-opening">{{ __('Opening balance (BDT)') }}</label>
                            <input id="bank-opening" type="number" step="0.01" min="0" name="opening_balance"
                                   class="form-control @error('opening_balance') is-invalid @enderror" value="{{ old('opening_balance', '0') }}">
                            @error('opening_balance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i>{{ __('Add account') }}</button>
                    </form>
                </section>
            </div>
        @endcan
    </div>

    @can('bank.manage')
        @foreach ($accounts as $account)
            <div class="modal fade" id="bankEdit{{ $account->id }}" tabindex="-1" aria-labelledby="bankEditLabel{{ $account->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('bank.update', $account) }}" class="modal-content">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="bankEditLabel{{ $account->id }}">{{ __('Edit bank account') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="edit-bank-name-{{ $account->id }}">{{ __('Account label') }}</label>
                                <input id="edit-bank-name-{{ $account->id }}" type="text" name="name" class="form-control" value="{{ $account->name }}" maxlength="80" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="edit-bank-institution-{{ $account->id }}">{{ __('Bank name') }}</label>
                                <input id="edit-bank-institution-{{ $account->id }}" type="text" name="bank_name" class="form-control" value="{{ $account->bank_name }}" maxlength="80" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="edit-bank-no-{{ $account->id }}">{{ __('Account number') }}</label>
                                <input id="edit-bank-no-{{ $account->id }}" type="text" name="account_no" class="form-control" value="{{ $account->account_no }}" maxlength="40" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="edit-bank-branch-{{ $account->id }}">{{ __('Branch') }}</label>
                                <input id="edit-bank-branch-{{ $account->id }}" type="text" name="branch" class="form-control" value="{{ $account->branch }}" maxlength="80">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="edit-bank-opening-{{ $account->id }}">{{ __('Opening balance (BDT)') }}</label>
                                <input id="edit-bank-opening-{{ $account->id }}" type="number" step="0.01" min="0" name="opening_balance" class="form-control" value="{{ $account->opening_balance }}">
                            </div>
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="edit-bank-active-{{ $account->id }}"
                                       name="is_active" value="1" @checked($account->is_active)>
                                <label class="form-check-label" for="edit-bank-active-{{ $account->id }}">{{ __('Active') }}</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('Save changes') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endcan
@endsection
