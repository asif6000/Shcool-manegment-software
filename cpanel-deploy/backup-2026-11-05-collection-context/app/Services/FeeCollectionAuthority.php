<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\FeeInvoice;
use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Who is allowed to take money, and for which students.
 *
 * `fees.collect` is the accountant-grade right over the whole school.
 * `fees.collect.scoped` is the same desk restricted to the students on the
 * holder's own timetable, so a school can let class teachers collect without
 * handing them every learner's financial record.
 */
class FeeCollectionAuthority
{
    /**
     * The office monitors the cash desk but must never hold a permission that
     * can record a payment. The role matrix is not allowed to grant these back.
     */
    public const MONITORING_ONLY = [
        'fees.collect',
        'fees.collect.scoped',
    ];

    public function __construct(private readonly TeachingScope $scope) {}

    /**
     * Admin and principal roles monitor the money desk. Even if a stale
     * permission row still says they may collect, they must not be able to
     * create a payment from any portal.
     */
    public function isMonitoringOnly(User $actor): bool
    {
        return $actor->hasAnyRole(['school_admin', 'principal']);
    }

    public function mayCollect(User $actor): bool
    {
        if ($this->isMonitoringOnly($actor)) {
            return false;
        }

        return $actor->can('fees.collect') || $actor->can('fees.collect.scoped');
    }

    public function isUnrestricted(User $actor): bool
    {
        return ! $this->isMonitoringOnly($actor) && $actor->can('fees.collect');
    }

    public function assertMayCollectFor(User $actor, Student $student, ?AcademicSession $session): void
    {
        if ($this->isUnrestricted($actor)) {
            return;
        }

        if (! $this->mayCollect($actor)) {
            throw ValidationException::withMessages([
                'student' => __('You are not authorised to collect fees.'),
            ]);
        }

        if (! $this->scope->mayViewStudent($actor, $student, $session)) {
            throw ValidationException::withMessages([
                'student' => __('This student is not on your class list, so you cannot collect fees for them.'),
            ]);
        }
    }

    public function assertMayCollectInvoice(User $actor, FeeInvoice $invoice, ?AcademicSession $session): void
    {
        $invoice->loadMissing('student');

        $this->assertMayCollectFor($actor, $invoice->student, $session ?? $invoice->academicSession);
    }
}
