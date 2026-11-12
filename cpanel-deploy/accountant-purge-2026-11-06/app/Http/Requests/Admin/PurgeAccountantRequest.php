<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The purge dialog is the only place an accountant login and its money can be
 * removed for good, so the admin proves both identity and intent: their own
 * password, and the exact phrase DELETE ALL.
 */
class PurgeAccountantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('delete', $this->route('accountant'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'delete_confirmation' => trim((string) $this->input('delete_confirmation')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admin_password' => ['required', 'string', 'current_password'],
            'delete_confirmation' => ['required', 'string', Rule::in(['DELETE ALL'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'admin_password.required' => __('Enter your password to confirm the deletion.'),
            'admin_password.current_password' => __('That password does not match your account.'),
            'delete_confirmation.required' => __('Type DELETE ALL to confirm the deletion.'),
            'delete_confirmation.in' => __('Type DELETE ALL exactly, in capital letters.'),
        ];
    }
}
