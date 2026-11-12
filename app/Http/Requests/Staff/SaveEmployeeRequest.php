<?php

namespace App\Http\Requests\Staff;

use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('employee') === null ? 'staff.create' : 'staff.update';

        return (bool) $this->user()?->can($permission);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();
        $employeeId = $this->route('employee')?->id;

        return [
            'employee_code' => [
                'nullable', 'string', 'max:30',
                Rule::unique('employees', 'employee_code')->where('school_id', $schoolId)->ignore($employeeId),
            ],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'designation' => ['required', 'string', 'max:80'],
            'department' => ['nullable', 'string', 'max:80'],
            'joining_date' => ['required', 'date'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'visiting'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'resigned'])],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
