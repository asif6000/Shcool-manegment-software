@extends('layouts.app')

@section('title', $student->full_name)

@section('content')
    @php($guardian = $student->primaryGuardian())
    @php($current = $student->enrollments->firstWhere('status', 'active'))

    <div class="page-header">
        <div class="d-flex align-items-center gap-3">
            <span class="avatar avatar-lg">
                @if ($student->photoUrl())
                    <img src="{{ $student->photoUrl() }}" alt="{{ $student->full_name }}">
                @else
                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($student->first_name, 0, 1).\Illuminate\Support\Str::substr($student->last_name ?? '', 0, 1)) }}
                @endif
            </span>
            <div>
                <span class="page-eyebrow"><i class="bi bi-mortarboard"></i>{{ __('Student profile') }}</span>
                <h1 class="page-title mb-1">{{ $student->full_name }}</h1>
                <p class="page-subtitle mb-0">
                    {{ $student->student_id }} · {{ $student->admission_no }}
                    <span class="badge-soft {{ $student->status === 'active' ? 'is-success' : 'is-warning' }} ms-1">{{ __(ucfirst($student->status)) }}</span>
                </p>
            </div>
        </div>
        <div class="page-actions">
            <a href="{{ route('students.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Students') }}</a>
            @can('students.view')
                <a href="{{ route('students.idcards.card', $student) }}" class="btn btn-light-soft"><i class="bi bi-person-vcard"></i>{{ __('Identity card') }}</a>
            @endcan
            @can('students.update')
                <a href="{{ route('students.edit', $student) }}" class="btn btn-primary"><i class="bi bi-pencil"></i>{{ __('Edit student') }}</a>
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <section class="ui-card p-lg mb-3">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h2 class="card-title mb-0">{{ __('Personal details') }}</h2>
                    <span class="badge-soft">{{ __('ID') }} {{ $student->student_id }}</span>
                </div>
                <dl class="detail-list">
                    <div><dt>{{ __('Gender') }}</dt><dd>{{ __(ucfirst($student->gender)) }}</dd></div>
                    <div><dt>{{ __('Date of birth') }}</dt><dd>{{ $student->date_of_birth?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>{{ __('Blood group') }}</dt><dd>{{ $student->blood_group ?? '—' }}</dd></div>
                    <div><dt>{{ __('Religion') }}</dt><dd>{{ $student->religion ?? '—' }}</dd></div>
                    <div><dt>{{ __('Nationality') }}</dt><dd>{{ $student->nationality ?? '—' }}</dd></div>
                    <div><dt>{{ __('Phone') }}</dt><dd>{{ $student->phone ?? '—' }}</dd></div>
                    <div><dt>{{ __('Email') }}</dt><dd>{{ $student->email ?? '—' }}</dd></div>
                    <div><dt>{{ __('Admission date') }}</dt><dd>{{ $student->admission_date?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>{{ __('Present address') }}</dt><dd>{{ $student->present_address ?? '—' }}</dd></div>
                    <div><dt>{{ __('Permanent address') }}</dt><dd>{{ $student->permanent_address ?? '—' }}</dd></div>
                </dl>
            </section>

            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('Guardian') }}</h2>
                @if ($guardian)
                    <dl class="detail-list mt-3">
                        <div><dt>{{ __('Name') }}</dt><dd>{{ $guardian->name }}</dd></div>
                        <div><dt>{{ __('Relation') }}</dt><dd>{{ __(ucfirst($guardian->relation)) }}</dd></div>
                        <div><dt>{{ __('Phone') }}</dt><dd>{{ $guardian->phone }}</dd></div>
                        <div><dt>{{ __('Email') }}</dt><dd>{{ $guardian->email ?? '—' }}</dd></div>
                        <div><dt>{{ __('Occupation') }}</dt><dd>{{ $guardian->occupation ?? '—' }}</dd></div>
                        <div><dt>{{ __('Address') }}</dt><dd>{{ $guardian->address ?? '—' }}</dd></div>
                    </dl>
                @else
                    <p class="text-muted-2 mb-0 mt-2">{{ __('No guardian has been linked yet.') }}</p>
                @endif
            </section>
        </div>

        <div class="col-lg-7">
            <section class="ui-card table-card mb-3">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Enrollment history') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Enrollment is per academic session. Rolls are unique inside a class section.') }}</p>
                    </div>
                    @if ($current)
                        <span class="badge-soft is-success">{{ $current->academicSession?->name }} · {{ $current->schoolClass?->name }}</span>
                    @endif
                </div>

                @if ($student->enrollments->isEmpty())
                    <div class="empty-state py-4"><p class="mb-0">{{ __('This student has not been enrolled in a session yet.') }}</p></div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Session') }}</th>
                                    <th>{{ __('Class') }}</th>
                                    <th>{{ __('Roll') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($student->enrollments as $enrollment)
                                    <tr>
                                        <td>{{ $enrollment->academicSession?->name ?? '—' }}</td>
                                        <td>
                                            {{ $enrollment->schoolClass?->name ?? '—' }}
                                            <div class="text-muted-2" style="font-size:.78rem;">{{ $enrollment->section?->name ? __('Section').' '.$enrollment->section->name : __('No section') }}</div>
                                        </td>
                                        <td>{{ $enrollment->roll_no ?? '—' }}</td>
                                        <td><span class="badge-soft {{ $enrollment->status === 'active' ? 'is-success' : '' }}">{{ __(ucfirst($enrollment->status)) }}</span></td>
                                        <td class="text-end">
                                            @can('students.update')
                                                <form method="POST" action="{{ route('students.enrollments.update', [$student, $enrollment]) }}" class="d-inline-flex gap-1 align-items-center">
                                                    @csrf
                                                    @method('PUT')
                                                    <label class="visually-hidden" for="roll-{{ $enrollment->id }}">{{ __('Roll') }}</label>
                                                    <input id="roll-{{ $enrollment->id }}" type="number" name="roll_no" min="1" max="9999" value="{{ $enrollment->roll_no }}" class="form-control form-control-sm" style="width:88px;">
                                                    <label class="visually-hidden" for="status-{{ $enrollment->id }}">{{ __('Status') }}</label>
                                                    <select id="status-{{ $enrollment->id }}" name="status" class="form-select form-select-sm" style="width:130px;">
                                                        @foreach (['active' => __('Active'), 'completed' => __('Completed'), 'transferred' => __('Transferred'), 'withdrawn' => __('Withdrawn')] as $value => $label)
                                                            <option value="{{ $value }}" @selected($enrollment->status === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-light-soft" data-bs-toggle="tooltip" title="{{ __('Save') }}"><i class="bi bi-check2"></i></button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="ui-card p-lg mb-3">
                <div class="card-head">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Student portal access') }}</h2>
                        <p class="card-subtitle">{{ __('One login per student. Students only see their own records.') }}</p>
                    </div>
                    @if ($student->user)
                        <span class="badge-soft {{ $student->user->is_active ? 'is-success' : 'is-danger' }}">
                            <span class="dot"></span>{{ $student->user->is_active ? __('Active') : __('Disabled') }}
                        </span>
                    @else
                        <span class="badge-soft is-warning"><span class="dot"></span>{{ __('Not created') }}</span>
                    @endif
                </div>

                @if (session('portal_password'))
                    <div class="alert alert-warning d-flex align-items-start gap-2">
                        <i class="bi bi-key-fill"></i>
                        <div>
                            <strong>{{ __('Temporary password (shown once)') }}</strong>
                            <div><code style="font-size:1rem;">{{ session('portal_password') }}</code></div>
                            <div class="small mt-1">{{ __('Share it privately. The student must change it at first sign-in.') }}</div>
                        </div>
                    </div>
                @endif

                @if ($student->user)
                    <dl class="detail-list">
                        <div><dt>{{ __('Mobile number') }}</dt><dd>{{ $student->user->mobile_number }}</dd></div>
                        <div><dt>{{ __('Last sign-in') }}</dt><dd>{{ $student->user->last_login_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                        <div><dt>{{ __('Must change password') }}</dt><dd>{{ $student->user->must_change_password ? __('Yes') : __('No') }}</dd></div>
                    </dl>

                    <div class="d-flex flex-wrap gap-2">
                        @can('users.reset-password')
                            <form method="POST" action="{{ route('students.portal.reset', $student) }}" data-confirm="{{ __('Reset the portal password for this student?') }}">
                                @csrf
                                <button type="submit" class="btn btn-light-soft"><i class="bi bi-key"></i>{{ __('Reset password') }}</button>
                            </form>
                        @endcan
                        @can('users.update')
                            <form method="POST" action="{{ route('students.portal.status', $student) }}" data-confirm="{{ $student->user->is_active ? __('Disable portal access for this student?') : __('Enable portal access for this student?') }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $student->user->is_active ? '0' : '1' }}">
                                <button type="submit" class="btn btn-light-soft">
                                    <i class="bi {{ $student->user->is_active ? 'bi-person-dash' : 'bi-person-check' }}"></i>
                                    {{ $student->user->is_active ? __('Disable access') : __('Enable access') }}
                                </button>
                            </form>
                        @endcan
                    </div>
                @else
                    @can('users.create')
                        <form method="POST" action="{{ route('students.portal.store', $student) }}" class="row g-3">
                            @csrf
                            <div class="col-md-6">
                                <label class="form-label" for="portal-mobile">{{ __('Mobile number') }}</label>
                                <input id="portal-mobile" type="tel" name="mobile_number" value="{{ old('mobile_number', $student->phone) }}" class="form-control @error('mobile_number') is-invalid @enderror" placeholder="01XXXXXXXXX" required autocomplete="off">
                                @error('mobile_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="portal-password">{{ __('Password (optional)') }}</label>
                                <input id="portal-password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <div class="form-hint mb-2">{{ __('The student signs in with this mobile number and password. No email address is used. Leave the password blank to generate one; the student must change it at first sign-in.') }}</div>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus"></i>{{ __('Create portal account') }}</button>
                            </div>
                        </form>
                    @else
                        <p class="text-muted-2 mb-0">{{ __('Only an administrator can create portal accounts.') }}</p>
                    @endcan
                @endif
            </section>

            @can('students.update')
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('Enroll in a new session') }}</h2>
                    <p class="text-muted-2" style="font-size:.8rem;">{{ __('A student can have one enrollment per academic session.') }}</p>
                    <form method="POST" action="{{ route('students.enrollments.store', $student) }}" class="row g-3">
                        @csrf
                        <div class="col-sm-6">
                            <label class="form-label" for="enroll-session">{{ __('Session') }}</label>
                            <select id="enroll-session" name="academic_session_id" class="form-select" required>
                                <option value="">{{ __('Choose a session') }}</option>
                                @foreach ($sessions as $session)
                                    <option value="{{ $session->id }}" @selected($session->is_current)>{{ $session->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="enroll-class">{{ __('Class') }}</label>
                            <select id="enroll-class" name="class_id" class="form-select" required data-enroll-class>
                                <option value="">{{ __('Choose a class') }}</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="enroll-section">{{ __('Section') }}</label>
                            <select id="enroll-section" name="section_id" class="form-select" required data-enroll-section>
                                <option value="">{{ __('Choose a section') }}</option>
                                @foreach ($classes as $class)
                                    @foreach ($class->sections as $section)
                                        <option value="{{ $section->id }}" data-class-id="{{ $class->id }}">{{ $class->name }} · {{ $section->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="enroll-roll">{{ __('Roll number') }}</label>
                            <input id="enroll-roll" type="number" name="roll_no" min="1" max="9999" class="form-control" placeholder="{{ __('Automatic') }}">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="enroll-date">{{ __('Enrolled on') }}</label>
                            <input id="enroll-date" type="date" name="enrolled_on" class="form-control" value="{{ now()->toDateString() }}">
                        </div>
                        <div class="col-sm-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i>{{ __('Add enrollment') }}</button>
                        </div>
                    </form>
                </section>
            @endcan
        </div>
    </div>

    @can('students.delete')
        <section class="ui-card p-lg mt-3">
            <div class="card-head">
                <div>
                    <h2 class="card-title mb-1">{{ __('Account and profile lifecycle') }}</h2>
                    <p class="card-subtitle mb-0">{{ __('These actions are separate. Deleting the login or archiving the profile never removes attendance, marks, results or audit history; deleting the student only works while no such record still belongs to them.') }}</p>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-lg-6">
                    <div class="border rounded p-3 h-100">
                        <h3 class="h6">{{ __('Delete login account') }}</h3>
                        <p class="text-muted-2" style="font-size:.82rem;">
                            {{ __('Permanently removes the mobile number, password, role assignments, active sessions and API tokens. The student can no longer sign in. The student profile and every academic record stay in place.') }}
                        </p>
                        @if ($student->user)
                            <form method="POST" action="{{ route('students.login.destroy', $student) }}"
                                data-confirm="{{ __('Permanently delete this student login account? The mobile number and password stop working and active sessions end. Academic history is kept.') }}"
                                data-confirm-delete="1"
                                data-confirm-type="1"
                                data-confirm-label="{{ __('Delete Login Account') }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="permanent_delete" value="DELETE">
                                <button type="submit" class="btn btn-danger-soft">
                                    <i class="bi bi-person-x"></i>{{ __('Delete login account') }}
                                </button>
                            </form>
                        @else
                            <span class="badge-soft is-warning">{{ __('No login account') }}</span>
                        @endif
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="border rounded p-3 h-100">
                        <h3 class="h6">{{ __('Archive student profile') }}</h3>
                        <p class="text-muted-2" style="font-size:.82rem;">
                            {{ __('Moves the profile out of the active register and disables portal access without deleting the student row. Attendance, marks, results and audit history remain readable.') }}
                        </p>
                        @if ($student->status === \App\Models\Student::STATUS_ARCHIVED)
                            <span class="badge-soft is-warning">{{ __('Profile archived') }}</span>
                        @else
                            <form method="POST" action="{{ route('students.archive', $student) }}"
                                data-confirm="{{ __('Archive this student profile? The profile leaves the active register and portal access is disabled, but attendance, marks, results and audit history are kept.') }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="archive_confirmed" value="1">
                                <button type="submit" class="btn btn-light-soft">
                                    <i class="bi bi-archive"></i>{{ __('Archive student profile') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="border rounded p-3 h-100">
                        <h3 class="h6">{{ __('Permanently delete student') }}</h3>
                        <p class="text-muted-2" style="font-size:.82rem;">
                            {{ __('Removes the student from the database for good: profile, photo, documents, enrolments, attendance, fee invoices, payments, receipts, marks, results, QR cards, guardian links and the login account of their own. Other students keep their records, and the audit entry keeps only the id and the row counts.') }}
                        </p>
                        <form method="POST" action="{{ route('students.destroy', $student) }}"
                            data-confirm="{{ __('Student: :name (ID: :id)', ['name' => $student->full_name, 'id' => $student->student_id]) }}&#10;{{ __('Are you sure you want to permanently delete this student?') }}&#10;{{ __('The profile, photo, attendance, fees, payments, marks, results, documents and the login account are removed from the database. This action cannot be undone.') }}"
                            data-confirm-delete="1"
                            data-confirm-type="1"
                            data-confirm-label="{{ __('Permanently Delete') }}">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="permanent_delete" value="DELETE">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash"></i>{{ __('Permanently Delete') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    @endcan
@endsection

@push('scripts')
<script>
(function () {
    const classSelect = document.querySelector('[data-enroll-class]');
    const sectionSelect = document.querySelector('[data-enroll-section]');

    if (!classSelect || !sectionSelect) return;

    function filterSections() {
        const classId = classSelect.value;
        Array.from(sectionSelect.options).forEach(function (option) {
            if (!option.dataset.classId) return;
            const matches = option.dataset.classId === classId;
            option.hidden = !matches;
            option.disabled = !matches;
            if (!matches && option.selected) sectionSelect.value = '';
        });
    }

    classSelect.addEventListener('change', function () {
        sectionSelect.value = '';
        filterSections();
    });

    filterSections();
})();
</script>
@endpush
