<?php

namespace App\Http\Requests\Notifications;

use App\Models\NotificationPreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.category' => ['required', 'string', Rule::in(NotificationPreference::CATEGORIES)],
            'preferences.*.channel' => ['required', 'string', Rule::in(NotificationPreference::CHANNELS)],
            'preferences.*.enabled' => ['required', 'boolean'],
        ];
    }
}
