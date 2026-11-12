@php
    $studentInfo = $snapshot['student'] ?? [];
    $enrollmentInfo = $snapshot['enrollment'] ?? [];
    $subjects = $snapshot['subjects'] ?? [];
    $outcome = $snapshot['outcome'] ?? null;
    $complete = (bool) ($snapshot['complete'] ?? false);
    $snapshotPublishedAt = $snapshotPublishedAt ?? null;
    $school = $shellSchool ?? null;
@endphp

<div class="receipt-sheet">
    <div class="receipt-head">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="portal-brand-mark" style="width:40px;height:40px;border-radius:12px;font-size:.85rem;">
                    {{ $school?->initials() ?? 'EN' }}
                </span>
                <div>
                    <div class="fw-700">{{ $school?->name ?? config('app.name') }}</div>
                    <div class="text-muted-2" style="font-size:.76rem;">{{ $school?->address }}</div>
                </div>
            </div>
            <div class="text-muted-2" style="font-size:.76rem;">
                @if ($school?->phone)
                    {{ __('Phone') }}: {{ $school->phone }}
                @endif
                @if ($school?->eiin)
                    · EIIN {{ $school->eiin }}
                @endif
            </div>
        </div>
        <div class="text-lg-end">
            <span class="badge-soft {{ $published ? 'is-success' : 'is-warning' }}">
                {{ $published ? __('Published result') : __('Draft preview') }}
            </span>
            <div class="text-muted-2 mt-2" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;">{{ __('Academic report card') }}</div>
            <div class="fw-700">{{ $exam->name }}</div>
            <div class="text-muted-2" style="font-size:.78rem;">{{ $exam->academicSession?->name }}</div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-sm-7">
            <div class="text-muted-2" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;">{{ __('Student') }}</div>
            <div class="fw-700 mt-1">{{ $studentInfo['name'] ?? $student->full_name }}</div>
            <div class="text-muted-2" style="font-size:.8rem;">
                {{ __('Student ID') }}: {{ $studentInfo['student_id'] ?? $student->student_id }}
                @if (! empty($enrollmentInfo['class']))
                    · {{ $enrollmentInfo['class'] }}
                @endif
                @if (! empty($enrollmentInfo['section']))
                    ({{ __('Section') }} {{ $enrollmentInfo['section'] }})
                @endif
                @if (! empty($enrollmentInfo['roll_no']))
                    · {{ __('Roll') }} {{ $enrollmentInfo['roll_no'] }}
                @endif
            </div>
        </div>
        <div class="col-sm-5 text-sm-end">
            <div class="text-muted-2" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;">{{ __('GPA') }}</div>
            <div class="receipt-amount mt-1">{{ $snapshot['gpa'] !== null ? number_format((float) $snapshot['gpa'], 2) : '—' }}</div>
            <div class="text-muted-2" style="font-size:.8rem;">
                {{ __('Grade') }} <strong class="text-body">{{ $snapshot['grade_letter'] ?? '—' }}</strong>
                @if (! empty($snapshot['position']))
                    · {{ __('Position') }} <strong class="text-body">{{ $snapshot['position'] }}</strong>
                @endif
            </div>
        </div>
    </div>

    <div class="table-responsive mt-4">
        <table class="receipt-table w-100">
            <thead>
                <tr>
                    <th>{{ __('Subject') }}</th>
                    <th class="text-end">{{ __('Full') }}</th>
                    <th class="text-end">{{ __('Pass') }}</th>
                    <th class="text-end">{{ __('Obtained') }}</th>
                    <th class="text-end">{{ __('Grade') }}</th>
                    <th class="text-end">{{ __('Point') }}</th>
                    <th>{{ __('Remarks') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subjects as $subject)
                    <tr>
                        <td>
                            <div class="fw-600">{{ $subject['name'] ?? '—' }}</div>
                            @if (! empty($subject['code']))
                                <div class="text-muted-2" style="font-size:.72rem;">{{ $subject['code'] }}</div>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format((float) ($subject['full_marks'] ?? 0), 2) }}</td>
                        <td class="text-end">{{ number_format((float) ($subject['pass_marks'] ?? 0), 2) }}</td>
                        <td class="text-end">
                            @if (($subject['is_absent'] ?? false))
                                <span class="badge-soft is-warning">{{ __('Absent') }}</span>
                            @elseif (($subject['obtained'] ?? null) !== null)
                                {{ number_format((float) $subject['obtained'], 2) }}
                            @else
                                <span class="text-muted-2">—</span>
                            @endif
                        </td>
                        <td class="text-end">{{ $subject['grade_letter'] ?? '—' }}</td>
                        <td class="text-end">{{ $subject['grade_point'] !== null ? number_format((float) $subject['grade_point'], 2) : '—' }}</td>
                        <td>{{ $subject['remarks'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-muted-2">{{ __('No subject marks were recorded.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="row g-3 mt-3">
        <div class="col-sm-6">
            <dl class="detail-list mb-0">
                <div><dt>{{ __('Total marks') }}</dt><dd>{{ number_format((float) ($snapshot['total_obtained'] ?? 0), 2) }} / {{ number_format((float) ($snapshot['total_full_marks'] ?? 0), 2) }}</dd></div>
                <div><dt>{{ __('GPA') }}</dt><dd class="fw-700">{{ $snapshot['gpa'] !== null ? number_format((float) $snapshot['gpa'], 2) : '—' }}</dd></div>
                <div><dt>{{ __('Grade') }}</dt><dd class="fw-700">{{ $snapshot['grade_letter'] ?? '—' }}</dd></div>
            </dl>
        </div>
        <div class="col-sm-6 text-sm-end">
            @if ($complete && $outcome !== null)
                <span class="badge-soft {{ $outcome === 'fail' ? 'is-danger' : 'is-success' }}">
                    {{ $outcome === 'fail' ? __('Not passed') : __('Passed') }}
                </span>
            @else
                <span class="badge-soft is-warning">{{ __('Incomplete') }}</span>
            @endif
            <div class="text-muted-2 mt-2" style="font-size:.76rem;">
                @if (! empty($snapshot['position']))
                    {{ __('Position') }} {{ $snapshot['position'] }} · {{ __('Section') }} {{ $enrollmentInfo['section'] ?? '—' }}
                @endif
            </div>
        </div>
    </div>

    <p class="text-muted-2 mt-4 mb-0" style="font-size:.76rem;">
        @if ($published)
            {{ __('This report card reflects a published, immutable result snapshot. Contact the school office for corrections.') }}
        @else
            {{ __('Draft preview only. Results become final when the school publishes them.') }}
        @endif
    </p>
    @if ($published && ! empty($snapshotPublishedAt))
        <p class="text-muted-2 mt-1 mb-0" style="font-size:.72rem;">{{ __('Published') }}: {{ $snapshotPublishedAt }}</p>
    @endif
</div>
