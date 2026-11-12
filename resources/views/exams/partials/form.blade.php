@php
    /** @var \App\Models\Exam|null $exam */
    $exam = $exam ?? null;
    $currentSession = $currentSession ?? null;
    $editing = $exam !== null;
@endphp

<form method="POST" action="{{ $editing ? route('exams.update', $exam) : route('exams.store') }}">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('Exam details') }}</h2>
                <p class="text-muted-2" style="font-size:.8rem;">{{ __('The exam must sit inside its academic session. Papers and marks are added after it is created.') }}</p>

                <div class="row g-3 mt-1">
                    <div class="col-md-7">
                        <label class="form-label" for="exam-name">{{ __('Exam name') }}</label>
                        <input id="exam-name" type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $exam?->name) }}" maxlength="120" required placeholder="{{ __('First Term Examination') }}">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="exam-type">{{ __('Exam type') }}</label>
                        <select id="exam-type" name="exam_type" class="form-select @error('exam_type') is-invalid @enderror" required>
                            @foreach (\App\Models\Exam::TYPES as $type)
                                <option value="{{ $type }}" @selected(old('exam_type', $exam?->exam_type ?? 'term') === $type)>
                                    {{ __(ucfirst(str_replace('_', ' ', $type))) }}
                                </option>
                            @endforeach
                        </select>
                        @error('exam_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="exam-session">{{ __('Academic session') }}</label>
                        <select id="exam-session" name="academic_session_id" class="form-select @error('academic_session_id') is-invalid @enderror" required>
                            @foreach ($sessions as $session)
                                <option value="{{ $session->id }}" @selected((int) old('academic_session_id', $exam?->academic_session_id ?? $currentSession?->id) === $session->id)>
                                    {{ $session->name }}@if ($session->is_current) · {{ __('Current') }}@endif
                                </option>
                            @endforeach
                        </select>
                        @error('academic_session_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="exam-start">{{ __('Start date') }}</label>
                        <input id="exam-start" type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror"
                            value="{{ old('start_date', $exam?->start_date?->format('Y-m-d')) }}" required>
                        @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="exam-end">{{ __('End date') }}</label>
                        <input id="exam-end" type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror"
                            value="{{ old('end_date', $exam?->end_date?->format('Y-m-d')) }}" required>
                        @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="exam-description">{{ __('Description') }} <span class="text-muted-2 fw-normal">({{ __('optional') }})</span></label>
                        <textarea id="exam-description" name="description" rows="3" maxlength="1000"
                            class="form-control @error('description') is-invalid @enderror"
                            placeholder="{{ __('Instructions or notes for staff.') }}">{{ old('description', $exam?->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle"></i>{{ $editing ? __('Save changes') : __('Create exam') }}
                    </button>
                    <a href="{{ $editing ? route('exams.show', $exam) : route('exams.index') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                </div>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('Next steps') }}</h2>
                <ol class="text-muted-2 mt-3 mb-0" style="font-size:.84rem;padding-left:1.1rem;">
                    <li>{{ __('Add subject papers for each class.') }}</li>
                    <li>{{ __('Enter marks for every enrolled student.') }}</li>
                    <li>{{ __('Review the tabulation sheet and publish results.') }}</li>
                </ol>
                <p class="text-muted-2 mt-3 mb-0" style="font-size:.78rem;">
                    {{ __('Publishing freezes marks and stores an immutable result snapshot for every student.') }}
                </p>
            </section>
        </div>
    </div>
</form>
