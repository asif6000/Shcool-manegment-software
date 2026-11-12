<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Services\AuthenticationService;
use App\Support\RoleHierarchy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * An accountant signs in with the mobile number, so that is the only
 * identifier this form collects. The email column stays for the roles that
 * need it and is left empty here.
 */
class StoreAccountantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class)
            && RoleHierarchy::canAssignRole($this->user(), 'accountant');
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
                'unique:users,mobile_number',
            ],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Give the accountant a name.'),
            'mobile_number.required' => __('The mobile number is the sign-in identifier for the accountant.'),
            'mobile_number.regex' => __('Enter a valid Bangladeshi mobile number, e.g. 01XXXXXXXXX.'),
            'mobile_number.unique' => __('Another account already signs in with this mobile number.'),
        ];
    }
}
