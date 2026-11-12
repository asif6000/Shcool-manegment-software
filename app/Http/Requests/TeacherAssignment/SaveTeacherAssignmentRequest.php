<?php

namespace App\Http\Requests\TeacherAssignment;

use App\Models\TeacherAssignment;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveTeacherAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $assignment instanceof TeacherAssignment
            ? (bool) $this->user()?->can('update', $assignment)
            : (bool) $this->user()?->can('create', TeacherAssignment::class);
    }

    /**
     * Defaults the screen shows, so a hand-built post cannot skip them.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'assigned_on' => $this->input('assigned_on') ?: now()->toDateString(),
            'status' => $this->input('status') ?: TeacherAssignment::STATUS_ACTIVE,
        ]);
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
            'teacher_id' => [
                'required', 'integer',
                Rule::exists('teachers', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'class_id' => [
                'required', 'integer',
                Rule::exists('classes', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'section_id' => [
                'required', 'integer',
                // The section has to belong to the chosen class, so a hand-built
                // post cannot invent a class/section pair that never existed.
                Rule::exists('sections', 'id')
                    ->where('school_id', $schoolId)
                    ->where('class_id', (int) $this->input('class_id')),
            ],
            'subject_id' => [
                'required', 'integer',
                Rule::exists('subjects', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'assigned_on' => ['required', 'date'],
            'status' => ['required', Rule::in(TeacherAssignment::STATUSES)],
        ];
    }

    /**
     * A duplicate is refused by the screen with a message the admin can act on.
     * The matching unique index in the table is the final guard against two
     * simultaneous saves.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $assignment = $this->route('assignment');
            $currentId = $assignment instanceof TeacherAssignment ? $assignment->id : null;

            $exists = TeacherAssignment::query()
                ->where('school_id', app(SchoolContext::class)->id())
                ->where('academic_session_id', (int) $this->input('academic_session_id'))
                ->where('teacher_id', (int) $this->input('teacher_id'))
                ->where('class_id', (int) $this->input('class_id'))
                ->where('section_id', (int) $this->input('section_id'))
                ->where('subject_id', (int) $this->input('subject_id'))
                ->when($currentId !== null, fn ($query) => $query->whereKeyNot($currentId))
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'subject_id',
                    __('This teacher already has that class, section and subject for this session. Edit the existing assignment instead.'),
                );
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'academic_session_id' => __('academic session'),
            'teacher_id' => __('teacher'),
            'class_id' => __('class'),
            'section_id' => __('section'),
            'subject_id' => __('subject'),
            'assigned_on' => __('assignment date'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'section_id.exists' => __('The chosen section does not belong to the chosen class.'),
        ];
    }
}
