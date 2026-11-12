<?php

namespace App\Http\Requests\Exams;

use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExamMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('marks.enter');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return [
            'paper_id' => ['required', 'integer', 'min:1'],
            'section_id' => [
                'nullable', 'integer',
                Rule::exists('sections', 'id')->where('school_id', $schoolId),
            ],
            'marks' => ['required', 'array', 'min:1'],
            'marks.*.marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'marks.*.is_absent' => ['nullable', 'boolean'],
            'marks.*.remarks' => ['nullable', 'string', 'max:160'],
            'correction_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
