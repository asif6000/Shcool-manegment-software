<?php

namespace App\Http\Requests\Staff;

use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLeaveTypeRequest extends FormRequest
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
        $leaveTypeId = $this->route('leaveType')?->id;

        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('leave_types', 'name')->where('school_id', $schoolId)->ignore($leaveTypeId),
            ],
            'code' => [
                'nullable', 'string', 'max:20',
                Rule::unique('leave_types', 'code')->where('school_id', $schoolId)->ignore($leaveTypeId),
            ],
            'max_days_per_year' => ['nullable', 'integer', 'min:1', 'max:365'],
            'is_paid' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
