<?php

namespace App\Http\Requests\Homework;

use App\Models\AcademicSession;
use App\Models\Homework;
use App\Services\TeachingScope;
use App\Support\SchoolContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The single save behind the unified "Add homework / classwork" screen. One
 * period, one title and one attachment can produce either one entry or the
 * classwork-plus-homework pair, and the timetable decides which periods the
 * actor may name at all.
 */
class StoreWorkRequest extends FormRequest
{
    public function __construct(private readonly TeachingScope $teaching) {}

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Homework::class);
    }

    /**
     * The form shows one "Class / Section / Subject" box, so the period arrives
     * as a single "class:section:subject" value and is split before the rules run.
     */
    protected function prepareForValidation(): void
    {
        $parts = array_pad(explode(':', (string) $this->input('period')), 3, '');

        $this->merge([
            'class_id' => ctype_digit($parts[0]) ? (int) $parts[0] : null,
            'section_id' => ctype_digit($parts[1]) ? (int) $parts[1] : null,
            'subject_id' => ctype_digit($parts[2]) ? (int) $parts[2] : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();
        $type = (string) $this->input('work_type');

        return [
            'period' => ['required', 'string'],
            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'class_id' => [
                'required', 'integer',
                Rule::exists('classes', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'section_id' => [
                'nullable', 'integer',
                Rule::exists('sections', 'id')
                    ->where('school_id', $schoolId)
                    ->where('class_id', (int) $this->input('class_id')),
            ],
            'subject_id' => [
                'required', 'integer',
                Rule::exists('subjects', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'work_type' => ['required', 'in:homework,classwork,both'],
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'classwork' => array_merge(
                in_array($type, ['classwork', 'both'], true) ? ['required'] : ['nullable'],
                ['string', 'min:5', 'max:8000'],
            ),
            'homework' => array_merge(
                in_array($type, ['homework', 'both'], true) ? ['required'] : ['nullable'],
                ['string', 'min:5', 'max:8000'],
            ),
            'instructions' => ['nullable', 'string', 'max:4000'],
            'assigned_date' => ['required', 'date'],
            // Classwork is the work already done in class, so it has no deadline.
            'due_date' => ['nullable', 'date', 'after_or_equal:assigned_date'],
            'attachment' => ['nullable', 'file', 'extensions:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,webp', 'max:4096'],
            'publish' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['class_id', 'section_id', 'subject_id', 'academic_session_id'])) {
                return;
            }

            $session = AcademicSession::query()->find((int) $this->input('academic_session_id'));

            if ($session === null) {
                return;
            }

            try {
                $this->teaching->authorize(
                    $this->user(),
                    $session,
                    (int) $this->input('class_id'),
                    $this->input('section_id') === null ? null : (int) $this->input('section_id'),
                    (int) $this->input('subject_id'),
                );
            } catch (AuthorizationException $exception) {
                $validator->errors()->add('period', $exception->getMessage());
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period.required' => __('Choose the class, section and subject this work is for.'),
            'title.required' => __('Give the work a topic or title.'),
            'classwork.required' => __('Write what was covered in class.'),
            'homework.required' => __('Write what the students have to do at home.'),
            'due_date.after_or_equal' => __('The deadline cannot be earlier than the day the work was assigned.'),
            'attachment.extensions' => __('Attach a PDF, Word or Excel document, a text file or an image.'),
        ];
    }
}
