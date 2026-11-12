<?php use \App\Support\Money; ?>


<?php $__env->startSection('title', __('Accountant list')); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-badge"></i><?php echo e(__('Accountant Management')); ?></span>
            <h1 class="page-title"><?php echo e(__('Accountant list')); ?></h1>
            <p class="page-subtitle"><?php echo e(__('Every account holder who collects fees, with the money that actually passed through their hands.')); ?></p>
        </div>
        <div class="page-actions">
            <a href="<?php echo e(route('accountants.daily')); ?>" class="btn btn-light-soft"><i class="bi bi-calendar-day"></i><?php echo e(__('Daily report')); ?></a>
            <a href="<?php echo e(route('accountants.monthly')); ?>" class="btn btn-light-soft"><i class="bi bi-calendar-month"></i><?php echo e(__('Monthly report')); ?></a>
            <?php if($canExport): ?>
                <a href="<?php echo e(route('accountants.export', array_filter(['q' => $search, 'status' => $status]))); ?>" class="btn btn-light-soft">
                    <i class="bi bi-filetype-csv"></i><?php echo e(__('Export CSV')); ?>

                </a>
            <?php endif; ?>
            <?php if($canCreate): ?>
                <a href="<?php echo e(route('accountants.create')); ?>" class="btn btn-primary"><i class="bi bi-person-plus"></i><?php echo e(__('Add accountant')); ?></a>
            <?php endif; ?>
        </div>
    </div>

    <section class="stats-grid mb-3" aria-label="<?php echo e(__('Roster totals')); ?>">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label"><?php echo e(__('Accountants')); ?></p>
                    <p class="stat-value"><?php echo e(number_format($totals['accountants'])); ?></p>
                </div>
                <span class="stat-icon" aria-hidden="true"><i class="bi bi-person-badge"></i></span>
            </div>
            <div class="stat-foot"><span><?php echo e(trans_choice(':count active|:count active', $totals['active'], ['count' => $totals['active']])); ?></span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label"><?php echo e(__('Today collection')); ?></p>
                    <p class="stat-value"><?php echo e(Money::format($totals['today'])); ?></p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-sunrise"></i></span>
            </div>
            <div class="stat-foot"><span><?php echo e(__('Posted payments taken today')); ?></span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label"><?php echo e(__('This month')); ?></p>
                    <p class="stat-value"><?php echo e(Money::format($totals['month'])); ?></p>
                </div>
                <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-calendar-month"></i></span>
            </div>
            <div class="stat-foot"><span><?php echo e(now()->translatedFormat('F Y')); ?></span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label"><?php echo e(__('Total collected')); ?></p>
                    <p class="stat-value"><?php echo e(Money::format($totals['total'])); ?></p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-cash-stack"></i></span>
            </div>
            <div class="stat-foot">
                <span><?php echo e(trans_choice(':count payment|:count payments', $totals['payments'], ['count' => $totals['payments']])); ?></span>
            </div>
        </article>
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="<?php echo e(route('accountants.index')); ?>" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="accountant-search"><?php echo e(__('Search accountants')); ?></label>
                    <input id="accountant-search" type="search" name="q" class="form-control" value="<?php echo e($search); ?>" placeholder="<?php echo e(__('Name, mobile number, phone or employee ID')); ?>">
                </div>
                <label class="visually-hidden" for="filter-status"><?php echo e(__('Account status')); ?></label>
                <select id="filter-status" name="status" class="form-select" data-auto-submit>
                    <option value=""><?php echo e(__('All statuses')); ?></option>
                    <option value="active" <?php if($status === 'active'): echo 'selected'; endif; ?>><?php echo e(__('Active')); ?></option>
                    <option value="inactive" <?php if($status === 'inactive'): echo 'selected'; endif; ?>><?php echo e(__('Inactive')); ?></option>
                </select>
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i><?php echo e(__('Filter')); ?></button>
                <?php if($search !== '' || $status !== ''): ?>
                    <a href="<?php echo e(route('accountants.index')); ?>" class="btn btn-sm btn-link"><?php echo e(__('Clear')); ?></a>
                <?php endif; ?>
            </form>
            <span class="text-muted-2" style="font-size:.78rem;"><?php echo e(trans_choice(':count collector|:count collectors', $accountants->total(), ['count' => $accountants->total()])); ?></span>
        </div>

        <?php if($accountants->isEmpty()): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-person-lines-fill"></i></div>
                <h3><?php echo e(__('No collectors found')); ?></h3>
                <p><?php echo e(__('Someone appears here once they hold the accountant role or a fee payment has been posted under their name.')); ?></p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo e(__('Accountant')); ?></th>
                            <th scope="col"><?php echo e(__('Employee ID')); ?></th>
                            <th scope="col"><?php echo e(__('Phone')); ?></th>
                            <th scope="col"><?php echo e(__('Login')); ?></th>
                            <th scope="col"><?php echo e(__('Status')); ?></th>
                            <th scope="col" class="text-end"><?php echo e(__('Total collection')); ?></th>
                            <th scope="col" class="text-end"><?php echo e(__('Today')); ?></th>
                            <th scope="col" class="text-end"><?php echo e(__('This month')); ?></th>
                            <th scope="col" class="text-end"><?php echo e(__('Students')); ?></th>
                            <th scope="col" class="text-end"><?php echo e(__('Actions')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $accountants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $accountant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td>
                                    <div class="cell-person">
                                        <span class="avatar">
                                            <?php if($accountant->avatarUrl()): ?>
                                                <img src="<?php echo e($accountant->avatarUrl()); ?>" alt="">
                                            <?php else: ?>
                                                <?php echo e($accountant->initials()); ?>

                                            <?php endif; ?>
                                        </span>
                                        <span>
                                            <span class="name">
                                                <a href="<?php echo e(route('accountants.show', $accountant)); ?>"><?php echo e($accountant->name); ?></a>
                                            </span>
                                            <span class="email"><?php echo e($accountant->getAttribute('employee_designation') ?: $accountant->primaryRoleLabel()); ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if($accountant->getAttribute('employee_code')): ?>
                                        <span class="fw-600"><?php echo e($accountant->getAttribute('employee_code')); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted-2"><?php echo e(__('Not linked')); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($accountant->getAttribute('employee_phone') ?: ($accountant->phone ?: '—')); ?></td>
                                <td style="font-size:.8rem;"><?php echo e($accountant->mobile_number ?: ($accountant->email ?: '—')); ?></td>
                                <td>
                                    <?php if($accountant->is_active): ?>
                                        <span class="badge-soft is-success"><span class="dot"></span><?php echo e(__('Active')); ?></span>
                                    <?php else: ?>
                                        <span class="badge-soft is-danger"><span class="dot"></span><?php echo e(__('Inactive')); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-600"><?php echo e(Money::format($accountant->collection_stats['total'])); ?></td>
                                <td class="text-end"><?php echo e(Money::format($accountant->collection_stats['today'])); ?></td>
                                <td class="text-end"><?php echo e(Money::format($accountant->collection_stats['month'])); ?></td>
                                <td class="text-end">
                                    <?php echo e(number_format($accountant->collection_stats['students'])); ?>

                                    <span class="d-block text-muted-2" style="font-size:.72rem;"><?php echo e(trans_choice(':count payment|:count payments', $accountant->collection_stats['payments'], ['count' => $accountant->collection_stats['payments']])); ?></span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="<?php echo e(route('accountants.show', $accountant)); ?>" class="icon-action" data-bs-toggle="tooltip" title="<?php echo e(__('View details')); ?>" aria-label="<?php echo e(__('View :name', ['name' => $accountant->name])); ?>">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $accountant)): ?>
                                            <a href="<?php echo e(route('accountants.edit', $accountant)); ?>" class="icon-action" data-bs-toggle="tooltip" title="<?php echo e(__('Edit')); ?>" aria-label="<?php echo e(__('Edit :name', ['name' => $accountant->name])); ?>">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="<?php echo e(route('accountants.edit', $accountant)); ?>#password" class="icon-action" data-bs-toggle="tooltip" title="<?php echo e(__('Reset password')); ?>" aria-label="<?php echo e(__('Reset the password for :name', ['name' => $accountant->name])); ?>">
                                                <i class="bi bi-key"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('changeStatus', $accountant)): ?>
                                            <form method="POST" action="<?php echo e(route('accountants.status', $accountant)); ?>" data-confirm="<?php echo e($accountant->is_active ? __('Deactivate :name? They will be signed out immediately.', ['name' => $accountant->name]) : __('Activate :name?', ['name' => $accountant->name])); ?>">
                                                <?php echo csrf_field(); ?>
                                                <?php echo method_field('PATCH'); ?>
                                                <button type="submit" class="icon-action" data-bs-toggle="tooltip" title="<?php echo e($accountant->is_active ? __('Deactivate') : __('Activate')); ?>" aria-label="<?php echo e($accountant->is_active ? __('Deactivate :name', ['name' => $accountant->name]) : __('Activate :name', ['name' => $accountant->name])); ?>">
                                                    <i class="bi <?php echo e($accountant->is_active ? 'bi-pause-circle' : 'bi-play-circle'); ?>"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete', $accountant)): ?>
                                            <button type="button" class="icon-action is-danger" data-bs-toggle="modal" data-bs-target="#purgeAccountantModal-<?php echo e($accountant->id); ?>" data-accountant-delete="<?php echo e($accountant->id); ?>" title="<?php echo e(__('Delete permanently')); ?>" aria-label="<?php echo e(__('Permanently delete :name', ['name' => $accountant->name])); ?>">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2"><?php echo e(__('Showing :from–:to of :total', ['from' => $accountants->firstItem(), 'to' => $accountants->lastItem(), 'total' => $accountants->total()])); ?></span>
                <?php echo e($accountants->links()); ?>

            </div>
        <?php endif; ?>
    </section>

    <?php $__currentLoopData = $accountants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $accountant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('delete', $accountant)): ?>
            <?php echo $__env->make('accountants.partials.purge-modal', [
                'accountant' => $accountant,
                'purge' => $purges[$accountant->id],
                'modalId' => 'purgeAccountantModal-'.$accountant->id,
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    <p class="text-muted-2 mt-3" style="font-size:.8rem;">
        <i class="bi bi-info-circle"></i>
        <?php echo e(__('Collections are posted payments only. A pending, held, cancelled or reversed receipt is never counted as money collected, so these totals match the cashbook and Collector activity line for line.')); ?>

    </p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/irvrzogs/dream_app/resources/views/accountants/index.blade.php ENDPATH**/ ?>