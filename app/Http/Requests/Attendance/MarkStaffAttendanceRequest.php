<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkStaffAttendanceRequest extends FormRequest
{
    public const STATUSES = ['present', 'absent', 'late', 'leave'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('attendance.mark');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'teachers' => ['nullable', 'array'],
            'teachers.*' => ['required', 'string', Rule::in(self::STATUSES)],
            'employees' => ['nullable', 'array'],
            'employees.*' => ['required', 'string', Rule::in(self::STATUSES)],
            'remarks' => ['nullable', 'array'],
            'remarks.*' => ['nullable', 'string', 'max:255'],
            'override_holiday' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (empty($this->input('teachers')) && empty($this->input('employees'))) {
                $validator->errors()->add('teachers', __('Mark at least one staff member before saving.'));
            }
        });
    }
}
