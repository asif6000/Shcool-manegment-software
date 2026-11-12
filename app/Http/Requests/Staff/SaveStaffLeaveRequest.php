<?php

namespace App\Http\Requests\Staff;

use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStaffLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('leave.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return [
            'leave_type_id' => [
                'required', 'integer',
                Rule::exists('leave_types', 'id')->where('school_id', $schoolId)->where('is_active', true),
            ],
            'teacher_id' => [
                'nullable', 'required_without:employee_id', 'integer',
                Rule::exists('teachers', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'employee_id' => [
                'nullable', 'required_without:teacher_id', 'integer',
                Rule::exists('employees', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
