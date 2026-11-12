    @php
        $editing = $homework->exists;
        $published = $editing && $homework->status === \App\Models\Homework::PUBLISHED;
        $oldClass = (int) old('class_id', (string) ($selectedClass ?? 0));
    @endphp

    <div class="page-header{{ ($panel ?? false) ? ' panel-header' : '' }}">
        <div>
            @if (! ($panel ?? false))
                <span class="page-eyebrow"><i class="bi bi-pencil-square"></i>{{ __('Homework') }}</span>
                <h1 class="page-title">{{ $editing ? __('Edit homework') : __('Assign homework') }}</h1>
            @endif
            <p class="page-subtitle">{{ $published
                ? __('This homework is published — students can already see it. Saving the form updates what they see.')
                : __('A draft stays visible to staff only. Publishing it shows it to the class and notifies their inbox.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('teacher.homework.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('All homework') }}</a>
        </div>
    </div>

    @if ($sessions->isEmpty() || $classes->isEmpty())
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-journal-x"></i></div>
                <h3>{{ __('Set up the academic structure first') }}</h3>
                <p>{{ __('Homework needs an academic session, a class and at least one subject.') }}</p>
                @can('academic.manage')
                    <a href="{{ route('academic.index') }}" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i>{{ __('Open academic setup') }}</a>
                @else
                    <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Ask the school office to add the class and subject to your timetable.') }}</p>
                @endcan
            </div>
        </section>
    @else
        <form method="POST" enctype="multipart/form-data"
              action="{{ $editing ? route('teacher.homework.update', $homework) : route('teacher.homework.store') }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="row g-3">
                <div class="col-lg-8">
                    <section class="ui-card p-lg">
                        <h2 class="card-title">{{ __('The task') }}</h2>
                        <p class="text-muted-2" style="font-size:.8rem;">{{ __('Write what the students must do, in enough detail that they do not need to ask again.') }}</p>

                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <label class="form-label" for="hw-title">{{ __('Title') }}<span class="required">*</span></label>
                                <input id="hw-title" type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                       value="{{ old('title', $homework->title) }}" maxlength="160" required
                                       placeholder="{{ __('Worksheet: quadratic equations') }}">
                                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="hw-description">{{ __('Homework description') }}<span class="required">*</span></label>
                                <textarea id="hw-description" name="description" rows="6" class="form-control @error('description') is-invalid @enderror"
                                          maxlength="8000" required
                                          placeholder="{{ __('Complete exercise 4.2 from question 1 to 12. Show every step.') }}">{{ old('description', $homework->description) }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="hw-instructions">{{ __('Instructions') }}</label>
                                <textarea id="hw-instructions" name="instructions" rows="4" class="form-control @error('instructions') is-invalid @enderror"
                                          maxlength="4000"
                                          placeholder="{{ __('What to bring, how to hand it in, what is not allowed.') }}">{{ old('instructions', $homework->instructions) }}</textarea>
                                @error('instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">{{ __('Optional. Students read this under the description.') }}</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="hw-assigned">{{ __('Assigned on') }}<span class="required">*</span></label>
                                <input id="hw-assigned" type="date" name="assigned_date" class="form-control @error('assigned_date') is-invalid @enderror"
                                       value="{{ old('assigned_date', $homework->assigned_date?->format('Y-m-d')) }}" required>
                                @error('assigned_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="hw-due">{{ __('Deadline') }}<span class="required">*</span></label>
                                <input id="hw-due" type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror"
                                       value="{{ old('due_date', $homework->due_date?->format('Y-m-d')) }}" required>
                                @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="hw-attachment">{{ __('Attachment') }}</label>
                                <input id="hw-attachment" type="file" name="attachment" class="form-control @error('attachment') is-invalid @enderror"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.webp">
                                @error('attachment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">
                                    {{ __('PDF, Word, Excel, text or an image up to 4 MB. The file type is checked from the bytes, not the name.') }}
                                </div>
                                @if ($editing)
                                    <div class="form-text text-muted-2">{{ __('Choose a file to replace the current attachment. Leave empty to keep it.') }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            @if (! $editing)
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="publish" value="1" id="hw-publish" @checked(old('publish', true))>
                                    <label class="form-check-label" for="hw-publish">{{ __('Publish now') }}</label>
                                    <div class="form-text">{{ __('Leave unchecked to keep it as a draft you can publish later.') }}</div>
                                </div>
                            @endif
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2"></i>{{ $editing ? __('Save changes') : (old('publish', true) ? __('Assign homework') : __('Save draft')) }}
                            </button>
                            <a href="{{ route('teacher.homework.index') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                        </div>
                    </section>
                </div>

                <div class="col-lg-4">
                    <section class="ui-card p-lg mb-3">
                        <h2 class="card-title">{{ __('Who gets it') }}</h2>

                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <label class="form-label" for="hw-session">{{ __('Academic session') }}<span class="required">*</span></label>
                                <select id="hw-session" name="academic_session_id" class="form-select @error('academic_session_id') is-invalid @enderror" required>
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
                                <label class="form-label" for="hw-class">{{ __('Class') }}<span class="required">*</span></label>
                                <select id="hw-class" name="class_id" class="form-select @error('class_id') is-invalid @enderror" required>
                                    @foreach ($classes as $class)
                                        <option value="{{ $class->id }}" @selected($oldClass === $class->id)>
                                            {{ $class->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="hw-section">{{ __('Section') }}</label>
                                <select id="hw-section" name="section_id" class="form-select @error('section_id') is-invalid @enderror">
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
                                <label class="form-label" for="hw-subject">{{ __('Subject') }}<span class="required">*</span></label>
                                <select id="hw-subject" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
                                    @foreach ($subjects as $subject)
                                        <option value="{{ $subject->id }}" @selected((int) old('subject_id', (string) $homework->subject_id) === $subject->id)>
                                            {{ $subject->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            @if ($mayNameTeacher ?? false)
                                <div class="col-12">
                                    <label class="form-label" for="hw-teacher">{{ __('Class teacher') }}</label>
                                    <select id="hw-teacher" name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror">
                                        <option value="">{{ __('Not assigned') }}</option>
                                        @foreach ($teachers as $teacher)
                                            <option value="{{ $teacher->id }}" @selected((int) old('teacher_id', (string) $homework->teacher_id) === $teacher->id)>
                                                {{ $teacher->first_name }}{{ $teacher->last_name ? ' '.$teacher->last_name : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('teacher_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            @else
                                <div class="col-12">
                                    <span class="form-label">{{ __('Teacher') }}</span>
                                    <div class="form-control-plaintext" style="font-size:.86rem;">{{ $homework->teacher?->full_name ?? auth()->user()->name }}</div>
                                    <div class="form-text">{{ __('Homework is filed under your own name.') }}</div>
                                </div>
                            @endif
                        </div>
                    </section>

                    @if ($editing && $attachments !== [])
                        <section class="ui-card p-lg">
                            <h2 class="card-title">{{ __('Current attachment') }}</h2>
                            @foreach ($attachments as $file)
                                <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
                                    <div style="min-width:0;">
                                        <div class="text-truncate" style="font-size:.82rem;">{{ $file['name'] }}</div>
                                        @if ($file['size'])
                                            <div class="text-muted-2" style="font-size:.72rem;">{{ number_format($file['size'] / 1024, 0) }} KB</div>
                                        @endif
                                    </div>
                                    <a href="{{ $file['route'] }}" class="btn btn-sm btn-light-soft">
                                        <i class="bi bi-download"></i>{{ __('Open') }}
                                    </a>
                                </div>
                            @endforeach
                        </section>
                    @endif
                </div>
            </div>
        </form>
    @endif