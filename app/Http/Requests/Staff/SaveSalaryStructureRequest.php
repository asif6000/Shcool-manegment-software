<?php

namespace App\Http\Requests\Staff;

use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSalaryStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('salary.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return [
            'teacher_id' => [
                'nullable', 'required_without:employee_id', 'integer',
                Rule::exists('teachers', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'employee_id' => [
                'nullable', 'required_without:teacher_id', 'integer',
                Rule::exists('employees', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'house_allowance' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'medical_allowance' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'other_allowance' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
