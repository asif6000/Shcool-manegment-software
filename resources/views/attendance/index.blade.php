@extends('layouts.app')

@section('title', __('Attendance'))

@section('content')
    @include('attendance.partials.register-body')
@endsection

@push('head')
    @include('attendance.partials.register-css')
@endpush

@push('scripts')
    @include('attendance.partials.register-js')
@endpush
