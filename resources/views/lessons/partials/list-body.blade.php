    @php
        $user = auth()->user();
        $ownTeacherId = $user->teacher?->id;

        // The buttons only ever appear where the server will accept the write:
        // the office works on every lesson, a teacher on the ones they wrote.
        $isMine = fn (App\Models\Lesson $lesson): bool => $mayDeleteAny
            || (int) $lesson->created_by === (int) $user->id
            || ($ownTeacherId !== null && $lesson->teacher_id !== null && (int) $lesson->teacher_id === (int) $ownTeacherId);
    @endphp

    <div class="page-header{{ ($panel ?? false) ? ' panel-header' : '' }}">
        @if (! ($panel ?? false))
            <div>
                <span class="page-eyebrow"><i class="bi bi-easel2"></i>{{ __('Academic') }}</span>
                <h1 class="page-title">{{ __('Lesson management') }}</h1>
                <p class="page-subtitle">{{ __('Write a lesson for a period on your own timetable, keep it as a draft until it reads well, then publish it for the class — no approval is needed.') }}</p>
            </div>
        @endif
        <div class="page-actions">
            @if (! ($panel ?? false) && auth()->user()->hasRole('teacher') && ! auth()->user()->hasAnyRole(['school_admin', 'principal', 'super_admin']))
                <a href="{{ route('materials.index') }}" class="btn btn-light-soft">
                    <i class="bi bi-journal-bookmark"></i>{{ __('Study materials') }}
                </a>
            @endif
            @if ($mayManage)
                <a href="{{ route('lessons.create') }}" class="btn btn-primary" data-td-open="lessons">
                    <i class="bi bi-plus-lg"></i>{{ __('New lesson') }}
                </a>
            @endif
        </div>
    </div>

    <div class="lsn-stat-row">
        <div class="lsn-stat">
            <span class="lsn-stat-value">{{ number_format($counts['total']) }}</span>
            <span class="lsn-stat-label"><i class="bi bi-collection"></i>{{ __('Total lessons') }}</span>
        </div>
        <div class="lsn-stat is-accent">
            <span class="lsn-stat-value">{{ number_format($counts['published']) }}</span>
            <span class="lsn-stat-label"><i class="bi bi-broadcast"></i>{{ __('Published lessons') }}</span>
        </div>
        <div class="lsn-stat is-draft">
            <span class="lsn-stat-value">{{ number_format($counts['draft']) }}</span>
            <span class="lsn-stat-label"><i class="bi bi-pencil"></i>{{ __('Draft lessons') }}</span>
        </div>
        <div class="lsn-stat">
            <span class="lsn-stat-value">{{ number_format($counts['recent']) }}</span>
            <span class="lsn-stat-label"><i class="bi bi-clock-history"></i>{{ __('Updated this week') }}</span>
        </div>
    </div>

    <div class="lsn-banner" data-lsn-banner role="status" aria-live="polite"></div>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('lessons.index') }}" class="filter-form" role="search">
                <label class="visually-hidden" for="lsn-filter-class">{{ __('Class') }}</label>
                <select id="lsn-filter-class" name="class" class="form-select" data-auto-submit>
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected((int) ($filters['class'] ?? 0) === $class->id)>
                            {{ $class->name }}
                        </option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="lsn-filter-section">{{ __('Section') }}</label>
                <select id="lsn-filter-section" name="section" class="form-select" data-auto-submit>
                    <option value="">{{ __('All sections') }}</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}" @selected((int) ($filters['section'] ?? 0) === $section->id)>
                            {{ $section->name }}
                        </option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="lsn-filter-subject">{{ __('Subject') }}</label>
                <select id="lsn-filter-subject" name="subject" class="form-select" data-auto-submit>
                    <option value="">{{ __('All subjects') }}</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected((int) ($filters['subject'] ?? 0) === $subject->id)>
                            {{ $subject->name }}
                        </option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="lsn-filter-status">{{ __('Status') }}</label>
                <select id="lsn-filter-status" name="status" class="form-select" data-auto-submit>
                    <option value="">{{ __('Published & drafts') }}</option>
                    <option value="published" @selected(($filters['status'] ?? '') === 'published')>{{ __('Published') }}</option>
                    <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>{{ __('Draft') }}</option>
                </select>
                <label class="visually-hidden" for="lsn-filter-chapter">{{ __('Chapter') }}</label>
                <input id="lsn-filter-chapter" type="search" name="chapter" value="{{ $filters['chapter'] ?? '' }}" class="form-control"
                       placeholder="{{ __('Chapter no.') }}" style="max-width:7.5rem;">
                <label class="visually-hidden" for="lsn-filter-q">{{ __('Search') }}</label>
                <input id="lsn-filter-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control"
                       placeholder="{{ __('Search title, chapter or summary…') }}">
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-search"></i>{{ __('Search') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('lessons.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">
                {{ trans_choice(':count lesson|:count lessons', $lessons->total(), ['count' => $lessons->total()]) }}
            </span>
        </div>

        @if ($lessons->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-easel2"></i></div>
                <h3>{{ __('No lesson here yet') }}</h3>
                <p>{{ $mayManage
                    ? __('Write the first lesson for one of your periods. It stays a draft until you publish it.')
                    : __('Nothing has been published to the classes you can see.') }}</p>
                @if ($mayManage)
                    <a href="{{ route('lessons.create') }}" class="btn btn-primary mt-3" data-td-open="lessons">
                        <i class="bi bi-plus-lg"></i>{{ __('New lesson') }}
                    </a>
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Lesson title') }}</th>
                            <th>{{ __('Class') }}</th>
                            <th>{{ __('Section') }}</th>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Chapter') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Last updated') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody data-lsn-list>
                        @foreach ($lessons as $lesson)
                            @php $mine = $isMine($lesson); @endphp
                            <tr data-lsn-row="{{ $lesson->id }}"
                                data-lsn-show="{{ route('lessons.show', $lesson) }}"
                                data-lsn-edit="{{ route('lessons.edit', $lesson) }}"
                                data-lsn-delete-published="{{ $mayDeleteAny ? '1' : '0' }}">
                                <td>
                                    <div class="fw-semibold">{{ $lesson->title }}</div>
                                    <div class="text-muted-2" style="font-size:.74rem;">
                                        @if ($lesson->materials_count > 0)
                                            <i class="bi bi-paperclip"></i>{{ trans_choice(':count file|:count files', $lesson->materials_count, ['count' => $lesson->materials_count]) }}
                                        @endif
                                        @if ($lesson->youtube_id)
                                            <i class="bi bi-camera-reels"></i>{{ __('Video') }}
                                        @endif
                                        @if ($lesson->description)
                                            <div>{{ Str::limit(strip_tags($lesson->description), 80) }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td data-lsn-field="class">{{ $lesson->schoolClass?->name ?? '—' }}</td>
                                <td data-lsn-field="section">{{ $lesson->section?->name ?? __('Whole class') }}</td>
                                <td data-lsn-field="subject">{{ $lesson->subject?->name ?? '—' }}</td>
                                <td data-lsn-field="chapter" style="font-size:.8rem;">{{ $lesson->chapterLabel() ?? '—' }}</td>
                                <td data-lsn-field="status">
                                    {{-- Both states are drawn and one is hidden, so a
                                         publish can be answered without a reload. --}}
                                    <span class="badge-soft is-brand {{ $lesson->isPublished() ? '' : 'd-none' }}" data-lsn-part="badge-published">
                                        <i class="bi bi-broadcast"></i>{{ __('Published') }}
                                    </span>
                                    <span class="badge-soft {{ $lesson->isPublished() ? 'd-none' : '' }}" data-lsn-part="badge-draft">
                                        <i class="bi bi-pencil"></i>{{ __('Draft') }}
                                    </span>
                                </td>
                                <td style="font-size:.8rem;">
                                    <span data-lsn-when>{{ $lesson->updated_at?->format('d M Y') ?? '—' }}</span>
                                    <div class="text-muted-2" style="font-size:.72rem;">
                                        {{ $lesson->teacher?->full_name ?? ($lesson->creator?->name ?? '—') }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                        <a href="{{ route('lessons.show', $lesson) }}" class="icon-action"
                                           title="{{ __('View') }}" aria-label="{{ __('View lesson') }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('lessons.show', ['lesson' => $lesson, 'as' => 'student']) }}" class="icon-action"
                                           title="{{ __('Preview as student') }}" aria-label="{{ __('Preview as student') }}">
                                            <i class="bi bi-mortarboard"></i>
                                        </a>
                                        @if ($mayManage && $mine)
                                            <a href="{{ route('lessons.edit', $lesson) }}" class="icon-action"
                                               title="{{ __('Edit') }}" aria-label="{{ __('Edit lesson') }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="icon-action {{ $lesson->isPublished() ? 'd-none' : '' }}"
                                                    data-lsn-part="publish" data-lsn-action="publish"
                                                    data-lsn-url="{{ route('lessons.publish', $lesson) }}"
                                                    data-lsn-title="{{ $lesson->title }}"
                                                    title="{{ __('Publish') }}" aria-label="{{ __('Publish lesson') }}">
                                                <i class="bi bi-broadcast"></i>
                                            </button>
                                            <button type="button" class="icon-action {{ $lesson->isPublished() ? '' : 'd-none' }}"
                                                    data-lsn-part="unpublish" data-lsn-action="unpublish"
                                                    data-lsn-url="{{ route('lessons.unpublish', $lesson) }}"
                                                    data-lsn-title="{{ $lesson->title }}"
                                                    title="{{ __('Unpublish') }}" aria-label="{{ __('Unpublish lesson') }}">
                                                <i class="bi bi-eye-slash"></i>
                                            </button>
                                            <button type="button" class="icon-action is-danger {{ $lesson->isPublished() && ! $mayDeleteAny ? 'd-none' : '' }}"
                                                    data-lsn-part="delete" data-lsn-action="delete"
                                                    data-lsn-url="{{ route('lessons.destroy', $lesson) }}"
                                                    data-lsn-title="{{ $lesson->title }}"
                                                    title="{{ $lesson->isPublished() ? __('Delete') : __('Delete draft') }}"
                                                    aria-label="{{ $lesson->isPublished() ? __('Delete lesson') : __('Delete draft') }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">{{ $lessons->links() }}</div>
        @endif
    </section>

    {{-- An AJAX save has nowhere to go on its own; this anchor lets the panel
         machinery fetch the screen it points at, using the same route the menu
         uses. The href is rewritten before each click. --}}
    <a href="{{ route('lessons.index', array_filter($filters)) }}" data-td-open="lessons" data-lsn-refresh hidden></a>
