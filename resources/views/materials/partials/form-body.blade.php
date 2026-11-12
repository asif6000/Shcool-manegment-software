    @php
        $oldClass = (int) old('class_id', (string) ($selectedClass ?? 0));
        $maxMb = round($storageKb / 1024, 1);
        $editing = $material->exists;
        $oldKind = old('kind', $editing ? $material->kind : ($defaultKind ?? \App\Models\StudyMaterial::MATERIAL));
    @endphp

    <div class="page-header{{ ($panel ?? false) ? ' panel-header' : '' }}">
        @if (! ($panel ?? false))
            <div>
                <span class="page-eyebrow"><i class="bi bi-journal-bookmark"></i>{{ __('Study materials') }}</span>
                <h1 class="page-title">{{ $editing ? __('Edit shared material') : __('Share study material') }}</h1>
                <p class="page-subtitle">{{ __('One file, filed against a class and subject. Only the staff who reach that class can open it.') }}</p>
            </div>
        @endif
        <div class="page-actions">
            <a href="{{ route('materials.index') }}" class="btn btn-light-soft">
                <i class="bi bi-arrow-left"></i>{{ __('All materials') }}
            </a>
        </div>
    </div>

    @if ($sessions->isEmpty() || $classes->isEmpty() || $subjects->isEmpty())
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-journal-x"></i></div>
                <h3>{{ __('Set up the academic structure first') }}</h3>
                <p>{{ __('A material needs an academic session, a class and at least one subject.') }}</p>
                @can('academic.manage')
                    <a href="{{ route('academic.index') }}" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-lg"></i>{{ __('Open academic setup') }}
                    </a>
                @else
                    <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Ask the school office to add the class and subject to your timetable.') }}</p>
                @endcan
            </div>
        </section>
    @else
        <form method="POST" enctype="multipart/form-data"
              action="{{ $editing ? route('materials.update', $material) : route('materials.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif
            <div class="row g-3">
                <div class="col-lg-8">
                    <section class="ui-card p-lg">
                        <h2 class="card-title">{{ __('The file') }}</h2>
                        <p class="text-muted-2" style="font-size:.8rem;">
                            {{ __('Name it so it can be found again: the chapter, the paper and the year.') }}
                        </p>

                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <label class="form-label" for="mt-title">{{ __('Title') }}<span class="required">*</span></label>
                                <input id="mt-title" type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                       value="{{ old('title', $editing ? $material->title : '') }}" maxlength="160" required
                                       placeholder="{{ __('Chapter 4 worksheet — Algebra') }}">
                                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                @if ($editing && $oldKind === \App\Models\StudyMaterial::CLASSWORK)
                                    <input type="hidden" name="kind" value="{{ \App\Models\StudyMaterial::CLASSWORK }}">
                                    <span class="form-label">{{ __('Type') }}</span>
                                    <div><span class="badge-soft is-info">{{ __('Classwork') }}</span></div>
                                    <div class="form-text">{{ __('An older class work entry. It stays editable here; new class work is recorded on the Homework & Classwork screen.') }}</div>
                                @else
                                    <input type="hidden" name="kind" value="{{ \App\Models\StudyMaterial::MATERIAL }}">
                                    <span class="form-label">{{ __('Type') }}</span>
                                    <div class="form-control-plaintext">{{ __('Study material') }}</div>
                                    <div class="form-text">{{ __('Class work and homework are recorded on the Homework & Classwork screen.') }}</div>
                                @endif
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="mt-work-date">{{ __('Class date') }}</label>
                                <input id="mt-work-date" type="date" name="work_date" class="form-control @error('work_date') is-invalid @enderror"
                                       value="{{ old('work_date', $editing && $material->work_date ? $material->work_date->toDateString() : '') }}">
                                @error('work_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="mt-file">{{ __('File') }}@unless ($editing || $oldKind === 'classwork')<span class="required">*</span>@endunless</label>
                                <input id="mt-file" type="file" name="file" class="form-control @error('file') is-invalid @enderror"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.webp"
                                       @required(! $editing && $oldKind !== 'classwork')>
                                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">
                                    @if ($editing && $current_file)
                                        {{ __('Current file: :name. Choose a file only to replace it.', ['name' => $current_file]) }}
                                    @else
                                        {{ __('PDF, Word, Excel, text or an image up to :max MB. The file type is checked from the bytes, not the name.', ['max' => $maxMb]) }}
                                    @endif
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="mt-description">{{ __('Note') }}</label>
                                <textarea id="mt-description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror"
                                          maxlength="4000"
                                          placeholder="{{ __('Which pages to read, what it is for, when it will be used in class.') }}">{{ old('description', $editing ? $material->description : '') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" name="publish" value="1" class="btn btn-primary">
                                <i class="bi bi-broadcast"></i>{{ $editing ? __('Save and share') : __('Share with the class') }}
                            </button>
                            <button type="submit" name="publish" value="0" class="btn btn-light-soft">
                                <i class="bi bi-file-earmark-text"></i>{{ __('Save as draft') }}
                            </button>
                            <a href="{{ route('materials.index') }}" class="btn btn-link">{{ __('Cancel') }}</a>
                        </div>
                        <div class="form-text mt-2">
                            {{ __('A draft stays visible to staff only. Sharing shows it to the class and sends a notice.') }}
                        </div>
                    </section>
                </div>

                <div class="col-lg-4">
                    <section class="ui-card p-lg">
                        <h2 class="card-title">{{ __('Which class') }}</h2>

                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <label class="form-label" for="mt-session">{{ __('Academic session') }}<span class="required">*</span></label>
                                <select id="mt-session" name="academic_session_id" class="form-select @error('academic_session_id') is-invalid @enderror" required>
                                    @foreach ($sessions as $session)
                                        <option value="{{ $session->id }}"
                                                @selected((int) old('academic_session_id', (string) ($selectedSession ?? 0)) === $session->id)>                                            {{ $session->name }}@if ($session->is_current) · {{ __('Current') }}@endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('academic_session_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="mt-class">{{ __('Class') }}<span class="required">*</span></label>
                                <select id="mt-class" name="class_id" class="form-select @error('class_id') is-invalid @enderror" required>
                                    @foreach ($classes as $class)
                                        <option value="{{ $class->id }}" @selected($oldClass === $class->id)>
                                            {{ $class->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="mt-section">{{ __('Section') }}</label>
                                <select id="mt-section" name="section_id" class="form-select @error('section_id') is-invalid @enderror">
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
                                <label class="form-label" for="mt-subject">{{ __('Subject') }}<span class="required">*</span></label>
                                <select id="mt-subject" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
                                    @foreach ($subjects as $subject)
                                        <option value="{{ $subject->id }}" @selected((int) old('subject_id', (string) ($selectedSubject ?? 0)) === $subject->id)>
                                            {{ $subject->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="mt-lesson">{{ __('Lesson / chapter') }}</label>
                                <select id="mt-lesson" name="lesson_id" class="form-select @error('lesson_id') is-invalid @enderror">
                                    <option value="">{{ __('Not linked to a lesson') }}</option>
                                    @foreach ($lessons as $lesson)
                                        <option value="{{ $lesson->id }}" @selected((int) old('lesson_id', (string) ($material->lesson_id ?? 0)) === $lesson->id)>
                                            {{ $lesson->schoolClass?->name }} · {{ $lesson->section?->name ?? __('Whole class') }} · {{ $lesson->subject?->name }}
                                            — {{ $lesson->chapterLabel() ?? $lesson->title }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('lesson_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">{{ __('Optional. Only lessons of the class and subject chosen above can be linked.') }}</div>
                            </div>

                            @if ($mayNameTeacher ?? false)
                                <div class="col-12">
                                    <label class="form-label" for="mt-teacher">{{ __('Teacher') }}</label>
                                    <select id="mt-teacher" name="teacher_id" class="form-select">
                                        <option value="">{{ __('Filed under you') }}</option>
                                        @foreach ($teachers as $teacher)
                                            <option value="{{ $teacher->id }}" @selected((int) old('teacher_id', (string) ($material->teacher_id ?? 0)) === $teacher->id)>
                                                {{ $teacher->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </div>
        </form>
    @endif