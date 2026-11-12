@extends('layouts.app')

@section('title', __('Admit student'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-plus"></i>{{ __('Student information') }}</span>
            <h1 class="page-title">{{ __('Admit student') }}</h1>
            <p class="page-subtitle">{{ __('Create the student record, attach a guardian and optionally enroll them in a class.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('students.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Back to students') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('students.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @include('students.partials.form', ['withEnrollment' => true])
    </form>
@endsection
