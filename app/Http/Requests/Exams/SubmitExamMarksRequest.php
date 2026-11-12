<?php

namespace App\Http\Requests\Exams;

use App\Models\Exam;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitExamMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('marks.submit');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();
        /** @var Exam $exam */
        $exam = $this->route('exam');

        return [
            'paper_id' => [
                'required', 'integer',
                Rule::exists('exam_subjects', 'id')->where('school_id', $schoolId)->where('exam_id', $exam?->id),
            ],
            'section_id' => [
                'nullable', 'integer',
                Rule::exists('sections', 'id')->where('school_id', $schoolId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'paper_id.exists' => __('Choose one of this exam\'s papers.'),
        ];
    }
}
