<?php

namespace App\Http\Requests\Staff;

use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyOwnLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('leave.apply');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return [
            'leave_type_id' => [
                'required', 'integer',
                Rule::exists('leave_types', 'id')->where('school_id', $schoolId)->where('is_active', true),
            ],
            // A week back is the honest window for a sick day told to the office
            // the next morning; anything older belongs in the office register.
            'start_date' => ['required', 'date', 'after_or_equal:'.now()->subWeek()->toDateString()],
            'end_date' => [
                'required', 'date', 'after_or_equal:start_date',
                'before_or_equal:'.now()->addYear()->toDateString(),
            ],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.after_or_equal' => __('Leave has to start within the last week. Ask the office for older days.'),
            'end_date.before_or_equal' => __('Leave can be applied for up to a year ahead.'),
            'reason.min' => __('Say in a sentence why you will be away.'),
        ];
    }
}
