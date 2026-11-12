<?php

namespace App\Http\Requests\Materials;

use App\Models\ClassVideo;
use App\Support\SchoolContext;
use App\Support\YouTubeUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveClassVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('videos.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();
        $maxKb = (int) config('school.uploads.'.ClassVideo::UPLOAD_PROFILE.'.max_kb');

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
                'nullable', 'integer',
                Rule::exists('subjects', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['nullable', 'string', 'max:4000'],
            'source' => ['required', Rule::in(ClassVideo::SOURCES)],
            'youtube_url' => ['required_if:source,'.ClassVideo::YOUTUBE, 'nullable', 'string', 'max:300'],
            'file' => [
                $this->needsFile() ? 'required' : 'nullable',
                'file',
                'mimes:'.implode(',', config('school.uploads.'.ClassVideo::UPLOAD_PROFILE.'.mimes', [])),
                'max:'.$maxKb,
            ],
            'show_on_website' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $inner): void {
            if ($this->input('source') !== ClassVideo::YOUTUBE) {
                return;
            }

            if (YouTubeUrl::videoId($this->string('youtube_url')->toString()) === null) {
                $inner->errors()->add('youtube_url', __('Paste a YouTube link such as youtu.be/AbCdEfGhIjK or youtube.com/watch?v=AbCdEfGhIjK.'));
            }
        });
    }

    /**
     * An uploaded clip is needed unless the row already carries one, in which
     * case editing the title or the website flag keeps the stored file.
     */
    private function needsFile(): bool
    {
        if ($this->input('source') !== ClassVideo::UPLOAD) {
            return false;
        }

        $video = $this->route('video');

        return ! ($video instanceof ClassVideo && $video->file_path !== null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'youtube_url.required_if' => __('A YouTube video needs the link it points to.'),
            'youtube_url.max' => __('That link is too long to be a video address.'),
            'file.mimes' => __('Upload an MP4, WebM, Ogg or QuickTime video file.'),
            'file.max' => __('The video is larger than the allowed size.'),
            'title.min' => __('Give the video a name the class will recognise.'),
        ];
    }
}
