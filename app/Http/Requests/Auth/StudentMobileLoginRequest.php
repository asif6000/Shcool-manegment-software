<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class StudentMobileLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mobile = preg_replace('/[\s\-]/', '', (string) $this->input('mobile_number'));
        $this->merge(['mobile_number' => $mobile]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mobile_number' => ['required', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'password'      => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile_number.required' => __('Mobile number is required.'),
            'mobile_number.regex'    => __('Enter a valid Bangladeshi mobile number, e.g. 01XXXXXXXXX.'),
            'password.required'      => __('Password is required.'),
        ];
    }
}
