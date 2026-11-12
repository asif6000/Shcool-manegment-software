<?php

namespace App\Services;

use App\Models\SalaryPayment;
use App\Models\SalaryStructure;
use App\Models\School;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SchoolContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CashLedgerService $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function pay(User $actor, array $data): SalaryPayment
    {
        $schoolId = $this->schoolId();
        $month = Carbon::createFromFormat('Y-m', (string) $data['salary_month'])->startOfMonth();
        $paidOn = Carbon::parse((string) $data['paid_on']);

        return DB::transaction(function () use ($actor, $data, $schoolId, $month, $paidOn): SalaryPayment {
            School::query()->whereKey($schoolId)->lockForUpdate()->firstOrFail();

            /** @var SalaryStructure $structure */
            $structure = SalaryStructure::query()
                ->with(['teacher', 'employee'])
                ->findOrFail((int) $data['salary_structure_id']);

            if (! $structure->is_active) {
                throw ValidationException::withMessages([
                    'salary_structure_id' => __('Only active salary structures can be paid.'),
                ]);
            }

            $duplicate = SalaryPayment::query()
                ->where('salary_month', $month->toDateString())
                ->when(
                    $structure->teacher_id !== null,
                    fn ($query) => $query->where('teacher_id', $structure->teacher_id),
                    fn ($query) => $query->where('employee_id', $structure->employee_id),
                )
                ->where('status', '!=', 'reversed')
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'salary_month' => __('Salary for this staff member and month has already been recorded.'),
                ]);
            }

            $gross = round((float) $structure->gross_salary, 2);
            $basic = round((float) $structure->basic_salary, 2);
            $deductions = round((float) ($data['deductions_total'] ?? 0), 2);

            if ($deductions < 0 || $deductions > $gross) {
                throw ValidationException::withMessages([
                    'deductions_total' => __('Deductions must be between zero and the gross salary.'),
                ]);
            }

            $net = round($gross - $deductions, 2);

            if ($net <= 0) {
                throw ValidationException::withMessages([
                    'deductions_total' => __('The net salary must be greater than zero.'),
                ]);
            }

            $payment = SalaryPayment::query()->create([
                'salary_structure_id' => $structure->id,
                'teacher_id' => $structure->teacher_id,
                'employee_id' => $structure->employee_id,
                'salary_month' => $month->toDateString(),
                'basic_salary' => $basic,
                'allowances_total' => round($gross - $basic, 2),
                'deductions_total' => $deductions,
                'gross_salary' => $gross,
                'net_salary' => $net,
                'payment_method' => (string) $data['payment_method'],
                'reference_no' => $data['reference_no'] ?? null,
                'paid_on' => $paidOn->toDateString(),
                'status' => 'paid',
                'paid_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $name = $structure->staffName();

            $this->ledger->postExpense(
                actor: $actor,
                amount: $net,
                date: $paidOn->toDateString(),
                description: __('Salary :month · :name', ['month' => $month->format('F Y'), 'name' => $name]),
                source: $payment,
                reference: $payment->reference_no,
            );

            $this->audit->log(
                event: 'salary_payment.posted',
                auditable: $payment,
                description: __('Salary for :name (:month) was paid', ['name' => $name, 'month' => $month->format('F Y')]),
                newValues: [
                    'net_salary' => $net,
                    'gross_salary' => $gross,
                    'deductions_total' => $deductions,
                    'salary_month' => $month->toDateString(),
                ],
                actor: $actor,
                schoolId: $schoolId,
            );

            return $payment;
        });
    }

    public function reverse(User $actor, SalaryPayment $payment, string $reason): SalaryPayment
    {
        return DB::transaction(function () use ($actor, $payment, $reason): SalaryPayment {
            /** @var SalaryPayment $locked */
            $locked = SalaryPayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->status !== 'paid') {
                throw ValidationException::withMessages([
                    'payment' => __('Only paid salary records can be reversed.'),
                ]);
            }

            $this->ledger->reverseForSource($actor, $locked, $reason);

            $locked->update([
                'status' => 'reversed',
                'reversed_at' => now(),
                'reversed_by' => $actor->id,
                'reversal_reason' => $reason,
            ]);

            $this->audit->log(
                event: 'salary_payment.reversed',
                auditable: $locked,
                description: __('Salary payment for :name was reversed: :reason', [
                    'name' => $locked->staffName(),
                    'reason' => $reason,
                ]),
                oldValues: ['status' => 'paid', 'net_salary' => (float) $locked->net_salary],
                newValues: ['status' => 'reversed', 'reversal_reason' => $reason],
                actor: $actor,
                schoolId: (int) $locked->school_id,
            );

            return $locked->refresh();
        });
    }

    private function schoolId(): int
    {
        $schoolId = app(SchoolContext::class)->id();

        if ($schoolId === null) {
            throw ValidationException::withMessages([
                'school' => __('A school context is required.'),
            ]);
        }

        return $schoolId;
    }
}
