@extends('layouts.app')

@section('title', $type === 'teachers' ? __('Add teacher') : __('Add staff member'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-plus"></i>{{ __('Human resources') }}</span>
            <h1 class="page-title">{{ $type === 'teachers' ? __('Add teacher') : __('Add staff member') }}</h1>
            <p class="page-subtitle">{{ __('Create an employee record for this school. Leave and salary can be added from the profile.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('staff.index', ['type' => $type]) }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Back to staff') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('staff.'.$type.'.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @include('staff.partials.form')
    </form>
@endsection
