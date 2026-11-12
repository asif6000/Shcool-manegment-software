@extends('layouts.app')

@section('title', $homework->exists ? __('Edit homework') : __('Assign homework'))

@section('content')
    @include('homework/partials/form-body')
@endsection
