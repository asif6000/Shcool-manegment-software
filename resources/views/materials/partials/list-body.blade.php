    <div class="page-header{{ ($panel ?? false) ? ' panel-header' : '' }}">
        @if (! ($panel ?? false))
            <div>
                <span class="page-eyebrow"><i class="bi bi-journal-bookmark"></i>{{ __('Academic') }}</span>
                <h1 class="page-title">{{ __('Study materials') }}</h1>
                <p class="page-subtitle">{{ __('Notes, suggestions and question papers kept against a class and subject, so the paper is still there when the next teacher needs it.') }}</p>
            </div>
        @endif
        <div class="page-actions">
            @if (! ($panel ?? false))
                <a href="{{ route('teacher.homework.index') }}" class="btn btn-light-soft">
                    <i class="bi bi-pencil-square"></i>{{ __('Homework') }}
                </a>
            @endif
            @can('materials.manage')
                <a href="{{ route('materials.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i>{{ __('Share material') }}
                </a>
            @endcan
        </div>
    </div>

    <div class="mini-stats mb-3">
        <div class="mini-stat">
            <span class="value">{{ $materials->total() }}</span>
            <span class="label">{{ __('Files for this filter') }}</span>
        </div>
        <div class="mini-stat">
            <span class="value">{{ $thisWeek }}</span>
            <span class="label">{{ __('Shared in the last 7 days') }}</span>
        </div>
        <div class="mini-stat">
            <span class="value">{{ $drafts ?? 0 }}</span>
            <span class="label">{{ __('Drafts the class cannot see') }}</span>
        </div>
        <div class="mini-stat text-start">
            <span class="label">{{ __('A teacher sees the materials of the classes on their own timetable; the office sees every class.') }}</span>
        </div>
    </div>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('materials.index') }}" class="filter-form" role="search">
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
                <label class="visually-hidden" for="filter-kind">{{ __('Type') }}</label>
                <select id="filter-kind" name="kind" class="form-select" data-auto-submit>
                    <option value="">{{ __('Material & classwork') }}</option>
                    <option value="material" @selected(($filters['kind'] ?? '') === 'material')>{{ __('Study materials') }}</option>
                    <option value="classwork" @selected(($filters['kind'] ?? '') === 'classwork')>{{ __('Classwork') }}</option>
                </select>
                <label class="visually-hidden" for="filter-status">{{ __('Status') }}</label>
                <select id="filter-status" name="status" class="form-select" data-auto-submit>
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="published" @selected(($filters['status'] ?? '') === 'published')>{{ __('Published') }}</option>
                    <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>{{ __('Drafts') }}</option>
                </select>
                <label class="visually-hidden" for="filter-q">{{ __('Search') }}</label>
                <input id="filter-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control"
                       placeholder="{{ __('Search title or note…') }}">
                <label class="visually-hidden" for="filter-from">{{ __('From') }}</label>
                <input id="filter-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
                <label class="visually-hidden" for="filter-to">{{ __('To') }}</label>
                <input id="filter-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-search"></i>{{ __('Search') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('materials.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">
                {{ trans_choice(':count file|:count files', $materials->total(), ['count' => $materials->total()]) }}
            </span>
        </div>

        @if ($materials->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-journal-x"></i></div>
                <h3>{{ __('No material shared yet') }}</h3>
                <p>{{ __('Upload the first note or suggestion paper for a class you teach.') }}</p>
                @can('materials.manage')
                    <a href="{{ route('materials.create') }}" class="btn btn-primary mt-3">
                        <i class="bi bi-cloud-arrow-up"></i>{{ __('Share material') }}
                    </a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Material') }}</th>
                            <th>{{ __('Class') }}</th>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Shared') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($materials as $entry)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $entry->title }}</div>
                                    @if (! $entry->isPublished())
                                        <span class="badge-soft is-warning mt-1">{{ __('Draft') }}</span>
                                    @endif
                                    @if ($entry->isClasswork())
                                        <span class="badge bg-label-info mt-1">
                                            <i class="bi bi-pencil-square"></i>{{ __('Classwork') }}
                                            @if ($entry->work_date) · {{ $entry->work_date->format('d M Y') }} @endif
                                        </span>
                                    @endif
                                    @if ($entry->lesson)
                                        <div class="text-muted-2" style="font-size:.74rem;">
                                            <i class="bi bi-journal-text"></i>
                                            {{ $entry->lesson->chapterLabel() ?? $entry->lesson->title }}
                                        </div>
                                    @endif
                                    <div class="text-muted-2" style="font-size:.74rem;">
                                        <i class="bi bi-file-earmark"></i>
                                        <span class="text-truncate d-inline-block" style="max-width:240px; vertical-align:bottom;">
                                            {{ $entry->downloadLabel() ?: '—' }}
                                        </span>
                                        @if ($entry->file_size)
                                            · {{ number_format($entry->file_size / 1024, 0) }} KB
                                        @endif
                                    </div>
                                    @if ($entry->description)
                                        <div class="text-muted-2" style="font-size:.74rem;">{{ Str::limit($entry->description, 90) }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $entry->schoolClass?->name ?? '—' }}
                                    <div class="text-muted-2" style="font-size:.74rem;">
                                        {{ $entry->section?->name ?? __('Whole class') }}
                                    </div>
                                </td>
                                <td>{{ $entry->subject?->name ?? '—' }}</td>
                                <td style="font-size:.8rem;">
                                    {{ $entry->created_at?->format('d M Y') ?? '—' }}
                                    <div class="text-muted-2" style="font-size:.72rem;">
                                        {{ $entry->creator?->name ?? '—' }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                        @if ($entry->file_path)
                                            <a href="{{ route('materials.file', $entry) }}" class="btn btn-sm btn-light-soft">
                                                <i class="bi bi-download"></i>{{ __('Open') }}
                                            </a>
                                        @endif
                                        @can('update', $entry)
                                            @if ($entry->isPublished())
                                                <form method="POST" action="{{ route('materials.unpublish', $entry) }}"
                                                      data-confirm="{{ __('Move :title back to draft? The class stops seeing it until you share it again.', ['title' => $entry->title]) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-light-soft"
                                                            title="{{ __('Unpublish') }}" aria-label="{{ __('Unpublish') }}">
                                                        <i class="bi bi-arrow-counterclockwise"></i>{{ __('Unpublish') }}
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('materials.publish', $entry) }}"
                                                      data-confirm="{{ __('Share :title with the class now?', ['title' => $entry->title]) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-primary"
                                                            title="{{ __('Publish') }}" aria-label="{{ __('Publish') }}">
                                                        <i class="bi bi-broadcast"></i>{{ __('Publish') }}
                                                    </button>
                                                </form>
                                            @endif
                                            <a href="{{ route('materials.edit', $entry) }}" class="icon-action"
                                               title="{{ __('Edit') }}" aria-label="{{ __('Edit material') }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="{{ route('materials.destroy', $entry) }}"
                                                  data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                                  data-confirm-delete="1"
                                                  data-confirm-label="{{ __('Permanently Delete') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="icon-action is-danger"
                                                        title="{{ __('Permanently Delete') }}" aria-label="{{ __('Withdraw material') }}">
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

            <div class="table-footer">{{ $materials->links() }}</div>
        @endif
    </section>