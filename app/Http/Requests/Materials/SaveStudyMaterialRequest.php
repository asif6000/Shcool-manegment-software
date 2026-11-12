<?php

namespace App\Http\Requests\Materials;

use App\Models\StudyMaterial;
use App\Services\TeachingScope;
use App\Services\StudyMaterialService;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudyMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('materials.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();
        $maxKb = (int) config('school.uploads.'.StudyMaterialService::UPLOAD_PROFILE.'.max_kb');
        $classId = (int) $this->input('class_id');

        // A teacher files under their own name; only the office may name
        // someone else, and only for a material the chooser allowed.
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
                    ->where('class_id', $classId),
            ],
            'subject_id' => [
                'required', 'integer',
                Rule::exists('subjects', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'teacher_id' => ['nullable', 'integer', $teacherRule],
            // The classwork is filed against the lesson it was set in, so the
            // lesson has to be one of the same class and subject.
            'lesson_id' => [
                'nullable', 'integer',
                Rule::exists('lessons', 'id')
                    ->where('school_id', $schoolId)
                    ->where('class_id', $classId)
                    ->where('subject_id', (int) $this->input('subject_id'))
                    ->whereNull('deleted_at'),
            ],
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['nullable', 'string', 'max:4000'],
            'kind' => ['nullable', Rule::in(StudyMaterial::KINDS)],
            'work_date' => ['nullable', 'date'],
            // Checked shares it with the class and notifies them; unchecked
            // leaves it as a draft only staff can see.
            'publish' => ['nullable', 'boolean'],
            // A classwork entry can be notes only; a study material or a new
            // share without text still needs the file. FileUploadService
            // re-sniffs the real MIME type, so the extension alone cannot be
            // trusted.
            'file' => [
                $this->needsFile() ? 'required' : 'nullable',
                'file',
                'extensions:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,webp',
                'max:'.$maxKb,
            ],
        ];
    }

    private function needsFile(): bool
    {
        // Editing keeps the current file, and classwork can be notes only.
        // A brand-new study material must carry the document.
        if ($this->route('material') !== null) {
            return false;
        }

        return $this->input('kind') !== StudyMaterial::CLASSWORK;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => __('Attach the file the class should open.'),
            'file.mimes' => __('Attach a PDF, Word or Excel document, a text file or an image.'),
            'file.max' => __('The file is larger than the allowed size.'),
            'title.min' => __('Give the material a name the students will recognise.'),
        ];
    }
}
