@extends('layouts.app')

@section('title', __('Edit accountant').' · '.$accountant->name)

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-badge"></i>{{ __('Accountant Management') }}</span>
            <h1 class="page-title">{{ __('Edit accountant') }}</h1>
            <p class="page-subtitle">{{ __('The mobile number is the sign-in identifier. Changing it changes the login.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('accountants.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Back to accountants') }}</a>
            <a href="{{ route('accountants.show', $accountant) }}" class="btn btn-light-soft"><i class="bi bi-eye"></i>{{ __('View details') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('accountants.update', $accountant) }}" novalidate>
        @csrf
        @method('PUT')

        <div class="ui-card form-card">
            <div class="form-section">
                <div class="form-section-head">
                    <h2>{{ __('Account details') }}</h2>
                    <p>{{ __('The accountant keeps the accountant role; only the name and the sign-in number can change here.') }}</p>
                </div>

                <div class="form-grid">
                    <div>
                        <label for="name" class="form-label">{{ __('Full name') }}<span class="required">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name', $accountant->name) }}" class="form-control @error('name') is-invalid @enderror" required autofocus autocomplete="name">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="mobile_number" class="form-label">{{ __('Mobile number') }}<span class="required">*</span></label>
                        <input type="tel" id="mobile_number" name="mobile_number" value="{{ old('mobile_number', $accountant->mobile_number) }}" class="form-control @error('mobile_number') is-invalid @enderror" autocomplete="tel" inputmode="numeric" placeholder="01XXXXXXXXX" required>
                        @error('mobile_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-hint">{{ __('Unique across the school. The accountant signs in with this number.') }}</div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('accountants.index') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i>{{ __('Save changes') }}</button>
            </div>
        </div>
    </form>

    <div class="ui-card form-card mt-3" id="password">
        <div class="form-section">
            <div class="form-section-head">
                <h2>{{ __('Reset password') }}</h2>
                <p>{{ __('Set a temporary password. Every other device signed in as :name is signed out, and a new password is required at the next sign-in.', ['name' => $accountant->name]) }}</p>
            </div>

            <form method="POST" action="{{ route('accountants.password', $accountant) }}" novalidate>
                @csrf

                <div class="form-grid">
                    <div>
                        <label for="new_password" class="form-label">{{ __('New password') }}<span class="required">*</span></label>
                        <div class="input-icon">
                            <i class="bi bi-lock"></i>
                            <input type="password" id="new_password" name="password" class="form-control has-toggle @error('password') is-invalid @enderror" required autocomplete="new-password">
                            <button type="button" class="password-toggle" data-toggle-password="new_password" data-show-label="{{ __('Show password') }}" data-hide-label="{{ __('Hide password') }}" aria-label="{{ __('Show password') }}"><i class="bi bi-eye"></i></button>
                        </div>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-hint">{{ __('At least 8 characters including letters and numbers.') }}</div>
                    </div>

                    <div>
                        <label for="new_password_confirmation" class="form-label">{{ __('Confirm password') }}<span class="required">*</span></label>
                        <div class="input-icon">
                            <i class="bi bi-lock-fill"></i>
                            <input type="password" id="new_password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-light-soft"><i class="bi bi-key"></i>{{ __('Reset password') }}</button>
                </div>
            </form>
        </div>

        <div class="form-section">
            <div class="form-section-head">
                <h2>{{ __('Account status') }}</h2>
                <p>{{ $accountant->is_active ? __('Active. This login can sign in and collect fees.') : __('Inactive. This login cannot sign in; the receipts already posted are kept.') }}</p>
            </div>

            <form method="POST" action="{{ route('accountants.status', $accountant) }}" data-confirm="{{ $accountant->is_active ? __('Deactivate :name? They will be signed out immediately.', ['name' => $accountant->name]) : __('Activate :name?', ['name' => $accountant->name]) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn {{ $accountant->is_active ? 'btn-light-soft' : 'btn-primary' }}">
                    <i class="bi {{ $accountant->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                    {{ $accountant->is_active ? __('Deactivate accountant') : __('Activate accountant') }}
                </button>
            </form>

            @can('delete', $accountant)
                <hr class="my-4">

                <div class="danger-zone">
                    <div class="form-section-head">
                        <h3 class="h6 fw-bold text-danger">{{ __('Permanently delete this accountant') }}</h3>
                        <p>{{ __('Deletes the login and every record posted in this accountant’s name. The deletion cannot be undone through the app; a backup is written before anything is removed.') }}</p>
                    </div>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#purgeAccountantModal">
                        <i class="bi bi-exclamation-octagon"></i>{{ __('Permanently delete accountant') }}
                    </button>
                </div>
            @endcan
        </div>
    </div>

    @can('delete', $accountant)
        <div class="modal fade" id="purgeAccountantModal" tabindex="-1" aria-labelledby="purgeAccountantModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="POST" action="{{ route('accountants.destroy', $accountant) }}" data-purge-form>
                        @csrf
                        @method('DELETE')

                        <div class="modal-header">
                            <h2 class="modal-title h5 text-danger" id="purgeAccountantModalLabel">
                                <i class="bi bi-exclamation-octagon"></i>
                                {{ __('Permanently delete :name', ['name' => $accountant->name]) }}
                            </h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>

                        <div class="modal-body">
                            <div class="alert alert-danger">
                                <strong>{{ __('Warning:') }}</strong>
                                {{ __('ALL records associated with this accountant will be permanently deleted. This includes fee collections, receipts, cashbook entries, expenses, vouchers and the staff profile. The deletion cannot be undone through the app.') }}
                                <div class="mt-2 fw-semibold">
                                    {{ __('Total related records to be deleted: :count', ['count' => number_format($purge['total'])]) }}
                                </div>
                                <div class="small mt-1">
                                    {{ __('That count is the login itself plus every row listed below, including the role, session and token rows.') }}
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <div class="text-muted-2 small">{{ __('Accountant') }}</div>
                                    <div class="fw-600">{{ $accountant->name }}</div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-muted-2 small">{{ __('Mobile number') }}</div>
                                    <div class="fw-600">{{ $accountant->mobile_number ?: '—' }}</div>
                                </div>
                            </div>

                            <h3 class="h6 fw-bold">{{ __('Records that will be removed') }}</h3>
                            <ul class="small mb-3">
                                <li>{{ trans_choice(':count fee collection|:count fee collections', $purge['payments'], ['count' => $purge['payments']]) }}
                                    · ৳{{ number_format($purge['payment_amount'], 2) }}
                                    · {{ trans_choice(':count student|:count students', $purge['students'], ['count' => $purge['students']]) }}</li>
                                <li>{{ trans_choice(':count receipt|:count receipts', $purge['receipts'], ['count' => $purge['receipts']]) }}</li>
                                <li>{{ trans_choice(':count cashbook entry|:count cashbook entries', $purge['cash'], ['count' => $purge['cash']]) }}</li>
                                <li>{{ trans_choice(':count expense|:count expenses', $purge['expenses'], ['count' => $purge['expenses']]) }}
                                    · ৳{{ number_format($purge['expense_amount'], 2) }}</li>
                                <li>{{ trans_choice(':count voucher|:count vouchers', $purge['vouchers'], ['count' => $purge['vouchers']]) }}
                                    · ৳{{ number_format($purge['voucher_amount'], 2) }}</li>
                                <li>{{ $purge['employee'] ? __('Staff profile and employee record') : __('No staff profile is linked') }}
                                    @if ($purge['attendance'] || $purge['leaves'] || $purge['salary_payments'])
                                        · {{ __(':attendance attendance, :leaves leave and :salary payroll rows', [
                                            'attendance' => $purge['attendance'],
                                            'leaves' => $purge['leaves'],
                                            'salary' => $purge['salary_payments'],
                                        ]) }}
                                    @endif
                                </li>
                                <li>{{ __(':sessions sessions and :tokens API tokens', ['sessions' => $purge['sessions'], 'tokens' => $purge['tokens']]) }}</li>
                            </ul>

                            <div class="alert alert-warning small mb-3">
                                <i class="bi bi-calculator"></i>
                                {{ trans_choice(':count invoice|:count invoices', $purge['invoices'], ['count' => $purge['invoices']]) }}
                                {{ __('will be recalculated from the payments that remain. Student dues increase by about ৳:amount, and the removed collections disappear from every report and the cashbook.', ['amount' => number_format($purge['reopened_due'], 2)]) }}
                            </div>

                            <div class="alert alert-warning small mb-3">
                                <i class="bi bi-journal-x"></i>
                                {{ __('The fee collections above are money the school already received. Removing their receipts and cashbook rows makes the books disagree with the cash actually in hand, so the backup is the only remaining record of those transactions.') }}
                            </div>

                            <div class="mb-3">
                                <label for="purge_admin_password" class="form-label">{{ __('Your admin password') }}<span class="required">*</span></label>
                                <div class="input-icon">
                                    <i class="bi bi-shield-lock"></i>
                                    <input type="password" id="purge_admin_password" name="admin_password" class="form-control @error('admin_password') is-invalid @enderror" required autocomplete="current-password">
                                </div>
                                @error('admin_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label for="purge_delete_confirmation" class="form-label">{{ __('Type DELETE ALL to confirm') }}<span class="required">*</span></label>
                                <input type="text" id="purge_delete_confirmation" name="delete_confirmation" value="{{ old('delete_confirmation') }}" class="form-control @error('delete_confirmation') is-invalid @enderror" placeholder="DELETE ALL" autocomplete="off" required>
                                @error('delete_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-hint">{{ __('Type the phrase exactly as shown; the server checks it before anything is deleted.') }}</div>
                            </div>

                            <p class="small text-muted-2 mb-0">
                                <i class="bi bi-archive"></i>
                                {{ __('A JSON backup of the accountant and every record about to be removed is written to the private storage disk first. The path is shown on the accountant list after deletion.') }}
                            </p>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash3"></i>{{ __('Delete everything permanently') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
    <script>
        (function () {
            const form = document.querySelector('[data-purge-form]');

            if (!form) {
                return;
            }

            @if ($errors->has('admin_password') || $errors->has('delete_confirmation'))
                document.addEventListener('DOMContentLoaded', function () {
                    const modal = document.getElementById('purgeAccountantModal');

                    if (modal && window.bootstrap) {
                        window.bootstrap.Modal.getOrCreateInstance(modal).show();
                    }
                });
            @endif
        })();
    </script>
@endpush
