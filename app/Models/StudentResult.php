<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentResult extends Model
{
    use BelongsToSchool;

    public const STATUSES = ['draft', 'published'];

    protected $fillable = [
        'exam_id',
        'student_id',
        'total_full_marks',
        'total_obtained',
        'gpa',
        'grade_letter',
        'position',
        'status',
        'snapshot',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'total_full_marks' => 'decimal:2',
            'total_obtained' => 'decimal:2',
            'gpa' => 'decimal:2',
            'position' => 'integer',
            'snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
