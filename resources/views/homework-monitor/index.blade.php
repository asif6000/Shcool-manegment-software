@extends('layouts.app')

@section('title', __('Homework & Classwork monitor'))

@section('content')
    @php
        $statuses = [
            \App\Models\Homework::DRAFT => ['is-warning', __('Draft')],
            \App\Models\Homework::PUBLISHED => ['is-success', __('Published')],
            \App\Models\Homework::CANCELLED => ['is-danger', __('Cancelled')],
        ];
        $classNames = $classes->pluck('name', 'id');
        // The tabs carry the current filters so switching type keeps the rest.
        $tabQuery = fn (string $value): array => array_filter($filters + ['type' => $value]);
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-eye"></i>{{ __('Academic') }}</span>
            <h1 class="page-title">{{ __('Homework & Classwork') }}</h1>
            <p class="page-subtitle">{{ __('Read every piece of work the teachers set for the school. The office watches the record; the teachers write it.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('timetable.index') }}" class="btn btn-light-soft">
                <i class="bi bi-calendar-week"></i>{{ __('Class routine') }}
            </a>
        </div>
    </div>

    <div class="mini-stats mb-3">
        <div class="mini-stat">
            <span class="value">{{ $counts['all'] }}</span>
            <span class="label">{{ __('Entries in the school') }}</span>
        </div>
        <div class="mini-stat">
            <span class="value">{{ $counts['homework'] }}</span>
            <span class="label">{{ __('Homework') }}</span>
        </div>
        <div class="mini-stat">
            <span class="value">{{ $counts['classwork'] }}</span>
            <span class="label">{{ __('Classwork') }}</span>
        </div>
        <div class="mini-stat">
            <span class="value">{{ $counts['drafts'] }}</span>
            <span class="label">{{ __('Still in draft') }}</span>
        </div>
        <div class="mini-stat text-start">
            <span class="label">{{ __('This screen is read-only by design. Creating, editing, publishing or deleting work belongs to the teacher who holds the class, and those routes refuse an office account.') }}</span>
        </div>
    </div>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <div class="d-flex flex-wrap gap-2 align-items-center" role="tablist" aria-label="{{ __('Work type') }}">
                @foreach ([
                    '' => __('All work'),
                    \App\Models\Homework::CATEGORY_HOMEWORK => __('Homework'),
                    \App\Models\Homework::CATEGORY_CLASSWORK => __('Classwork'),
                ] as $tabValue => $tabLabel)
                    <a href="{{ route('work-monitor.index', $tabQuery($tabValue)) }}"
                       class="btn btn-sm {{ ($filters['type'] ?? '') === $tabValue ? 'btn-primary' : 'btn-light-soft' }}"
                       role="tab" aria-selected="{{ ($filters['type'] ?? '') === $tabValue ? 'true' : 'false' }}">
                        {{ $tabLabel }}
                        <span class="badge-soft ms-1">{{ $counts[$tabValue === '' ? 'all' : $tabValue] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('work-monitor.index') }}" class="filter-form" role="search">
                <input type="hidden" name="type" value="{{ $filters['type'] ?? '' }}">
                <label class="visually-hidden" for="filter-q">{{ __('Search') }}</label>
                <input id="filter-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       class="form-control" placeholder="{{ __('Search title or text…') }}">
                <label class="visually-hidden" for="filter-class">{{ __('Class') }}</label>
                <select id="filter-class" name="class" class="form-select" data-auto-submit>
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected((int) ($filters['class'] ?? 0) === $class->id)>
                            {{ $class->name }}
                        </option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="filter-section">{{ __('Section') }}</label>
                <select id="filter-section" name="section" class="form-select" data-auto-submit>
                    <option value="">{{ __('All sections') }}</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}" @selected((int) ($filters['section'] ?? 0) === $section->id)>
                            {{ ($classNames[$section->class_id] ?? __('Class')).' · '.$section->name }}
                        </option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="filter-subject">{{ __('Subject') }}</label>
                <select id="filter-subject" name="subject" class="form-select" data-auto-submit>
                    <option value="">{{ __('All subjects') }}</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected((int) ($filters['subject'] ?? 0) === $subject->id)>
                            {{ $subject->name }}
                        </option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="filter-teacher">{{ __('Teacher') }}</label>
                <select id="filter-teacher" name="teacher" class="form-select" data-auto-submit>
                    <option value="">{{ __('All teachers') }}</option>
                    @foreach ($teachers as $member)
                        <option value="{{ $member->id }}" @selected((int) ($filters['teacher'] ?? 0) === $member->id)>
                            {{ $member->full_name }}
                        </option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="filter-status">{{ __('Status') }}</label>
                <select id="filter-status" name="status" class="form-select" data-auto-submit>
                    <option value="">{{ __('Any status') }}</option>
                    <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>{{ __('Drafts') }}</option>
                    <option value="published" @selected(($filters['status'] ?? '') === 'published')>{{ __('Published') }}</option>
                    <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>{{ __('Cancelled') }}</option>
                </select>
                <label class="visually-hidden" for="filter-due">{{ __('Deadline') }}</label>
                <select id="filter-due" name="due" class="form-select" data-auto-submit>
                    <option value="">{{ __('Any deadline') }}</option>
                    <option value="open" @selected(($filters['due'] ?? '') === 'open')>{{ __('Still open') }}</option>
                    <option value="past" @selected(($filters['due'] ?? '') === 'past')>{{ __('Closed') }}</option>
                </select>
                <button class="btn btn-sm btn-light-soft" type="submit"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('work-monitor.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">
                {{ trans_choice(':count entry|:count entries', $records->total(), ['count' => $records->total()]) }}
            </span>
            <span class="text-muted-2" style="font-size:.72rem;" data-work-live-state></span>
        </div>

        <div data-work-live-list>
        @if ($records->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-clipboard-x"></i></div>
                <h3>{{ __('No work matches this filter') }}</h3>
                <p>{{ __('Homework and class work appear here as soon as a teacher saves one. Drafts stay invisible to students until they are published.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Work') }}</th>
                            <th>{{ __('Class') }}</th>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Teacher') }}</th>
                            <th>{{ __('Deadline') }}</th>
                            <th>{{ __('Submissions') }}</th>
                            <th class="text-end">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $entry)
                            @php $badge = $statuses[$entry->status] ?? null; @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('work-monitor.show', $entry) }}" class="fw-semibold">{{ $entry->title }}</a>
                                    <span class="badge-soft {{ $entry->isClasswork() ? 'is-brand' : 'is-info' }} ms-1">{{ $entry->typeLabel() }}</span>
                                    @if ($entry->attachment_path)
                                        <i class="bi bi-paperclip text-muted-2" title="{{ __('Attachment') }}"></i>
                                    @endif
                                    <div class="text-muted-2" style="font-size:.74rem;">
                                        {{ $entry->academicSession?->name ?? '—' }}
                                        · {{ $entry->assigned_date?->format('d M Y') ?? '—' }}
                                    </div>
                                </td>
                                <td>
                                    {{ $entry->schoolClass?->name ?? '—' }}
                                    <div class="text-muted-2" style="font-size:.74rem;">
                                        {{ $entry->section?->name ?? __('Whole class') }}
                                    </div>
                                </td>
                                <td>{{ $entry->subject?->name ?? '—' }}</td>
                                <td style="font-size:.8rem;">{{ $entry->teacher?->full_name ?? __('—') }}</td>
                                <td style="font-size:.8rem;">
                                    {{ $entry->due_date?->format('d M Y') ?? '—' }}
                                    @if ($entry->isPastDue())
                                        <div class="text-danger" style="font-size:.72rem;">{{ __('Closed') }}</div>
                                    @elseif ($entry->closesToday())
                                        <div class="text-warning" style="font-size:.72rem;">{{ __('Due today') }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge-soft {{ $entry->submissions_count > 0 ? 'is-success' : 'is-warning' }}">
                                        {{ $entry->submissions_count }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @if ($badge)
                                        <span class="badge-soft {{ $badge[0] }}">{{ $badge[1] }}</span>
                                    @endif
                                    <a href="{{ route('work-monitor.show', $entry) }}" class="btn btn-sm btn-light-soft ms-1">
                                        <i class="bi bi-eye"></i>{{ __('Open') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">{{ $records->links() }}</div>
        @endif
        </div>
    </section>

    @include('homework/partials/live-script')
@endsection
