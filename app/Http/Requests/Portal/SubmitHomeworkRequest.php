<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class SubmitHomeworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('student');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'text_answer' => ['nullable', 'string', 'min:1', 'max:8000'],
            // FileUploadService re-sniffs the real MIME type, so the extension
            // alone cannot be trusted.
            'attachment' => ['nullable', 'file', 'extensions:pdf,doc,docx,txt,jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attachment.file' => __('Upload a file for your answer.'),
            'attachment.mimes' => __('Allowed file types are PDF, Word, text and images.'),
            'attachment.max' => __('The attachment must be 4 MB or smaller.'),
            'text_answer.max' => __('Your answer can be up to :max characters.', ['max' => 8000]),
        ];
    }
}
