<?php

namespace App\Services\Attendance\Adapters;

use App\Models\AttendanceDevice;
use App\Services\Attendance\DeviceAdapter;
use App\Services\Attendance\DeviceEvent;
use Illuminate\Support\Carbon;

/**
 * Driver for Hikvision DS-K1A340FWX Face Recognition Attendance Terminals.
 *
 * In the cPanel deployment architecture, the terminal resides on the school's
 * private LAN/Wi-Fi and pushes punches to the Laravel HTTPS API through the
 * Local Windows Bridge Agent. This adapter satisfies the server contract so
 * that authenticated device punches are accepted and processed.
 */
class HikvisionFaceAdapter implements DeviceAdapter
{
    public const DRIVER_NAME = 'hikvision';

    public function driver(): string
    {
        return self::DRIVER_NAME;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * In a cloud/cPanel hosting setup, inbound pulls into private RFC 1918
     * subnets are blocked by NAT/firewalls. Punches are ingested via outbound
     * push from the Local Windows Bridge Agent to /api/v1/attendance/events.
     *
     * @return iterable<DeviceEvent>
     */
    public function pull(AttendanceDevice $device, Carbon $since): iterable
    {
        return [];
    }

    /**
     * Evaluates health based on recent heartbeat and recorded error state.
     *
     * @return array{ok: bool, message: ?string}
     */
    public function health(AttendanceDevice $device): array
    {
        if (! $device->is_active) {
            return [
                'ok' => false,
                'message' => __('Device is disabled.'),
            ];
        }

        $heartbeatMinutes = (int) config('attendance.devices.heartbeat_minutes', 15);
        $lastSeen = $device->last_seen_at;

        if ($lastSeen === null) {
            return [
                'ok' => false,
                'message' => __('Device has never authenticated or checked in.'),
            ];
        }

        if ($lastSeen->lt(now()->subMinutes($heartbeatMinutes))) {
            return [
                'ok' => false,
                'message' => __('Device heartbeat is stale. Last seen :time.', [
                    'time' => $lastSeen->diffForHumans(),
                ]),
            ];
        }

        return [
            'ok' => true,
            'message' => __('Device is online and active.'),
        ];
    }
}
