@php
    $dayNames = [
        0 => 'শনিবার',
        1 => 'রবিবার',
        2 => 'সোমবার',
        3 => 'মঙ্গলবার',
        4 => 'বুধবার',
        5 => 'বৃহস্পতিবার',
        6 => 'শুক্রবার',
    ];

    $byDay = $routines->groupBy('day_of_week');
@endphp
@extends('layouts.public')

@section('title', 'ক্লাস রুটিন')
@section('page-banner', 'ক্লাস রুটিন')
@section('page-banner-extra')
    <span class="pub-page-banner-current">{{ $session?->name ?? 'শিক্ষাবর্ষ' }}</span>
@endsection

@section('content')
    <section class="pub-section">
        <div class="container">
            <div class="pub-panel mb-4">
                <div class="pub-panel-body">
                    <form method="GET" action="{{ route('public.routines.index') }}" class="pub-filter-form">
                        <div>
                            <label class="pub-label" for="class_id">শ্রেণি</label>
                            <select class="pub-input" id="class_id" name="class_id" onchange="this.form.submit()">
                                @foreach ($classes as $classOption)
                                    <option value="{{ $classOption->id }}" @selected($class?->id === $classOption->id)>{{ $classOption->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($sections->isNotEmpty())
                            <div>
                                <label class="pub-label" for="section_id">শাখা</label>
                                <select class="pub-input" id="section_id" name="section_id" onchange="this.form.submit()">
                                    @foreach ($sections as $sectionOption)
                                        <option value="{{ $sectionOption->id }}" @selected($section?->id === $sectionOption->id)>{{ $sectionOption->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </form>
                </div>
            </div>

            @if ($class === null || $routines->isEmpty())
                <div class="pub-panel">
                    <div class="pub-panel-body">
                        <p class="pub-empty mb-0">এই শ্রেণির জন্য কোনো ক্লাস রুটিন প্রকাশিত হয়নি।</p>
                    </div>
                </div>
            @else
                <div class="row g-3">
                    @foreach ($dayNames as $dayNumber => $dayName)
                        @php($dayRoutines = $byDay->get($dayNumber))
                        <div class="col-md-6 col-lg-4">
                            <div class="pub-panel pub-routine-day">
                                <div class="pub-panel-head pub-panel-blue">
                                    <h3 class="pub-h3 mb-0">{{ $dayName }}</h3>
                                </div>
                                <div class="pub-panel-body pub-routine-body">
                                    @forelse ($dayRoutines ?? [] as $routine)
                                        <div class="pub-routine-item">
                                            <span class="pub-routine-time">
                                                {{ \Carbon\Carbon::parse($routine->start_time)->format('g:i A') }}
                                                –
                                                {{ \Carbon\Carbon::parse($routine->end_time)->format('g:i A') }}
                                            </span>
                                            <strong>{{ $routine->subject?->name }}</strong>
                                            <small>
                                                @if ($routine->teacher)
                                                    {{ $routine->teacher->full_name }}
                                                @endif
                                                @if ($routine->room)
                                                    &middot; রুম {{ $routine->room }}
                                                @endif
                                            </small>
                                        </div>
                                    @empty
                                        <p class="pub-empty">কোনো ক্লাস নেই</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
