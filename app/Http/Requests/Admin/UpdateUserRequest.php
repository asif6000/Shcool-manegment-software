<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Services\AuthenticationService;
use App\Support\RoleHierarchy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
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
        /** @var User $target */
        $target = $this->route('user');

        $allowedRoles = (array) config('school.workspace_roles');

        if ($target->hasRole('student')) {
            $allowedRoles[] = 'student';
        }

        $role = (string) ($this->input('role') ?? $target->getRoleNames()->first() ?? '');
        $usesMobile = RoleHierarchy::usesMobileLogin($role);

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                $usesMobile ? 'nullable' : 'required',
                'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($target->id),
            ],
            'mobile_number' => [
                $usesMobile ? 'required' : 'nullable',
                'string', 'max:30',
                'regex:'.AuthenticationService::MOBILE_PATTERN,
                Rule::unique('users', 'mobile_number')->ignore($target->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
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
