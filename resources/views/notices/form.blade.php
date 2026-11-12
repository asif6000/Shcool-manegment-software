@extends('layouts.app')

@php
    $isEdit = $notice->exists;
    $audienceLabels = [
        'all' => __('Everyone (website + inbox)'),
        'students' => __('Students (website + inbox)'),
        'guardians' => __('Guardians (website + inbox)'),
        'teachers' => __('Teachers (signed-in inbox only)'),
        'staff' => __('Staff (signed-in inbox only)'),
    ];
@endphp

@section('title', $isEdit ? __('Edit notice') : __('Write a notice'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-megaphone"></i>{{ __('School communication') }}</span>
            <h1 class="page-title">{{ $isEdit ? __('Edit notice') : __('Write a notice') }}</h1>
            <p class="page-subtitle">{{ __('A notice with no publish date stays a draft. Everyone, Students and Guardians audiences also appear on the public website.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('notices.index') }}" class="btn btn-light-soft">
                <i class="bi bi-arrow-left"></i>{{ __('Back to notices') }}
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <form method="POST" action="{{ $isEdit ? route('notices.update', $notice) : route('notices.store') }}" class="ui-card p-lg">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div class="mb-3">
                    <label class="form-label" for="notice-title">{{ __('Title') }}</label>
                    <input id="notice-title" type="text" name="title" maxlength="160" required
                           class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title', $notice->title) }}">
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label" for="notice-body">{{ __('Body') }}</label>
                    <textarea id="notice-body" name="body" rows="9" required
                              class="form-control @error('body') is-invalid @enderror">{{ old('body', $notice->body) }}</textarea>
                    <div class="form-text">{{ __('Plain text. Line breaks are preserved on the website.') }}</div>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="notice-audience">{{ __('Audience') }}</label>
                        <select id="notice-audience" name="audience" class="form-select @error('audience') is-invalid @enderror">
                            @foreach ($audienceLabels as $value => $label)
                                <option value="{{ $value }}" @selected(old('audience', $notice->audience ?? 'all') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('audience')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="notice-session">{{ __('Academic session') }}</label>
                        <select id="notice-session" name="academic_session_id" class="form-select @error('academic_session_id') is-invalid @enderror">
                            <option value="">{{ __('Not tied to a session') }}</option>
                            @foreach ($sessions as $session)
                                <option value="{{ $session->id }}" @selected((string) old('academic_session_id', $notice->academic_session_id) === (string) $session->id)>{{ $session->name }}</option>
                            @endforeach
                        </select>
                        @error('academic_session_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="notice-published">{{ __('Publish at') }}</label>
                        <input id="notice-published" type="datetime-local" name="published_at"
                               class="form-control @error('published_at') is-invalid @enderror"
                               value="{{ old('published_at', $notice->published_at?->format('Y-m-d\TH:i')) }}">
                        <div class="form-text">{{ __('Leave empty to keep it as a draft, or choose a future time to schedule.') }}</div>
                        @error('published_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="notice-expires">{{ __('Expires at') }}</label>
                        <input id="notice-expires" type="datetime-local" name="expires_at"
                               class="form-control @error('expires_at') is-invalid @enderror"
                               value="{{ old('expires_at', $notice->expires_at?->format('Y-m-d\TH:i')) }}">
                        <div class="form-text">{{ __('Optional. After this moment the notice leaves the website.') }}</div>
                        @error('expires_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="notice-pinned" name="is_pinned" value="1"
                           @checked(old('is_pinned', $notice->is_pinned))>
                    <label class="form-check-label" for="notice-pinned">{{ __('Pin to the top of the notice board') }}</label>
                </div>

                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="notice-notify" name="notify_audience" value="1"
                           @checked(old('notify_audience', true))>
                    <label class="form-check-label" for="notice-notify">
                        {{ __('Send it to the audience inbox and lock screen when published') }}
                    </label>
                    <div class="form-text">{{ __('Only the title is used on the lock screen — never student names, marks or amounts.') }}</div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i>{{ __('Save notice') }}</button>
                    <a href="{{ route('notices.index') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <section class="ui-card p-lg">
                <h2 class="card-title mb-3">{{ __('Delivery') }}</h2>

                @if (! $isEdit)
                    <p class="text-muted-2 mb-0">{{ __('Save the notice first. Publish, pin and delete actions appear here afterwards.') }}</p>
                @else
                    <dl class="detail-list mb-3">
                        <div>
                            <dt>{{ __('Website') }}</dt>
                            <dd>
                                @if (! $notice->isVisibleOnWebsite())
                                    {{ __('Inbox only') }}
                                @elseif ($notice->isLiveOnWebsite())
                                    {{ __('Public page') }}
                                @else
                                    {{ __('Not live yet') }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>{{ __('Published') }}</dt>
                            <dd>{{ $notice->published_at?->format('d M Y, g:i a') ?? __('Draft') }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Expires') }}</dt>
                            <dd>{{ $notice->expires_at?->format('d M Y, g:i a') ?? __('Never') }}</dd>
                        </div>
                    </dl>

                    @if ($notice->published_at === null || $notice->published_at->isFuture() || ($notice->expires_at !== null && $notice->expires_at->isPast()))
                        <form method="POST" action="{{ route('notices.publish', $notice) }}" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-broadcast"></i>{{ __('Publish now') }}
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('notices.unpublish', $notice) }}" class="mb-2"
                              data-confirm="{{ __('Unpublish this notice? It leaves the website immediately.') }}">
                            @csrf
                            <button type="submit" class="btn btn-light-soft w-100">
                                <i class="bi bi-eye-slash"></i>{{ __('Unpublish') }}
                            </button>
                        </form>
                    @endif

                    @if ($notice->isLiveOnWebsite())
                        <a href="{{ route('public.notices.show', $notice) }}" target="_blank" rel="noopener"
                           class="btn btn-link px-0">
                            <i class="bi bi-box-arrow-up-right"></i>{{ __('Open on the website') }}
                        </a>
                    @endif

                    <form method="POST" action="{{ route('notices.destroy', $notice) }}" class="mt-3"
                          data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                          data-confirm-delete="1"
                          data-confirm-label="{{ __('Permanently Delete') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i>{{ __('Delete notice') }}
                        </button>
                    </form>
                @endif
            </section>
        </div>
    </div>
@endsection
