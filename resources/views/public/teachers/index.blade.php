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
                        <div class="pub-teacher-card" role="button" data-bs-toggle="modal" data-bs-target="#teacherModal_{{ $teacher->id }}" title="প্রোফাইল দেখতে ক্লিক করুন" style="cursor: pointer;">
                            <span class="pub-teacher-avatar">
                                @if ($photo = $teacher->publicPhotoUrl())
                                    <img src="{{ $photo }}" alt="{{ $teacher->full_name }}" loading="lazy">
                                @else
                                    {{ mb_substr($teacher->first_name, 0, 1) }}
                                @endif
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

            {{-- Teacher Profile Modals --}}
            @foreach ($teachers as $teacher)
                <div class="modal fade" id="teacherModal_{{ $teacher->id }}" tabindex="-1" aria-labelledby="teacherModalLabel_{{ $teacher->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0d6efd, #052c65); padding: 1.25rem 1.5rem;">
                                <h5 class="modal-title d-flex align-items-center gap-2" id="teacherModalLabel_{{ $teacher->id }}">
                                    <i class="bi bi-person-badge"></i> শিক্ষক পরিচিতি
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4 text-center">
                                <div class="mb-3 d-inline-block position-relative">
                                    <span class="pub-teacher-avatar mx-auto shadow" style="width: 100px; height: 100px; font-size: 2.2rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; overflow: hidden; border: 4px solid #fff; background: #e9ecef;">
                                        @if ($photo = $teacher->publicPhotoUrl())
                                            <img src="{{ $photo }}" alt="{{ $teacher->full_name }}" style="width: 100%; height: 100%; object-fit: cover;">
                                        @else
                                            {{ mb_substr($teacher->first_name, 0, 1) }}
                                        @endif
                                    </span>
                                </div>
                                <h4 class="fw-bold mb-1 text-dark">{{ $teacher->full_name }}</h4>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 fs-6 rounded-pill fw-semibold mb-3 d-inline-block">
                                    <i class="bi bi-briefcase me-1"></i> {{ $teacher->designation ?: 'শিক্ষক' }}
                                </span>

                                <div class="text-start bg-light p-3 rounded-3 mb-4">
                                    @if ($teacher->qualification)
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-mortarboard text-primary me-2 fs-5"></i>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">শিক্ষাগত যোগ্যতা</small>
                                                <span class="fw-semibold text-dark">{{ $teacher->qualification }}</span>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($teacher->specialization)
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-journal-bookmark text-primary me-2 fs-5"></i>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">বিশেষজ্ঞতা / বিষয়</small>
                                                <span class="fw-semibold text-dark">{{ $teacher->specialization }}</span>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($teacher->phone)
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-telephone text-primary me-2 fs-5"></i>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">মোবাইল নম্বর</small>
                                                <a href="tel:{{ $teacher->phone }}" class="text-primary fw-semibold text-decoration-none">{{ $teacher->phone }}</a>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($teacher->email)
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-envelope text-primary me-2 fs-5"></i>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">ইমেইল</small>
                                                <a href="mailto:{{ $teacher->email }}" class="text-primary fw-semibold text-decoration-none">{{ $teacher->email }}</a>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($teacher->joining_date)
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-calendar-check text-primary me-2 fs-5"></i>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">যোগদানের সময়</small>
                                                <span class="fw-semibold text-dark">{{ $teacher->joining_date->format('F Y') }}</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">বন্ধ করুন</button>
                                    <a href="{{ route('public.teachers.show', $teacher) }}" class="btn btn-primary px-3">
                                        <i class="bi bi-arrow-up-right-circle me-1"></i> বিস্তারিত প্রোফাইল
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endsection
