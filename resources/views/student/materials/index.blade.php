@extends('layouts.student')

@section('title', $kind === 'classwork' ? __('Classwork') : __('Study materials'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-journal-bookmark"></i>{{ __('Studies') }}</span>
            <h1 class="page-title">{{ __('Classwork & study materials') }}</h1>
            <p class="page-subtitle">{{ __('Notes and files your teachers shared with your class.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('student.homework.index') }}" class="btn btn-light-soft">
                <i class="bi bi-pencil-square"></i>{{ __('Homework & Classwork') }}
            </a>
        </div>
    </div>

    <div class="ui-card table-card">
        <div class="table-toolbar">
            <div>
                <h2 class="card-title mb-1">{{ trans_choice(':count item|:count items', $rows->total(), ['count' => $rows->total()]) }}</h2>
                <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Newest first.') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <a href="{{ route('student.materials.index') }}"
                   class="btn btn-sm {{ $kind === 'material' ? 'btn-primary' : 'btn-light-soft' }}">{{ __('Study materials') }}</a>
                <a href="{{ route('student.materials.index', ['kind' => 'classwork']) }}"
                   class="btn btn-sm {{ $kind === 'classwork' ? 'btn-primary' : 'btn-light-soft' }}">{{ __('Classwork') }}</a>
                <form method="GET" action="{{ route('student.materials.index') }}" class="d-flex gap-2">
                    <input type="hidden" name="kind" value="{{ $kind }}">
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm"
                           placeholder="{{ __('Search titles…') }}" aria-label="{{ __('Search') }}">
                    <select name="subject" class="form-select form-select-sm" aria-label="{{ __('Subject') }}">
                        <option value="">{{ __('All subjects') }}</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected($filters['subject'] === $subject->id)>{{ $subject->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-light-soft" type="submit"><i class="bi bi-funnel"></i></button>
                </form>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-journal-text"></i></div>
                <h3>{{ __('Nothing here yet') }}</h3>
                <p>{{ $kind === 'classwork'
                    ? __('Older class work posted here stays available. New class work is on the Homework & classwork screen.')
                    : __('Study materials shared with your class will appear here.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Subject') }}</th>
                            @if ($kind === 'classwork')
                                <th>{{ __('Date') }}</th>
                            @endif
                            <th>{{ __('Shared') }}</th>
                            <th class="text-end">{{ __('File') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $material)
                            <tr>
                                <td>
                                    <div class="fw-medium">{{ $material->title }}</div>
                                    @if ($material->lesson)
                                        <div class="text-muted-2" style="font-size:.74rem;">
                                            <i class="bi bi-journal-text"></i>
                                            {{ $material->lesson->chapterLabel() ?? $material->lesson->title }}
                                        </div>
                                    @endif
                                    @if ($material->teacher)
                                        <div class="text-muted-2" style="font-size:.74rem;">
                                            <i class="bi bi-person"></i>{{ $material->teacher->full_name }}
                                        </div>
                                    @endif
                                    @if ($material->description)
                                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ Str::limit($material->description, 120) }}</p>
                                    @endif
                                </td>
                                <td>{{ $material->subject?->name ?? '—' }}</td>
                                @if ($kind === 'classwork')
                                    <td>{{ ($material->work_date ?? $material->created_at)?->translatedFormat('d M Y') ?? '—' }}</td>
                                @endif
                                <td>{{ $material->created_at?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="text-end">
                                    @if ($material->file_path !== null)
                                        <a href="{{ route('student.materials.file', $material) }}" class="btn btn-sm btn-light-soft" download>
                                            <i class="bi bi-download"></i>{{ __('Open') }}
                                        </a>
                                    @else
                                        <span class="text-muted-2" style="font-size:.8rem;">{{ __('Notes only') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-top">{{ $rows->links() }}</div>
        @endif
    </div>
@endsection
