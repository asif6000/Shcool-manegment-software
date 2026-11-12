@extends('layouts.app')

@section('title', __('Expense categories'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-tags"></i>{{ __('Accounts') }}</span>
            <h1 class="page-title">{{ __('Expense categories') }}</h1>
            <p class="page-subtitle">{{ __('Group expenses so reports and budgets stay readable. Inactive categories stay on old records.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('expenses.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Expenses') }}</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <section class="ui-card table-card">
                <div class="table-toolbar">
                    <h2 class="card-title mb-0">{{ __('Category list') }}</h2>
                    <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count category|:count categories', $categories->count(), ['count' => $categories->count()]) }}</span>
                </div>

                @if ($categories->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-tags"></i></div>
                        <h3>{{ __('No categories yet') }}</h3>
                        <p>{{ __('Add a category such as Utilities, Transport or Stationery before recording expenses.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Category') }}</th>
                                    <th scope="col">{{ __('Code') }}</th>
                                    <th scope="col" class="text-end">{{ __('Expenses') }}</th>
                                    <th scope="col">{{ __('Status') }}</th>
                                    <th scope="col" class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($categories as $category)
                                    <tr>
                                        <td>
                                            <span class="fw-600">{{ $category->name }}</span>
                                            @if ($category->description)
                                                <div class="text-muted-2" style="font-size:.76rem;">{{ $category->description }}</div>
                                            @endif
                                        </td>
                                        <td><code>{{ $category->code }}</code></td>
                                        <td class="text-end">{{ number_format($category->expenses_count) }}</td>
                                        <td>
                                            <span class="badge-soft {{ $category->is_active ? 'is-success' : 'is-muted' }}">
                                                {{ $category->is_active ? __('Active') : __('Inactive') }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            @can('expenses.manage')
                                                <button type="button" class="icon-action" data-bs-toggle="modal" data-bs-target="#categoryEdit{{ $category->id }}"
                                                        title="{{ __('Edit category') }}" aria-label="{{ __('Edit :name', ['name' => $category->name]) }}">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
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

        @can('expenses.manage')
            <div class="col-lg-5">
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('Add a category') }}</h2>
                    <form method="POST" action="{{ route('expenses.categories.store') }}" class="mt-3">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="category-name">{{ __('Name') }}</label>
                            <input id="category-name" type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" maxlength="100" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="category-code">{{ __('Code') }}</label>
                            <input id="category-code" type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code') }}" maxlength="20" placeholder="UTIL" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="category-description">{{ __('Description') }}</label>
                            <textarea id="category-description" name="description" class="form-control @error('description') is-invalid @enderror"
                                      rows="2" maxlength="255">{{ old('description') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <input type="hidden" name="is_active" value="1">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i>{{ __('Create category') }}</button>
                    </form>
                </section>
            </div>
        @endcan
    </div>

    @can('expenses.manage')
        @foreach ($categories as $category)
            <div class="modal fade" id="categoryEdit{{ $category->id }}" tabindex="-1" aria-labelledby="categoryEditLabel{{ $category->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('expenses.categories.update', $category) }}" class="modal-content">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="categoryEditLabel{{ $category->id }}">{{ __('Edit category') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="edit-name-{{ $category->id }}">{{ __('Name') }}</label>
                                <input id="edit-name-{{ $category->id }}" type="text" name="name" class="form-control"
                                       value="{{ $category->name }}" maxlength="100" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="edit-code-{{ $category->id }}">{{ __('Code') }}</label>
                                <input id="edit-code-{{ $category->id }}" type="text" name="code" class="form-control"
                                       value="{{ $category->code }}" maxlength="20" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="edit-description-{{ $category->id }}">{{ __('Description') }}</label>
                                <textarea id="edit-description-{{ $category->id }}" name="description" class="form-control" rows="2" maxlength="255">{{ $category->description }}</textarea>
                            </div>
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="edit-active-{{ $category->id }}"
                                       name="is_active" value="1" @checked($category->is_active)>
                                <label class="form-check-label" for="edit-active-{{ $category->id }}">{{ __('Active for new expenses') }}</label>
                            </div>
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
