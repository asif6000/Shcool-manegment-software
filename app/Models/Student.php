<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use BelongsToSchool, HasFactory, SoftDeletes;

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = ['active', 'inactive', 'graduated', 'transferred', self::STATUS_ARCHIVED];

    protected $fillable = [
        'student_id',
        'admission_no',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'blood_group',
        'religion',
        'nationality',
        'photo_path',
        'email',
        'phone',
        'present_address',
        'permanent_address',
        'status',
        'admission_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admission_date' => 'date',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim($this->first_name.' '.($this->last_name ?? '')),
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(StudentAttendance::class);
    }

    public function examMarks(): HasMany
    {
        return $this->hasMany(ExamMark::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(StudentResult::class);
    }

    public function feeInvoices(): HasMany
    {
        return $this->hasMany(FeeInvoice::class);
    }

    public function feePayments(): HasMany
    {
        return $this->hasMany(FeePayment::class);
    }

    public function homeworkSubmissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(StudentLeave::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians')
            ->withPivot(['is_primary', 'is_emergency_contact'])
            ->withTimestamps();
    }

    public function currentEnrollment(): ?Enrollment
    {
        return $this->enrollments()
            ->where('status', 'active')
            ->latest('enrolled_on')
            ->with(['schoolClass', 'section', 'academicSession'])
            ->first();
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path !== null
            ? route('students.photo', $this)
            : null;
    }

    /**
     * Every upload is stored under a fresh uuid, so the path itself identifies
     * the picture. Views append it to the stream URL and a changed photo can
     * never be hidden behind a cached one.
     */
    public function photoVersion(): ?string
    {
        return $this->photo_path === null ? null : substr(md5($this->photo_path), 0, 10);
    }

    public function primaryGuardian(): ?Guardian
    {
        $guardians = $this->relationLoaded('guardians')
            ? $this->guardians
            : $this->guardians()->get();

        return $guardians->firstWhere('pivot.is_primary', true) ?? $guardians->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $like = '%'.trim($term).'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('student_id', 'like', $like)
                ->orWhere('admission_no', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }
}
