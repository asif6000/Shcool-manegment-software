<?php

namespace App\Http\Requests\Applicant;

use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdmissionApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('student');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return [
            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')
                    ->where('school_id', $schoolId)
                    ->where('status', 'active'),
            ],
            'applied_class_id' => [
                'required', 'integer',
                Rule::exists('classes', 'id')
                    ->where('school_id', $schoolId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'first_name' => ['required', 'string', 'min:2', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => [
                'required', 'date',
                'before_or_equal:'.now()->subYears(3)->toDateString(),
                'after_or_equal:1950-01-01',
            ],
            'guardian_name' => ['required', 'string', 'min:2', 'max:120'],
            'guardian_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'guardian_email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'previous_school' => ['nullable', 'string', 'max:120'],
            // FileUploadService re-sniffs the real MIME type, so the extension
            // alone cannot be trusted.
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'guardian_phone.regex' => __('Enter a valid mobile number, for example 01XXXXXXXXX.'),
            'date_of_birth.before_or_equal' => __('The applicant must be at least :age years old.', ['age' => 3]),
            'photo.image' => __('Upload a passport-size photo as a JPEG, PNG or WebP image.'),
            'photo.dimensions' => __('The photo must be between 100x100 and 4000x4000 pixels.'),
        ];
    }
}
