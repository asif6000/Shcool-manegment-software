<?php

namespace App\Http\Requests\Finance;

use App\Services\FeeAssignmentService;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFeeAssignmentRequest extends FormRequest
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
        $schoolId = (int) app(SchoolContext::class)->id();
        $scope = (string) $this->input('scope', 'class');

        return [
            'fee_type_id' => [
                'required', 'integer',
                Rule::exists('fee_types', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'scope' => ['required', Rule::in(FeeAssignmentService::SCOPES)],
            'class_id' => [
                $scope === 'class' ? 'required' : 'nullable',
                'integer',
                Rule::exists('classes', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'section_id' => [
                'nullable',
                'integer',
                Rule::exists('sections', 'id')->where('school_id', $schoolId),
            ],
            'student_ids' => [
                $scope === 'students' ? 'required' : 'nullable',
                'array',
                'min:1',
                'max:500',
            ],
            'student_ids.*' => [
                'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:9999999'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_ids.required' => __('Pick the students this fee applies to.'),
            'ends_on.after' => __('The end date must be after the start date.'),
        ];
    }
}
