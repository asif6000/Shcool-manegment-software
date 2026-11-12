@extends('layouts.app')

@section('title', __('Search'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-search"></i>{{ __('Workspace search') }}</span>
            <h1 class="page-title">{{ __('Search') }}</h1>
            <p class="page-subtitle">{{ __('Find people in your school workspace. Results respect your role permissions.') }}</p>
        </div>
    </div>

    <section class="ui-card p-lg mb-3">
        <form method="GET" action="{{ route('search') }}" class="d-flex gap-2 flex-wrap" role="search">
            <div class="flex-grow-1" style="min-width:240px;">
                <label for="search-query" class="visually-hidden">{{ __('Search') }}</label>
                <div class="input-icon">
                    <i class="bi bi-search"></i>
                    <input id="search-query" type="search" name="q" value="{{ $query }}" class="form-control" placeholder="{{ __('Search by name, email or phone') }}" autofocus>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i>{{ __('Search') }}</button>
            @if ($query !== '')
                <a href="{{ route('search') }}" class="btn btn-light-soft">{{ __('Clear') }}</a>
            @endif
        </form>
    </section>

    @if ($query === '')
        <div class="ui-card empty-state">
            <div class="empty-icon"><i class="bi bi-command"></i></div>
            <h3>{{ __('Search your workspace') }}</h3>
            <p>{{ __('Start typing a name, email address or phone number. You will only see records you are authorised to view.') }}</p>
        </div>
    @else
        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
            <span class="text-muted-2">{{ __('Results for') }}</span>
            <span class="badge-soft is-brand">“{{ $query }}”</span>
            <span class="text-muted-2" style="font-size:.78rem;">{{ $users->count() }} {{ __('found') }}</span>
        </div>

        @if ($users->isNotEmpty())
            <section class="ui-card table-card mb-3">
                <div class="table-toolbar">
                    <h2 class="card-title mb-0">{{ __('Users') }}</h2>
                    @can('users.view')
                        <a href="{{ route('users.index', ['search' => $query]) }}" class="card-link">{{ __('Open in users') }} <i class="bi bi-arrow-right"></i></a>
                    @endcan
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>
                                        <div class="cell-person">
                                            <span class="avatar">
                                                @if ($user->avatarUrl())<img src="{{ $user->avatarUrl() }}" alt="">@else{{ $user->initials() }}@endif
                                            </span>
                                            <span>
                                                <span class="name">{{ $user->name }}</span>
                                                <span class="email">{{ $user->email }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td><span class="badge-soft">{{ $user->primaryRoleLabel() }}</span></td>
                                    <td>
                                        @if ($user->is_active)
                                            <span class="badge-soft is-success"><span class="dot"></span>{{ __('Active') }}</span>
                                        @else
                                            <span class="badge-soft is-danger"><span class="dot"></span>{{ __('Inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @can('update', $user)
                                            <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-light-soft"><i class="bi bi-pencil"></i>{{ __('Edit') }}</a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($users->isEmpty())
            <div class="ui-card empty-state">
                <div class="empty-icon"><i class="bi bi-search"></i></div>
                <h3>{{ __('No results found') }}</h3>
                <p>{{ __('Check the spelling, try a different identifier, or confirm you have permission to view the record.') }}</p>
            </div>
        @endif
    @endif
@endsection
