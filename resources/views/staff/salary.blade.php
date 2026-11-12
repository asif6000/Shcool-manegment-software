@extends('layouts.app')

@section('title', __('Salary structures'))

@section('content')
    @php
        $initialKind = (string) old('employee_id', $editing?->employee_id) !== '' ? 'employee' : 'teacher';
        $formAction = $editing ? route('staff.salary.update', $editing) : route('staff.salary.store');
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-cash-stack"></i>{{ __('Human resources') }}</span>
            <h1 class="page-title">{{ __('Salary structures') }}</h1>
            <p class="page-subtitle">{{ __('Define basic salary and allowances per employee. Saving an active structure closes the previous one.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('staff.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Staff directory') }}</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <section class="ui-card p-lg">
                <div class="mb-3">
                    <h2 class="card-title mb-1">{{ $editing ? __('Edit salary structure') : __('Add salary structure') }}</h2>
                    <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('The gross salary is calculated from the components. A new active structure deactivates the previous one for that employee.') }}</p>
                </div>

                <form method="POST" action="{{ $formAction }}" class="row g-3">
                    @csrf
                    @if ($editing)
                        @method('PUT')
                    @endif

                    <div class="col-12">
                        <span class="form-label d-block">{{ __('Employee type') }}</span>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="staff_kind" value="teacher" id="salary-kind-teacher" data-salary-kind @checked($initialKind === 'teacher')>
                                <label class="form-check-label" for="salary-kind-teacher">{{ __('Teacher') }}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="staff_kind" value="employee" id="salary-kind-employee" data-salary-kind @checked($initialKind === 'employee')>
                                <label class="form-check-label" for="salary-kind-employee">{{ __('Staff member') }}</label>
                            </div>
                        </div>
                    </div>

                    <div class="col-12" data-staff-panel="teacher">
                        <label class="form-label" for="salary-teacher">{{ __('Teacher') }}</label>
                        <select id="salary-teacher" name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror" @disabled($initialKind !== 'teacher')>
                            <option value="">{{ __('Choose a teacher') }}</option>
                            @foreach ($teachers as $teacher)
                                <option value="{{ $teacher->id }}" @selected((string) old('teacher_id', $editing?->teacher_id) === (string) $teacher->id)>{{ $teacher->full_name }}</option>
                            @endforeach
                        </select>
                        @error('teacher_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12" data-staff-panel="employee">
                        <label class="form-label" for="salary-employee">{{ __('Staff member') }}</label>
                        <select id="salary-employee" name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" @disabled($initialKind !== 'employee')>
                            <option value="">{{ __('Choose a staff member') }}</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) old('employee_id', $editing?->employee_id) === (string) $employee->id)>{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                        @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-sm-6">
                        <label class="form-label" for="basic-salary">{{ __('Basic salary') }}</label>
                        <input id="basic-salary" type="number" step="0.01" min="0" name="basic_salary" class="form-control @error('basic_salary') is-invalid @enderror" value="{{ old('basic_salary', $editing?->basic_salary) }}" required>
                        @error('basic_salary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-sm-6">
                        <label class="form-label" for="house-allowance">{{ __('House allowance') }}</label>
                        <input id="house-allowance" type="number" step="0.01" min="0" name="house_allowance" class="form-control @error('house_allowance') is-invalid @enderror" value="{{ old('house_allowance', $editing?->house_allowance ?? 0) }}">
                        @error('house_allowance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-sm-6">
                        <label class="form-label" for="transport-allowance">{{ __('Transport allowance') }}</label>
                        <input id="transport-allowance" type="number" step="0.01" min="0" name="transport_allowance" class="form-control @error('transport_allowance') is-invalid @enderror" value="{{ old('transport_allowance', $editing?->transport_allowance ?? 0) }}">
                        @error('transport_allowance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-sm-6">
                        <label class="form-label" for="medical-allowance">{{ __('Medical allowance') }}</label>
                        <input id="medical-allowance" type="number" step="0.01" min="0" name="medical_allowance" class="form-control @error('medical_allowance') is-invalid @enderror" value="{{ old('medical_allowance', $editing?->medical_allowance ?? 0) }}">
                        @error('medical_allowance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-sm-6">
                        <label class="form-label" for="other-allowance">{{ __('Other allowance') }}</label>
                        <input id="other-allowance" type="number" step="0.01" min="0" name="other_allowance" class="form-control @error('other_allowance') is-invalid @enderror" value="{{ old('other_allowance', $editing?->other_allowance ?? 0) }}">
                        @error('other_allowance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-sm-6">
                        <label class="form-label" for="effective-from">{{ __('Effective from') }}</label>
                        <input id="effective-from" type="date" name="effective_from" class="form-control @error('effective_from') is-invalid @enderror" value="{{ old('effective_from', $editing?->effective_from?->toDateString() ?? now()->toDateString()) }}" required>
                        @error('effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-sm-6">
                        <label class="form-label" for="effective-to">{{ __('Effective to') }}</label>
                        <input id="effective-to" type="date" name="effective_to" class="form-control @error('effective_to') is-invalid @enderror" value="{{ old('effective_to', $editing?->effective_to?->toDateString()) }}">
                        <div class="form-hint">{{ __('Leave blank for open-ended.') }}</div>
                        @error('effective_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="salary-active" @checked(old('is_active', $editing?->is_active ?? true))>
                            <label class="form-check-label" for="salary-active">{{ __('Active structure') }}</label>
                        </div>
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i>{{ $editing ? __('Save changes') : __('Save structure') }}</button>
                        @if ($editing)
                            <a href="{{ route('staff.salary.index') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                        @endif
                    </div>
                </form>
            </section>
        </div>

        <div class="col-lg-7">
            <section class="ui-card table-card h-100">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Salary register') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Active structures are listed first.') }}</p>
                    </div>
                    <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count structure|:count structures', $structures->count(), ['count' => $structures->count()]) }}</span>
                </div>

                @if ($structures->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-cash-stack"></i></div>
                        <h3>{{ __('No salary structures yet') }}</h3>
                        <p>{{ __('Add the first structure using the form.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Basic') }}</th>
                                    <th>{{ __('Gross') }}</th>
                                    <th>{{ __('Effective') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($structures as $structure)
                                    <tr>
                                        <td>
                                            <div>{{ $structure->staffName() }}</div>
                                            <div class="text-muted-2" style="font-size:.75rem;">{{ $structure->teacher_id ? __('Teacher') : __('Staff member') }}</div>
                                        </td>
                                        <td>৳ {{ number_format((float) $structure->basic_salary, 2) }}</td>
                                        <td><strong>৳ {{ number_format((float) $structure->gross_salary, 2) }}</strong></td>
                                        <td>
                                            {{ $structure->effective_from?->format('d M Y') }}
                                            @if ($structure->effective_to)
                                                <div class="text-muted-2" style="font-size:.75rem;">{{ __('until') }} {{ $structure->effective_to->format('d M Y') }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge-soft {{ $structure->is_active ? 'is-success' : '' }}">{{ $structure->is_active ? __('Active') : __('Closed') }}</span>
                                        </td>
                                        <td>
                                            <div class="row-actions">
                                                <a href="{{ route('staff.salary.index', ['edit' => $structure->id]) }}" class="icon-action" title="{{ __('Edit') }}" aria-label="{{ __('Edit salary for :name', ['name' => $structure->staffName()]) }}">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="POST" action="{{ route('staff.salary.destroy', $structure) }}"
                                                      data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                                      data-confirm-delete="1"
                                                      data-confirm-label="{{ __('Permanently Delete') }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="icon-action is-danger" title="{{ __('Delete') }}" aria-label="{{ __('Delete salary structure for :name', ['name' => $structure->staffName()]) }}">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const radios = document.querySelectorAll('[data-salary-kind]');

    function applyKind() {
        const selected = document.querySelector('[data-salary-kind]:checked');
        const kind = selected ? selected.value : 'teacher';

        document.querySelectorAll('[data-staff-panel]').forEach(function (panel) {
            const select = panel.querySelector('select');
            const matches = panel.getAttribute('data-staff-panel') === kind;
            panel.hidden = !matches;

            if (select) {
                select.disabled = !matches;
                if (!matches) select.value = '';
            }
        });
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', applyKind);
    });

    applyKind();
})();
</script>
@endpush
