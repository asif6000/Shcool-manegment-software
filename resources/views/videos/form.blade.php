@extends('layouts.app')

@section('title', __('Class video'))

@section('content')
    @php
        $oldClass = (int) old('class_id', (string) ($selectedClass ?? 0));
        $maxMb = round($storageKb / 1024, 1);
        $editing = $video->exists;
        $oldSource = old('source', $editing ? $video->source : \App\Models\ClassVideo::YOUTUBE);
        // A hidden required input still blocks the browser submit, so only the
        // source now on screen may carry the required flag.
        $youtubeRequired = $oldSource === \App\Models\ClassVideo::YOUTUBE;
        $fileRequired = $oldSource === \App\Models\ClassVideo::UPLOAD && ! ($editing && $current_file);
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-camera-reels"></i>{{ __('Class videos') }}</span>
            <h1 class="page-title">{{ $editing ? __('Edit class video') : __('Add class video') }}</h1>
            <p class="page-subtitle">{{ __('One video, filed against a class. Paste a YouTube link or upload a clip — the home page only shows the ones you switch on.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('videos.index') }}" class="btn btn-light-soft">
                <i class="bi bi-arrow-left"></i>{{ __('All videos') }}
            </a>
        </div>
    </div>

    @if ($classes->isEmpty() || $sessions->isEmpty())
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-camera-reels"></i></div>
                <h3>{{ __('Set up the academic structure first') }}</h3>
                <p>{{ __('A video needs an academic session and at least one class.') }}</p>
                <a href="{{ route('academic.index') }}" class="btn btn-primary mt-3">
                    <i class="bi bi-plus-lg"></i>{{ __('Open academic setup') }}
                </a>
            </div>
        </section>
    @else
        <form method="POST" enctype="multipart/form-data"
              action="{{ $editing ? route('videos.update', $video) : route('videos.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif
            <div class="row g-3">
                <div class="col-lg-8">
                    <section class="ui-card p-lg">
                        <h2 class="card-title">{{ __('The video') }}</h2>
                        <p class="text-muted-2" style="font-size:.8rem;">
                            {{ __('Name it after the lesson so it can be found again.') }}
                        </p>

                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <label class="form-label" for="cv-title">{{ __('Title') }}<span class="required">*</span></label>
                                <input id="cv-title" type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                       value="{{ old('title', $editing ? $video->title : '') }}" maxlength="160" required
                                       placeholder="{{ __('Maths — fractions revision, Class Five') }}">
                                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <span class="form-label">{{ __('Where does the video come from?') }}</span>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="source" id="cv-source-youtube"
                                               value="youtube" data-source-choice="youtube" @checked($oldSource === 'youtube')>
                                        <label class="form-check-label" for="cv-source-youtube">
                                            <i class="bi bi-youtube"></i>{{ __('YouTube link') }}
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="source" id="cv-source-upload"
                                               value="upload" data-source-choice="upload" @checked($oldSource === 'upload')>
                                        <label class="form-check-label" for="cv-source-upload">
                                            <i class="bi bi-cloud-arrow-up"></i>{{ __('Upload a file') }}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12" data-source-field="youtube" data-requires-input="1"
                                 @if ($oldSource !== 'youtube') hidden @endif>
                                <label class="form-label" for="cv-youtube">{{ __('YouTube link') }}<span class="required">*</span></label>
                                <input id="cv-youtube" type="url" name="youtube_url" inputmode="url"
                                       class="form-control @error('youtube_url') is-invalid @enderror"
                                       value="{{ old('youtube_url', $editing ? $video->youtube_url : '') }}" maxlength="300"
                                       @required($youtubeRequired)
                                       placeholder="https://www.youtube.com/watch?v=AbCdEfGhIjK">
                                @error('youtube_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">
                                    {{ __('Any youtube.com or youtu.be link to the video works. Only the video itself is played — nothing else from the page.') }}
                                </div>
                            </div>

                            <div class="col-12" data-source-field="upload" @if ($oldSource !== 'upload') hidden @endif
                                 @unless ($editing && $current_file) data-requires-input="1" @endunless>
                                <label class="form-label" for="cv-file">{{ __('Video file') }}<span class="required" @if (! $fileRequired) hidden @endif>*</span></label>
                                <input id="cv-file" type="file" name="file" class="form-control @error('file') is-invalid @enderror"
                                       accept=".mp4,.webm,.ogv,.mov" @required($fileRequired)>
                                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">
                                    @if ($editing && $current_file)
                                        {{ __('Current file: :name. Choose a file only to replace it.', ['name' => $current_file]) }}
                                    @else
                                        {{ __('MP4, WebM, Ogg or QuickTime up to :max MB. The file type is checked from the bytes, not the name.', ['max' => $maxMb]) }}
                                    @endif
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="cv-description">{{ __('Note') }}</label>
                                <textarea id="cv-description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror"
                                          maxlength="4000"
                                          placeholder="{{ __('What the class should watch and when.') }}">{{ old('description', $editing ? $video->description : '') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-camera-reels"></i>{{ $editing ? __('Save changes') : __('Save video') }}
                            </button>
                            <a href="{{ route('videos.index') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                        </div>
                    </section>
                </div>

                <div class="col-lg-4">
                    <section class="ui-card p-lg">
                        <h2 class="card-title">{{ __('Which class') }}</h2>

                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <label class="form-label" for="cv-session">{{ __('Academic session') }}<span class="required">*</span></label>
                                <select id="cv-session" name="academic_session_id" class="form-select @error('academic_session_id') is-invalid @enderror" required>
                                    @foreach ($sessions as $session)
                                        <option value="{{ $session->id }}"
                                                @selected((int) old('academic_session_id', (string) ($selectedSession ?? 0)) === $session->id)>
                                            {{ $session->name }}@if ($session->is_current) · {{ __('Current') }}@endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('academic_session_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="cv-class">{{ __('Class') }}<span class="required">*</span></label>
                                <select id="cv-class" name="class_id" class="form-select @error('class_id') is-invalid @enderror" required>
                                    @foreach ($classes as $class)
                                        <option value="{{ $class->id }}" @selected($oldClass === $class->id)>
                                            {{ $class->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="cv-section">{{ __('Section') }}</label>
                                <select id="cv-section" name="section_id" class="form-select @error('section_id') is-invalid @enderror">
                                    <option value="">{{ __('Whole class (all sections)') }}</option>
                                    @foreach ($classes as $class)
                                        @if ($class->sections->isNotEmpty())
                                            <optgroup label="{{ $class->name }}">
                                                @foreach ($class->sections as $section)
                                                    <option value="{{ $section->id }}"
                                                            @selected((int) old('section_id', (string) ($selectedSection ?? 0)) === $section->id)>
                                                        {{ $section->name }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach
                                </select>
                                @error('section_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="cv-subject">{{ __('Subject') }}</label>
                                <select id="cv-subject" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror">
                                    <option value="">{{ __('Not tied to one subject') }}</option>
                                    @foreach ($subjects as $subject)
                                        <option value="{{ $subject->id }}" @selected((int) old('subject_id', (string) ($selectedSubject ?? 0)) === $subject->id)>
                                            {{ $subject->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">{{ __('Leave it blank for an assembly or event recording.') }}</div>
                            </div>
                        </div>
                    </section>

                    <section class="ui-card p-lg mt-3">
                        <h2 class="card-title">{{ __('School website') }}</h2>
                        <div class="form-check form-switch mt-2">
                            <input type="hidden" name="show_on_website" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="cv-website"
                                   name="show_on_website" value="1"
                                   @checked((bool) old('show_on_website', $editing ? $video->show_on_website : false))>
                            <label class="form-check-label" for="cv-website">{{ __('Show on the home page') }}</label>
                        </div>
                        <p class="text-muted-2 mt-2 mb-0" style="font-size:.78rem;">
                            {{ __('Anyone visiting the school website can play a video you switch on, so publish the ones meant for families.') }}
                        </p>
                    </section>
                </div>
            </div>
        </form>

        <script>
            (function () {
                var choices = document.querySelectorAll('[data-source-choice]');
                var fields = document.querySelectorAll('[data-source-field]');

                if (!choices.length || !fields.length) {
                    return;
                }

                function apply(value) {
                    fields.forEach(function (field) {
                        var shown = field.getAttribute('data-source-field') === value;
                        var input = field.querySelector('input');
                        var mark = field.querySelector('.required');

                        field.hidden = !shown;

                        if (input) {
                            // Only a visible field may block the browser submit.
                            input.required = shown && field.hasAttribute('data-requires-input');
                        }

                        if (mark) {
                            mark.hidden = !input || !input.required;
                        }
                    });
                }

                choices.forEach(function (choice) {
                    choice.addEventListener('change', function () {
                        apply(choice.value);
                    });
                });
            })();
        </script>
    @endif
@endsection
