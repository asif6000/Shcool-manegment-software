<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'access_token',
        'secret',
        'authorization',
    ];

    public function log(
        string $event,
        ?Model $auditable,
        string $description,
        array $oldValues = [],
        array $newValues = [],
        ?User $actor = null,
        ?int $schoolId = null,
    ): AuditLog {
        $actor ??= Auth::user();
        $schoolId ??= $auditable?->getAttribute('school_id') ?? $actor?->school_id;

        return AuditLog::create([
            'school_id' => $schoolId,
            'user_id' => $actor?->getKey(),
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'description' => Str::limit($description, 250, ''),
            'old_values' => $oldValues === [] ? null : self::redact($oldValues),
            'new_values' => $newValues === [] ? null : self::redact($newValues),
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 500, ''),
            'created_at' => now(),
        ]);
    }

    /**
     * Remove secrets from audit payloads before persistence.
     */
    public static function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $values[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $values[$key] = self::redact($value);
            }
        }

        return $values;
    }
}
