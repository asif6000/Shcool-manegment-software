@extends('layouts.applicant')

@section('title', __('My applications'))

@section('content')
    @php
        $statusClasses = [
            'pending' => 'is-info',
            'waitlisted' => 'is-warning',
            'approved' => 'is-success',
            'rejected' => 'is-danger',
        ];
        $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first();
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-clipboard-check"></i>{{ __('Admission application') }}</span>
            <h1 class="page-title">{{ __('Hello, :name', ['name' => $firstName]) }}</h1>
            <p class="page-subtitle">{{ __('Track every application you submit. The school office reviews them and posts the decision here.') }}</p>
        </div>
        <div class="page-actions">
            @if ($canApply)
                <a href="{{ route('applicant.apply') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i>{{ __('New application') }}
                </a>
            @endif
        </div>
    </div>

    @if (! $canApply)
        <div class="alert alert-success d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill"></i>
            <div>
                {{ __('Your admission is complete.') }}
                <a href="{{ route('student.dashboard') }}" class="fw-600">{{ __('Open the student portal') }}</a>
            </div>
        </div>
    @endif

    @if ($admissions->isEmpty())
        <section class="ui-card p-lg">
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                <h3>{{ __('No application yet') }}</h3>
                <p>{{ __('Start your admission application and you will get an application number to track here.') }}</p>
                @if ($canApply)
                    <a href="{{ route('applicant.apply') }}" class="btn btn-primary mt-2">
                        <i class="bi bi-pencil-square"></i>{{ __('Start application') }}
                    </a>
                @endif
            </div>
        </section>
    @else
        <section class="ui-card table-card">
            <div class="table-toolbar">
                <div>
                    <h2 class="card-title mb-1">{{ __('Your applications') }}</h2>
                    <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ trans_choice(':count application|:count applications', $admissions->count(), ['count' => $admissions->count()]) }}</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Application ID') }}</th>
                            <th>{{ __('Applied for') }}</th>
                            <th>{{ __('Submitted') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($admissions as $admission)
                            <tr>
                                <td>
                                    <a href="{{ route('applicant.show', $admission) }}" class="fw-600">{{ $admission->application_no }}</a>
                                    <div class="text-muted-2" style="font-size:.76rem;">{{ $admission->fullName() }}</div>
                                </td>
                                <td>
                                    {{ $admission->appliedClass?->name ?? '—' }}
                                    <div class="text-muted-2" style="font-size:.76rem;">{{ $admission->academicSession?->name }}</div>
                                </td>
                                <td>{{ $admission->created_at->format('d M Y') }}</td>
                                <td>
                                    <span class="badge-soft {{ $statusClasses[$admission->status] ?? '' }}">
                                        <span class="dot"></span>{{ __(ucfirst($admission->status)) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('applicant.show', $admission) }}" class="portal-card-link">{{ __('Open') }} <i class="bi bi-arrow-right"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
