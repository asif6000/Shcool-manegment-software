@extends('layouts.app')

@section('title', __('Fee types'))

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
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-tags"></i>{{ __('Finance') }}</span>
            <h1 class="page-title">{{ __('Fee types') }}</h1>
            <p class="page-subtitle">{{ __('The catalogue of things the school bills for. Setting an amount per class happens on the class fees page.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('fees.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Fees & payments') }}</a>
            <a href="{{ route('fees.structures.index') }}" class="btn btn-light-soft"><i class="bi bi-collection"></i>{{ __('Class fees') }}</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}</div>
    @endif

    @error('name')
        <div class="alert alert-danger"><i class="bi bi-x-circle"></i> {{ $message }}</div>
    @enderror

    <div class="row g-3">
        <div class="col-lg-7">
            <section class="ui-card table-card">
                <div class="table-toolbar">
                    <h2 class="card-title mb-0">{{ __('Fee catalogue') }}</h2>
                    <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count fee type|:count fee types', $feeTypes->count(), ['count' => $feeTypes->count()]) }}</span>
                </div>

                <div class="px-lg pb-3">
                    <form method="GET" action="{{ route('fees.types.index') }}" class="d-flex gap-2">
                        <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('Search by name') }}" aria-label="{{ __('Search fee types') }}">
                        <button type="submit" class="btn btn-light-soft"><i class="bi bi-search"></i>{{ __('Search') }}</button>
                        @if ($search !== '')
                            <a href="{{ route('fees.types.index') }}" class="btn btn-light-soft">{{ __('Clear') }}</a>
                        @endif
                    </form>
                </div>

                @if ($feeTypes->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-tags"></i></div>
                        <h3>{{ __('No fee types yet') }}</h3>
                        <p>{{ __('Create one such as Monthly Tuition or Exam Fee, then set the amount for each class.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Fee type') }}</th>
                                    <th scope="col">{{ __('Charged') }}</th>
                                    <th scope="col" class="text-end">{{ __('Classes') }}</th>
                                    <th scope="col" class="text-end">{{ __('Students') }}</th>
                                    <th scope="col">{{ __('Status') }}</th>
                                    <th scope="col" class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($feeTypes as $type)
                                    @php
                                        $usage = $type->structures_count + $type->assignments_count
                                            + $type->invoice_items_count + $type->payments_count
                                            + $type->discount_rules_count + $type->invoice_discounts_count;
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="fw-600">{{ $type->name }}</span>
                                        </td>
                                        <td>
                                            <span class="badge-soft is-muted">{{ $frequencyLabels[$type->frequency] ?? $type->frequency }}</span>
                                        </td>
                                        <td class="text-end">{{ number_format($type->structures_count) }}</td>
                                        <td class="text-end">{{ number_format($type->assignments_count) }}</td>
                                        <td>
                                            <span class="badge-soft {{ $type->is_active ? 'is-success' : 'is-muted' }}">
                                                {{ $type->is_active ? __('Active') : __('Inactive') }}
                                            </span>
                                            @if ($usage > 0)
                                                <div class="text-muted-2" style="font-size:.72rem;">{{ __('Used by :count record(s)', ['count' => $usage]) }}</div>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @can('fees.manage')
                                                <button type="button" class="icon-action" data-bs-toggle="modal" data-bs-target="#feeTypeEdit{{ $type->id }}"
                                                        title="{{ __('Edit fee type') }}" aria-label="{{ __('Edit :name', ['name' => $type->name]) }}">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <form method="POST" action="{{ route('fees.types.toggle', $type) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="icon-action"
                                                            title="{{ $type->is_active ? __('Deactivate') : __('Activate') }}"
                                                            aria-label="{{ $type->is_active ? __('Deactivate :name' , ['name' => $type->name]) : __('Activate :name', ['name' => $type->name]) }}">
                                                        <i class="bi {{ $type->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('fees.types.destroy', $type) }}" class="d-inline"
                                                      data-confirm="{{ __('Delete :name for good? Only a fee type with no class fee, no student setup, no invoice and no payment can be deleted.', ['name' => $type->name]) }}"
                                                      data-confirm-delete="1"
                                                      data-confirm-type="1"
                                                      data-confirm-label="{{ __('Permanently Delete') }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="icon-action text-danger"
                                                            title="{{ __('Delete fee type') }}" aria-label="{{ __('Delete :name', ['name' => $type->name]) }}">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
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
        </div>

        @can('fees.manage')
            <div class="col-lg-5">
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('Add a fee type') }}</h2>
                    <p class="text-muted-2" style="font-size:.8rem;">{{ __('Type the name the school bills by. Nothing else is needed.') }}</p>
                    <form method="POST" action="{{ route('fees.types.store') }}" class="mt-3">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="fee-type-name">{{ __('Fee name') }}</label>
                            <input id="fee-type-name" type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" maxlength="80" placeholder="{{ __('Monthly Tuition') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i>{{ __('Create fee type') }}</button>
                    </form>
                </section>
            </div>
        @endcan
    </div>

    @can('fees.manage')
        @foreach ($feeTypes as $type)
            <div class="modal fade" id="feeTypeEdit{{ $type->id }}" tabindex="-1" aria-labelledby="feeTypeEditLabel{{ $type->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('fees.types.update', $type) }}" class="modal-content">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="feeTypeEditLabel{{ $type->id }}">{{ __('Edit fee type') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="edit-name-{{ $type->id }}">{{ __('Fee name') }}</label>
                                <input id="edit-name-{{ $type->id }}" type="text" name="name" class="form-control" value="{{ $type->name }}" maxlength="80" required>
                            </div>
                            <p class="text-muted-2 mb-0" style="font-size:.78rem;">
                                {{ __('The new name appears everywhere this fee is listed. Invoices already issued keep the amounts they were billed with.') }}
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
@endsection
