@extends('layouts.app')

@section('title', __('Enter marks') . ' · ' . $exam->name)

@section('content')
    @include('exams.partials.marks-body')
@endsection

@push('head')
    @include('exams.partials.marks-body-css')
@endpush

@push('scripts')
    @include('exams.partials.marks-body-js')
@endpush
