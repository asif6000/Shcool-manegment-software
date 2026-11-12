<?php

namespace App\Http\Requests\Finance;

use App\Models\Expense;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('payroll.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return [
            'salary_structure_id' => [
                'required', 'integer',
                Rule::exists('salary_structures', 'id')->where('school_id', $schoolId),
            ],
            'salary_month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'deductions_total' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'payment_method' => ['required', Rule::in(Expense::METHODS)],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
