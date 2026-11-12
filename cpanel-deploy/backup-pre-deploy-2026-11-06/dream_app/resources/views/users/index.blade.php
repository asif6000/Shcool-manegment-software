@extends('layouts.app')

@section('title', __('Users'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-people"></i>{{ __('Access control') }}</span>
            <h1 class="page-title">{{ __('Users') }}</h1>
            <p class="page-subtitle">{{ __('Manage the people who can sign in to your school workspace, their roles and account status.') }}</p>
        </div>
        @can('create', \App\Models\User::class)
            <div class="page-actions">
                <a href="{{ route('users.create') }}" class="btn btn-primary">
                    <i class="bi bi-person-plus"></i>{{ __('Add user') }}
                </a>
            </div>
        @endcan
    </div>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('users.index') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="user-search">{{ __('Search users') }}</label>
                    <input id="user-search" type="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search name, email or phone') }}">
                </div>
                <label class="visually-hidden" for="filter-role">{{ __('Role') }}</label>
                <select id="filter-role" name="role" class="form-select" data-auto-submit>
                    <option value="">{{ __('All roles') }}</option>
                    @foreach ($roles as $name => $meta)
                        <option value="{{ $name }}" @selected(($filters['role'] ?? '') === $name)>{{ __($meta['label']) }}</option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="filter-status">{{ __('Status') }}</label>
                <select id="filter-status" name="status" class="form-select" data-auto-submit>
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Active') }}</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('Inactive') }}</option>
                </select>
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('users.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count user|:count users', $users->total(), ['count' => $users->total()]) }}</span>
        </div>

        @if ($users->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-person-lines-fill"></i></div>
                <h3>{{ __('No users found') }}</h3>
                <p>{{ __('Try a different search or filter, or add a new user to get started.') }}</p>
                @can('create', \App\Models\User::class)
                    <a href="{{ route('users.create') }}" class="btn btn-primary mt-3"><i class="bi bi-person-plus"></i>{{ __('Add user') }}</a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('User') }}</th>
                            <th scope="col">{{ __('Role') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col">{{ __('Last sign-in') }}</th>
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            @php($roleName = $user->getRoleNames()->first())
                            @php($roleClass = match ($roleName) {
                                'school_admin' => 'is-brand',
                                'principal' => 'is-info',
                                'accountant' => 'is-warning',
                                'teacher' => 'is-success',
                                default => '',
                            })
                            <tr>
                                <td>
                                    <div class="cell-person">
                                        <span class="avatar">
                                            @if ($user->avatarUrl())
                                                <img src="{{ $user->avatarUrl() }}" alt="">
                                            @else
                                                {{ $user->initials() }}
                                            @endif
                                        </span>
                                        <span>
                                            <span class="name">{{ $user->name }}</span>
                                            <span class="email">{{ $user->email }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-soft {{ $roleClass }}">{{ $user->primaryRoleLabel() }}</span>
                                    @if ($user->must_change_password)
                                        <span class="badge-soft is-warning ms-1" title="{{ __('Temporary password has not been changed') }}">
                                            <i class="bi bi-key"></i>{{ __('Temp password') }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if ($user->is_active)
                                        <span class="badge-soft is-success"><span class="dot"></span>{{ __('Active') }}</span>
                                    @else
                                        <span class="badge-soft is-danger"><span class="dot"></span>{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($user->last_login_at)
                                        <span data-bs-toggle="tooltip" title="{{ $user->last_login_at->format('j M Y, h:i A') }}">
                                            {{ $user->last_login_at->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-muted-2">{{ __('Never') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="row-actions">
                                        @if ($user->is(auth()->user()))
                                            <span class="text-muted-2" style="font-size:.78rem;">{{ __('You') }}</span>
                                        @else
                                        @can('update', $user)
                                            <a href="{{ route('users.edit', $user) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('Edit user') }}" aria-label="{{ __('Edit :name', ['name' => $user->name]) }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan
                                        @can('changeStatus', $user)
                                            <form method="POST" action="{{ route('users.status', $user) }}" data-confirm="{{ $user->is_active ? __('Deactivate :name? They will be signed out immediately.', ['name' => $user->name]) : __('Activate :name?', ['name' => $user->name]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="icon-action" data-bs-toggle="tooltip" title="{{ $user->is_active ? __('Deactivate') : __('Activate') }}" aria-label="{{ $user->is_active ? __('Deactivate :name', ['name' => $user->name]) : __('Activate :name', ['name' => $user->name]) }}">
                                                    <i class="bi {{ $user->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                                </button>
                                            </form>
                                        @endcan
                                        @can('sendResetLink', $user)
                                            <form method="POST" action="{{ route('users.reset-link', $user) }}" data-confirm="{{ __('Send a password reset link to :email?', ['email' => $user->email]) }}">
                                                @csrf
                                                <button type="submit" class="icon-action" data-bs-toggle="tooltip" title="{{ __('Send reset link') }}" aria-label="{{ __('Send password reset link') }}">
                                                    <i class="bi bi-envelope-arrow-up"></i>
                                                </button>
                                            </form>
                                        @endcan
                                        @can('delete', $user)
                                            <form method="POST" action="{{ route('users.destroy', $user) }}"
                                                  data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                                  data-confirm-delete="1" data-confirm-type="1"
                                                  data-confirm-label="{{ __('Permanently Delete') }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="permanent_delete" value="DELETE">
                                                <button type="submit" class="icon-action is-danger" data-bs-toggle="tooltip" title="{{ __('Permanently Delete') }}" aria-label="{{ __('Delete :name', ['name' => $user->name]) }}">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $users->firstItem(), 'to' => $users->lastItem(), 'total' => $users->total()]) }}</span>
                {{ $users->links() }}
            </div>
        @endif
    </section>
@endsection
