@extends('layouts.app')

@section('title', __('Edit student'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-pencil"></i>{{ __('Student information') }}</span>
            <h1 class="page-title">{{ $student->full_name }}</h1>
            <p class="page-subtitle">{{ __('Student ID') }} {{ $student->student_id }} · {{ __('Admission no') }} {{ $student->admission_no }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('students.show', $student) }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Back to profile') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('students.update', $student) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')
        @include('students.partials.form', ['student' => $student, 'withEnrollment' => false])
    </form>
@endsection
