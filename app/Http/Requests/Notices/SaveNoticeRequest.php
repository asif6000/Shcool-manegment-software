<?php

namespace App\Http\Requests\Notices;

use App\Models\Notice;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('notices.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:8000'],
            'audience' => ['required', 'string', Rule::in(Notice::AUDIENCES)],
            'academic_session_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:published_at'],
            'is_pinned' => ['nullable', 'boolean'],
            'notify_audience' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expires_at.after_or_equal' => __('The expiry date must be on or after the publish date.'),
        ];
    }
}
