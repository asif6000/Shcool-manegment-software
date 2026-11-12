<?php

namespace App\Http\Requests\Lessons;

use App\Models\Lesson;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The lesson form. Which class, section and subject may be chosen is not
 * decided here — TeachingScope answers that in the controller, because the
 * timetable is the only place a teacher's assignments live.
 */
class SaveLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('lessons.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();
        $upload = (array) config('school.uploads.lesson_attachment');

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
            'teacher_id' => [
                'nullable', 'integer',
                Rule::exists('teachers', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'chapter_number' => ['nullable', 'string', 'max:20'],
            'chapter_name' => ['nullable', 'string', 'max:160'],
            'lesson_number' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
            'objectives' => ['nullable', 'string', 'max:4000'],
            // Stored only after HtmlContent has had its pass, so the length limit
            // is a cap on the editor output, not a security boundary.
            'content' => ['nullable', 'string', 'max:120000'],
            'video_url' => ['nullable', 'string', 'max:300'],
            'reference_links' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(Lesson::STATUSES)],
            'materials' => ['nullable', 'array', 'max:8'],
            // FileUploadService re-sniffs the real MIME type, so the extension
            // alone cannot be trusted.
            'materials.*' => [
                'file',
                'extensions:'.implode(',', (array) ($upload['mimes'] ?? ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'webp'])),
                'max:'.(int) ($upload['max_kb'] ?? 8192),
            ],
            'remove_materials' => ['nullable', 'array'],
            'remove_materials.*' => ['integer'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('status') === Lesson::PUBLISHED && trim((string) $this->input('content')) === '') {
                    $validator->errors()->add('content', __('Write the lesson before publishing it.'));
                }

                if (is_string($this->input('video_url')) && trim($this->input('video_url')) !== ''
                    && ! preg_match('#^https?://#i', trim((string) $this->input('video_url')))) {
                    $validator->errors()->add('video_url', __('Give the full web address of the video, starting with https://.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => __('Give the lesson a title.'),
            'materials.max' => __('A lesson takes up to eight files. Put the rest in the text or link to them.'),
            'materials.*.mimes' => __('Attach a PDF, Word or Excel document, a text file or an image.'),
            'status.required' => __('Choose whether the lesson is a draft or published.'),
        ];
    }
}
