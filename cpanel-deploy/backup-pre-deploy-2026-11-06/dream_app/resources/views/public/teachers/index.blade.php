@extends('layouts.public')

@section('title', 'শিক্ষকমণ্ডলী')
@section('page-banner', 'শিক্ষকমণ্ডলী')
@section('page-banner-extra')
    <span class="pub-page-banner-current">আমাদের শিক্ষকবৃন্দ</span>
@endsection

@section('content')
    <section class="pub-section">
        <div class="container">
            <div class="row g-3">
                @forelse ($teachers as $teacher)
                    <div class="col-md-6 col-lg-4">
                        <div class="pub-teacher-card">
                            <span class="pub-teacher-avatar">
                                {{ mb_substr($teacher->first_name, 0, 1) }}
                            </span>
                            <div class="pub-teacher-info">
                                <strong>{{ $teacher->full_name }}</strong>
                                <span class="pub-teacher-designation">
                                    <i class="bi bi-briefcase"></i> {{ $teacher->designation ?: 'শিক্ষক' }}
                                </span>
                                @if ($teacher->qualification)
                                    <span class="pub-teacher-meta">
                                        <i class="bi bi-mortarboard"></i> {{ $teacher->qualification }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="pub-panel">
                            <div class="pub-panel-body">
                                <p class="pub-empty mb-0">শিক্ষকমণ্ডলীর তথ্য শীঘ্রই যুক্ত করা হবে।</p>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
