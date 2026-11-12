<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
        'is_active',
        'last_used_at',
        'last_failure_at',
        'failure_count',
    ];

    protected $hidden = [
        'endpoint',
        'endpoint_hash',
        'auth_token',
    ];

    protected function casts(): array
    {
        return [
            'auth_token' => 'encrypted',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'failure_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function endpointHash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function markUsed(): void
    {
        $this->forceFill([
            'is_active' => true,
            'last_used_at' => now(),
            'failure_count' => 0,
        ])->save();
    }

    public function markFailure(): void
    {
        $this->forceFill([
            'last_failure_at' => now(),
            'failure_count' => $this->failure_count + 1,
        ])->save();
    }

    public function deactivate(): void
    {
        $this->forceFill(['is_active' => false])->save();
    }

    /**
     * A short, log-safe representation of the endpoint. Never log the full URL.
     */
    public function maskedEndpoint(): string
    {
        $host = parse_url($this->endpoint, PHP_URL_HOST);

        return is_string($host) && $host !== ''
            ? Str::limit($host, 80, '')
            : 'push-endpoint:'.Str::substr((string) $this->endpoint_hash, 0, 12);
    }
}
