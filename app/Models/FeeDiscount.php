<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class FeeDiscount extends Model
{
    use BelongsToSchool;

    public const TYPES = ['fixed', 'percent'];

    public const FREQUENCIES = ['one_time', 'monthly', 'quarterly', 'half_yearly', 'yearly', 'custom'];

    public const SCOPES = ['all', 'class', 'student'];

    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'value',
        'frequency',
        'scope',
        'class_id',
        'student_id',
        'applies_to_fee_type_id',
        'starts_on',
        'ends_on',
        'max_amount',
        'auto_apply',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'auto_apply' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class, 'applies_to_fee_type_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailableOn(Builder $query, Carbon|string $date): Builder
    {
        $day = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return $query
            ->where(fn (Builder $query) => $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', $day))
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $day));
    }

    public function isAvailableOn(Carbon|string $date): bool
    {
        $day = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();

        if ($this->starts_on !== null && $this->starts_on->gt($day)) {
            return false;
        }

        return $this->ends_on === null || $this->ends_on->gte($day);
    }

    public function appliesTo(Student $student, ?Enrollment $enrollment = null): bool
    {
        return match ($this->scope) {
            'student' => (int) $this->student_id === (int) $student->id,
            'class' => $enrollment !== null && (int) $this->class_id === (int) $enrollment->class_id,
            default => true,
        };
    }
}
