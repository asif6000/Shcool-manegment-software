<?php

namespace App\Http\Requests\Staff;

use App\Models\User;
use App\Support\SchoolContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('teacher') === null ? 'staff.create' : 'staff.update';

        return (bool) $this->user()?->can($permission);
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
        $schoolId = app(SchoolContext::class)->id();
        $teacherId = $this->route('teacher')?->id;
        $loginUserId = $this->route('teacher')?->user_id;

        return [
            'employee_code' => [
                'nullable', 'string', 'max:30',
                Rule::unique('teachers', 'employee_code')->where('school_id', $schoolId)->ignore($teacherId),
            ],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => [
                'required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/',
                // A portal login signs in with this number, so it cannot already
                // belong to another account. Teachers without a login may share.
                function (string $attribute, mixed $value, Closure $fail) use ($loginUserId): void {
                    if (blank($this->input('password'))) {
                        return;
                    }

                    $holder = User::query()
                        ->where('mobile_number', preg_replace('/[\s\-()]/', '', (string) $value))
                        ->when(
                            $loginUserId !== null,
                            fn ($query) => $query->whereKeyNot($loginUserId),
                        )
                        ->first();

                    if ($holder !== null) {
                        $fail(__('This mobile number already belongs to another account.'));
                    }
                },
            ],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'designation' => ['required', 'string', 'max:80'],
            'joining_date' => ['required', 'date'],
            'qualification' => ['nullable', 'string', 'max:120'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => [
                'integer',
                Rule::exists('classes', 'id')
                    ->where('school_id', $schoolId)
                    ->whereNull('deleted_at'),
            ],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'visiting'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'resigned'])],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
