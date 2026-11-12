@extends('layouts.app')

@section('title', __('Edit application'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-clipboard-check"></i>{{ __('Admissions') }}</span>
            <h1 class="page-title">{{ $admission->application_no }}</h1>
            <p class="page-subtitle">{{ $admission->fullName() }} · {{ $admission->appliedClass?->name }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('admissions.show', $admission) }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Back to application') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admissions.update', $admission) }}" novalidate>
        @csrf
        @method('PUT')
        @include('admissions.partials.form', ['admission' => $admission])
    </form>
@endsection
