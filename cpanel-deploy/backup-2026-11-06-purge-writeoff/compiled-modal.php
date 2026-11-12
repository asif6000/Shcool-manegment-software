<?php
    $modalId = $modalId ?? 'purgeAccountantModal';
    $purgeFailed = (string) old('purged_id') === (string) $accountant->id
        && ($errors->has('admin_password') || $errors->has('delete_confirmation'));
?>

<div class="modal fade" id="<?php echo e($modalId); ?>" tabindex="-1" aria-labelledby="<?php echo e($modalId); ?>Label" aria-hidden="true" style="z-index: 2011;">
    
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        
        <form class="modal-content" method="POST" action="<?php echo e(route('accountants.destroy', $accountant)); ?>" data-purge-form>
            <?php echo csrf_field(); ?>
            <?php echo method_field('DELETE'); ?>
            <input type="hidden" name="purged_id" value="<?php echo e($accountant->id); ?>">

            <div class="modal-header">
                <h2 class="modal-title h5 text-danger" id="<?php echo e($modalId); ?>Label">
                    <i class="bi bi-exclamation-octagon"></i>
                    <?php echo e(__('Permanently delete :name', ['name' => $accountant->name])); ?>

                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo e(__('Close')); ?>"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-octagon"></i>
                    <div>
                        <strong><?php echo e(__('Warning:')); ?></strong>
                        <?php echo e(__('ALL records associated with this accountant will be permanently deleted. This includes fee collections, receipts, cashbook entries, expenses, vouchers and the staff profile. The deletion cannot be undone through the app.')); ?>

                        <div class="mt-2 fw-semibold">
                            <?php echo e(__('Total related records to be deleted: :count', ['count' => number_format($purge['total'])])); ?>

                        </div>
                        <div class="small mt-1">
                            <?php echo e(__('That count is the login itself plus every row listed below, including the role, session and token rows.')); ?>

                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <div class="text-muted-2 small"><?php echo e(__('Accountant')); ?></div>
                        <div class="fw-600"><?php echo e($accountant->name); ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted-2 small"><?php echo e(__('Mobile number')); ?></div>
                        <div class="fw-600"><?php echo e($accountant->mobile_number ?: '—'); ?></div>
                    </div>
                </div>

                <h3 class="h6 fw-bold"><?php echo e(__('Records that will be removed')); ?></h3>
                <ul class="small mb-3">
                    <li><?php echo e(trans_choice(':count fee collection|:count fee collections', $purge['payments'], ['count' => $purge['payments']])); ?>

                        · ৳<?php echo e(number_format($purge['payment_amount'], 2)); ?>

                        · <?php echo e(trans_choice(':count student|:count students', $purge['students'], ['count' => $purge['students']])); ?></li>
                    <li><?php echo e(trans_choice(':count receipt|:count receipts', $purge['receipts'], ['count' => $purge['receipts']])); ?></li>
                    <li><?php echo e(trans_choice(':count cashbook entry|:count cashbook entries', $purge['cash'], ['count' => $purge['cash']])); ?></li>
                    <li><?php echo e(trans_choice(':count expense|:count expenses', $purge['expenses'], ['count' => $purge['expenses']])); ?>

                        · ৳<?php echo e(number_format($purge['expense_amount'], 2)); ?></li>
                    <li><?php echo e(trans_choice(':count voucher|:count vouchers', $purge['vouchers'], ['count' => $purge['vouchers']])); ?>

                        · ৳<?php echo e(number_format($purge['voucher_amount'], 2)); ?></li>
                    <li><?php echo e($purge['employee'] ? __('Staff profile and employee record') : __('No staff profile is linked')); ?>

                        <?php if($purge['attendance'] || $purge['leaves'] || $purge['salary_payments']): ?>
                            · <?php echo e(__(':attendance attendance, :leaves leave and :salary payroll rows', [
                                'attendance' => $purge['attendance'],
                                'leaves' => $purge['leaves'],
                                'salary' => $purge['salary_payments'],
                            ])); ?>

                        <?php endif; ?>
                    </li>
                    <li><?php echo e(__(':sessions sessions and :tokens API tokens', ['sessions' => $purge['sessions'], 'tokens' => $purge['tokens']])); ?></li>
                </ul>

                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-calculator"></i>
                    <div>
                        <?php echo e(trans_choice(':count invoice|:count invoices', $purge['invoices'], ['count' => $purge['invoices']])); ?>

                        <?php echo e(__('will be recalculated from the payments that remain. Student dues increase by about ৳:amount, and the removed collections disappear from every report and the cashbook.', ['amount' => number_format($purge['reopened_due'], 2)])); ?>

                    </div>
                </div>

                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-journal-x"></i>
                    <div>
                        <?php echo e(__('The fee collections above are money the school already received. Removing their receipts and cashbook rows makes the books disagree with the cash actually in hand, so the backup is the only remaining record of those transactions.')); ?>

                    </div>
                </div>

                <div class="mb-3">
                    <label for="<?php echo e($modalId); ?>AdminPassword" class="form-label"><?php echo e(__('Your admin password')); ?><span class="required">*</span></label>
                    <div class="input-icon">
                        <i class="bi bi-shield-lock"></i>
                        <input type="password" id="<?php echo e($modalId); ?>AdminPassword" name="admin_password" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['form-control', 'is-invalid' => $purgeFailed && $errors->has('admin_password')]); ?>" required autocomplete="current-password">
                    </div>
                    <?php if($purgeFailed): ?>
                        <?php $__errorArgs = ['admin_password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback d-block"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="<?php echo e($modalId); ?>DeleteConfirmation" class="form-label"><?php echo e(__('Type DELETE ALL to confirm')); ?><span class="required">*</span></label>
                    <input type="text" id="<?php echo e($modalId); ?>DeleteConfirmation" name="delete_confirmation" value="<?php echo e($purgeFailed ? old('delete_confirmation') : ''); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['form-control', 'is-invalid' => $purgeFailed && $errors->has('delete_confirmation')]); ?>" placeholder="DELETE ALL" autocomplete="off" required>
                    <?php if($purgeFailed): ?>
                        <?php $__errorArgs = ['delete_confirmation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    <?php endif; ?>
                    <div class="form-hint"><?php echo e(__('Type the phrase exactly as shown; the server checks it before anything is deleted.')); ?></div>
                </div>

                <p class="small text-muted-2 mb-0">
                    <i class="bi bi-archive"></i>
                    <?php echo e(__('A JSON backup of the accountant and every record about to be removed is written to the private storage disk first. The path is shown on the accountant list after deletion.')); ?>

                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light-soft" data-bs-dismiss="modal"><?php echo e(__('Cancel')); ?></button>
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-trash3"></i><?php echo e(__('Delete everything permanently')); ?>

                </button>
            </div>
        </form>
    </div>
</div>

<?php if (! $__env->hasRenderedOnce('4230fad8-e19f-47f6-aa9f-b1642376f81e')): $__env->markAsRenderedOnce('4230fad8-e19f-47f6-aa9f-b1642376f81e'); ?>
    <?php $__env->startPush('scripts'); ?>
        <script>
            (function () {
                const failedId = <?php echo json_encode((string) old('purged_id'), 15, 512) ?>;

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
    <?php $__env->stopPush(); ?>
<?php endif; ?>
<?php /**PATH /home/irvrzogs/dream_app/resources/views/accountants/partials/purge-modal.blade.php ENDPATH**/ ?>