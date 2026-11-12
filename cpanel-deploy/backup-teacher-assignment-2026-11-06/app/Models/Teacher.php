<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    /** @use HasFactory<TeacherFactory> */
    use BelongsToSchool, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_code',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'phone',
        'email',
        'designation',
        'joining_date',
        'qualification',
        'specialization',
        'employment_type',
        'status',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joining_date' => 'date',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim($this->first_name.' '.($this->last_name ?? '')),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(StaffLeave::class);
    }

    public function salaryStructures(): HasMany
    {
        return $this->hasMany(SalaryStructure::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path !== null
            ? route('staff.teachers.photo', $this)
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
}
