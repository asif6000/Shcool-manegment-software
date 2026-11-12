@extends('layouts.public')

@section('title', $teacher->full_name . ' — শিক্ষক পরিচিতি')
@section('page-banner', $teacher->full_name)
@section('page-banner-extra')
    <a href="{{ route('public.teachers.index') }}" class="text-white text-decoration-none">আমাদের শিক্ষকবৃন্দ</a>
    <span class="mx-2 text-white-50">/</span>
    <span class="pub-page-banner-current">{{ $teacher->full_name }}</span>
@endsection

@section('content')
<section class="pub-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="pub-panel shadow-sm border-0" style="border-radius: 16px; overflow: hidden;">
                    <div class="p-4 p-md-5 text-center text-white" style="background: linear-gradient(135deg, #0d6efd, #052c65);">
                        <div class="d-inline-block position-relative mb-3">
                            <span class="pub-teacher-avatar shadow-lg mx-auto" style="width: 120px; height: 120px; font-size: 3rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; overflow: hidden; border: 4px solid #fff; background: #e9ecef;">
                                @if ($photo = $teacher->publicPhotoUrl())
                                    <img src="{{ $photo }}" alt="{{ $teacher->full_name }}" style="width: 100%; height: 100%; object-fit: cover;">
                                @else
                                    {{ mb_substr($teacher->first_name, 0, 1) }}
                                @endif
                            </span>
                        </div>
                        <h2 class="fw-bold mb-1">{{ $teacher->full_name }}</h2>
                        <span class="badge bg-white text-primary px-3 py-2 fs-6 rounded-pill fw-semibold">
                            <i class="bi bi-briefcase me-1"></i> {{ $teacher->designation ?: 'শিক্ষক' }}
                        </span>
                    </div>

                    <div class="p-4 p-md-5 bg-white">
                        <h5 class="fw-bold text-dark border-bottom pb-3 mb-4">
                            <i class="bi bi-person-lines-fill text-primary me-2"></i> বিস্তারিত বিবরণ
                        </h5>

                        <div class="row g-4 mb-4">
                            @if ($teacher->qualification)
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 h-100">
                                        <small class="text-muted d-block mb-1">
                                            <i class="bi bi-mortarboard text-primary me-1"></i> শিক্ষাগত যোগ্যতা
                                        </small>
                                        <strong class="text-dark fs-6">{{ $teacher->qualification }}</strong>
                                    </div>
                                </div>
                            @endif

                            @if ($teacher->specialization)
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 h-100">
                                        <small class="text-muted d-block mb-1">
                                            <i class="bi bi-journal-bookmark text-primary me-1"></i> বিশেষজ্ঞতা / প্রধান বিষয়
                                        </small>
                                        <strong class="text-dark fs-6">{{ $teacher->specialization }}</strong>
                                    </div>
                                </div>
                            @endif

                            @if ($teacher->phone)
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 h-100">
                                        <small class="text-muted d-block mb-1">
                                            <i class="bi bi-telephone text-primary me-1"></i> মোবাইল নম্বর
                                        </small>
                                        <a href="tel:{{ $teacher->phone }}" class="text-primary fw-semibold text-decoration-none fs-6">
                                            {{ $teacher->phone }}
                                        </a>
                                    </div>
                                </div>
                            @endif

                            @if ($teacher->email)
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 h-100">
                                        <small class="text-muted d-block mb-1">
                                            <i class="bi bi-envelope text-primary me-1"></i> ইমেইল
                                        </small>
                                        <a href="mailto:{{ $teacher->email }}" class="text-primary fw-semibold text-decoration-none">
                                            {{ $teacher->email }}
                                        </a>
                                    </div>
                                </div>
                            @endif

                            @if ($teacher->joining_date)
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 h-100">
                                        <small class="text-muted d-block mb-1">
                                            <i class="bi bi-calendar-check text-primary me-1"></i> যোগদানের সময়কাল
                                        </small>
                                        <strong class="text-dark fs-6">{{ $teacher->joining_date->format('F Y') }}</strong>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if ($teacher->assignments && $teacher->assignments->isNotEmpty())
                            <h5 class="fw-bold text-dark border-bottom pb-3 mb-3 mt-4">
                                <i class="bi bi-book text-primary me-2"></i> দায়িত্বপ্রাপ্ত শ্রেণি ও বিষয়
                            </h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>শ্রেণি</th>
                                            <th>শাখা</th>
                                            <th>পাঠ্য বিষয়</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($teacher->assignments as $assignment)
                                            <tr>
                                                <td class="fw-semibold">{{ $assignment->schoolClass?->name ?? '—' }}</td>
                                                <td>{{ $assignment->section?->name ?? '—' }}</td>
                                                <td class="text-primary fw-semibold">{{ $assignment->subject?->name ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <div class="text-center mt-5 pt-3">
                            <a href="{{ route('public.teachers.index') }}" class="btn btn-outline-primary px-4 py-2 rounded-pill">
                                <i class="bi bi-arrow-left me-1"></i> সব শিক্ষক দেখুন
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
