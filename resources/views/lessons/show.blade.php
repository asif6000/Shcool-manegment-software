@extends('layouts.app')

@section('title', $lesson->title)

@section('content')
    @include('lessons.partials.style')
    @include('lessons.partials.script')
    @include('lessons.partials.detail-body')
@endsection
