@extends('layouts.app')

@section('title', __('Edit :name', ['name' => $person->full_name]))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-pencil"></i>{{ __('Human resources') }}</span>
            <h1 class="page-title">{{ __('Edit :name', ['name' => $person->full_name]) }}</h1>
            <p class="page-subtitle">{{ $person->employee_code }} · {{ $person->designation }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('staff.'.$type.'.show', $person) }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Back to profile') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('staff.'.$type.'.update', $person) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')
        @include('staff.partials.form')
    </form>
@endsection
