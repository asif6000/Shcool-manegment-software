<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeStudentAssignment extends Model
{
    use BelongsToSchool;

    protected $table = 'fee_student_assignments';

    protected $fillable = [
        'fee_type_id',
        'academic_session_id',
        'student_id',
        'enrollment_id',
        'class_id',
        'section_id',
        'amount',
        'starts_on',
        'ends_on',
        'batch',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The amount this student is billed for the fee type. Null defers to the
     * class structure so an authorised override and the default coexist.
     */
    public function effectiveAmount(?float $structureAmount): ?float
    {
        $amount = $this->amount !== null ? round((float) $this->amount, 2) : $structureAmount;

        return $amount === null ? null : round($amount, 2);
    }

    public function coversDate(CarbonInterface $date): bool
    {
        if ($this->starts_on !== null && $date->lt($this->starts_on)) {
            return false;
        }

        return $this->ends_on === null || $date->lte($this->ends_on);
    }
}
