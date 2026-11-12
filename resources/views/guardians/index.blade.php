@extends('layouts.app')

@section('title', __('Guardians'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><x-icon name="user-round" :size="14" />{{ __('Family directory') }}</span>
            <h1 class="page-title">{{ __('Guardian Management') }}</h1>
            <p class="page-subtitle">{{ __('Search guardian contacts and see the students linked to each family record.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('students.index') }}" class="btn btn-light-soft">
                <x-icon name="users-round" :size="17" />{{ __('All students') }}
            </a>
        </div>
    </div>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('guardians.index') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <x-icon name="search" class="filter-icon" :size="16" />
                    <label class="visually-hidden" for="guardian-search">{{ __('Search guardians') }}</label>
                    <input id="guardian-search" type="search" name="search" class="form-control" value="{{ $search }}" placeholder="{{ __('Search name, phone, email or NID') }}">
                </div>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Search') }}</button>
                @if ($search !== '')
                    <a href="{{ route('guardians.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count guardian|:count guardians', $guardians->total(), ['count' => $guardians->total()]) }}</span>
        </div>

        @if ($guardians->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><x-icon name="user-round" :size="28" /></div>
                <h3>{{ __('No guardians found') }}</h3>
                <p>{{ $search !== '' ? __('No guardian matches your search.') : __('Guardian records appear here when they are added to a student profile.') }}</p>
                @if ($search !== '')
                    <a href="{{ route('guardians.index') }}" class="btn btn-light-soft mt-3">{{ __('Clear search') }}</a>
                @endif
            </div>
        @else
            <div class="guardian-grid">
                @foreach ($guardians as $guardian)
                    <article class="guardian-card">
                        <div class="guardian-card-head">
                            <span class="avatar avatar-lg">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($guardian->name, 0, 1)) }}</span>
                            <div class="guardian-identity">
                                <h2>{{ $guardian->name }}</h2>
                                <span class="badge-soft is-brand">{{ __(ucfirst($guardian->relation)) }}</span>
                            </div>
                            <span class="guardian-count" title="{{ __('Linked students') }}">
                                <x-icon name="graduation-cap" :size="15" />{{ $guardian->students_count }}
                            </span>
                        </div>

                        <div class="guardian-contact">
                            @if ($guardian->phone)
                                <a href="tel:{{ $guardian->phone }}">
                                    <x-icon name="bell" :size="15" />{{ $guardian->phone }}
                                </a>
                            @endif
                            @if ($guardian->alt_phone)
                                <span><x-icon name="clock" :size="15" />{{ $guardian->alt_phone }}</span>
                            @endif
                            @if ($guardian->email)
                                <a href="mailto:{{ $guardian->email }}">
                                    <x-icon name="send" :size="15" />{{ $guardian->email }}
                                </a>
                            @endif
                        </div>

                        @if ($guardian->students->isNotEmpty())
                            <div class="guardian-students">
                                @foreach ($guardian->students as $student)
                                    @php($enrollment = $student->enrollments->first())
                                    <a href="{{ route('students.show', $student) }}" class="guardian-student">
                                        <span>
                                            <strong>{{ $student->full_name }}</strong>
                                            <small>{{ $student->student_id }}</small>
                                        </span>
                                        <span class="guardian-student-class">
                                            {{ $enrollment?->schoolClass?->name ?? __('Not enrolled') }}
                                            @if ($enrollment?->section)
                                                <small>{{ $enrollment->section->name }}</small>
                                            @endif
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="guardian-empty">{{ __('No student is linked to this guardian yet.') }}</p>
                        @endif

                        <div class="guardian-card-foot">
                            <span>{{ trans_choice(':count linked student|:count linked students', $guardian->students_count, ['count' => $guardian->students_count]) }}</span>
                            <span>{{ __('Updated :date', ['date' => $guardian->updated_at?->diffForHumans()]) }}</span>
                            @can('students.delete')
                                <form method="POST" action="{{ route('guardians.destroy', $guardian) }}"
                                      data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                      data-confirm-delete="1"
                                      data-confirm-type="1"
                                      data-confirm-label="{{ __('Permanently Delete') }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="permanent_delete" value="DELETE">
                                    <button type="submit" class="btn btn-sm btn-link text-danger">
                                        <x-icon name="trash" :size="15" />{{ __('Delete') }}
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from to :to of :total', ['from' => $guardians->firstItem(), 'to' => $guardians->lastItem(), 'total' => $guardians->total()]) }}</span>
                {{ $guardians->links() }}
            </div>
        @endif
    </section>
@endsection
