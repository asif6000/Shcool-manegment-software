<?php

namespace App\Http\Requests\Exams;

use Illuminate\Foundation\Http\FormRequest;

class ReviewMarkSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('marks.approve');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // A rejection has to say what is wrong; an approval note is optional
            // because most sheets are approved as they stand.
            'note' => [
                $this->route('action') === 'reject' ? 'required' : 'nullable',
                'string',
                'min:5',
                'max:255',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => __('Say what needs to change before sending the marks sheet back.'),
            'note.min' => __('Give at least 5 characters so the teacher knows what to fix.'),
        ];
    }
}
