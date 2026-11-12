    @php
        $editing = $lesson->exists;
        $oldClass = (int) old('class_id', (string) ($selectedClass ?? 0));
        $oldStatus = old('status', $editing ? $lesson->status : \App\Models\Lesson::DRAFT);
        $maxMb = round($storageKb / 1024, 1);
        $imageMb = round($imageStorageKb / 1024, 1);

        $oldSession = (int) old('academic_session_id', (string) ($selectedSession ?? 0));
        $oldSection = (int) old('section_id', (string) ($selectedSection ?? 0));
        $oldSubject = (int) old('subject_id', (string) ($editing ? $lesson->subject_id : 0));

        // Class, section and subject all come out of the same period map the save
        // is checked against, so a combination the server would refuse cannot be
        // offered here in the first place.
        $periodRows = $periods[$oldSession] ?? [];
        $classChoices = collect($periodRows)->unique('class')->values();
        $chosenClass = $oldClass ?: (int) ($classChoices[0]['class'] ?? 0);
        $sectionChoices = collect($periodRows)->where('class', $chosenClass)->unique('section')->values();
        $chosenSection = $oldSection ?: (int) ($sectionChoices[0]['section'] ?? 0);
        $subjectChoices = collect($periodRows)
            ->where('class', $chosenClass)
            ->when($chosenSection > 0, fn ($rows): \Illuminate\Support\Collection => $rows->where('section', $chosenSection))
            ->unique('subject')
            ->values();

        // A failed save sends the typed body back through the same gate, so the
        // page can never echo markup that the browser would act on.
        $body = app(\App\Support\HtmlContent::class)->sanitize(old('content', $editing ? $lesson->content : ''));
    @endphp

    <div class="page-header{{ ($panel ?? false) ? ' panel-header' : '' }}">
        @if (! ($panel ?? false))
            <div>
                <span class="page-eyebrow"><i class="bi bi-easel2"></i>{{ __('Lesson management') }}</span>
                <h1 class="page-title">{{ $editing ? __('Edit lesson') : __('Write a lesson') }}</h1>
                <p class="page-subtitle">{{ __('Everything the class needs for one period: the chapter, the objectives, the explanation and the files that go with it.') }}</p>
            </div>
        @endif
        <div class="page-actions">
            <a href="{{ route('lessons.index') }}" class="btn btn-light-soft" data-td-open="lessons">
                <i class="bi bi-arrow-left"></i>{{ __('All lessons') }}
            </a>
        </div>
    </div>

    @if ($sessions->isEmpty() || $classes->isEmpty() || $subjects->isEmpty() || $periodRows === [])
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-easel2"></i></div>
                <h3>{{ __('Set up the academic structure first') }}</h3>
                <p>{{ $periodRows === [] && ! $classes->isEmpty()
                    ? __('None of the periods for that session are on your timetable, so there is nothing to file a lesson against.')
                    : __('A lesson needs an academic session, a class on your timetable and at least one subject.') }}</p>
                @can('academic.manage')
                    <a href="{{ route('academic.index') }}" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-lg"></i>{{ __('Open academic setup') }}
                    </a>
                @endcan
            </div>
        </section>
    @else
        <form method="POST" enctype="multipart/form-data" data-lsn-form
              data-lsn-image-url="{{ $imageUrl }}" data-lsn-image-max="{{ $imageStorageKb }}"
              data-lsn-periods="{{ json_encode($periods) }}"
              action="{{ $editing ? route('lessons.update', $lesson) : route('lessons.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif

            <input type="hidden" name="content" value="{{ $body }}">

            <div class="lsn-banner" data-lsn-banner role="status" aria-live="polite"></div>
            <div class="lsn-error" data-lsn-global-error role="alert"></div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <section class="ui-card p-lg">
                        <h2 class="card-title">{{ __('The lesson') }}</h2>

                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <label class="form-label" for="lsn-title">{{ __('Lesson title') }}<span class="required">*</span></label>
                                <input id="lsn-title" type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                       value="{{ old('title', $editing ? $lesson->title : '') }}" maxlength="160" required
                                       placeholder="{{ __('Introduction to Fractions') }}">
                                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="lsn-chapter-number">{{ __('Chapter number') }}</label>
                                <input id="lsn-chapter-number" type="text" name="chapter_number" maxlength="20"
                                       class="form-control @error('chapter_number') is-invalid @enderror"
                                       value="{{ old('chapter_number', $editing ? $lesson->chapter_number : '') }}"
                                       placeholder="{{ __('4') }}">
                                @error('chapter_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-5">
                                <label class="form-label" for="lsn-chapter-name">{{ __('Chapter name') }}</label>
                                <input id="lsn-chapter-name" type="text" name="chapter_name" maxlength="160"
                                       class="form-control @error('chapter_name') is-invalid @enderror"
                                       value="{{ old('chapter_name', $editing ? $lesson->chapter_name : '') }}"
                                       placeholder="{{ __('Fractions and Decimals') }}">
                                @error('chapter_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="lsn-lesson-number">{{ __('Lesson number') }}</label>
                                <input id="lsn-lesson-number" type="text" name="lesson_number" maxlength="20"
                                       class="form-control @error('lesson_number') is-invalid @enderror"
                                       value="{{ old('lesson_number', $editing ? $lesson->lesson_number : '') }}"
                                       placeholder="{{ __('2') }}">
                                @error('lesson_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="lsn-description">{{ __('Short description') }}</label>
                                <textarea id="lsn-description" name="description" rows="2" maxlength="2000"
                                          class="form-control @error('description') is-invalid @enderror"
                                          placeholder="{{ __('Two lines the class sees before opening the lesson.') }}">{{ old('description', $editing ? $lesson->description : '') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="lsn-objectives">{{ __('Learning objectives') }}</label>
                                <textarea id="lsn-objectives" name="objectives" rows="3" maxlength="4000"
                                          class="form-control @error('objectives') is-invalid @enderror"
                                          placeholder="{{ __('One objective per line — what the student can do after this lesson.') }}">{{ old('objectives', $editing ? $lesson->objectives : '') }}</textarea>
                                @error('objectives')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">{{ __('Write one per line; they are shown as a list.') }}</div>
                            </div>
                        </div>

                        <div class="lsn-editor-card mt-4">
                            <div class="lsn-toolbar" role="toolbar" aria-label="{{ __('Text formatting') }}">
                                <div class="lsn-toolbar-group">
                                    <select class="lsn-tool lsn-tool-block" data-lsn-block aria-label="{{ __('Paragraph style') }}">
                                        <option value="p">{{ __('Paragraph') }}</option>
                                        <option value="h2">{{ __('Heading') }}</option>
                                        <option value="h3">{{ __('Subheading') }}</option>
                                        <option value="h4">{{ __('Minor heading') }}</option>
                                        <option value="pre">{{ __('Monospace') }}</option>
                                    </select>
                                </div>
                                <div class="lsn-toolbar-group">
                                    <button type="button" class="lsn-tool" data-lsn-cmd="bold" title="{{ __('Bold') }}" aria-label="{{ __('Bold') }}"><i class="bi bi-type-bold"></i></button>
                                    <button type="button" class="lsn-tool" data-lsn-cmd="italic" title="{{ __('Italic') }}" aria-label="{{ __('Italic') }}"><i class="bi bi-type-italic"></i></button>
                                    <button type="button" class="lsn-tool" data-lsn-cmd="underline" title="{{ __('Underline') }}" aria-label="{{ __('Underline') }}"><i class="bi bi-type-underline"></i></button>
                                </div>
                                <div class="lsn-toolbar-group">
                                    <button type="button" class="lsn-tool" data-lsn-cmd="insertUnorderedList" title="{{ __('Bullet list') }}" aria-label="{{ __('Bullet list') }}"><i class="bi bi-list-ul"></i></button>
                                    <button type="button" class="lsn-tool" data-lsn-cmd="insertOrderedList" title="{{ __('Numbered list') }}" aria-label="{{ __('Numbered list') }}"><i class="bi bi-list-ol"></i></button>
                                    <button type="button" class="lsn-tool" data-lsn-cmd="insertTable" title="{{ __('Table') }}" aria-label="{{ __('Insert table') }}"><i class="bi bi-table"></i></button>
                                </div>
                                <div class="lsn-toolbar-group">
                                    <button type="button" class="lsn-tool" data-lsn-cmd="justifyLeft" title="{{ __('Align left') }}" aria-label="{{ __('Align left') }}"><i class="bi bi-text-left"></i></button>
                                    <button type="button" class="lsn-tool" data-lsn-cmd="justifyCenter" title="{{ __('Align centre') }}" aria-label="{{ __('Align centre') }}"><i class="bi bi-text-center"></i></button>
                                    <button type="button" class="lsn-tool" data-lsn-cmd="justifyRight" title="{{ __('Align right') }}" aria-label="{{ __('Align right') }}"><i class="bi bi-text-right"></i></button>
                                </div>
                                <div class="lsn-toolbar-group">
                                    <button type="button" class="lsn-tool" data-lsn-link title="{{ __('Insert link') }}" aria-label="{{ __('Insert link') }}"><i class="bi bi-link-45deg"></i></button>
                                    <button type="button" class="lsn-tool" data-lsn-image title="{{ __('Insert picture from your computer') }}" aria-label="{{ __('Insert picture from your computer') }}"><i class="bi bi-image"></i></button>
                                </div>
                                <div class="lsn-toolbar-group">
                                    <button type="button" class="lsn-tool" data-lsn-cmd="removeFormat" title="{{ __('Clear formatting') }}" aria-label="{{ __('Clear formatting') }}"><i class="bi bi-eraser"></i></button>
                                    <button type="button" class="lsn-tool" data-lsn-preview-toggle title="{{ __('Show preview') }}" aria-pressed="false">
                                        <i class="bi bi-eye"></i>{{ __('Preview') }}
                                    </button>
                                </div>
                            </div>

                            <div class="lsn-editor" contenteditable="true" data-lsn-editor
                                 data-placeholder="{{ __('Explain the lesson here — headings, lists, a table, a picture. Up to :max MB per picture, and the markup is cleaned on save, so nothing but your formatting survives.', ['max' => $imageMb]) }}"
                                 role="textbox" aria-multiline="true" aria-label="{{ __('Lesson content') }}">{!! $body !!}</div>

                            <div class="lsn-editor-foot">
                                <span data-lsn-wordcount>{{ __('0 words') }}</span>
                                <span class="text-muted-2">{{ __('What you see here is what the class reads.') }}</span>
                            </div>

                            <div class="lsn-preview" data-lsn-preview hidden>
                                <div class="lsn-preview-head"><i class="bi bi-eye"></i>{{ __('Preview — this is the lesson body') }}</div>
                                <div class="lesson-body" data-lsn-preview-body></div>
                            </div>

                            <input type="file" accept=".jpg,.jpeg,.png,.webp" data-lsn-file-input hidden>
                        </div>
                        @error('content')<div class="text-danger mt-2" style="font-size:.8rem;">{{ $message }}</div>@enderror
                    </section>

                    <section class="ui-card p-lg mt-3">
                        <h2 class="card-title">{{ __('Files, video and references') }}</h2>

                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <label class="form-label" for="lsn-materials">{{ __('PDF / document & picture attachments') }}</label>
                                <input id="lsn-materials" type="file" name="materials[]" multiple
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.webp"
                                       class="form-control @error('materials.*') is-invalid @enderror">
                                @error('materials.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <div class="form-text">
                                    {{ __('Up to 8 files, :max MB each. The type is read from the bytes, not the name.', ['max' => $maxMb]) }}
                                </div>

                                @if ($materials->isNotEmpty())
                                    <ul class="lsn-material-list mt-3">
                                        @foreach ($materials as $material)
                                            <li class="lsn-material-row">
                                                <i class="bi {{ $material->isImage() ? 'bi-file-earmark-image' : 'bi-file-earmark-text' }}"></i>
                                                <span class="flex-grow-1 text-truncate">{{ $material->title }}</span>
                                                <span class="text-muted-2" style="font-size:.74rem;">{{ $material->sizeLabel() }}</span>
                                                <label class="form-check form-check-inline m-0">
                                                    <input class="form-check-input" type="checkbox" name="remove_materials[]" value="{{ $material->id }}">
                                                    <span class="form-check-label">{{ __('Remove') }}</span>
                                                </label>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="lsn-video">{{ __('Video URL (YouTube or Vimeo)') }}</label>
                                <input id="lsn-video" type="url" name="video_url" maxlength="300"
                                       class="form-control @error('video_url') is-invalid @enderror"
                                       value="{{ old('video_url', $editing ? $lesson->video_url : '') }}"
                                       placeholder="https://www.youtube.com/watch?v=…">
                                @error('video_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">{{ __('Paste the address as it is in the browser bar. A YouTube link is embedded safely; nothing else is loaded from outside.') }}</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="lsn-links">{{ __('Reference links') }}</label>
                                <textarea id="lsn-links" name="reference_links" rows="3" maxlength="2000"
                                          class="form-control @error('reference_links') is-invalid @enderror"
                                          placeholder="{{ __('One https address per line, up to 10.') }}">{{ old('reference_links', $editing ? $lesson->reference_links : '') }}</textarea>
                                @error('reference_links')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-lg-4">
                    <section class="ui-card p-lg">
                        <h2 class="card-title">{{ __('Which class') }}</h2>
                        <p class="text-muted-2" style="font-size:.8rem;">
                            {{ $mayNameTeacher
                                ? __('The office may file a lesson under any class, section and subject.')
                                : __('Only the periods on your own timetable are listed here.') }}
                        </p>

                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <label class="form-label" for="lsn-session">{{ __('Academic session') }}<span class="required">*</span></label>
                                <select id="lsn-session" name="academic_session_id" class="form-select @error('academic_session_id') is-invalid @enderror" required>
                                    @foreach ($sessions as $session)
                                        <option value="{{ $session->id }}"
                                                @selected($oldSession === (int) $session->id)>{{ $session->name }}@if ($session->is_current) · {{ __('Current') }}@endif</option>
                                    @endforeach
                                </select>
                                @error('academic_session_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="lsn-class">{{ __('Class') }}<span class="required">*</span></label>
                                <select id="lsn-class" name="class_id" class="form-select @error('class_id') is-invalid @enderror" required>
                                    @foreach ($classChoices as $choice)
                                        <option value="{{ $choice['class'] }}" @selected($chosenClass === (int) $choice['class'])>
                                            {{ $choice['class_label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="lsn-section">{{ __('Section') }}</label>
                                <select id="lsn-section" name="section_id" class="form-select @error('section_id') is-invalid @enderror">
                                    <option value="">{{ __('Whole class (all sections)') }}</option>
                                    @foreach ($sectionChoices as $choice)
                                        <option value="{{ $choice['section'] }}"
                                                @selected($chosenSection === (int) $choice['section'])>
                                            {{ $choice['section_label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('section_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="lsn-subject">{{ __('Subject') }}<span class="required">*</span></label>
                                <select id="lsn-subject" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
                                    @foreach ($subjectChoices as $choice)
                                        <option value="{{ $choice['subject'] }}" @selected($oldSubject === (int) $choice['subject'])>
                                            {{ $choice['subject_label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            @if ($mayNameTeacher)
                                <div class="col-12">
                                    <label class="form-label" for="lsn-teacher">{{ __('Teacher') }}</label>
                                    <select id="lsn-teacher" name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror">
                                        <option value="">{{ __('Filed under you') }}</option>
                                        @foreach ($teachers as $teacher)
                                            <option value="{{ $teacher->id }}"
                                                    @selected((int) old('teacher_id', (string) ($editing ? $lesson->teacher_id : 0)) === $teacher->id)>
                                                {{ $teacher->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('teacher_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            @endif
                        </div>
                    </section>

                    <section class="ui-card p-lg mt-3">
                        <h2 class="card-title">{{ __('Publication') }}</h2>
                        <div class="d-flex flex-column gap-2 mt-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="lsn-status-draft" value="draft" @checked($oldStatus === 'draft')>
                                <label class="form-check-label" for="lsn-status-draft">
                                    {{ __('Save as draft') }}
                                    <span class="d-block text-muted-2" style="font-size:.75rem;">{{ __('Only you and the office can open it.') }}</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="lsn-status-published" value="published" @checked($oldStatus === 'published')>
                                <label class="form-check-label" for="lsn-status-published">
                                    {{ __('Publish now') }}
                                    <span class="d-block text-muted-2" style="font-size:.75rem;">{{ __('The class sees it in their portal straight away. No approval needed.') }}</span>
                                </label>
                            </div>
                        </div>
                        @error('status')<div class="text-danger mt-2" style="font-size:.8rem;">{{ $message }}</div>@enderror

                        <div class="d-flex flex-column gap-2 mt-4">
                            <button type="submit" class="btn btn-primary" data-lsn-submit="published">
                                <i class="bi bi-broadcast"></i>{{ __('Publish lesson') }}
                            </button>
                            <button type="submit" class="btn btn-light-soft" data-lsn-submit="draft">
                                <i class="bi bi-file-earmark-text"></i>{{ __('Save as draft') }}
                            </button>
                        </div>
                        <div class="form-text mt-2">{{ __('The button you press sets the status; the choice above only decides what the form opens with.') }}</div>
                    </section>
                </div>
            </div>
        </form>
    @endif
