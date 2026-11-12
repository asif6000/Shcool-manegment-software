<?php

namespace App\Http\Requests\Finance;

use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('fees.manage');
    }

    /**
     * The manual invoice form: one student, one session, one month, one fee
     * type and one amount. Nothing else is asked.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return [
            'student_id' => [
                'required', 'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'month' => ['required', 'integer', Rule::in(range(1, 12))],
            'fee_type_id' => [
                'required', 'integer',
                Rule::exists('fee_types', 'id')
                    ->where('school_id', $schoolId)
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fee_type_id.exists' => __('That fee type is deactivated or deleted, so it cannot be billed any more.'),
        ];
    }
}
