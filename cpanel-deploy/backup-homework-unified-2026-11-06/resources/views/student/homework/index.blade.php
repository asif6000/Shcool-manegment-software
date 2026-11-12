@extends('layouts.student')

@section('title', __('Homework'))

@section('content')
    @php
        $rows = $rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $rows : collect();
        $badges = [
            'submitted' => ['is-success', __('Submitted')],
            'late' => ['is-warning', __('Late')],
            'reviewed' => ['is-brand', __('Reviewed')],
        ];
        $filters = [
            '' => __('All'),
            'pending' => __('To do'),
            'submitted' => __('Submitted'),
        ];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-pencil-square"></i>{{ __('Studies') }}</span>
            <h1 class="page-title">{{ __('Homework & assignments') }}</h1>
            <p class="page-subtitle">{{ __('Assignments your teachers set for your class, with deadlines and feedback.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('student.routine') }}" class="btn btn-light-soft">
                <i class="bi bi-calendar2-week"></i>{{ __('Class routine') }}
            </a>
        </div>
    </div>

    <div class="ui-card table-card">
        <div class="table-toolbar">
            <div>
                <h2 class="card-title mb-1">{{ trans_choice(':count assignment|:count assignments', $rows->total(), ['count' => $rows->total()]) }}</h2>
                <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Sorted by the nearest deadline first.') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($filters as $value => $label)
                    <a href="{{ route('student.homework.index', $value === '' ? [] : ['status' => $value]) }}"
                       class="btn btn-sm {{ $filter === $value ? 'btn-primary' : 'btn-light-soft' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-clipboard-check"></i></div>
                <h3>{{ __('Nothing here yet') }}</h3>
                <p>{{ $filter === 'pending'
                    ? __('You have no homework waiting. New assignments appear as soon as a teacher publishes one.')
                    : __('Homework assigned to your class will appear here.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Homework') }}</th>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Assigned') }}</th>
                            <th>{{ __('Due') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $entry)
                            @php
                                $status = $statuses[$entry->id] ?? null;
                                $badge = $badges[$status ?? ''] ?? null;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('student.homework.show', $entry->id) }}" class="fw-600">{{ $entry->title }}</a>
                                    @if ($entry->attachment_path)
                                        <span class="text-muted-2" style="font-size:.74rem;"><i class="bi bi-paperclip"></i></span>
                                    @endif
                                    <div class="text-muted-2" style="font-size:.74rem;">
                                        {{ \Illuminate\Support\Str::limit($entry->description, 70) }}
                                    </div>
                                </td>
                                <td>{{ $entry->subject?->name ?? '—' }}</td>
                                <td>{{ $entry->assigned_date?->format('d M Y') ?? '—' }}</td>
                                <td>
                                    {{ $entry->due_date?->format('d M Y') ?? '—' }}
                                    @if ($status === null && $entry->isPastDue())
                                        <div class="text-danger" style="font-size:.72rem;">{{ __('Overdue') }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($badge)
                                        <span class="badge-soft {{ $badge[0] }}">{{ $badge[1] }}</span>
                                    @elseif ($entry->isPastDue())
                                        <span class="badge-soft is-danger"><span class="dot"></span>{{ __('Not submitted') }}</span>
                                    @else
                                        <span class="badge-soft is-warning"><span class="dot"></span>{{ __('Not submitted') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($rows->hasPages())
                <div class="p-3 border-top">{{ $rows->links() }}</div>
            @endif
        @endif
    </div>
@endsection
