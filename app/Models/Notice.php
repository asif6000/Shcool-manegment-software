<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Notice extends Model
{
    use BelongsToSchool, SoftDeletes;

    /**
     * Audiences are also the website gate: only the three public audiences are
     * ever rendered on the school website; teachers and staff see notices in
     * their signed-in inbox only.
     */
    public const AUDIENCES = ['all', 'students', 'guardians', 'teachers', 'staff'];

    public const PUBLIC_AUDIENCES = ['all', 'students', 'guardians'];

    protected $fillable = [
        'academic_session_id',
        'title',
        'body',
        'audience',
        'published_at',
        'expires_at',
        'is_pinned',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_pinned' => 'boolean',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query, ?Carbon $now = null): Builder
    {
        $now ??= now();

        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now));
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->whereIn('audience', self::PUBLIC_AUDIENCES);
    }

    public function isVisibleOnWebsite(): bool
    {
        return in_array($this->audience, self::PUBLIC_AUDIENCES, true);
    }

    /**
     * Whether visitors can see the notice right now. The audience alone is not
     * enough: a draft, a scheduled notice and an expired one never render.
     */
    public function isLiveOnWebsite(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->isVisibleOnWebsite()
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo($now)
            && ($this->expires_at === null || $this->expires_at->greaterThan($now));
    }

    public function audienceLabel(): string
    {
        return match ($this->audience) {
            'students' => __('Students'),
            'guardians' => __('Guardians'),
            'teachers' => __('Teachers'),
            'staff' => __('Staff'),
            default => __('Everyone'),
        };
    }
}
