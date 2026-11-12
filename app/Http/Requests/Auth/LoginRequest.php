<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->input('role', 'admin');

        return [
            'role' => ['required', 'in:admin,principal,teacher,student,accountant'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
            // Email required for admin, mobile for other roles
            $role === 'admin' ? 'email' : 'mobile_number' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, $fail) use ($role): void {
                    if ($role === 'admin') {
                        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                            $fail(__('Enter a valid email address.'));
                        }
                    } else {
                        $mobile = preg_replace('/[\s\-()]/', '', (string) $value);
                        if (! preg_match('/^(?:\+?88)?01[3-9]\d{8}$/', $mobile)) {
                            $fail(__('Enter a valid Bangladeshi mobile number, e.g. 01XXXXXXXXX.'));
                        }
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $role = $this->input('role', 'admin');

        return [
            'role.required' => __('Please select your role.'),
            'role.in' => __('Invalid role selected.'),
            'password.required' => __('Password is required.'),
            'email.required' => __('Email address is required for admin login.'),
            'mobile_number.required' => __('Mobile number is required for login.'),
        ];
    }
}
