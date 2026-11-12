<?php

namespace App\Http\Requests\Exams;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGradeScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('exams.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.grade_letter' => ['required', 'string', 'max:5'],
            'items.*.min_percentage' => ['required', 'numeric', 'between:0,100'],
            'items.*.max_percentage' => ['required', 'numeric', 'between:0,100'],
            'items.*.grade_point' => ['required', 'numeric', 'between:0,5'],
            'items.*.remarks' => ['nullable', 'string', 'max:80'],
        ];
    }
}
