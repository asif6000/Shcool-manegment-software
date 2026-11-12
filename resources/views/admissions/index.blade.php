@extends('layouts.app')

@section('title', __('Admissions'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-clipboard-check"></i>{{ __('Student information') }}</span>
            <h1 class="page-title">{{ __('Admissions') }}</h1>
            <p class="page-subtitle">{{ __('Track applications from enquiry to enrolled student. Approval creates the student record and enrollment atomically.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('students.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Students') }}</a>
            @can('admissions.manage')
                <a href="{{ route('admissions.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i>{{ __('New application') }}</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            'pending' => __('Pending'),
            'waitlisted' => __('Waitlisted'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
        ] as $key => $label)
            <div class="col-6 col-lg-3">
                <div class="ui-card py-3">
                    <div class="text-muted-2 small">{{ $label }}</div>
                    <div style="font-size:1.45rem;font-weight:700;line-height:1.1;">{{ $counts[$key] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('admissions.index') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="admission-search">{{ __('Search applications') }}</label>
                    <input id="admission-search" type="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search application, name or phone') }}">
                </div>
                <label class="visually-hidden" for="filter-admission-status">{{ __('Status') }}</label>
                <select id="filter-admission-status" name="status" class="form-select" data-auto-submit>
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (['pending' => __('Pending'), 'waitlisted' => __('Waitlisted'), 'approved' => __('Approved'), 'rejected' => __('Rejected')] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="filter-admission-session">{{ __('Session') }}</label>
                <select id="filter-admission-session" name="session_id" class="form-select" data-auto-submit>
                    <option value="">{{ __('All sessions') }}</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected((string) ($filters['session_id'] ?? '') === (string) $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('admissions.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count application|:count applications', $admissions->total(), ['count' => $admissions->total()]) }}</span>
        </div>

        @if ($admissions->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-clipboard-check"></i></div>
                <h3>{{ __('No applications found') }}</h3>
                <p>{{ __('Record a new application or change the filters.') }}</p>
                @can('admissions.manage')
                    <a href="{{ route('admissions.create') }}" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i>{{ __('New application') }}</a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Application') }}</th>
                            <th>{{ __('Applicant') }}</th>
                            <th>{{ __('Applied for') }}</th>
                            <th>{{ __('Guardian') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($admissions as $admission)
                            @php($statusClass = match ($admission->status) {
                                'approved' => 'is-success',
                                'rejected' => 'is-danger',
                                'waitlisted' => 'is-warning',
                                default => 'is-info',
                            })
                            <tr>
                                <td>
                                    <a href="{{ route('admissions.show', $admission) }}" class="fw-semibold text-decoration-none">{{ $admission->application_no }}</a>
                                    <div class="text-muted-2" style="font-size:.78rem;">{{ $admission->created_at->format('d M Y') }}</div>
                                </td>
                                <td>
                                    <div class="name fw-semibold">{{ $admission->fullName() }}</div>
                                    <div class="text-muted-2" style="font-size:.78rem;">{{ $admission->gender ? __(ucfirst($admission->gender)) : '' }} · {{ $admission->date_of_birth?->format('d M Y') }}</div>
                                    @if ($admission->user_id !== null)
                                        <span class="badge-soft mt-1" style="font-size:.66rem;"><i class="bi bi-globe1"></i>{{ __('Online application') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $admission->appliedClass?->name ?? '—' }}</div>
                                    <div class="text-muted-2" style="font-size:.78rem;">{{ $admission->academicSession?->name }}</div>
                                </td>
                                <td>
                                    <div>{{ $admission->guardian_name }}</div>
                                    <div class="text-muted-2" style="font-size:.78rem;">{{ $admission->guardian_phone }}</div>
                                </td>
                                <td>
                                    @can('admissions.manage')
                                        @if ($admission->status !== 'approved')
                                            <button type="button" class="badge-soft {{ $statusClass }} status-trigger"
                                                data-bs-toggle="modal" data-bs-target="#statusModal{{ $admission->id }}"
                                                aria-label="{{ __('Update status of application :no', ['no' => $admission->application_no]) }}">
                                                {{ __(ucfirst($admission->status)) }}<i class="bi bi-chevron-down"></i>
                                            </button>
                                        @else
                                            <span class="badge-soft {{ $statusClass }}">{{ __(ucfirst($admission->status)) }}</span>
                                        @endif
                                    @else
                                        <span class="badge-soft {{ $statusClass }}">{{ __(ucfirst($admission->status)) }}</span>
                                    @endcan
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('admissions.show', $admission) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('Review') }}" aria-label="{{ __('Review application :no', ['no' => $admission->application_no]) }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @can('admissions.manage')
                                            @if ($admission->status !== 'approved')
                                                <a href="{{ route('admissions.edit', $admission) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('Edit') }}" aria-label="{{ __('Edit application :no', ['no' => $admission->application_no]) }}">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            @endif
                                            @if (in_array($admission->status, ['pending', 'waitlisted'], true) && $admission->appliedClass?->sections->isNotEmpty())
                                                <button type="button" class="btn btn-sm btn-primary"
                                                    data-bs-toggle="modal" data-bs-target="#statusModal{{ $admission->id }}"
                                                    data-status-preset="approved"
                                                    aria-label="{{ __('Approve application :no', ['no' => $admission->application_no]) }}">
                                                    <i class="bi bi-check2-circle"></i>{{ __('Approve') }}
                                                </button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $admissions->firstItem(), 'to' => $admissions->lastItem(), 'total' => $admissions->total()]) }}</span>
                {{ $admissions->links() }}
            </div>
        @endif
    </section>

    @can('admissions.manage')
        @foreach ($admissions as $admission)
            @if ($admission->status !== 'approved')
                <div class="modal fade" id="statusModal{{ $admission->id }}" tabindex="-1" aria-labelledby="statusModalLabel{{ $admission->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <form method="POST" action="{{ route('admissions.status', $admission) }}" class="modal-content">
                            @csrf
                            <input type="hidden" name="status_for" value="{{ $admission->id }}">
                            <div class="modal-header">
                                <div>
                                    <h2 class="modal-title fs-5" id="statusModalLabel{{ $admission->id }}">{{ __('Update status') }}</h2>
                                    <div class="text-muted-2" style="font-size:.78rem;">{{ $admission->application_no }} · {{ $admission->fullName() }}</div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label" for="status-select-{{ $admission->id }}">{{ __('Status') }}<span class="required">*</span></label>
                                    <select id="status-select-{{ $admission->id }}" name="status" class="form-select @error('status') is-invalid @enderror" data-status-select required>
                                        @foreach (['pending' => __('Pending'), 'waitlisted' => __('Waitlisted'), 'rejected' => __('Rejected'), 'approved' => __('Approved')] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('status', $admission->status) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div @if (old('status', $admission->status) !== 'approved') hidden @endif data-approval-fields>
                                    <p class="text-muted-2" style="font-size:.78rem;">{{ __('Approval creates the student, guardian link and enrollment in one transaction. The application cannot be approved twice.') }}</p>
                                    <div class="mb-3">
                                        <label class="form-label" for="status-section-{{ $admission->id }}">{{ __('Section') }}<span class="required">*</span></label>
                                        @if ($admission->appliedClass?->sections->isEmpty())
                                            <div class="alert alert-warning mb-3">{{ __('Add a section to the applied class before approving.') }}</div>
                                            @can('academic.manage')
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <label class="form-label" for="new-section-name-{{ $admission->id }}">{{ __('Section name') }}</label>
                                                        <input id="new-section-name-{{ $admission->id }}" form="addSectionForm{{ $admission->id }}" name="name" type="text" maxlength="30" class="form-control @error('name') is-invalid @enderror" placeholder="A" required>
                                                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label" for="new-section-capacity-{{ $admission->id }}">{{ __('Capacity') }}</label>
                                                        <input id="new-section-capacity-{{ $admission->id }}" form="addSectionForm{{ $admission->id }}" name="capacity" type="number" min="1" max="500" class="form-control @error('capacity') is-invalid @enderror" placeholder="40">
                                                        @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                    </div>
                                                    <div class="col-12">
                                                        <button type="submit" form="addSectionForm{{ $admission->id }}" class="btn btn-light-soft">
                                                            <i class="bi bi-plus-lg"></i>{{ __('Add section') }}
                                                        </button>
                                                    </div>
                                                </div>
                                            @endcan
                                        @else
                                            <select id="status-section-{{ $admission->id }}" name="section_id" class="form-select @error('section_id') is-invalid @enderror">
                                                <option value="">{{ __('Choose a section') }}</option>
                                                @foreach ($admission->appliedClass->sections as $section)
                                                    <option value="{{ $section->id }}" @selected((string) old('section_id') === (string) $section->id)>
                                                        {{ $admission->appliedClass->name }} · {{ $section->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('section_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        @endif
                                    </div>
                                    <div>
                                        <label class="form-label" for="status-roll-{{ $admission->id }}">{{ __('Roll number') }}</label>
                                        <input id="status-roll-{{ $admission->id }}" type="number" name="roll_no" min="1" max="9999" value="{{ old('roll_no') }}" class="form-control @error('roll_no') is-invalid @enderror" placeholder="{{ __('Automatic') }}">
                                        <div class="form-hint">{{ __('Leave blank to use the next available roll.') }}</div>
                                        @error('roll_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    @if ($admission->user_id !== null)
                                        <div class="form-hint mt-3"><i class="bi bi-person-check"></i> {{ __('The online applicant account is linked automatically and can open the student portal right away.') }}</div>
                                    @endif
                                </div>

                                <div @if (old('status', $admission->status) !== 'rejected') hidden @endif data-status-reason>
                                    <label class="form-label" for="status-reason-{{ $admission->id }}">{{ __('Reason') }}</label>
                                    <textarea id="status-reason-{{ $admission->id }}" name="reason" rows="2" class="form-control @error('reason') is-invalid @enderror" placeholder="{{ __('Why is this application being rejected?') }}">{{ old('reason', $admission->rejection_reason) }}</textarea>
                                    <div class="form-hint">{{ __('The applicant sees this text on their dashboard and receives a notification. Internal notes stay private.') }}</div>
                                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat"></i>{{ __('Update status') }}</button>
                            </div>
                        </form>
                    </div>
                </div>

                @if ($admission->appliedClass?->sections->isEmpty())
                    @can('academic.manage')
                        <form method="POST" action="{{ route('admissions.sections.store', $admission) }}" id="addSectionForm{{ $admission->id }}" class="d-none">
                            @csrf
                            <input type="hidden" name="status" value="approved">
                            <input type="hidden" name="status_for" value="{{ $admission->id }}">
                        </form>
                    @endcan
                @endif
            @endif
        @endforeach
    @endcan

    @push('scripts')
        <script>
            document.querySelectorAll('[data-status-select]').forEach(function (select) {
                var modal = select.closest('.modal');
                var reason = modal ? modal.querySelector('[data-status-reason]') : null;
                var approval = modal ? modal.querySelector('[data-approval-fields]') : null;
                var initial = select.value;

                function sync() {
                    if (reason) {
                        reason.hidden = select.value !== 'rejected';
                    }

                    if (approval) {
                        approval.hidden = select.value !== 'approved';
                    }
                }

                select.addEventListener('change', sync);

                if (!modal) {
                    return;
                }

                modal.addEventListener('show.bs.modal', function (event) {
                    var preset = event.relatedTarget && event.relatedTarget.getAttribute('data-status-preset');
                    select.value = preset || initial;
                    sync();
                });
            });
        </script>
    @endpush

    @if ($errors->any() && old('status_for'))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var modal = document.getElementById('statusModal' + @json(old('status_for')));
                    if (modal) {
                        bootstrap.Modal.getOrCreateInstance(modal).show();
                    }
                });
            </script>
        @endpush
    @endif

    @if (session('reopen_status'))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var modal = document.getElementById('statusModal' + @json(session('reopen_status')));
                    if (!modal) {
                        return;
                    }

                    var select = modal.querySelector('[data-status-select]');
                    bootstrap.Modal.getOrCreateInstance(modal).show();

                    if (select) {
                        select.value = 'approved';
                        select.dispatchEvent(new Event('change'));
                    }
                });
            </script>
        @endpush
    @endif
@endsection
