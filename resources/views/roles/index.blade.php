@extends('layouts.app')

@section('title', __('Roles & permissions'))

@section('content')
    @php
        $roleLabels = collect($roleMeta)->mapWithKeys(fn ($meta, $name) => [$name => $meta['label']]);
        $permissionLabels = [
            'dashboard.view' => __('View dashboard'),
            'users.view' => __('View users'),
            'users.create' => __('Create users'),
            'users.update' => __('Update users'),
            'users.delete' => __('Delete users'),
            'users.reset-password' => __('Send password reset links'),
            'roles.view' => __('View roles'),
            'school.view' => __('View school profile'),
            'school.update' => __('Update school profile'),
            'audit.view' => __('View audit trail'),
        ];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-shield-lock"></i>{{ __('Access control') }}</span>
            <h1 class="page-title">{{ __('Roles & permissions') }}</h1>
            <p class="page-subtitle">{{ __('A clear, auditable matrix of what each role can do. Access is enforced on the server for every request.') }}</p>
        </div>
        <div class="page-actions">
            <span class="badge-soft {{ $canEdit ? 'is-brand' : 'is-info' }}">
                <i class="bi {{ $canEdit ? 'bi-pencil' : 'bi-eye' }}"></i>
                {{ $canEdit ? __('Editable by School Admin') : __('Read only') }}
            </span>
        </div>
    </div>

    <div class="alert alert-info mb-3">
        <i class="bi bi-info-circle-fill"></i>
        <div>
            {{ __('Changes apply immediately to every account with this role. Role permission changes are recorded in the audit trail.') }}
        </div>
    </div>

    <form method="POST" action="{{ route('roles.permissions.update') }}">
        @csrf
        @method('PUT')

        <section class="ui-card table-card">
            <div class="table-responsive">
                <table class="table role-matrix mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Permission') }}</th>
                            @foreach ($roles as $role)
                                @php($meta = $roleMeta[$role->name] ?? ['label' => $role->name, 'level' => 0])
                                <th scope="col">
                                    <span class="role-head">
                                        <strong>{{ __($meta['label']) }}</strong>
                                        <span>{{ __('Level') }} {{ $meta['level'] }} · {{ trans_choice(':count user|:count users', $role->users_count, ['count' => $role->users_count]) }}</span>
                                    </span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($catalogue as $group => $permissions)
                            <tr class="permission-group">
                                <td colspan="{{ $roles->count() + 1 }}">{{ __($group) }}</td>
                            </tr>
                            @foreach ($permissions as $permission)
                                <tr>
                                    <td>
                                        <span class="permission-name">{{ $permissionLabels[$permission] ?? \Illuminate\Support\Str::headline(str_replace('.', ' ', $permission)) }}</span>
                                        <span class="permission-code">{{ $permission }}</span>
                                    </td>
                                    @foreach ($roles as $role)
                                        @php($checked = $role->permissions->contains('name', $permission))
                                        @php($teacherOnly = in_array($permission, \App\Support\LearningPermissions::TEACHER_ONLY, true))
                                        @php($locked = $teacherOnly && $role->name !== 'teacher')
                                        <td>
                                            @if ($canEdit)
                                                <input type="hidden" name="roles[{{ $role->name }}][]" value="">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="roles[{{ $role->name }}][]"
                                                    value="{{ $permission }}"
                                                    id="perm-{{ $role->id }}-{{ str_replace('.', '-', $permission) }}"
                                                    @checked($checked && (! $teacherOnly || $role->name === 'teacher'))
                                                    @disabled($locked)
                                                    title="{{ $locked ? __('Only the teacher role can hold this permission') : '' }}"
                                                    aria-label="{{ __($roleLabels[$role->name] ?? $role->name) }} — {{ $permissionLabels[$permission] ?? $permission }}"
                                                >
                                                @if ($locked)
                                                    <span class="permission-code">{{ __('Teachers only') }}</span>
                                                @endif
                                            @else
                                                <i class="bi {{ $checked ? 'bi-check-circle-fill text-success' : 'bi-dash-circle text-muted-2' }}" style="font-size:1rem;" aria-label="{{ $checked ? __('Allowed') : __('Not allowed') }}"></i>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($canEdit)
                <div class="table-footer">
                    <span class="text-muted-2" style="font-size:.78rem;">
                        <i class="bi bi-clock-history me-1"></i>{{ __('Every change is recorded in the audit trail.') }}
                    </span>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle"></i>{{ __('Save permissions') }}
                    </button>
                </div>
            @endif
        </section>
    </form>

    <section class="ui-card p-lg mt-3">
        <div class="card-head mb-2">
            <div>
                <h2 class="card-title">{{ __('Role hierarchy') }}</h2>
                <p class="card-subtitle">{{ __('Higher levels can manage lower levels. Nobody can assign a role at or above their own level.') }}</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @foreach (collect($roleMeta)->sortByDesc('level') as $name => $meta)
                <span class="badge-soft {{ $name === 'school_admin' ? 'is-brand' : '' }}">
                    <i class="bi bi-layers"></i>{{ __($meta['label']) }} · {{ $meta['level'] }}
                </span>
            @endforeach
        </div>
    </section>
@endsection
