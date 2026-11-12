<?php

namespace App\Services\Attendance;

use Illuminate\Support\Carbon;

/**
 * One punch reported by a biometric terminal or provider. It carries only the
 * identifiers needed to find the student and the time of the punch; no
 * template, image or embedding is part of the contract.
 */
final readonly class DeviceEvent
{
    /**
     * @param  array<string, scalar|null>  $meta  Minimal, provider-defined metadata.
     */
    public function __construct(
        public string $externalRef,
        public string $deviceUserId,
        public Carbon $occurredAt,
        public array $meta = [],
    ) {}
}
