@extends('layouts.public')

@section('title', 'ফলাফল খুঁজুন')
@section('page-banner', 'ফলাফল')
@section('page-banner-extra')
    <span class="pub-page-banner-current">অনলাইন ফলাফল খোঁজ</span>
@endsection

@section('content')
    <section class="pub-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="pub-panel">
                        <div class="pub-panel-head pub-panel-blue">
                            <h2 class="pub-h3 mb-0"><i class="bi bi-search"></i> ফলাফল খুঁজুন</h2>
                        </div>
                        <div class="pub-panel-body">
                            <form method="GET" action="{{ route('public.results.index') }}" class="pub-result-form" id="resultSearchForm">
                                {{-- পরীক্ষা --}}
                                <div class="mb-3">
                                    <label class="pub-label" for="exam_id">পরীক্ষা</label>
                                    <select class="pub-input" id="exam_id" name="exam_id" required>
                                        <option value="">পরীক্ষা নির্বাচন করুন</option>
                                        @foreach ($exams as $exam)
                                            <option value="{{ $exam->id }}" @selected((int) old('exam_id', request('exam_id')) === $exam->id)>
                                                {{ $exam->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('exam_id')
                                        <small class="pub-field-error">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- শিক্ষাবর্ষ --}}
                                <div class="mb-3">
                                    <label class="pub-label" for="session_id">শিক্ষাবর্ষ</label>
                                    <select class="pub-input" id="session_id" name="session_id" required>
                                        <option value="">শিক্ষাবর্ষ নির্বাচন করুন</option>
                                        @foreach ($sessions as $session)
                                            <option value="{{ $session->id }}" @selected((int) old('session_id', request('session_id')) === $session->id)>
                                                {{ $session->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('session_id')
                                        <small class="pub-field-error">{{ $message }}</small>
                                    @enderror
                                </div>

                                {{-- শ্রেণি --}}
                                <div class="mb-3">
                                    <label class="pub-label" for="class_id">শ্রেণি <span style="font-size:.78rem;color:#6b7280;">(ঐচ্ছিক)</span></label>
                                    <select class="pub-input" id="class_id" name="class_id">
                                        <option value="">শ্রেণি নির্বাচন করুন</option>
                                        @foreach ($classes as $class)
                                            <option value="{{ $class->id }}"
                                                @selected((int) old('class_id', request('class_id')) === $class->id)>
                                                {{ $class->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- শাখা — AJAX দিয়ে শ্রেণি চাইলে লোড হবে --}}
                                <div class="mb-3" id="sectionWrapper"
                                     style="{{ $sections->isEmpty() && !request('class_id') ? 'display:none' : '' }}">
                                    <label class="pub-label" for="section_id">শাখা <span style="font-size:.78rem;color:#6b7280;">(ঐচ্ছিক)</span></label>
                                    <select class="pub-input" id="section_id" name="section_id">
                                        <option value="">সকল শাখা</option>
                                        @foreach ($sections as $sec)
                                            <option value="{{ $sec->id }}"
                                                @selected((int) old('section_id', request('section_id')) === $sec->id)>
                                                {{ $sec->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- রোল নম্বর --}}
                                <div class="mb-3">
                                    <label class="pub-label" for="roll_no">রোল নম্বর</label>
                                    <input class="pub-input" type="text" id="roll_no" name="roll_no"
                                           value="{{ old('roll_no', request('roll_no')) }}" placeholder="যেমন: ১২" required>
                                    @error('roll_no')
                                        <small class="pub-field-error">{{ $message }}</small>
                                    @enderror
                                </div>

                                <button class="pub-btn pub-btn-green pub-btn-block" type="submit">
                                    <i class="bi bi-search"></i> ফলাফল দেখুন
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                @if ($lookup !== null)
                    @php
                        ['exam' => $exam, 'result' => $result, 'enrollment' => $enrollment] = $lookup;
                        $student = $enrollment->student;
                    @endphp
                    <div class="col-lg-7 mt-4">
                        <div class="pub-panel pub-panel-success">
                            <div class="pub-panel-head pub-panel-green">
                                <h2 class="pub-h3 mb-0"><i class="bi bi-award"></i> {{ $exam->name }} — ফলাফল</h2>
                            </div>
                            <div class="pub-panel-body">
                                <div class="pub-result-summary">
                                    <div>
                                        <span>শিক্ষার্থীর নাম</span>
                                        <strong>{{ $student->first_name }} {{ $student->last_name }}</strong>
                                    </div>
                                    <div>
                                        <span>শ্রেণি</span>
                                        <strong>
                                            {{ $enrollment->schoolClass->name }}
                                            @if ($enrollment->section)
                                                ({{ $enrollment->section->name }})
                                            @endif
                                        </strong>
                                    </div>
                                    <div>
                                        <span>রোল</span>
                                        <strong>{{ $enrollment->roll_no }}</strong>
                                    </div>
                                    <div>
                                        <span>মোট নম্বর</span>
                                        <strong>{{ $result->total_obtained }} / {{ $result->total_full_marks }}</strong>
                                    </div>
                                    <div>
                                        <span>জিপিএ</span>
                                        <strong>{{ $result->gpa }}</strong>
                                    </div>
                                    <div>
                                        <span>লেটার গ্রেড</span>
                                        <strong>{{ $result->grade_letter }}</strong>
                                    </div>
                                </div>

                                <div class="text-center mt-3">
                                    <a class="pub-btn pub-btn-soft" href="{{ route('public.results.index') }}">
                                        <i class="bi bi-arrow-left"></i> আবার খুঁজুন
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const classSelect   = document.getElementById('class_id');
    const sectionSelect = document.getElementById('section_id');
    const sectionWrapper = document.getElementById('sectionWrapper');

    if (!classSelect || !sectionSelect) return;

    const sectionsUrl = '{{ route('public.results.sections') }}';

    classSelect.addEventListener('change', function () {
        const classId = this.value;

        // Reset section dropdown
        sectionSelect.innerHTML = '<option value="">সকল শাখা</option>';

        if (!classId) {
            sectionWrapper.style.display = 'none';
            return;
        }

        fetch(sectionsUrl + '?class_id=' + encodeURIComponent(classId))
            .then(function (res) { return res.json(); })
            .then(function (sections) {
                if (!sections || sections.length === 0) {
                    sectionWrapper.style.display = 'none';
                    return;
                }
                sections.forEach(function (sec) {
                    const opt = document.createElement('option');
                    opt.value = sec.id;
                    opt.textContent = sec.name;
                    sectionSelect.appendChild(opt);
                });
                sectionWrapper.style.display = '';
            })
            .catch(function () {
                sectionWrapper.style.display = 'none';
            });
    });
})();
</script>
@endpush
