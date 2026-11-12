@extends('layouts.app')

@section('title', __('Teachers & staff'))

@section('content')
    @php
        $isTeacher = $type === 'teachers';
        $typeLabel = $isTeacher ? __('Teachers') : __('Staff members');
        $createRoute = $isTeacher ? route('staff.teachers.create') : route('staff.employees.create');
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-workspace"></i>{{ __('Human resources') }}</span>
            <h1 class="page-title">{{ __('Teachers & staff') }}</h1>
            <p class="page-subtitle">{{ __('Employee records, leave and salary structures for this school. Codes are generated per school when left blank.') }}</p>
        </div>
        <div class="page-actions">
            @can('leave.view')
                <a href="{{ route('staff.leaves.index') }}" class="btn btn-light-soft">
                    <i class="bi bi-calendar2-week"></i>{{ __('Leave') }}
                </a>
            @endcan
            @can('salary.manage')
                <a href="{{ route('staff.salary.index') }}" class="btn btn-light-soft">
                    <i class="bi bi-cash-stack"></i>{{ __('Salary structures') }}
                </a>
            @endcan
            @can('staff.create')
                <a href="{{ $createRoute }}" class="btn btn-primary">
                    <i class="bi bi-person-plus"></i>{{ $isTeacher ? __('Add teacher') : __('Add staff member') }}
                </a>
            @endcan
        </div>
    </div>

    <ul class="nav nav-pills app-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $isTeacher ? 'active' : '' }}" href="{{ route('staff.index', ['type' => 'teachers']) }}">
                {{ __('Teachers') }} <span class="badge-soft ms-1">{{ $counts['teachers'] }}</span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ! $isTeacher ? 'active' : '' }}" href="{{ route('staff.index', ['type' => 'employees']) }}">
                {{ __('Staff members') }} <span class="badge-soft ms-1">{{ $counts['employees'] }}</span>
            </a>
        </li>
    </ul>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('staff.index') }}" class="filter-form" role="search">
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="staff-search">{{ __('Search :type', ['type' => $typeLabel]) }}</label>
                    <input id="staff-search" type="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search name, code, phone or designation') }}">
                </div>
                <label class="visually-hidden" for="staff-status">{{ __('Status') }}</label>
                <select id="staff-status" name="status" class="form-select" data-auto-submit>
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (['active' => __('Active'), 'inactive' => __('Inactive'), 'resigned' => __('Resigned')] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('staff.index', ['type' => $type]) }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count person|:count people', $staff->total(), ['count' => $staff->total()]) }}</span>
        </div>

        @if ($staff->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-person-workspace"></i></div>
                <h3>{{ __('No :type found', ['type' => \Illuminate\Support\Str::lower($typeLabel)]) }}</h3>
                <p>{{ __('Adjust the filters or add the first record for this school.') }}</p>
                @can('staff.create')
                    <a href="{{ $createRoute }}" class="btn btn-primary mt-3"><i class="bi bi-person-plus"></i>{{ $isTeacher ? __('Add teacher') : __('Add staff member') }}</a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Name') }}</th>
                            <th scope="col">{{ __('Designation') }}</th>
                            <th scope="col">{{ __('Contact') }}</th>
                            <th scope="col">{{ __('Joined') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($staff as $person)
                            <tr>
                                <td>
                                    <div class="cell-person">
                                        <span class="avatar">
                                            @if ($person->photoUrl())
                                                <img src="{{ $person->photoUrl() }}" alt="">
                                            @else
                                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($person->first_name, 0, 1).\Illuminate\Support\Str::substr($person->last_name ?? '', 0, 1)) }}
                                            @endif
                                        </span>
                                        <span>
                                            <a href="{{ route('staff.'.$type.'.show', $person) }}" class="name text-decoration-none">{{ $person->full_name }}</a>
                                            <span class="email">{{ $person->employee_code }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div>{{ $person->designation }}</div>
                                    <div class="text-muted-2" style="font-size:.78rem;">
                                        {{ $isTeacher ? ($person->specialization ?: __('No specialization')) : ($person->department ?: __('No department')) }}
                                    </div>
                                </td>
                                <td>
                                    <div>{{ $person->phone }}</div>
                                    <div class="text-muted-2" style="font-size:.78rem;">{{ $person->email ?: '—' }}</div>
                                </td>
                                <td>{{ $person->joining_date?->format('d M Y') }}</td>
                                <td>
                                    <span class="badge-soft {{ $person->status === 'active' ? 'is-success' : ($person->status === 'inactive' ? 'is-warning' : 'is-danger') }}">
                                        {{ __(ucfirst($person->status)) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('staff.'.$type.'.show', $person) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('View profile') }}" aria-label="{{ __('View :name', ['name' => $person->full_name]) }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @can('staff.update')
                                            <a href="{{ route('staff.'.$type.'.edit', $person) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('Edit') }}" aria-label="{{ __('Edit :name', ['name' => $person->full_name]) }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan
                                        @can('staff.delete')
                                            <form method="POST" action="{{ route('staff.'.$type.'.destroy', $person) }}"
                                                  data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                                  data-confirm-delete="1" data-confirm-type="1"
                                                  data-confirm-label="{{ __('Permanently Delete') }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="permanent_delete" value="DELETE">
                                                <button type="submit" class="icon-action is-danger" data-bs-toggle="tooltip" title="{{ __('Permanently Delete') }}" aria-label="{{ __('Delete :name', ['name' => $person->full_name]) }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $staff->firstItem(), 'to' => $staff->lastItem(), 'total' => $staff->total()]) }}</span>
                {{ $staff->links() }}
            </div>
        @endif
    </section>
@endsection
