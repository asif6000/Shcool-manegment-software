<?php

namespace App\Http\Requests\Portal;

use App\Models\StudentLeave;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyLeaveRequest extends FormRequest
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
        return [
            'from_date' => ['required', 'date', 'after_or_equal:'.now()->subDays(30)->toDateString()],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'leave_type' => ['required', Rule::in(StudentLeave::TYPES)],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.min' => __('Please tell the school why you need leave (at least :min characters).', ['min' => 10]),
            'to_date.after_or_equal' => __('The return date cannot be before the first day of leave.'),
            'from_date.after_or_equal' => __('You can only report leave for the last month.'),
            'contact_phone.regex' => __('Enter a valid mobile number, for example 01XXXXXXXXX.'),
        ];
    }
}
