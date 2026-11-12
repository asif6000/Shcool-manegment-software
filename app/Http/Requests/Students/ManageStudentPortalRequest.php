<?php

namespace App\Http\Requests\Students;

use App\Services\AuthenticationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ManageStudentPortalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('users.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('mobile_number')) {
            $this->merge([
                'mobile_number' => AuthenticationService::normaliseMobile(trim((string) $this->input('mobile_number'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mobile_number' => ['required', 'string', 'max:30', 'regex:'.AuthenticationService::MOBILE_PATTERN],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile_number.required' => __('Mobile number is required.'),
            'mobile_number.regex' => __('Enter a valid Bangladeshi mobile number, e.g. 01XXXXXXXXX.'),
        ];
    }
}
