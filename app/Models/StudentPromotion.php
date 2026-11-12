<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPromotion extends Model
{
    use BelongsToSchool;

    public const ACTIONS = ['promote', 'repeat', 'hold', 'transfer_out'];

    public const STATUSES = ['completed', 'held', 'transferred'];

    public const ELIGIBILITIES = ['eligible', 'ineligible', 'unavailable'];

    protected $fillable = [
        'student_id',
        'from_academic_session_id',
        'to_academic_session_id',
        'from_enrollment_id',
        'to_enrollment_id',
        'from_class_id',
        'from_section_id',
        'from_roll_no',
        'to_class_id',
        'to_section_id',
        'to_roll_no',
        'action',
        'status',
        'eligibility',
        'result_exam_id',
        'result_snapshot',
        'notes',
        'promoted_by',
        'promoted_at',
    ];

    protected function casts(): array
    {
        return [
            'from_roll_no' => 'integer',
            'to_roll_no' => 'integer',
            'result_snapshot' => 'array',
            'promoted_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fromSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'from_academic_session_id');
    }

    public function toSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'to_academic_session_id');
    }

    public function fromEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'from_enrollment_id');
    }

    public function toEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'to_enrollment_id');
    }

    public function fromClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'from_class_id');
    }

    public function fromSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'from_section_id');
    }

    public function toClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'to_class_id');
    }

    public function toSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'to_section_id');
    }

    public function resultExam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'result_exam_id');
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoted_by');
    }

    /**
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return [
            'promote' => __('Promote'),
            'repeat' => __('Repeat'),
            'hold' => __('Hold'),
            'transfer_out' => __('Transfer out'),
        ];
    }

    public function actionLabel(): string
    {
        return self::actionLabels()[$this->action] ?? __(ucfirst((string) $this->action));
    }
}
