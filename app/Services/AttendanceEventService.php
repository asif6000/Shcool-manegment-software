<?php

namespace App\Services;

use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
use App\Models\Student;
use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Owns the attendance_events log. A provider retry (same external reference)
 * returns the first decision instead of re-applying a punch, and a rejected
 * event is kept so staff can see why a scan did not become a mark.
 *
 * No biometric payload ever reaches this class: callers pass identifiers and
 * timestamps only, and anything that looks like template/image data is dropped.
 */
class AttendanceEventService
{
    /** Keys that must never be persisted, whatever a caller sends. */
    private const FORBIDDEN_META = [
        'image', 'photo', 'template', 'embedding', 'vector', 'fingerprint',
        'face', 'biometric', 'payload', 'raw',
    ];

    public function __construct(private readonly StudentAttendanceService $attendance) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function ingest(
        ?User $actor,
        Student $student,
        string $source,
        string $externalRef,
        Carbon $occurredAt,
        ?AttendanceDevice $device = null,
        ?string $deviceUserId = null,
        array $meta = [],
    ): AttendanceEvent {
        $schoolId = app(SchoolContext::class)->id();
        $now = now();
        $attributes = [
            'school_id' => $schoolId,
            'source' => $source,
            'external_ref' => $externalRef,
        ];

        $inserted = AttendanceEvent::query()->insertOrIgnore([
            ...$attributes,
            'attendance_device_id' => $device?->id,
            'student_id' => $student->id,
            'device_user_id' => $deviceUserId,
            'occurred_at' => $occurredAt->toDateTimeString(),
            'received_at' => $now->toDateTimeString(),
            'status' => AttendanceEvent::STATUS_REJECTED,
            'reason' => 'pending',
            'meta' => $meta === [] ? null : json_encode($this->sanitizeMeta($meta)),
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        $event = AttendanceEvent::query()->where($attributes)->firstOrFail();

        if ($inserted === 0) {
            // The punch was already delivered (provider retry or double click).
            return $event;
        }

        $rejection = $this->precheck($source, $occurredAt);

        if ($rejection !== null) {
            $event->forceFill([
                'status' => AttendanceEvent::STATUS_REJECTED,
                'reason' => $rejection,
                'updated_at' => now(),
            ])->save();

            return $event;
        }

        $outcome = $this->attendance->applyAutomated(
            actor: $actor,
            student: $student,
            source: $source,
            occurredAt: $occurredAt,
            sourceRef: $externalRef,
            device: $device,
            deviceUserId: $deviceUserId,
        );

        $event->forceFill([
            'student_attendance_id' => $outcome['attendance']?->id,
            'status' => $outcome['status'],
            'reason' => $outcome['reason'],
            'updated_at' => now(),
        ])->save();

        if ($device !== null) {
            $device->forceFill(['last_seen_at' => now()])->save();
        }

        return $event->refresh();
    }

    /**
     * Record a punch that could not be tied to a student (for example a device
     * user ID nobody has mapped yet) so the failure stays visible to staff.
     *
     * @param  array<string, mixed>  $meta
     */
    public function reject(
        AttendanceDevice $device,
        string $source,
        string $externalRef,
        string $deviceUserId,
        Carbon $occurredAt,
        string $reason,
        array $meta = [],
    ): AttendanceEvent {
        $now = now();
        $attributes = [
            'school_id' => app(SchoolContext::class)->id(),
            'source' => $source,
            'external_ref' => $externalRef,
        ];

        AttendanceEvent::query()->insertOrIgnore([
            ...$attributes,
            'attendance_device_id' => $device->id,
            'student_id' => null,
            'device_user_id' => $deviceUserId,
            'occurred_at' => $occurredAt->toDateTimeString(),
            'received_at' => $now->toDateTimeString(),
            'status' => AttendanceEvent::STATUS_REJECTED,
            'reason' => $reason,
            'meta' => $meta === [] ? null : json_encode($this->sanitizeMeta($meta)),
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        return AttendanceEvent::query()->where($attributes)->firstOrFail();
    }

    /**
     * Device clocks drift, so a punch stamped far in the future or days in the
     * past is rejected instead of quietly changing an old register.
     */
    private function precheck(string $source, Carbon $occurredAt): ?string
    {
        if (! in_array($source, StudentAttendanceService::AUTOMATED_SOURCES, true)) {
            return 'unknown_source';
        }

        if ($source === 'qr') {
            return null;
        }

        $skew = (int) config('attendance.devices.max_clock_skew_minutes', 15);

        if ($occurredAt->gt(now()->addMinutes($skew))) {
            return 'clock_skew';
        }

        $maxAge = (int) config('attendance.devices.max_event_age_hours', 72);

        if ($occurredAt->lt(now()->subHours($maxAge))) {
            return 'stale_event';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function sanitizeMeta(array $meta): array
    {
        return collect($meta)
            ->reject(fn ($value, $key): bool => Str::contains(Str::lower((string) $key), self::FORBIDDEN_META))
            ->map(fn ($value) => is_scalar($value) || $value === null ? $value : null)
            ->all();
    }
}
