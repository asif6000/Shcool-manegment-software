<?php

namespace App\Http\Requests\Admin;

use App\Services\AuthenticationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing an accountant changes the name and the number they sign in with.
 * The role is not part of the form, so this screen can never promote an
 * accountant into another role.
 */
class UpdateAccountantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('accountant'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mobile_number' => AuthenticationService::normaliseMobile((string) $this->input('mobile_number')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'mobile_number' => [
                'required', 'string', 'max:30',
                'regex:'.AuthenticationService::MOBILE_PATTERN,
                Rule::unique('users', 'mobile_number')->ignore($this->route('accountant')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile_number.required' => __('The mobile number is the sign-in identifier for the accountant.'),
            'mobile_number.regex' => __('Enter a valid Bangladeshi mobile number, e.g. 01XXXXXXXXX.'),
            'mobile_number.unique' => __('Another account already signs in with this mobile number.'),
        ];
    }
}
