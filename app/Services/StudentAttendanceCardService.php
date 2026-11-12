<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAttendanceCard;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Issues and revokes the opaque token printed on a student ID card. The token
 * itself is only ever returned once, at issue time; the database keeps a
 * SHA-256 hash, so a dump cannot be turned back into working cards.
 */
class StudentAttendanceCardService
{
    private const TOKEN_PATTERN = '/^atn1_[a-f0-9]{64}$/';

    public function __construct(private readonly AuditLogger $audit) {}

    public function activeFor(Student $student): ?StudentAttendanceCard
    {
        return StudentAttendanceCard::query()
            ->where('student_id', $student->id)
            ->active()
            ->latest('issued_at')
            ->first();
    }

    /**
     * Reissuing invalidates every earlier card for the student, so a lost card
     * stops working the moment a replacement is printed.
     *
     * @return array{card: StudentAttendanceCard, token: string}
     */
    public function issue(Student $student, User $actor): array
    {
        $token = 'atn1_'.bin2hex(random_bytes((int) config('attendance.qr.token_bytes', 32)));

        return DB::transaction(function () use ($student, $actor, $token): array {
            StudentAttendanceCard::query()
                ->where('student_id', $student->id)
                ->active()
                ->update([
                    'revoked_at' => now(),
                    'revoked_by' => $actor->id,
                    'revoke_reason' => __('Reissued'),
                    'updated_at' => now(),
                ]);

            $card = StudentAttendanceCard::query()->create([
                'student_id' => $student->id,
                'token_hash' => StudentAttendanceCard::hashToken($token),
                'issued_by' => $actor->id,
                'issued_at' => now(),
            ]);

            $this->audit->log(
                event: 'student_attendance.card_issued',
                auditable: $card,
                description: __('Attendance card issued for :student', ['student' => $student->full_name]),
                newValues: ['student_id' => $student->id, 'card_id' => $card->id],
                actor: $actor,
            );

            return ['card' => $card, 'token' => $token];
        });
    }

    public function revoke(StudentAttendanceCard $card, User $actor, ?string $reason = null): void
    {
        if (! $card->isActive()) {
            return;
        }

        DB::transaction(function () use ($card, $actor, $reason): void {
            $card->forceFill([
                'revoked_at' => now(),
                'revoked_by' => $actor->id,
                'revoke_reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            ])->save();

            $this->audit->log(
                event: 'student_attendance.card_revoked',
                auditable: $card,
                description: __('Attendance card revoked for :student', ['student' => $card->student?->full_name ?? '#'.$card->student_id]),
                newValues: ['reason' => $card->revoke_reason],
                actor: $actor,
            );
        });
    }

    /**
     * A scan payload is opaque: only the hash is compared, and revoked or
     * unknown tokens are indistinguishable to the caller.
     */
    public function resolve(string $token): ?StudentAttendanceCard
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            return null;
        }

        return StudentAttendanceCard::query()
            ->active()
            ->with('student')
            ->where('token_hash', StudentAttendanceCard::hashToken($token))
            ->first();
    }
}
