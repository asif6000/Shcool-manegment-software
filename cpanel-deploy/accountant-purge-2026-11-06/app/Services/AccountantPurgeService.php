<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Models\Voucher;
use App\Support\AuditLogger;
use App\Support\FileUploadService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Removes an accountant completely: the login, the staff profile and every
 * financial record that was posted in their name. This is deliberately not the
 * generic delete path, because money is involved: receipts and allocations must
 * go before their payment, and every invoice those payments had settled has to
 * be reopened by exactly the amount the accountant collected.
 *
 * A JSON copy of everything about to be removed is written to the private disk
 * before the first delete, and the whole purge runs in one transaction, so a
 * failure leaves both the database and the accountant untouched.
 */
class AccountantPurgeService
{
    private const BACKUP_DIRECTORY = 'backups/accountant-purge';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FileUploadService $uploads,
    ) {}

    /**
     * What the confirmation dialog tells the admin before the button is usable.
     *
     * @return array<string, int|float|bool>
     */
    public function preview(User $accountant): array
    {
        $schoolId = (int) $accountant->school_id;

        $payments = DB::table('fee_payments')
            ->where('school_id', $schoolId)
            ->where('received_by', $accountant->id);

        $paymentIds = $payments->pluck('id')->all();
        $paymentCount = count($paymentIds);
        $paymentAmount = (float) DB::table('fee_payments')
            ->where('school_id', $schoolId)
            ->where('received_by', $accountant->id)
            ->sum('amount');

        $students = $paymentCount > 0
            ? (int) DB::table('fee_payments')
                ->where('school_id', $schoolId)
                ->where('received_by', $accountant->id)
                ->distinct()
                ->count('student_id')
            : 0;

        $allocations = $this->allocationTotals($paymentIds);
        $invoices = $allocations['invoices'];
        $reopenedDue = $this->reopenedDue($allocations['rows']);

        $receipts = $paymentCount > 0
            ? (int) DB::table('fee_receipts')->whereIn('fee_payment_id', $paymentIds)->count()
            : 0;

        $expenseQuery = DB::table('expenses')
            ->where('school_id', $schoolId)
            ->where('created_by', $accountant->id);
        $expenseCount = (int) (clone $expenseQuery)->count();
        $expenseAmount = (float) (clone $expenseQuery)->sum('amount');

        $voucherQuery = DB::table('vouchers')
            ->where('school_id', $schoolId)
            ->where('created_by', $accountant->id);
        $voucherCount = (int) (clone $voucherQuery)->count();
        $voucherAmount = (float) (clone $voucherQuery)->sum('amount');

        $employeeIds = DB::table('employees')
            ->where('school_id', $schoolId)
            ->where('user_id', $accountant->id)
            ->pluck('id')
            ->all();

        $salaryPayments = $employeeIds === []
            ? 0
            : (int) DB::table('salary_payments')->whereIn('employee_id', $employeeIds)->count();
        $salaryAmount = $employeeIds === []
            ? 0.0
            : (float) DB::table('salary_payments')->whereIn('employee_id', $employeeIds)->sum('net_salary');

        $attendance = $employeeIds === []
            ? 0
            : (int) DB::table('staff_attendances')->whereIn('employee_id', $employeeIds)->count();
        $leaves = $employeeIds === []
            ? 0
            : (int) DB::table('staff_leaves')->whereIn('employee_id', $employeeIds)->count();

        $cashIds = $this->cashIds(
            $schoolId,
            (int) $accountant->id,
            $paymentIds,
            (clone $expenseQuery)->pluck('id')->all(),
            (clone $voucherQuery)->pluck('id')->all(),
            $employeeIds === []
                ? []
                : DB::table('salary_payments')->whereIn('employee_id', $employeeIds)->pluck('id')->all(),
        );

        $salaryStructures = $employeeIds === []
            ? 0
            : (int) DB::table('salary_structures')->whereIn('employee_id', $employeeIds)->count();

        $morph = $accountant->getMorphClass();

        $sessions = (int) DB::table(config('session.table', 'sessions'))->where('user_id', $accountant->id)->count();
        $tokens = (int) DB::table('personal_access_tokens')
            ->where('tokenable_type', $morph)
            ->where('tokenable_id', $accountant->id)
            ->count();
        $notifications = (int) DB::table('notifications')
            ->where('notifiable_type', $morph)
            ->where('notifiable_id', $accountant->id)
            ->count();
        $notificationPreferences = (int) DB::table('notification_preferences')->where('user_id', $accountant->id)->count();
        $pushSubscriptions = (int) DB::table('push_subscriptions')->where('user_id', $accountant->id)->count();
        $pushLogs = (int) DB::table('push_delivery_logs')->where('user_id', $accountant->id)->count();
        $deviceTokens = (int) DB::table('device_push_tokens')->where('user_id', $accountant->id)->count();
        $smsLogs = (int) DB::table('sms_logs')->where('user_id', $accountant->id)->count();
        $roleLinks = (int) DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->where('model_type', $morph)
            ->where('model_id', $accountant->id)
            ->count();
        $permissionLinks = (int) DB::table(config('permission.table_names.model_has_permissions', 'model_has_permissions'))
            ->where('model_type', $morph)
            ->where('model_id', $accountant->id)
            ->count();
        $resetTokens = filled($accountant->email)
            ? (int) DB::table('password_reset_tokens')->where('email', $accountant->email)->count()
            : 0;

        $total = $paymentCount + $receipts + count($cashIds) + $expenseCount + $voucherCount
            + $salaryStructures + $salaryPayments + $attendance + $leaves + ($employeeIds === [] ? 0 : 1)
            + $sessions + $tokens + $notifications + $notificationPreferences
            + $pushSubscriptions + $pushLogs + $deviceTokens
            + $roleLinks + $permissionLinks + $resetTokens + $smsLogs + 1;

        return [
            'payments' => $paymentCount,
            'payment_amount' => round($paymentAmount, 2),
            'students' => $students,
            'receipts' => $receipts,
            'invoices' => $invoices,
            'reopened_due' => $reopenedDue,
            'cash' => count($cashIds),
            'expenses' => $expenseCount,
            'expense_amount' => round($expenseAmount, 2),
            'vouchers' => $voucherCount,
            'voucher_amount' => round($voucherAmount, 2),
            'employee' => $employeeIds !== [],
            'attendance' => $attendance,
            'leaves' => $leaves,
            'salary_structures' => $salaryStructures,
            'salary_payments' => $salaryPayments,
            'salary_amount' => round($salaryAmount, 2),
            'sessions' => $sessions,
            'tokens' => $tokens,
            'notifications' => $notifications,
            'notification_preferences' => $notificationPreferences,
            'push_subscriptions' => $pushSubscriptions,
            'push_delivery_logs' => $pushLogs,
            'device_push_tokens' => $deviceTokens,
            'sms_logs' => $smsLogs,
            'role_links' => $roleLinks,
            'permission_links' => $permissionLinks,
            'password_reset_tokens' => $resetTokens,
            'total' => $total,
        ];
    }

    /**
     * @return array{backup: string, removed: array<string, int>, files: list<string>, counts: array<string, int|float|bool>}
     */
    public function purge(User $actor, User $accountant): array
    {
        if ($accountant->is($actor)) {
            throw ValidationException::withMessages([
                'accountant' => __('You cannot permanently delete your own account.'),
            ]);
        }

        $files = ['avatars' => [], 'photos' => []];

        $result = DB::transaction(function () use ($actor, $accountant, &$files): array {
            $locked = User::withTrashed()->lockForUpdate()->findOrFail($accountant->getKey());

            if ((int) $locked->school_id !== (int) $actor->school_id) {
                throw new AuthorizationException(__('This action is unauthorized.'));
            }

            if (! $locked->hasRole('accountant')) {
                throw ValidationException::withMessages([
                    'accountant' => __('Only an accountant login can be permanently deleted here.'),
                ]);
            }

            $otherRoles = $locked->roles()->where('name', '!=', 'accountant')->pluck('name');

            if ($otherRoles->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'accountant' => __(':name also holds the :roles role. Remove the other roles before deleting the accountant.', [
                        'name' => $locked->name,
                        'roles' => $otherRoles->implode(', '),
                    ]),
                ]);
            }

            $schoolId = (int) $locked->school_id;

            $payments = DB::table('fee_payments')
                ->where('school_id', $schoolId)
                ->where('received_by', $locked->id)
                ->lockForUpdate()
                ->get();

            $paymentIds = $payments->pluck('id')->all();

            $allocationRows = $paymentIds === []
                ? collect()
                : DB::table('fee_payment_allocations')
                    ->whereIn('fee_payment_id', $paymentIds)
                    ->lockForUpdate()
                    ->get();

            $receiptRows = $paymentIds === []
                ? collect()
                : DB::table('fee_receipts')->whereIn('fee_payment_id', $paymentIds)->get();

            $expenses = DB::table('expenses')
                ->where('school_id', $schoolId)
                ->where('created_by', $locked->id)
                ->lockForUpdate()
                ->get();

            $vouchers = DB::table('vouchers')
                ->where('school_id', $schoolId)
                ->where('created_by', $locked->id)
                ->lockForUpdate()
                ->get();

            $employees = DB::table('employees')
                ->where('school_id', $schoolId)
                ->where('user_id', $locked->id)
                ->lockForUpdate()
                ->get();

            $employeeIds = $employees->pluck('id')->all();

            $staffRows = $this->staffRows($employeeIds);

            $cashIds = $this->cashIds(
                $schoolId,
                (int) $locked->id,
                $paymentIds,
                $expenses->pluck('id')->all(),
                $vouchers->pluck('id')->all(),
                $staffRows['salary_payments']->pluck('id')->all(),
            );

            $cashRows = $cashIds === []
                ? collect()
                : DB::table('cash_transactions')->whereIn('id', $cashIds)->get();

            $invoiceRows = $allocationRows->pluck('fee_invoice_id')->unique()->values()->all();

            $invoices = $invoiceRows === []
                ? collect()
                : FeeInvoice::withoutSchoolScope()
                    ->where('school_id', $schoolId)
                    ->whereIn('id', $invoiceRows)
                    ->lockForUpdate()
                    ->get();

            $backupPath = $this->writeBackup($locked, $actor, [
                'payments' => $payments,
                'allocations' => $allocationRows,
                'receipts' => $receiptRows,
                'expenses' => $expenses,
                'vouchers' => $vouchers,
                'employees' => $employees,
                'staff_attendance' => $staffRows['attendance'],
                'staff_leaves' => $staffRows['leaves'],
                'salary_structures' => $staffRows['salary_structures'],
                'salary_payments' => $staffRows['salary_payments'],
                'cash_transactions' => $cashRows,
                'invoices' => $invoices,
                'employee_ids' => $employeeIds,
                'payment_ids' => $paymentIds,
                'cash_ids' => $cashIds,
            ]);

            $this->reopenInvoices($invoices, $allocationRows);

            $counts = $this->preview($locked);

            $removed = [];

            if ($cashIds !== []) {
                $removed['cash_transactions'] = DB::table('cash_transactions')->whereIn('id', $cashIds)->delete();
            }

            if ($paymentIds !== []) {
                // Receipts refuse model-level deletes by design; the purge
                // has already backed them up and removes them with their
                // payment, which the FK would otherwise refuse.
                $removed['fee_receipts'] = DB::table('fee_receipts')->whereIn('fee_payment_id', $paymentIds)->delete();
                $removed['fee_payment_allocations'] = DB::table('fee_payment_allocations')->whereIn('fee_payment_id', $paymentIds)->delete();
                $removed['fee_payments'] = DB::table('fee_payments')->whereIn('id', $paymentIds)->delete();
            }

            if ($expenses->isNotEmpty()) {
                $removed['expenses'] = DB::table('expenses')->whereIn('id', $expenses->pluck('id'))->delete();
            }

            if ($vouchers->isNotEmpty()) {
                $removed['vouchers'] = DB::table('vouchers')->whereIn('id', $vouchers->pluck('id'))->delete();
            }

            if ($employeeIds !== []) {
                $removed['salary_payments'] = DB::table('salary_payments')->whereIn('employee_id', $employeeIds)->delete();
                $removed['salary_structures'] = DB::table('salary_structures')->whereIn('employee_id', $employeeIds)->delete();
                $removed['staff_attendances'] = DB::table('staff_attendances')->whereIn('employee_id', $employeeIds)->delete();
                $removed['staff_leaves'] = DB::table('staff_leaves')->whereIn('employee_id', $employeeIds)->delete();
                $removed['employees'] = DB::table('employees')->whereIn('id', $employeeIds)->delete();
            }

            $removed += $this->removeAccountRows($locked);

            $files = [
                'avatars' => filled($locked->avatar_path) ? [$locked->avatar_path] : [],
                'photos' => $employees->pluck('photo_path')->filter()->values()->all(),
            ];

            $this->audit->log(
                event: 'accountant.purged',
                auditable: $locked,
                description: __('Accountant :name (:mobile) and every associated record were permanently deleted.', [
                    'name' => $locked->name,
                    'mobile' => (string) $locked->mobile_number,
                ]),
                oldValues: [
                    'accountant_id' => $locked->id,
                    'name' => $locked->name,
                    'mobile_number' => $locked->mobile_number,
                    'employee_code' => $employees->first()?->employee_code,
                    'removed' => $removed,
                    'backup' => $backupPath,
                ],
                newValues: ['deleted' => true],
                actor: $actor,
                schoolId: $schoolId,
            );

            $locked->forceDelete();

            return [
                'backup' => (string) $backupPath,
                'removed' => $removed,
                'counts' => $counts,
            ];
        });

        // Files are removed only once the rows that named them are committed.
        foreach ($files['avatars'] as $path) {
            $this->uploads->delete($path, 'user_avatar');
        }

        foreach ($files['photos'] as $path) {
            $this->uploads->delete($path, 'teacher_photo');
        }

        return [
            'backup' => $result['backup'],
            'removed' => $result['removed'],
            'files' => array_values(array_merge($files['avatars'], $files['photos'])),
            'counts' => $result['counts'],
        ];
    }

    /**
     * @param  list<int|string>  $paymentIds
     * @return array{rows: Collection<int, object>, invoices: int}
     */
    private function allocationTotals(array $paymentIds): array
    {
        if ($paymentIds === []) {
            return ['rows' => collect(), 'invoices' => 0];
        }

        $select = ['fee_invoice_id'];
        $select[] = DB::raw('SUM(amount) as amount');
        $select[] = Schema::hasColumn('fee_payment_allocations', 'waived_amount')
            ? DB::raw('SUM(waived_amount) as waived')
            : DB::raw('0 as waived');

        $rows = DB::table('fee_payment_allocations')
            ->whereIn('fee_payment_id', $paymentIds)
            ->groupBy('fee_invoice_id')
            ->get($select);

        return ['rows' => $rows, 'invoices' => $rows->count()];
    }

    /**
     * The exact amount of due each affected invoice gets back, mirroring the
     * balance rules FeePaymentService::reverse() uses.
     *
     * @param  Collection<int, object>  $allocationRows
     */
    private function reopenedDue(Collection $allocationRows): float
    {
        if ($allocationRows->isEmpty()) {
            return 0.0;
        }

        $hasWaived = Schema::hasColumn('fee_invoices', 'waived_total')
            && Schema::hasColumn('fee_payment_allocations', 'waived_amount');

        $invoices = DB::table('fee_invoices')
            ->whereIn('id', $allocationRows->pluck('fee_invoice_id')->all())
            ->get(['id', 'total', 'paid_total', 'due_total', ...($hasWaived ? ['waived_total'] : [])])
            ->keyBy('id');

        $restored = 0.0;

        foreach ($allocationRows as $row) {
            $invoice = $invoices->get($row->fee_invoice_id);

            if ($invoice === null) {
                continue;
            }

            $paid = max(0, round((float) $invoice->paid_total - (float) $row->amount, 2));
            $waived = max(0, round((float) ($invoice->waived_total ?? 0) - (float) ($row->waived ?? 0), 2));
            $due = max(0, round((float) $invoice->total - $paid - $waived, 2));

            $restored += max(0, round($due - (float) $invoice->due_total, 2));
        }

        return round($restored, 2);
    }

    /**
     * Cash rows that belong to this purge: the accountant's own manual entries,
     * every row sourced from a record being removed, and the reversal chain
     * hanging off those rows. Rows for another user's preserved source are left
     * alone and only lose the creator reference.
     *
     * @param  list<int|string>  $paymentIds
     * @param  list<int|string>  $expenseIds
     * @param  list<int|string>  $voucherIds
     * @param  list<int|string>  $salaryPaymentIds
     * @return list<int>
     */
    private function cashIds(
        int $schoolId,
        int $accountantId,
        array $paymentIds,
        array $expenseIds,
        array $voucherIds,
        array $salaryPaymentIds,
    ): array {
        $sources = [
            (new FeePayment)->getMorphClass() => $paymentIds,
            (new Expense)->getMorphClass() => $expenseIds,
            (new Voucher)->getMorphClass() => $voucherIds,
            (new SalaryPayment)->getMorphClass() => $salaryPaymentIds,
        ];

        $ids = DB::table('cash_transactions')
            ->where('school_id', $schoolId)
            ->where(function ($query) use ($accountantId, $sources): void {
                $query->where(function ($manual) use ($accountantId): void {
                    $manual->where('created_by', $accountantId)
                        ->where(function ($source): void {
                            $source->whereNull('source_type')->orWhereNull('source_id');
                        });
                });

                foreach ($sources as $morph => $recordIds) {
                    if ($recordIds === []) {
                        continue;
                    }

                    $query->orWhere(function ($source) use ($morph, $recordIds): void {
                        $source->where('source_type', $morph)->whereIn('source_id', $recordIds);
                    });
                }
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $seen = array_fill_keys($ids, true);

        // A reversal of a deleted entry has no meaning, whoever posted it, so
        // the whole chain down to the last reversal is removed together.
        do {
            $children = DB::table('cash_transactions')
                ->where('school_id', $schoolId)
                ->whereIn('reversal_of_id', array_keys($seen))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $new = array_values(array_filter($children, fn (int $id): bool => ! isset($seen[$id])));

            foreach ($new as $id) {
                $seen[$id] = true;
            }
        } while ($new !== []);

        return array_keys($seen);
    }

    /**
     * @param  list<int|string>  $employeeIds
     * @return array{attendance: Collection<int, object>, leaves: Collection<int, object>, salary_structures: Collection<int, object>, salary_payments: Collection<int, object>}
     */
    private function staffRows(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [
                'attendance' => collect(),
                'leaves' => collect(),
                'salary_structures' => collect(),
                'salary_payments' => collect(),
            ];
        }

        return [
            'attendance' => DB::table('staff_attendances')->whereIn('employee_id', $employeeIds)->get(),
            'leaves' => DB::table('staff_leaves')->whereIn('employee_id', $employeeIds)->get(),
            'salary_structures' => DB::table('salary_structures')->whereIn('employee_id', $employeeIds)->get(),
            'salary_payments' => DB::table('salary_payments')->whereIn('employee_id', $employeeIds)->get(),
        ];
    }

    /**
     * Reopen every invoice by exactly the share this accountant had settled.
     *
     * @param  Collection<int, FeeInvoice>  $invoices
     * @param  Collection<int, object>  $allocations
     */
    private function reopenInvoices(Collection $invoices, Collection $allocations): void
    {
        if ($invoices->isEmpty() || $allocations->isEmpty()) {
            return;
        }

        $grouped = $allocations->groupBy('fee_invoice_id');
        $hasWaived = Schema::hasColumn('fee_invoices', 'waived_total')
            && Schema::hasColumn('fee_payment_allocations', 'waived_amount');
        $hasActual = Schema::hasColumn('fee_invoices', 'actual_collected_amount');

        foreach ($invoices as $invoice) {
            $rows = $grouped->get($invoice->id, collect());

            $paid = max(0, round((float) $invoice->paid_total - (float) $rows->sum('amount'), 2));
            $waived = max(0, round((float) ($invoice->waived_total ?? 0) - (float) $rows->sum('waived'), 2));
            $due = max(0, round((float) $invoice->total - $paid - $waived, 2));

            $values = [
                'paid_total' => $paid,
                'due_total' => $due,
                'status' => FeeInvoice::statusForCollection((float) $invoice->total, $paid, $due),
                'updated_at' => now(),
            ];

            if ($hasWaived) {
                $values['waived_total'] = $waived;
            }

            if ($hasActual) {
                $values['actual_collected_amount'] = $paid;
            }

            DB::table('fee_invoices')->where('id', $invoice->id)->update($values);
        }
    }

    /**
     * Rows that name the user but are not linked by a foreign key, plus the
     * sessions and tokens that must stop working immediately.
     *
     * @return array<string, int>
     */
    private function removeAccountRows(User $user): array
    {
        $morph = $user->getMorphClass();

        $removed = [];

        $removed['notifications'] = DB::table('notifications')
            ->where('notifiable_type', $morph)
            ->where('notifiable_id', $user->id)
            ->delete();

        $removed['notification_preferences'] = DB::table('notification_preferences')
            ->where('user_id', $user->id)
            ->delete();

        $removed['push_delivery_logs'] = DB::table('push_delivery_logs')
            ->where('user_id', $user->id)
            ->delete();

        $removed['push_subscriptions'] = DB::table('push_subscriptions')
            ->where('user_id', $user->id)
            ->delete();

        $removed['device_push_tokens'] = DB::table('device_push_tokens')
            ->where('user_id', $user->id)
            ->delete();

        $removed['sms_logs'] = DB::table('sms_logs')
            ->where('user_id', $user->id)
            ->delete();

        $removed['sessions'] = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();

        $removed['personal_access_tokens'] = DB::table('personal_access_tokens')
            ->where('tokenable_type', $morph)
            ->where('tokenable_id', $user->id)
            ->delete();

        if (filled($user->email)) {
            $removed['password_reset_tokens'] = DB::table('password_reset_tokens')
                ->where('email', $user->email)
                ->delete();
        }

        $removed['model_has_roles'] = DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->where('model_type', $morph)
            ->where('model_id', $user->id)
            ->delete();

        $removed['model_has_permissions'] = DB::table(config('permission.table_names.model_has_permissions', 'model_has_permissions'))
            ->where('model_type', $morph)
            ->where('model_id', $user->id)
            ->delete();

        return $removed;
    }

    /**
     * Everything about to disappear, on the private disk. Written inside the
     * transaction before the first delete; a failed write aborts the purge.
     *
     * @param  array<string, mixed>  $rows
     */
    private function writeBackup(User $accountant, User $actor, array $rows): string
    {
        $path = self::BACKUP_DIRECTORY.'/accountant-'.$accountant->id.'-'.now()->format('Ymd-His').'.json';

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'generated_by' => [
                'id' => $actor->id,
                'name' => $actor->name,
            ],
            'accountant' => [
                'id' => $accountant->id,
                'name' => $accountant->name,
                'mobile_number' => $accountant->mobile_number,
                'email' => $accountant->email,
                'phone' => $accountant->phone,
                'school_id' => $accountant->school_id,
                'roles' => $accountant->getRoleNames()->all(),
                'employee_code' => $rows['employees']->first()?->employee_code,
                'created_at' => $accountant->created_at?->toIso8601String(),
                'last_login_at' => $accountant->last_login_at?->toIso8601String(),
            ],
            'counts' => [
                'payments' => $rows['payments']->count(),
                'allocations' => $rows['allocations']->count(),
                'receipts' => $rows['receipts']->count(),
                'cash_transactions' => $rows['cash_transactions']->count(),
                'expenses' => $rows['expenses']->count(),
                'vouchers' => $rows['vouchers']->count(),
                'employees' => $rows['employees']->count(),
                'salary_payments' => $rows['salary_payments']->count(),
            ],
            'rows' => [
                'payments' => $rows['payments']->map(fn ($row): array => (array) $row)->all(),
                'allocations' => $rows['allocations']->map(fn ($row): array => (array) $row)->all(),
                'receipts' => $rows['receipts']->map(fn ($row): array => (array) $row)->all(),
                'cash_transactions' => $rows['cash_transactions']->map(fn ($row): array => (array) $row)->all(),
                'expenses' => $rows['expenses']->map(fn ($row): array => (array) $row)->all(),
                'vouchers' => $rows['vouchers']->map(fn ($row): array => (array) $row)->all(),
                'employees' => $rows['employees']->map(fn ($row): array => (array) $row)->all(),
                'staff_attendance' => $rows['staff_attendance']->map(fn ($row): array => (array) $row)->all(),
                'staff_leaves' => $rows['staff_leaves']->map(fn ($row): array => (array) $row)->all(),
                'salary_structures' => $rows['salary_structures']->map(fn ($row): array => (array) $row)->all(),
                'salary_payments' => $rows['salary_payments']->map(fn ($row): array => (array) $row)->all(),
                'invoices_before' => $rows['invoices']->map(fn ($row): array => $row->toArray())->all(),
            ],
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($json)) {
            throw new RuntimeException(__('The accountant backup could not be encoded.'));
        }

        if (! Storage::disk('local')->put($path, $json)) {
            throw new RuntimeException(__('The accountant backup could not be written; nothing was deleted.'));
        }

        return $path;
    }
}
