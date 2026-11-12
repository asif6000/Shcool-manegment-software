@extends('layouts.applicant')

@section('title', $admission->application_no)

@section('content')
    @php
        $statusClasses = [
            'pending' => 'is-info',
            'waitlisted' => 'is-warning',
            'approved' => 'is-success',
            'rejected' => 'is-danger',
        ];
        $steps = [
            'submitted' => $admission->created_at,
            'reviewed' => $admission->reviewed_at,
        ];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-clipboard-check"></i>{{ __('Application ID') }}</span>
            <h1 class="page-title">{{ $admission->application_no }}</h1>
            <p class="page-subtitle">
                {{ $admission->fullName() }}
                <span class="badge-soft {{ $statusClasses[$admission->status] ?? '' }} ms-1">{{ __(ucfirst($admission->status)) }}</span>
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('applicant.dashboard') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('My applications') }}</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            @if ($admission->status === 'approved' && $admission->student)
                <section class="ui-card p-lg mb-3">
                    <h2 class="card-title">{{ __('Admission confirmed') }}</h2>
                    <p class="text-muted-2 mt-2 mb-3">{{ __('The school enrolled the student. These numbers are what you quote at the school office.') }}</p>
                    <dl class="detail-list mb-3">
                        <div><dt>{{ __('Student ID') }}</dt><dd>{{ $admission->student->student_id }}</dd></div>
                        <div><dt>{{ __('Admission number') }}</dt><dd>{{ $admission->student->admission_no }}</dd></div>
                    </dl>
                    <a href="{{ route('student.dashboard') }}" class="btn btn-primary">
                        <i class="bi bi-mortarboard"></i>{{ __('Open the student portal') }}
                    </a>
                </section>
            @endif

            @if ($admission->status === 'rejected')
                <section class="ui-card p-lg mb-3">
                    <h2 class="card-title">{{ __('Application not approved') }}</h2>
                    <p class="mt-2 mb-0">
                        {{ $admission->rejection_reason ?: __('The office did not add a reason. Please visit or call the school office.') }}
                    </p>
                    <p class="text-muted-2 mt-3 mb-0" style="font-size:.8rem;">
                        {{ __('You may submit a new application for another session.') }}
                    </p>
                </section>
            @endif

            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('What you submitted') }}</h2>
                <dl class="detail-list mt-2">
                    <div><dt>{{ __('Applied for') }}</dt><dd>{{ $admission->appliedClass?->name }} · {{ $admission->academicSession?->name }}</dd></div>
                    <div><dt>{{ __('Student name') }}</dt><dd>{{ $admission->fullName() }}</dd></div>
                    <div><dt>{{ __('Gender') }}</dt><dd>{{ __(ucfirst($admission->gender)) }}</dd></div>
                    <div><dt>{{ __('Date of birth') }}</dt><dd>{{ $admission->date_of_birth?->format('d M Y') }}</dd></div>
                    <div><dt>{{ __('Guardian') }}</dt><dd>{{ $admission->guardian_name }} · {{ $admission->guardian_phone }}</dd></div>
                    <div><dt>{{ __('Guardian email') }}</dt><dd>{{ $admission->guardian_email ?? '—' }}</dd></div>
                    <div><dt>{{ __('Address') }}</dt><dd>{{ $admission->address ?? '—' }}</dd></div>
                    <div><dt>{{ __('Previous school') }}</dt><dd>{{ $admission->previous_school ?? '—' }}</dd></div>
                    @if ($admission->hasPhoto())
                        <div>
                            <dt>{{ __('Photo') }}</dt>
                            <dd><img src="{{ route('applicant.photo', $admission) }}" alt="{{ __('Uploaded photo') }}" style="width:84px;height:84px;object-fit:cover;border-radius:10px;"></dd>
                        </div>
                    @endif
                </dl>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('Status') }}</h2>
                <dl class="detail-list mt-2 mb-0">
                    <div><dt>{{ __('Submitted') }}</dt><dd>{{ $steps['submitted']?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                    <div>
                        <dt>{{ __('Decision') }}</dt>
                        <dd>
                            @if ($admission->isOpen())
                                <span class="badge-soft {{ $statusClasses[$admission->status] }}">{{ __('Under review') }}</span>
                            @else
                                <span class="badge-soft {{ $statusClasses[$admission->status] }}">{{ __(ucfirst($admission->status)) }}</span>
                                <div class="text-muted-2" style="font-size:.76rem;">{{ $steps['reviewed']?->format('d M Y, h:i A') }}</div>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>{{ __('Student record') }}</dt>
                        <dd>{{ $admission->status === 'approved' ? ($admission->student?->student_id ?? '—') : __('Not created yet') }}</dd>
                    </div>
                </dl>
            </section>

            <section class="ui-card p-lg mt-3">
                <h2 class="card-title">{{ __('Need to change something?') }}</h2>
                <p class="text-muted-2 mt-2 mb-0" style="font-size:.82rem;">
                    {{ __('A submitted application is locked so the record stays trustworthy. Call the office with your application number if a detail is wrong.') }}
                </p>
                <div class="d-grid mt-3">
                    <a href="{{ route('applicant.apply') }}" class="btn btn-light-soft">
                        <i class="bi bi-plus-lg"></i>{{ __('New application') }}
                    </a>
                </div>
            </section>
        </div>
    </div>
@endsection
