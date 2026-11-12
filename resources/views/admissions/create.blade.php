@extends('layouts.app')

@section('title', __('New application'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-clipboard-check"></i>{{ __('Admissions') }}</span>
            <h1 class="page-title">{{ __('New application') }}</h1>
            <p class="page-subtitle">{{ __('Record the applicant and guardian details now; approve the application once a seat is confirmed.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('admissions.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Back to admissions') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admissions.store') }}" novalidate>
        @csrf
        @include('admissions.partials.form')
    </form>
@endsection
