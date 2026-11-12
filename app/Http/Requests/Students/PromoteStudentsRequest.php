<?php

namespace App\Http\Requests\Students;

use App\Models\StudentPromotion;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PromoteStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('academic.manage');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return [
            'from_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'action' => ['required', Rule::in(StudentPromotion::ACTIONS)],
            'student_ids' => ['required', 'array', 'min:1', 'max:500'],
            'student_ids.*' => [
                'integer', 'distinct',
                Rule::exists('students', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'target_session_id' => [
                'nullable', 'required_if:action,promote,repeat', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'target_class_id' => [
                'nullable', 'required_if:action,promote,repeat', 'integer',
                Rule::exists('classes', 'id')
                    ->where('school_id', $schoolId)
                    ->whereNull('deleted_at'),
            ],
            'target_section_id' => [
                'nullable', 'required_if:action,promote,repeat', 'integer',
                Rule::exists('sections', 'id')
                    ->where('school_id', $schoolId)
                    ->whereNull('deleted_at'),
            ],
            'roll_strategy' => ['nullable', Rule::in(['auto', 'keep', 'manual'])],
            'roll_no' => ['nullable', 'required_if:roll_strategy,manual', 'integer', 'min:1', 'max:9999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('roll_strategy') === 'manual' && count((array) $this->input('student_ids', [])) > 1) {
                    $validator->errors()->add(
                        'roll_strategy',
                        __('Manual rolls can only be assigned when one student is selected. Use automatic or keep roll for a batch.'),
                    );
                }
            },
        ];
    }
}
