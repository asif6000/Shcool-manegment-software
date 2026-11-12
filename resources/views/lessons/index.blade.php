@extends('layouts.app')

@section('title', __('Lesson management'))

@section('content')
    @include('lessons.partials.style')
    @include('lessons.partials.script')
    @include('lessons.partials.list-body')
@endsection
