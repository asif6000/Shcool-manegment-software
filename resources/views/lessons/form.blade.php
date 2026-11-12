@extends('layouts.app')

@section('title', $lesson->exists ? __('Edit lesson') : __('Write a lesson'))

@section('content')
    @include('lessons.partials.style')
    @include('lessons.partials.script')
    @include('lessons.partials.form-body')
@endsection
