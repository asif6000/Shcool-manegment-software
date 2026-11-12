<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkSubmission extends Model
{
    use BelongsToSchool;

    public const STATUSES = ['submitted', 'late', 'reviewed'];

    protected $fillable = [
        'homework_id',
        'student_id',
        'enrollment_id',
        'status',
        'text_answer',
        'attachment_path',
        'attachment_name',
        'submitted_at',
        'resubmitted_at',
        'marks',
        'feedback',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'marks' => 'decimal:2',
            'submitted_at' => 'datetime',
            'resubmitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isLate(): bool
    {
        return $this->status === 'late';
    }

    public function isReviewed(): bool
    {
        return $this->status === 'reviewed';
    }

    /**
     * A review is final: the teacher has recorded a result, so the student may
     * no longer overwrite the answer they were marked on.
     */
    public function isEditableByStudent(): bool
    {
        return ! $this->isReviewed();
    }
}
