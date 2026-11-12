<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceDevice extends Model
{
    use BelongsToSchool;

    public const KINDS = ['fingerprint', 'face'];

    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_READY = 'ready';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_ERROR = 'error';

    public const STATUSES = [
        self::STATUS_NOT_CONFIGURED,
        self::STATUS_READY,
        self::STATUS_DISCONNECTED,
        self::STATUS_ERROR,
    ];

    protected $fillable = [
        'name',
        'kind',
        'driver',
        'serial',
        'location',
        'secret_hash',
        'status',
        'config',
        'is_active',
        'last_seen_at',
        'last_synced_at',
        'last_error',
    ];

    protected $hidden = [
        'secret_hash',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class, 'attendance_device_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(AttendanceDeviceUser::class, 'attendance_device_id');
    }

    public function kindLabel(): string
    {
        return $this->kind === 'face' ? __('Face recognition') : __('Fingerprint');
    }

    /**
     * The provider driver is configured through the environment; a device row
     * alone proves nothing. Without a driver the truthful state is always
     * "not configured", whatever an operator typed into the status field.
     */
    public function hasConfiguredDriver(): bool
    {
        return filled(config('attendance.devices.drivers.'.$this->kind));
    }

    public function effectiveStatus(): string
    {
        if (! $this->is_active) {
            return self::STATUS_DISCONNECTED;
        }

        if (! $this->hasConfiguredDriver()) {
            return self::STATUS_NOT_CONFIGURED;
        }

        return in_array($this->status, self::STATUSES, true)
            ? $this->status
            : self::STATUS_NOT_CONFIGURED;
    }

    public function statusLabel(): string
    {
        return match ($this->effectiveStatus()) {
            self::STATUS_READY => __('Ready'),
            self::STATUS_DISCONNECTED => __('Disconnected'),
            self::STATUS_ERROR => __('Error'),
            default => __('Not configured'),
        };
    }
}
