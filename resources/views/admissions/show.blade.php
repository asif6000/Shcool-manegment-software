@extends('layouts.app')

@section('title', $admission->application_no)

@section('content')
    @php($statusClass = match ($admission->status) {
        'approved' => 'is-success',
        'rejected' => 'is-danger',
        'waitlisted' => 'is-warning',
        default => 'is-info',
    })

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-clipboard-check"></i>{{ __('Admission application') }}</span>
            <h1 class="page-title">{{ $admission->fullName() }}</h1>
            <p class="page-subtitle">
                {{ $admission->application_no }}
                <span class="badge-soft {{ $statusClass }} ms-1">{{ __(ucfirst($admission->status)) }}</span>
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('admissions.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Admissions') }}</a>
            @can('admissions.manage')
                @if ($admission->status !== 'approved')
                    <a href="{{ route('admissions.edit', $admission) }}" class="btn btn-light-soft"><i class="bi bi-pencil"></i>{{ __('Edit') }}</a>
                @endif
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <section class="ui-card p-lg mb-3">
                <h2 class="card-title">{{ __('Application details') }}</h2>
                <dl class="detail-list mt-2">
                    <div><dt>{{ __('Applied for') }}</dt><dd>{{ $admission->appliedClass?->name }} · {{ $admission->academicSession?->name }}</dd></div>
                    <div><dt>{{ __('Gender') }}</dt><dd>{{ __(ucfirst($admission->gender)) }}</dd></div>
                    <div><dt>{{ __('Date of birth') }}</dt><dd>{{ $admission->date_of_birth?->format('d M Y') }}</dd></div>
                    <div><dt>{{ __('Guardian') }}</dt><dd>{{ $admission->guardian_name }} · {{ $admission->guardian_phone }}</dd></div>
                    <div><dt>{{ __('Guardian email') }}</dt><dd>{{ $admission->guardian_email ?? '—' }}</dd></div>
                    <div><dt>{{ __('Address') }}</dt><dd>{{ $admission->address ?? '—' }}</dd></div>
                    <div><dt>{{ __('Previous school') }}</dt><dd>{{ $admission->previous_school ?? '—' }}</dd></div>
                    <div><dt>{{ __('Received') }}</dt><dd>{{ $admission->created_at->format('d M Y, h:i A') }}</dd></div>
                    <div>
                        <dt>{{ __('Submitted by') }}</dt>
                        <dd>
                            @if ($admission->user)
                                {{ $admission->user->name }} · {{ $admission->user->email }}
                                <div class="text-muted-2" style="font-size:.76rem;">{{ __('Online applicant account') }}</div>
                            @else
                                {{ __('School office (walk-in)') }}
                            @endif
                        </dd>
                    </div>
                    @if ($admission->hasPhoto())
                        <div>
                            <dt>{{ __('Photo') }}</dt>
                            <dd><img src="{{ route('admissions.photo', $admission) }}" alt="{{ __('Uploaded photo') }}" style="width:96px;height:96px;object-fit:cover;border-radius:10px;"></dd>
                        </div>
                    @endif
                    <div><dt>{{ __('Rejection reason') }}</dt><dd>{{ $admission->rejection_reason ?? '—' }}</dd></div>
                    <div><dt>{{ __('Internal notes') }}</dt><dd>{{ $admission->notes ?? '—' }}</dd></div>
                </dl>
            </section>

            @if ($admission->status === 'approved' && $admission->student)
                <section class="ui-card p-lg">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h2 class="card-title mb-1">{{ __('Enrolled student') }}</h2>
                            <p class="text-muted-2 mb-0">{{ $admission->student->full_name }} · {{ $admission->student->student_id }}</p>
                        </div>
                        <a href="{{ route('students.show', $admission->student) }}" class="btn btn-primary"><i class="bi bi-person-badge"></i>{{ __('Open student profile') }}</a>
                    </div>
                </section>
            @endif
        </div>

        <div class="col-lg-5">
            @can('admissions.manage')
                @if (! in_array($admission->status, ['approved', 'rejected'], true))
                    <section class="ui-card p-lg mb-3">
                        <h2 class="card-title">{{ __('Approve and enroll') }}</h2>
                        <p class="text-muted-2" style="font-size:.8rem;">{{ __('Approval creates the student, guardian link and enrollment in one transaction. The application cannot be approved twice.') }}</p>
                        @if ($admission->appliedClass?->sections->isEmpty())
                            <div class="alert alert-warning mb-3">{{ __('Add a section to the applied class before approving.') }}</div>
                            @can('academic.manage')
                                <form method="POST" action="{{ route('admissions.sections.store', $admission) }}" class="row g-3">
                                    @csrf
                                    <div class="col-sm-7">
                                        <label for="new-section-name" class="form-label">{{ __('Section name') }}<span class="required">*</span></label>
                                        <input id="new-section-name" name="name" type="text" maxlength="30" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="A" required>
                                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-sm-5">
                                        <label for="new-section-capacity" class="form-label">{{ __('Capacity') }}</label>
                                        <input id="new-section-capacity" name="capacity" type="number" min="1" max="500" class="form-control @error('capacity') is-invalid @enderror" value="{{ old('capacity') }}" placeholder="40">
                                        @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-light-soft"><i class="bi bi-plus-lg"></i>{{ __('Add section') }}</button>
                                    </div>
                                </form>
                            @endcan
                        @else
                            <form method="POST" action="{{ route('admissions.approve', $admission) }}" class="row g-3">
                                @csrf
                                <div class="col-12">
                                    <label for="approve-section" class="form-label">{{ __('Section') }}<span class="required">*</span></label>
                                    <select id="approve-section" name="section_id" class="form-select @error('section_id') is-invalid @enderror" required>
                                        <option value="">{{ __('Choose a section') }}</option>
                                        @foreach ($admission->appliedClass->sections as $section)
                                            <option value="{{ $section->id }}" @selected((string) old('section_id') === (string) $section->id)>
                                                {{ $admission->appliedClass->name }} · {{ $section->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('section_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label for="approve-roll" class="form-label">{{ __('Roll number') }}</label>
                                    <input id="approve-roll" type="number" name="roll_no" min="1" max="9999" value="{{ old('roll_no') }}" class="form-control @error('roll_no') is-invalid @enderror" placeholder="{{ __('Automatic') }}">
                                    <div class="form-hint">{{ __('Leave blank to use the next available roll.') }}</div>
                                    @error('roll_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2-circle"></i>{{ __('Approve and create student') }}</button>
                                </div>
                            </form>
                        @endif
                    </section>

                    <section class="ui-card p-lg">
                        <h2 class="card-title">{{ __('Reject application') }}</h2>
                        <form method="POST" action="{{ route('admissions.reject', $admission) }}" class="row g-3" data-confirm="{{ __('Reject application :no?', ['no' => $admission->application_no]) }}">
                            @csrf
                            <div class="col-12">
                                <label for="reject-reason" class="form-label">{{ __('Reason') }}</label>
                                <textarea id="reject-reason" name="reason" rows="2" class="form-control @error('reason') is-invalid @enderror" placeholder="{{ __('Why is this application being rejected?') }}"></textarea>
                                <div class="form-hint">{{ __('The applicant sees this text on their dashboard and receives a notification. Internal notes stay private.') }}</div>
                                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-x-circle"></i>{{ __('Reject application') }}</button>
                            </div>
                        </form>
                    </section>
                @elseif ($admission->status === 'approved')
                    <section class="ui-card p-lg">
                        <h2 class="card-title">{{ __('Decision') }}</h2>
                        <dl class="detail-list mt-2">
                            <div><dt>{{ __('Status') }}</dt><dd><span class="badge-soft is-success">{{ __('Approved') }}</span></dd></div>
                            <div><dt>{{ __('Reviewed by') }}</dt><dd>{{ $admission->reviewer?->name ?? '—' }}</dd></div>
                            <div><dt>{{ __('Reviewed at') }}</dt><dd>{{ $admission->reviewed_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                        </dl>
                    </section>
                @else
                    <section class="ui-card p-lg">
                        <h2 class="card-title">{{ __('Decision') }}</h2>
                        <dl class="detail-list mt-2">
                            <div><dt>{{ __('Status') }}</dt><dd><span class="badge-soft is-danger">{{ __('Rejected') }}</span></dd></div>
                            <div><dt>{{ __('Reviewed by') }}</dt><dd>{{ $admission->reviewer?->name ?? '—' }}</dd></div>
                            <div><dt>{{ __('Reviewed at') }}</dt><dd>{{ $admission->reviewed_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                        </dl>
                    </section>
                @endif
            @endcan
        </div>
    </div>
@endsection
