<?php

namespace App\Http\Requests\Timetable;

use App\Models\ClassRoutine;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Assigning a class, section and subject to a teacher is the school
        // administrator's job alone. The role check is hard-coded so the
        // editable permission matrix cannot hand it to the principal.
        return (bool) $this->user()?->hasRole('school_admin')
            && (bool) $this->user()?->can('timetable.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();
        $inSchool = fn (string $table): array => [
            'required', 'integer',
            Rule::exists($table, 'id')->where('school_id', $schoolId),
        ];

        return [
            'academic_session_id' => $inSchool('academic_sessions'),
            'class_id' => [
                'required', 'integer',
                Rule::exists('classes', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'section_id' => [
                'required', 'integer',
                // Pinning the section to the chosen class stops a slot being
                // written for a class/section pair that never existed.
                Rule::exists('sections', 'id')
                    ->where('school_id', $schoolId)
                    ->where('class_id', (int) $this->input('class_id')),
            ],
            'subject_id' => [
                'required', 'integer',
                Rule::exists('subjects', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'teacher_id' => ['nullable', 'integer',
                Rule::exists('teachers', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'day_of_week' => ['required', 'integer', Rule::in(array_keys(ClassRoutine::DAYS))],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_time.after' => __('The period must end after it starts.'),
            'day_of_week.required' => __('Choose which day of the school week this is.'),
        ];
    }
}
