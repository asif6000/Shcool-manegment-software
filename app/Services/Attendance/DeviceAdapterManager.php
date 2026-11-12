<?php

namespace App\Services\Attendance;

/**
 * Resolves a configured driver name to an adapter instance.
 *
 * The map is deliberately empty until a real vendor is contracted; there is no
 * invented HTTP client or SDK in this repository. Setting
 * ATTENDANCE_FINGERPRINT_DRIVER without adding a class to the map still leaves
 * the integration NOT_CONFIGURED, which is the honest state.
 */
class DeviceAdapterManager
{
    public function driverName(string $kind): ?string
    {
        $driver = config('attendance.devices.drivers.'.$kind);

        return filled($driver) ? (string) $driver : null;
    }

    public function for(string $kind): ?DeviceAdapter
    {
        $driver = $this->driverName($kind);

        if ($driver === null) {
            return null;
        }

        $class = config('attendance.devices.adapters.'.$driver);

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        $adapter = app($class);

        return $adapter instanceof DeviceAdapter ? $adapter : null;
    }

    public function configured(string $kind): bool
    {
        return $this->for($kind) !== null;
    }
}
