<?php

namespace Tests\Fixtures\Adapters;

use App\Models\AttendanceDevice;
use App\Services\Attendance\DeviceAdapter;
use App\Services\Attendance\DeviceEvent;
use Illuminate\Support\Carbon;

/**
 * Test double only. It proves the ingestion pipeline works end to end without
 * claiming that any real fingerprint hardware or vendor protocol is supported.
 */
final class FakeFingerprintAdapter implements DeviceAdapter
{
    public function driver(): string
    {
        return 'fake-fingerprint';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * @return iterable<DeviceEvent>
     */
    public function pull(AttendanceDevice $device, Carbon $since): iterable
    {
        return [];
    }

    /**
     * @return array{ok: bool, message: ?string}
     */
    public function health(AttendanceDevice $device): array
    {
        return ['ok' => true, 'message' => __('Test double; no hardware is contacted.')];
    }
}
