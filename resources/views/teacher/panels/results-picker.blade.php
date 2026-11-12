{{--
    Opens the result panel: the exams and papers this teacher owns on their own
    timetable. Choosing a paper fetches the real marks sheet, which repeats the
    same scope check. A teacher never sees an approval or publication control.
--}}
@php
    $exams = $marks['exams'];
    $rows = $marks['list'];
@endphp

<div class="tp-panel-inner">
    <div class="mini-stats mb-3">
        <div class="mini-stat">
            <span class="value">{{ number_format($marks['papers']) }}</span>
            <span class="label">{{ __('Subject papers in open exams') }}</span>
        </div>
        <div class="mini-stat">
            <span class="value">{{ number_format($marks['entered']) }}</span>
            <span class="label">{{ __('Marks you have entered') }}</span>
        </div>
        <div class="mini-stat">
            <span class="value">{{ number_format($marks['to_submit']) }}</span>
            <span class="label">{{ __('Sheets ready to hand over') }}</span>
        </div>
    </div>

    @if ($exams->isEmpty())
        <section class="ui-card p-lg">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-clipboard-check"></i></div>
                <h3>{{ __('No marks to enter right now') }}</h3>
                <p>{{ __('Marks appear here while an exam in the current session is still in draft and covers a class and subject on your timetable. Published results are locked and handled by the office.') }}</p>
            </div>
        </section>
    @else
        <section class="ui-card table-card mb-3">
            <div class="table-toolbar">
                <h2 class="card-title mb-0">{{ __('Choose an exam') }}</h2>
                <span class="text-muted-2" style="font-size:.78rem;">{{ __('Marks are entered one class and subject at a time.') }}</span>
            </div>

            <ul class="agenda-list mb-0">
                @foreach ($exams as $entry)
                    @php $exam = $entry['exam']; @endphp
                    <li class="agenda-item">
                        <div class="agenda-copy">
                            <p>{{ $exam->name }}</p>
                            <span>
                                {{ $exam->academicSession?->name ?? __('Current session') }}
                                @if ($exam->start_date) · {{ $exam->start_date->translatedFormat('d M') }}@endif
                            </span>
                        </div>
                        <span class="badge-soft is-brand">{{ trans_choice(':count paper|:count papers', $entry['papers'], ['count' => $entry['papers']]) }}</span>
                        <a class="btn btn-sm btn-primary" href="{{ route('exams.marks.index', ['exam' => $exam->id]) }}">
                            <i class="bi bi-pencil-square"></i>{{ __('Enter marks') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="ui-card table-card">
            <div class="table-toolbar">
                <h2 class="card-title mb-0">{{ __('Papers to complete') }}</h2>
                <span class="text-muted-2" style="font-size:.78rem;">{{ __('A sheet that is marked in full but not handed over is still showing as owed.') }}</span>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Exam') }}</th>
                            <th scope="col">{{ __('Class') }}</th>
                            <th scope="col">{{ __('Subject') }}</th>
                            <th scope="col" class="text-end">{{ __('Entered') }}</th>
                            <th scope="col" class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $paper = $row['paper']; @endphp
                            <tr>
                                <td class="fw-600">{{ $paper->exam?->name ?? '—' }}</td>
                                <td>
                                    {{ $paper->schoolClass?->name ?? '—' }}
                                    <div class="text-muted-2" style="font-size:.72rem;">{{ $paper->subject?->name ?? '—' }}</div>
                                </td>
                                <td>{{ $paper->subject?->name ?? '—' }}</td>
                                <td class="text-end">
                                    {{ $row['marked'] }}/{{ $row['enrolled'] }}
                                    @if ($row['handed_over'])
                                        <span class="badge-soft is-success">{{ __('Handed over') }}</span>
                                    @elseif ($row['remaining'] > 0)
                                        <span class="badge-soft is-warning">{{ __('Missing') }} {{ $row['remaining'] }}</span>
                                    @else
                                        <span class="badge-soft is-info">{{ __('To submit') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-light-soft" href="{{ route('exams.marks.index', [
                                        'exam' => $paper->exam_id,
                                        'class_id' => $paper->class_id,
                                        'paper_id' => $paper->id,
                                    ]) }}">{{ __('Open sheet') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
