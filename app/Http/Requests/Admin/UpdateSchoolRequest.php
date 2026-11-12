<?php

namespace App\Http\Requests\Admin;

use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('school.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'short_name', 'eiin', 'email', 'phone', 'phone_alt', 'website',
            'address', 'division', 'district', 'upazila', 'postcode', 'motto',
        ];

        $data = [];

        foreach ($nullable as $field) {
            $data[$field] = $this->filled($field) ? trim((string) $this->input($field)) : null;
        }

        $data['code'] = mb_strtoupper(trim((string) $this->input('code')));
        $data['email'] = $this->filled('email') ? mb_strtolower((string) $this->input('email')) : null;

        $this->merge($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = $this->schoolId();
        $logo = config('school.uploads.school_logo');

        return [
            'name' => ['required', 'string', 'max:160'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'code' => [
                'required', 'string', 'max:30', 'alpha_dash',
                Rule::unique('schools', 'code')->ignore($schoolId),
            ],
            'eiin' => [
                'nullable', 'string', 'max:20',
                Rule::unique('schools', 'eiin')->ignore($schoolId),
            ],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'phone_alt' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'division' => ['nullable', 'string', 'max:60'],
            'district' => ['nullable', 'string', 'max:60'],
            'upazila' => ['nullable', 'string', 'max:60'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'motto' => ['nullable', 'string', 'max:255'],
            'established_year' => ['nullable', 'integer', 'between:1800,'.(now()->year + 1)],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3'],
            'locale' => ['required', Rule::in(config('school.locales'))],
            'logo' => [
                'nullable',
                'file',
                'mimes:'.implode(',', $logo['mimes']),
                'max:'.$logo['max_kb'],
            ],
        ];
    }

    private function schoolId(): ?int
    {
        return app(SchoolContext::class)->id() ?? $this->user()?->school_id;
    }
}
