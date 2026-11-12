@php
    $workType = old('work_type', 'both');
    $selectedPeriod = old('period', '');
@endphp

<div class="page-header{{ ($panel ?? false) ? ' panel-header' : '' }}">
    <div>
        @if (! ($panel ?? false))
            <span class="page-eyebrow"><i class="bi bi-journal-plus"></i>{{ __('Teaching & Homework') }}</span>
            <h1 class="page-title">{{ __('Class Work & Homework') }}</h1>
        @endif
        <p class="page-subtitle">{{ __('Record what the class covered today and what the students take home, in one save. Only the classes on your own timetable appear here.') }}</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('teacher.homework.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('All work') }}</a>
    </div>
</div>

@if ($periods->isEmpty())
    <section class="ui-card">
        <div class="empty-state">
            <div class="empty-icon"><i class="bi bi-journal-x"></i></div>
            <h3>{{ __('No class periods on your timetable yet') }}</h3>
            <p>{{ __('Class work and homework are filed against a period, so the office has to place you on a class routine first.') }}</p>
            <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Ask the school office to add your subjects to the routine for the current session.') }}</p>
        </div>
    </section>
@else
    <form method="POST" enctype="multipart/form-data" action="{{ route('teacher.homework.store') }}">
        @csrf
        <input type="hidden" name="academic_session_id" value="{{ old('academic_session_id', $selectedSession) }}">

        <section class="ui-card p-lg">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="work-period">{{ __('Class / Section / Subject') }}<span class="required">*</span></label>
                    <select id="work-period" name="period" class="form-select @error('period') is-invalid @enderror" required>
                        <option value="">{{ __('Choose a period') }}</option>
                        @foreach ($periods as $period)
                            <option value="{{ $period['value'] }}" @selected($selectedPeriod === $period['value'])>
                                {{ $period['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('period')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="work-type">{{ __('Type') }}<span class="required">*</span></label>
                    <select id="work-type" name="work_type" class="form-select @error('work_type') is-invalid @enderror" required>
                        <option value="both" @selected($workType === 'both')>{{ __('Class Work + Homework') }}</option>
                        <option value="classwork" @selected($workType === 'classwork')>{{ __('Class Work') }}</option>
                        <option value="homework" @selected($workType === 'homework')>{{ __('Homework') }}</option>
                    </select>
                    @error('work_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('Only the boxes your Type covers are saved.') }}</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="work-date">{{ __('Date') }}<span class="required">*</span></label>
                    <input id="work-date" type="date" name="assigned_date" class="form-control @error('assigned_date') is-invalid @enderror"
                           value="{{ old('assigned_date', now()->format('Y-m-d')) }}" required>
                    @error('assigned_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="work-due">{{ __('Due Date') }}</label>
                    <input id="work-due" type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror"
                           value="{{ old('due_date') }}">
                    @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('Leave empty for class work. Homework closes on this date.') }}</div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="work-title">{{ __('Topic / Title') }}<span class="required">*</span></label>
                    <input id="work-title" type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title') }}" maxlength="160" required
                           placeholder="{{ __('যেমন: Chapter 2 - Grammar / সূরা কাতিহা অনুশীলন') }}">
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="work-classwork">{{ __('Class Work') }} / আজ ক্লাসে যা পড়ানো হয়েছে</label>
                    <textarea id="work-classwork" name="classwork" rows="6" class="form-control @error('classwork') is-invalid @enderror"
                              maxlength="8000"
                              placeholder="{{ __('ক্লাসে কী পড়ানো হলো লিখুন...') }}">{{ old('classwork') }}</textarea>
                    @error('classwork')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="work-homework">{{ __('Homework') }} / বাড়ির কাজ</label>
                    <textarea id="work-homework" name="homework" rows="6" class="form-control @error('homework') is-invalid @enderror"
                              maxlength="8000"
                              placeholder="{{ __('বাড়ির কাজ / প্রজেক্ট / নির্দেশনা লিখুন...') }}">{{ old('homework') }}</textarea>
                    @error('homework')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="work-attachment">{{ __('Attachment (optional)') }}</label>
                    <input id="work-attachment" type="file" name="attachment" class="form-control @error('attachment') is-invalid @enderror"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.webp">
                    @error('attachment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('PDF, Word, Excel, text or an image up to 4 MB.') }}</div>
                </div>

                <div class="col-md-6">
                    <div class="form-text mb-2">{{ __('Publishing sends the work to the class and notifies every student in it. A draft stays visible to you only.') }}</div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary" name="publish" value="1">
                            <i class="bi bi-save"></i>{{ __('Save Class Work / Homework') }}
                        </button>
                        <button type="submit" class="btn btn-light-soft" name="publish" value="0">
                            <i class="bi bi-file-earmark"></i>{{ __('Save as draft') }}
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </form>
@endif
