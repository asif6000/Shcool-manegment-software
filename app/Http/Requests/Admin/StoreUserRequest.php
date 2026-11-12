<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Services\AuthenticationService;
use App\Support\RoleHierarchy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $email = mb_strtolower(trim((string) $this->input('email')));
        $mobile = preg_replace('/[\s\-()]/', '', (string) $this->input('mobile_number'));

        $this->merge([
            'email' => $email === '' ? null : $email,
            'mobile_number' => $mobile === '' ? null : $mobile,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = (string) $this->input('role', '');
        $usesMobile = $role !== '' && RoleHierarchy::usesMobileLogin($role);

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => $this->identifierRules('email', $usesMobile),
            'mobile_number' => $this->identifierRules('mobile_number', $usesMobile),
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'string', 'in:'.implode(',', (array) config('school.workspace_roles'))],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function identifierRules(string $field, bool $usesMobile): array
    {
        if ($field === 'mobile_number') {
            return [
                $usesMobile ? 'required' : 'nullable',
                'string', 'max:30',
                'regex:'.AuthenticationService::MOBILE_PATTERN,
                'unique:users,mobile_number',
            ];
        }

        return [
            $usesMobile ? 'nullable' : 'required',
            'string', 'email', 'max:255',
            'unique:users,email',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile_number.required' => __('The mobile number is the sign-in identifier for this role.'),
            'mobile_number.regex' => __('Enter a valid Bangladeshi mobile number, e.g. 01XXXXXXXXX.'),
            'role.in' => __('Only staff roles can be assigned here. Student portal accounts are created from a student record.'),
        ];
    }
}
