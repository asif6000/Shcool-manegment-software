<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\AuthenticationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StudentMobileRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Store the same canonical form the sign-in lookup uses, so a number
        // typed with +88 is not a second, unreachable account.
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
            'name'          => ['required', 'string', 'min:2', 'max:120'],
            'mobile_number' => [
                'required',
                'string',
                'regex:/^(?:\+?88)?01[3-9]\d{8}$/',
                // Unique across both mobile_number and phone columns so the
                // mobile sign-in can never match two different accounts.
                Rule::unique('users', 'mobile_number')->whereNull('deleted_at'),
                function (string $attribute, mixed $value, $fail): void {
                    // Compare the country-code variants too: an account written
                    // before normalisation may still hold +8801...
                    $variants = array_unique([$value, '88'.$value, '+88'.$value]);

                    $taken = User::query()
                        ->whereNull('deleted_at')
                        ->where(function ($query) use ($variants): void {
                            $query->whereIn('mobile_number', $variants)
                                ->orWhereIn('phone', $variants);
                        })
                        ->exists();

                    if ($taken) {
                        $fail(__('An account with that mobile number already exists. Sign in instead.'));
                    }
                },
            ],
            'password'      => ['required', 'string', 'confirmed', Password::defaults()],
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
            'mobile_number.unique'   => __('An account with that mobile number already exists. Sign in instead.'),
        ];
    }
}
