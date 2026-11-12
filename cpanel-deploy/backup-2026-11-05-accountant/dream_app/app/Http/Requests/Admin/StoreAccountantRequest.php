<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\RoleHierarchy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

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
            'mobile_number' => preg_replace('/[\s\-()]/', '', (string) $this->input('mobile_number')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'mobile_number' => ['required', 'string', 'max:30', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/', 'unique:users,mobile_number'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
