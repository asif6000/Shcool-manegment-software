<?php

namespace App\Http\Requests\Homework;

use App\Models\Homework;
use App\Services\TeachingScope;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveHomeworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Homework::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();

        // Only an office account may name another teacher on an assignment. A
        // teacher's own name is taken from their profile, so a hand-posted
        // teacher_id cannot move work onto a colleague.
        $teacherRule = Rule::exists('teachers', 'id')
            ->where('school_id', $schoolId)
            ->whereNull('deleted_at');

        if (! app(TeachingScope::class)->isUnrestricted($this->user())) {
            $teacherRule = $teacherRule->where('user_id', (int) $this->user()->id);
        }

        return [
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
            'teacher_id' => ['nullable', 'integer', $teacherRule],
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['required', 'string', 'min:5', 'max:8000'],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'assigned_date' => ['required', 'date'],
            // Classwork has no deadline; homework keeps one.
            'due_date' => ['nullable', 'date', 'after_or_equal:assigned_date'],
            // FileUploadService re-sniffs the real MIME type, so the extension
            // alone cannot be trusted.
            'attachment' => ['nullable', 'file', 'extensions:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,webp', 'max:4096'],
            // Checked saves the homework published and notifies the class;
            // unchecked keeps it as a draft only staff can see.
            'publish' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_date.after_or_equal' => __('The deadline cannot be earlier than the day the work was assigned.'),
            'description.required' => __('Describe what the students have to do.'),
            'attachment.mimes' => __('Attach a PDF, Word or Excel document, a text file or an image.'),
        ];
    }
}
