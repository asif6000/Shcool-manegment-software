    <div class="page-header{{ ($panel ?? false) ? ' panel-header' : '' }}">
        @if (! ($panel ?? false))
            <div>
                <span class="page-eyebrow"><i class="bi bi-pencil-square"></i>{{ __('Academic') }}</span>
                <h1 class="page-title">{{ __('Homework & Classwork') }}</h1>
                <p class="page-subtitle">{{ __('One place for what the class covered and what students take home. Open an entry to review what was sent in.') }}</p>
            </div>
        @endif
        <div class="page-actions">
            @if (! ($panel ?? false))
                <a href="{{ route('timetable.index') }}" class="btn btn-light-soft">
                    <i class="bi bi-calendar-week"></i>{{ __('Class routine') }}
                </a>
            @endif
            @can('homework.manage')
                <a href="{{ route('teacher.homework.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i>{{ __('Add class work / homework') }}
                </a>
            @endcan
        </div>
    </div>

    <div class="mini-stats mb-3">
        <div class="mini-stat">
            <span class="value">{{ $homeworkRecords->total() }}</span>
            <span class="label">{{ __('Entries for this filter') }}</span>
        </div>
        <div class="mini-stat">
            <span class="value">{{ $dueCount }}</span>
            <span class="label">{{ __('Past their deadline') }}</span>
        </div>
        <div class="mini-stat text-start">
            <span class="label">{{ __('Publishing sends the work to the class and notifies its students. They can answer in the portal until the deadline, and a reviewed answer is locked.') }}</span>
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
                    <a href="{{ route('teacher.homework.index', array_filter(($filters ?? []) + ['category' => $tabValue])) }}"
                       class="btn btn-sm {{ ($filters['category'] ?? '') === $tabValue ? 'btn-primary' : 'btn-light-soft' }}"
                       role="tab" aria-selected="{{ ($filters['category'] ?? '') === $tabValue ? 'true' : 'false' }}">
                        {{ $tabLabel }}
                        <span class="badge-soft ms-1">{{ $categoryCounts[($tabValue === '' ? 'all' : $tabValue)] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('teacher.homework.index') }}" class="filter-form" role="search">
                <input type="hidden" name="category" value="{{ $filters['category'] ?? '' }}">
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
                            {{ $section->name }}
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
                <label class="visually-hidden" for="filter-due">{{ __('Deadline') }}</label>
                <select id="filter-due" name="due" class="form-select" data-auto-submit>
                    <option value="">{{ __('Any deadline') }}</option>
                    <option value="open" @selected(($filters['due'] ?? '') === 'open')>{{ __('Still open') }}</option>
                    <option value="past" @selected(($filters['due'] ?? '') === 'past')>{{ __('Closed') }}</option>
                </select>
                <label class="visually-hidden" for="filter-status">{{ __('Status') }}</label>
                <select id="filter-status" name="status" class="form-select" data-auto-submit>
                    <option value="">{{ __('Any status') }}</option>
                    <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>{{ __('Drafts') }}</option>
                    <option value="published" @selected(($filters['status'] ?? '') === 'published')>{{ __('Published') }}</option>
                    <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>{{ __('Cancelled') }}</option>
                </select>
                @if (array_filter($filters))
                    <a href="{{ route('teacher.homework.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">
                {{ trans_choice(':count assignment|:count assignments', $homeworkRecords->total(), ['count' => $homeworkRecords->total()]) }}
            </span>
            <span class="text-muted-2" style="font-size:.72rem;" data-work-live-state></span>
        </div>

        <div data-work-live-list>
        @if ($homeworkRecords->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-clipboard-x"></i></div>
                <h3>{{ __('Nothing here yet') }}</h3>
                <p>{{ __('Saved work appears here by type. Publishing a piece puts it in the portal of every student in that class.') }}</p>
                @can('homework.manage')
                    <a href="{{ route('teacher.homework.create') }}" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-lg"></i>{{ __('Add class work / homework') }}
                    </a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Work') }}</th>
                            <th>{{ __('Class') }}</th>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Deadline') }}</th>
                            <th>{{ __('Submissions') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($homeworkRecords as $entry)
                            <tr>
                                <td>
                                    <a href="{{ route('teacher.homework.show', $entry) }}" class="fw-semibold">{{ $entry->title }}</a>
                                    <span class="badge-soft {{ $entry->isClasswork() ? 'is-brand' : 'is-info' }} ms-1">{{ $entry->typeLabel() }}</span>
                                    @if ($entry->status === \App\Models\Homework::DRAFT)
                                        <span class="badge-soft is-warning ms-1">{{ __('Draft') }}</span>
                                    @elseif ($entry->status === \App\Models\Homework::CANCELLED)
                                        <span class="badge-soft is-danger ms-1">{{ __('Cancelled') }}</span>
                                    @endif
                                    @if ($entry->attachment_path)
                                        <i class="bi bi-paperclip text-muted-2" title="{{ __('Attachment') }}"></i>
                                    @endif
                                    <div class="text-muted-2" style="font-size:.74rem;">
                                        {{ $entry->academicSession?->name ?? '—' }}
                                        @if ($entry->teacher)
                                            · {{ $entry->teacher->full_name }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    {{ $entry->schoolClass?->name ?? '—' }}
                                    <div class="text-muted-2" style="font-size:.74rem;">
                                        {{ $entry->section?->name ?? __('Whole class') }}
                                    </div>
                                </td>
                                <td>{{ $entry->subject?->name ?? '—' }}</td>
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
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                        <a href="{{ route('teacher.homework.show', $entry) }}" class="btn btn-sm btn-light-soft">
                                            <i class="bi bi-inbox"></i>{{ __('Review') }}
                                        </a>
                                        @can('update', $entry)
                                            @if ($entry->status === \App\Models\Homework::DRAFT)
                                                <form method="POST" action="{{ route('teacher.homework.publish', $entry) }}"
                                                      data-confirm="{{ __('Publish :title? The class is notified now.', ['title' => $entry->title]) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-primary" title="{{ __('Publish') }}">
                                                        <i class="bi bi-megaphone"></i>{{ __('Publish') }}
                                                    </button>
                                                </form>
                                            @elseif ($entry->status === \App\Models\Homework::PUBLISHED)
                                                <form method="POST" action="{{ route('teacher.homework.unpublish', $entry) }}"
                                                      data-confirm="{{ __('Move :title back to draft? Students stop seeing it until you publish again.', ['title' => $entry->title]) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-light-soft"
                                                            title="{{ __('Unpublish') }}" aria-label="{{ __('Unpublish homework') }}">
                                                        <i class="bi bi-arrow-counterclockwise"></i>{{ __('Unpublish') }}
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('teacher.homework.cancel', $entry) }}"
                                                      data-confirm="{{ __('Cancel :title? Students stop seeing it; submissions already made are kept.', ['title' => $entry->title]) }}">
                                                    @csrf
                                                    <button type="submit" class="icon-action is-warning"
                                                            title="{{ __('Cancel') }}" aria-label="{{ __('Cancel homework') }}">
                                                        <i class="bi bi-slash-circle"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <a href="{{ route('teacher.homework.edit', $entry) }}" class="icon-action"
                                               title="{{ __('Edit') }}" aria-label="{{ __('Edit homework') }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="{{ route('teacher.homework.destroy', $entry) }}"
                                                  data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                                  data-confirm-delete="1"
                                                  data-confirm-label="{{ __('Permanently Delete') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="icon-action is-danger"
                                                        title="{{ __('Delete') }}" aria-label="{{ __('Delete homework') }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">{{ $homeworkRecords->links() }}</div>
        @endif
        </div>
    </section>