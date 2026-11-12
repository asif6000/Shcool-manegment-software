<?php

namespace App\Services;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceUser;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\DeviceAdapterManager;
use App\Support\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Registry, credentials and device-user mapping for fingerprint/face terminals.
 * Secrets are generated here and only their hash is persisted; the plain value
 * is returned once so it can be copied into the device configuration.
 */
class AttendanceDeviceService
{
    public function __construct(
        private readonly DeviceAdapterManager $adapters,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, kind: string, serial?: ?string, location?: ?string}  $data
     * @return array{device: AttendanceDevice, secret: string}
     */
    public function register(User $actor, array $data): array
    {
        $secret = bin2hex(random_bytes(32));
        $kind = $data['kind'];

        $device = AttendanceDevice::query()->create([
            'name' => $data['name'],
            'kind' => $kind,
            'driver' => $this->adapters->driverName($kind),
            'serial' => $data['serial'] ?? null,
            'location' => $data['location'] ?? null,
            'secret_hash' => hash('sha256', $secret),
            // A driver on the server does not prove the terminal is reachable.
            // Until it authenticates once, the device is disconnected.
            'status' => $this->adapters->configured($kind)
                ? AttendanceDevice::STATUS_DISCONNECTED
                : AttendanceDevice::STATUS_NOT_CONFIGURED,
            'is_active' => true,
            // FIX: persist optional caller-supplied config (LAN IP, port, model etc.)
            // Previously the 'config' key from AdminFaceController was silently dropped
            // because only name/kind/serial/location were forwarded to the create() call.
            'config' => isset($data['config']) && is_array($data['config']) && count($data['config']) > 0
                ? $data['config']
                : null,
        ]);

        $this->audit->log(
            event: 'attendance_device.registered',
            auditable: $device,
            description: __(':kind device :name was registered', [
                'kind' => $device->kindLabel(),
                'name' => $device->name,
            ]),
            newValues: ['kind' => $device->kind, 'serial' => $device->serial],
            actor: $actor,
        );

        return ['device' => $device, 'secret' => $secret];
    }

    public function rotateSecret(AttendanceDevice $device, User $actor): string
    {
        $secret = bin2hex(random_bytes(32));

        $device->forceFill(['secret_hash' => hash('sha256', $secret)])->save();

        $this->audit->log(
            event: 'attendance_device.secret_rotated',
            auditable: $device,
            description: __('Credentials for device :name were rotated', ['name' => $device->name]),
            actor: $actor,
        );

        return $secret;
    }

    public function setActive(AttendanceDevice $device, bool $active, User $actor): void
    {
        $device->forceFill([
            'is_active' => $active,
            'status' => $active
                ? ($this->adapters->configured($device->kind) ? AttendanceDevice::STATUS_DISCONNECTED : AttendanceDevice::STATUS_NOT_CONFIGURED)
                : AttendanceDevice::STATUS_DISCONNECTED,
        ])->save();

        $this->audit->log(
            event: 'attendance_device.updated',
            auditable: $device,
            description: $active
                ? __('Device :name was enabled', ['name' => $device->name])
                : __('Device :name was disabled', ['name' => $device->name]),
            newValues: ['is_active' => $active],
            actor: $actor,
        );
    }

    public function authenticate(?string $deviceId, ?string $secret): ?AttendanceDevice
    {
        if (! is_string($deviceId) || ! ctype_digit($deviceId) || ! is_string($secret) || $secret === '') {
            return null;
        }

        $device = AttendanceDevice::query()
            ->whereKey((int) $deviceId)
            ->where('is_active', true)
            ->first();

        if ($device === null || $device->secret_hash === null) {
            return null;
        }

        return hash_equals($device->secret_hash, hash('sha256', $secret)) ? $device : null;
    }

    public function studentFor(AttendanceDevice $device, string $deviceUserId): ?Student
    {
        return AttendanceDeviceUser::query()
            ->where('attendance_device_id', $device->id)
            ->where('device_user_id', $deviceUserId)
            ->first()
            ?->student;
    }

    public function mapUser(AttendanceDevice $device, string $deviceUserId, Student $student, User $actor): AttendanceDeviceUser
    {
        if ((int) $student->school_id !== (int) $device->school_id) {
            throw ValidationException::withMessages([
                'student_id' => __('The student does not belong to this school.'),
            ]);
        }

        try {
            $mapping = AttendanceDeviceUser::query()->updateOrCreate(
                ['attendance_device_id' => $device->id, 'device_user_id' => $deviceUserId],
                ['student_id' => $student->id, 'created_by' => $actor->id],
            );
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'device_user_id' => __('This student is already mapped to a different device user ID on this device.'),
            ]);
        }

        $this->audit->log(
            event: 'attendance_device.mapping_saved',
            auditable: $device,
            description: __('Device user :user was mapped to :student', [
                'user' => $deviceUserId,
                'student' => $student->full_name,
            ]),
            newValues: ['device_user_id' => $deviceUserId, 'student_id' => $student->id],
            actor: $actor,
        );

        return $mapping;
    }
}
