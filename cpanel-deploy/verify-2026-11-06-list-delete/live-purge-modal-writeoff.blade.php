@php
    $modalId = $modalId ?? 'purgeAccountantModal';
    $purgeFailed = (string) old('purged_id') === (string) $accountant->id
        && ($errors->has('admin_password') || $errors->has('delete_confirmation'));
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true" style="z-index: 2011;">
    {{-- The PWA update toast sits at z-index 2010 above a normal modal and would
         cover the footer, so this dialog is lifted just above it. --}}
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        {{-- The form is the .modal-content itself so the header, body and footer
             stay direct flex children; an extra wrapper makes the dialog refuse
             to scroll and clips the delete button below the viewport. --}}
        <form class="modal-content" method="POST" action="{{ route('accountants.destroy', $accountant) }}" data-purge-form>
            @csrf
            @method('DELETE')
            <input type="hidden" name="purged_id" value="{{ $accountant->id }}">

            <div class="modal-header">
                <h2 class="modal-title h5 text-danger" id="{{ $modalId }}Label">
                    <i class="bi bi-exclamation-octagon"></i>
                    {{ __('Permanently delete :name', ['name' => $accountant->name]) }}
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-octagon"></i>
                    <div>
                        <strong>{{ __('Warning:') }}</strong>
                        {{ __('ALL records associated with this accountant will be permanently deleted. This includes fee collections, receipts, cashbook entries, expenses, vouchers and the staff profile. The deletion cannot be undone through the app.') }}
                        <div class="mt-2 fw-semibold">
                            {{ __('Total related records to be deleted: :count', ['count' => number_format($purge['total'])]) }}
                        </div>
                        <div class="small mt-1">
                            {{ __('That count is the login itself plus every row listed below, including the role, session and token rows.') }}
                        </div>
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
                    <div>
                        {{ trans_choice(':count invoice|:count invoices', $purge['invoices'], ['count' => $purge['invoices']]) }}
                        {{ __('are recalculated from the payments that remain. The ৳:amount those collections had already cleared is written off as settled, so no outstanding due comes back and no student is asked to pay it again. Balances this accountant never collected stay payable.', ['amount' => number_format($purge['cleared_due'], 2)]) }}
                    </div>
                </div>

                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-journal-x"></i>
                    <div>
                        {{ __('The fee collections above are money the school already received. Removing their receipts and cashbook rows makes the books disagree with the cash actually in hand, so the backup is the only remaining record of those transactions.') }}
                    </div>
                </div>

                <div class="mb-3">
                    <label for="{{ $modalId }}AdminPassword" class="form-label">{{ __('Your admin password') }}<span class="required">*</span></label>
                    <div class="input-icon">
                        <i class="bi bi-shield-lock"></i>
                        <input type="password" id="{{ $modalId }}AdminPassword" name="admin_password" @class(['form-control', 'is-invalid' => $purgeFailed && $errors->has('admin_password')]) required autocomplete="current-password">
                    </div>
                    @if ($purgeFailed)
                        @error('admin_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @endif
                </div>

                <div class="mb-3">
                    <label for="{{ $modalId }}DeleteConfirmation" class="form-label">{{ __('Type DELETE ALL to confirm') }}<span class="required">*</span></label>
                    <input type="text" id="{{ $modalId }}DeleteConfirmation" name="delete_confirmation" value="{{ $purgeFailed ? old('delete_confirmation') : '' }}" @class(['form-control', 'is-invalid' => $purgeFailed && $errors->has('delete_confirmation')]) placeholder="DELETE ALL" autocomplete="off" required>
                    @if ($purgeFailed)
                        @error('delete_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @endif
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

@once
    @push('scripts')
        <script>
            (function () {
                const failedId = @json((string) old('purged_id'));

                if (!failedId) {
                    return;
                }

                document.addEventListener('DOMContentLoaded', function () {
                    const modal = document.getElementById('purgeAccountantModal-' + failedId)
                        || document.getElementById('purgeAccountantModal');

                    if (modal && window.bootstrap) {
                        window.bootstrap.Modal.getOrCreateInstance(modal).show();
                    }
                });
            })();
        </script>
    @endpush
@endonce
