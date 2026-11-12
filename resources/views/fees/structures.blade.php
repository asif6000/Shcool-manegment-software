@extends('layouts.app')

@section('title', __('Class fees'))

@section('content')
    @php
        $frequencyLabels = [
            'monthly' => __('Monthly'),
            'quarterly' => __('Quarterly'),
            'half_yearly' => __('Half yearly'),
            'yearly' => __('Yearly'),
            'one_time' => __('One time'),
            'exam_based' => __('Exam based'),
            'custom' => __('Custom'),
        ];
        $grouped = $structures->groupBy(fn ($row): string => ($row->schoolClass?->name ?? __('Class').' '.$row->class_id));
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-collection"></i>{{ __('Finance') }}</span>
            <h1 class="page-title">{{ __('Class fees') }}</h1>
            <p class="page-subtitle">{{ __('What each class pays for every fee type in a session. Invoice generation reads these amounts.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('fees.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Fees & payments') }}</a>
            <a href="{{ route('fees.types.index') }}" class="btn btn-light-soft"><i class="bi bi-tags"></i>{{ __('Fee types') }}</a>
            @can('fees.manage')
                <a href="{{ route('fees.create') }}" class="btn btn-primary"><i class="bi bi-receipt-cutoff"></i>{{ __('Generate invoice') }}</a>
            @endcan
        </div>
    </div>

    @if ($sessions->isEmpty())
        <section class="ui-card p-lg">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
                <h3>{{ __('No academic session yet') }}</h3>
                <p>{{ __('Class fees are set per session. Create the academic year first under Academics.') }}</p>
            </div>
        </section>
    @else
        <div class="row g-3">
            <div class="col-lg-7">
                <section class="ui-card table-card">
                    <div class="table-toolbar">
                        <h2 class="card-title mb-0">{{ $session ? $session->name : __('All sessions') }}</h2>
                        <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count class fee|:count class fees', $structures->count(), ['count' => $structures->count()]) }}</span>
                    </div>

                    <div class="px-lg pb-3">
                        <form method="GET" action="{{ route('fees.structures.index') }}" class="d-flex gap-2 flex-wrap">
                            <label class="form-label align-self-center mb-0" for="session-filter" style="font-size:.8rem;">{{ __('Session') }}</label>
                            <select id="session-filter" name="session_id" class="form-select" style="max-width:260px;" onchange="this.form.submit()">
                                @foreach ($sessions as $option)
                                    <option value="{{ $option->id }}" @selected($session && $session->id === $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                            <noscript><button type="submit" class="btn btn-light-soft">{{ __('Go') }}</button></noscript>
                        </form>
                    </div>

                    @if ($structures->isEmpty())
                        <div class="empty-state">
                            <div class="empty-icon"><i class="bi bi-cash-stack"></i></div>
                            <h3>{{ __('Nothing is billed in this session yet') }}</h3>
                            @can('fees.manage')
                                <p>{{ __('Add a class fee on the right, or copy last year\'s amounts in one go.') }}</p>
                            @else
                                <p>{{ __('A finance admin has to set the class amounts before invoices can be raised.') }}</p>
                            @endcan
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Class') }}</th>
                                        <th scope="col">{{ __('Fee') }}</th>
                                        <th scope="col" class="text-end">{{ __('Amount') }}</th>
                                        <th scope="col">{{ __('Due day') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
                                        <th scope="col" class="text-end">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($grouped as $className => $rows)
                                        <tr>
                                            <td colspan="6" class="bg-body-tertiary fw-600" style="font-size:.78rem;">{{ $className }}</td>
                                        </tr>
                                        @foreach ($rows as $row)
                                            <tr>
                                                <td class="text-muted-2" style="font-size:.78rem;">{{ $row->schoolClass?->name }}</td>
                                                <td>
                                                    <span class="fw-600">{{ $row->feeType?->name ?? __('Fee') }}</span>
                                                    @if ($row->name && $row->name !== $row->feeType?->name)
                                                        <div class="text-muted-2" style="font-size:.74rem;">{{ $row->name }}</div>
                                                    @endif
                                                    @if ($row->feeType)
                                                        <div class="text-muted-2" style="font-size:.72rem;">
                                                            {{ $frequencyLabels[$row->feeType->frequency] ?? $row->feeType->frequency }}
                                                            @if ($row->starts_on || $row->ends_on)
                                                                · {{ $row->starts_on?->format('d M Y') }} – {{ $row->ends_on?->format('d M Y') ?: __('open') }}
                                                            @endif
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="text-end fw-600">৳ {{ number_format((float) $row->amount, 2) }}</td>
                                                <td>{{ $row->due_day ? $row->due_day.' '.__('of the month') : '—' }}</td>
                                                <td>
                                                    <span class="badge-soft {{ $row->is_active ? 'is-success' : 'is-muted' }}">
                                                        {{ $row->is_active ? __('Active') : __('Inactive') }}
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    @can('fees.manage')
                                                        <button type="button" class="icon-action" data-bs-toggle="modal" data-bs-target="#structureEdit{{ $row->id }}"
                                                                title="{{ __('Edit amount') }}" aria-label="{{ __('Edit :fee for :class', ['fee' => $row->feeType?->name ?? __('Fee'), 'class' => $row->schoolClass?->name ?? '']) }}">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <form method="POST" action="{{ route('fees.structures.toggle', $row) }}" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="icon-action"
                                                                    title="{{ $row->is_active ? __('Stop billing this fee') : __('Start billing this fee') }}"
                                                                    aria-label="{{ $row->is_active ? __('Deactivate') : __('Activate') }}">
                                                                <i class="bi {{ $row->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            @can('fees.manage')
                <div class="col-lg-5">
                    <section class="ui-card p-lg mb-3">
                        <h2 class="card-title">{{ __('Add a class fee') }}</h2>
                        <p class="text-muted-2" style="font-size:.8rem;">{{ __('One amount per class, per fee type, per session. Students in that class are billed this amount.') }}</p>
                        <form method="POST" action="{{ route('fees.structures.store') }}" class="mt-3">
                            @csrf
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label" for="st-session">{{ __('Session') }}</label>
                                    <select id="st-session" name="academic_session_id" class="form-select @error('academic_session_id') is-invalid @enderror" required>
                                        @foreach ($sessions as $option)
                                            <option value="{{ $option->id }}" @selected((int) old('academic_session_id', $session->id ?? 0) === $option->id)>{{ $option->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('academic_session_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="st-class">{{ __('Class') }}</label>
                                    <select id="st-class" name="class_id" class="form-select @error('class_id') is-invalid @enderror" required>
                                        <option value="">{{ __('Choose a class') }}</option>
                                        @foreach ($classes as $class)
                                            <option value="{{ $class->id }}" @selected((int) old('class_id') === $class->id)>{{ $class->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="st-type">{{ __('Fee type') }}</label>
                                    <select id="st-type" name="fee_type_id" class="form-select @error('fee_type_id') is-invalid @enderror" required>
                                        <option value="">{{ __('Choose a fee type') }}</option>
                                        @foreach ($feeTypes as $type)
                                            <option value="{{ $type->id }}" @selected((int) old('fee_type_id') === $type->id)>
                                                {{ $type->name }} · {{ $frequencyLabels[$type->frequency] ?? $type->frequency }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('fee_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    @if ($feeTypes->isEmpty())
                                        <div class="form-text text-danger">{{ __('Create a fee type first.') }} <a href="{{ route('fees.types.index') }}">{{ __('Fee types') }}</a></div>
                                    @endif
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="st-amount">{{ __('Amount') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text">৳</span>
                                        <input id="st-amount" type="number" step="0.01" min="0.01" max="9999999" name="amount"
                                               class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="st-due-day">{{ __('Due day of month') }}</label>
                                    <input id="st-due-day" type="number" min="1" max="31" name="due_day"
                                           class="form-control @error('due_day') is-invalid @enderror" value="{{ old('due_day') }}" placeholder="{{ __('Optional') }}">
                                    @error('due_day')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="st-starts">{{ __('Starts on') }}</label>
                                    <input id="st-starts" type="date" name="starts_on" class="form-control @error('starts_on') is-invalid @enderror" value="{{ old('starts_on') }}">
                                    @error('starts_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="st-ends">{{ __('Ends on') }}</label>
                                    <input id="st-ends" type="date" name="ends_on" class="form-control @error('ends_on') is-invalid @enderror" value="{{ old('ends_on') }}">
                                    @error('ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label" for="st-name">{{ __('Label on the invoice') }}</label>
                                <input id="st-name" type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" maxlength="100" placeholder="{{ __('Leave blank to use the fee type name') }}">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <input type="hidden" name="is_active" value="1">
                            <button type="submit" class="btn btn-primary w-100 mt-3" @disabled($feeTypes->isEmpty() || $classes->isEmpty())>
                                <i class="bi bi-plus-lg"></i>{{ __('Save class fee') }}
                            </button>
                        </form>
                    </section>

                    @if ($sessions->count() > 1)
                        <section class="ui-card p-lg">
                            <h2 class="card-title">{{ __('Copy a session') }}</h2>
                            <p class="text-muted-2" style="font-size:.8rem;">{{ __('Carry this year\'s class fees into a new session. Anything already set there is left alone.') }}</p>
                            <form method="POST" action="{{ route('fees.structures.copy') }}" class="mt-3">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label" for="copy-from">{{ __('From') }}</label>
                                        <select id="copy-from" name="from_session_id" class="form-select @error('from_session_id') is-invalid @enderror" required>
                                            @foreach ($sessions as $option)
                                                <option value="{{ $option->id }}" @selected((int) old('from_session_id', $session->id ?? 0) === $option->id)>{{ $option->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('from_session_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="copy-to">{{ __('To') }}</label>
                                        <select id="copy-to" name="to_session_id" class="form-select @error('to_session_id') is-invalid @enderror" required>
                                            <option value="">{{ __('Choose') }}</option>
                                            @foreach ($sessions as $option)
                                                <option value="{{ $option->id }}" @selected((int) old('to_session_id') === $option->id)>{{ $option->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('to_session_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-light-soft w-100 mt-3"><i class="bi bi-files"></i>{{ __('Copy class fees') }}</button>
                            </form>
                        </section>
                    @endif
                </div>
            @endcan
        </div>

        @can('fees.manage')
            @foreach ($structures as $row)
                <div class="modal fade" id="structureEdit{{ $row->id }}" tabindex="-1" aria-labelledby="structureEditLabel{{ $row->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <form method="POST" action="{{ route('fees.structures.update', $row) }}" class="modal-content">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="academic_session_id" value="{{ $row->academic_session_id }}">
                            <input type="hidden" name="class_id" value="{{ $row->class_id }}">
                            <input type="hidden" name="fee_type_id" value="{{ $row->fee_type_id }}">
                            <div class="modal-header">
                                <h2 class="modal-title fs-5" id="structureEditLabel{{ $row->id }}">
                                    {{ $row->feeType?->name ?? __('Fee') }} · {{ $row->schoolClass?->name }}
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label" for="edit-amount-{{ $row->id }}">{{ __('Amount') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text">৳</span>
                                            <input id="edit-amount-{{ $row->id }}" type="number" step="0.01" min="0.01" max="9999999"
                                                   name="amount" class="form-control" value="{{ $row->amount }}" required>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="edit-due-day-{{ $row->id }}">{{ __('Due day of month') }}</label>
                                        <input id="edit-due-day-{{ $row->id }}" type="number" min="1" max="31" name="due_day"
                                               class="form-control" value="{{ $row->due_day }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="edit-starts-{{ $row->id }}">{{ __('Starts on') }}</label>
                                        <input id="edit-starts-{{ $row->id }}" type="date" name="starts_on" class="form-control" value="{{ $row->starts_on?->format('Y-m-d') }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="edit-ends-{{ $row->id }}">{{ __('Ends on') }}</label>
                                        <input id="edit-ends-{{ $row->id }}" type="date" name="ends_on" class="form-control" value="{{ $row->ends_on?->format('Y-m-d') }}">
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label" for="edit-name-{{ $row->id }}">{{ __('Label on the invoice') }}</label>
                                    <input id="edit-name-{{ $row->id }}" type="text" name="name" class="form-control" value="{{ $row->name }}" maxlength="100">
                                </div>
                                <div class="form-check form-switch mt-3">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="edit-active-{{ $row->id }}"
                                           name="is_active" value="1" @checked($row->is_active)>
                                    <label class="form-check-label" for="edit-active-{{ $row->id }}">{{ __('Bill this fee') }}</label>
                                </div>
                                <p class="text-muted-2 mt-3 mb-0" style="font-size:.78rem;">
                                    {{ __('Invoices already issued keep the amounts they were billed with.') }}
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-primary">{{ __('Save changes') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        @endcan
    @endif
@endsection
