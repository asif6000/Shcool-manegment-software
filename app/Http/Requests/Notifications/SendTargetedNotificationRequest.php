<?php

namespace App\Http\Requests\Notifications;

use App\Services\TargetedNotificationService;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendTargetedNotificationRequest extends FormRequest
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
            'campaign_id' => ['required', 'uuid'],
            'audience' => ['required', 'string', Rule::in(TargetedNotificationService::AUDIENCES)],
            'recipient_scope' => ['required', 'string', Rule::in(['all', 'selected'])],
            'recipient_ids' => [
                'nullable',
                'array',
                'max:500',
                Rule::requiredIf(fn (): bool => $this->input('recipient_scope') === 'selected'),
            ],
            'recipient_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($schoolId): void {
                    $query->where('school_id', $schoolId)
                        ->where('is_active', true)
                        ->whereNull('deleted_at');
                }),
            ],
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'message' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipient_ids.required' => __('Choose at least one recipient.'),
            'recipient_ids.max' => __('Send to 500 recipients at a time.'),
            'title.min' => __('Give the message a clear title.'),
            'message.min' => __('Write a message for the recipients.'),
        ];
    }
}
