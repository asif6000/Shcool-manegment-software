@extends('layouts.app')

@section('title', __('Homework & Classwork'))

@section('content')
    @include('homework/partials/list-body')
    @include('homework/partials/live-script')
@endsection
