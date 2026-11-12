<?php

namespace App\Http\Requests\Finance;

use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateFeeInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('fees.manage');
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
                Rule::exists('sections', 'id')->where('school_id', $schoolId),
            ],
            'fee_type_ids' => ['required', 'array', 'min:1'],
            'fee_type_ids.*' => [
                'integer',
                Rule::exists('fee_types', 'id')
                    ->where('school_id', $schoolId)
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
            // A fee type that has no class fee and no assignment yet is priced
            // here instead, which is how a brand new fee becomes billable.
            'amounts' => ['nullable', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            // Set when billing one named learner instead of a whole class.
            'student_ids' => ['nullable', 'array', 'max:200'],
            'student_ids.*' => [
                'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'billing_period' => ['nullable', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ];
    }

    /**
     * An empty amount box means "this line is not priced", not zero.
     */
    protected function prepareForValidation(): void
    {
        $amounts = collect((array) $this->input('amounts', []))
            ->filter(fn ($amount): bool => filled($amount))
            ->all();

        $this->merge(['amounts' => $amounts === [] ? null : $amounts]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fee_type_ids.*.exists' => __('That fee type is deactivated or deleted, so it cannot be billed any more.'),
            'amounts.*.numeric' => __('Enter the amount as a number, or leave the box empty.'),
            'amounts.*.min' => __('The amount has to be more than zero.'),
        ];
    }
}
