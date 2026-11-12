<?php

namespace App\Http\Requests\Notifications;

use App\Models\DevicePushToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceTokenRequest extends FormRequest
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
            'platform' => ['required', 'string', Rule::in(DevicePushToken::PLATFORMS)],
            'token' => [
                'required',
                'string',
                'min:20',
                'max:255',
                'regex:/^(ExponentPushToken|ExpoPushToken)\[[A-Za-z0-9_\-]{8,200}\]$/',
            ],
            'device_name' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.regex' => __('The token must be a valid Expo push token.'),
        ];
    }
}
