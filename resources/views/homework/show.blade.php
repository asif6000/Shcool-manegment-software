@extends('layouts.app')

@section('title', $homework->title)

@section('content')
    @include('homework/partials/detail-body')
@endsection
