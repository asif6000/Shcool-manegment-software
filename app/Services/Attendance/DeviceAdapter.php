<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use Illuminate\Support\Carbon;

/**
 * Provider-neutral seam for a fingerprint or face terminal.
 *
 * No manufacturer has been selected, so nothing in this application implements
 * this interface yet. A driver may only be written once the real device model,
 * API documentation and network/authentication details are known; the
 * ingestion endpoint refuses traffic until that driver exists.
 */
interface DeviceAdapter
{
    /** Stable identifier stored in config/attendance.php. */
    public function driver(): string;

    public function isConfigured(): bool;

    /**
     * Pull punches that happened at or after $since.
     *
     * @return iterable<DeviceEvent>
     */
    public function pull(AttendanceDevice $device, Carbon $since): iterable;

    /**
     * @return array{ok: bool, message: ?string}
     */
    public function health(AttendanceDevice $device): array;
}
