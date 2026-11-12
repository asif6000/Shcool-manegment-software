<?php

namespace App\Http\Requests\Attendance;

use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkStudentAttendanceRequest extends FormRequest
{
    public const STATUSES = ['present', 'absent', 'late', 'leave', 'excused'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('attendance.mark');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return [
            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'class_id' => [
                'required', 'integer',
                Rule::exists('classes', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'section_id' => [
                'nullable', 'integer',
                Rule::exists('sections', 'id')
                    ->where('school_id', $schoolId)
                    ->where('class_id', $this->input('class_id')),
            ],
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['required', 'string', Rule::in(self::STATUSES)],
            'remarks' => ['nullable', 'array'],
            'remarks.*' => ['nullable', 'string', 'max:255'],
            'correction_reason' => ['nullable', 'string', 'min:5', 'max:255'],
            'override_holiday' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'statuses.required' => __('Mark at least one student before saving.'),
        ];
    }
}
