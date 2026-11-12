@extends('layouts.app')

@section('title', __('Send notification'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-send"></i>{{ __('School communication') }}</span>
            <h1 class="page-title">{{ __('Send a targeted notification') }}</h1>
            <p class="page-subtitle">{{ __('Deliver one message to students, teachers or staff. In-app copies keep the message; browser lock screens show only a generic school alert.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('notifications.index') }}" class="btn btn-light-soft">
                <i class="bi bi-bell"></i>{{ __('Open inbox') }}
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('notifications.compose.store') }}">
        @csrf
        <input type="hidden" name="campaign_id" value="{{ old('campaign_id', $campaignId) }}">

        <div class="row g-3">
            <div class="col-lg-8">
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('Message') }}</h2>

                    <div class="mb-3 mt-3">
                        <label class="form-label" for="notification-title">{{ __('Title') }}</label>
                        <input id="notification-title" type="text" name="title" maxlength="160" required
                               class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title') }}"
                               placeholder="{{ __('For example: Tomorrow\'s assembly') }}">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="notification-message">{{ __('Message') }}</label>
                        <textarea id="notification-message" name="message" rows="7" maxlength="2000" required
                                  class="form-control @error('message') is-invalid @enderror"
                                  placeholder="{{ __('Write the update the selected group should read in the portal.') }}">{{ old('message') }}</textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <p class="text-muted-2 mb-0" style="font-size:.8rem;">
                        <i class="bi bi-shield-lock"></i>
                        {{ __('Recipients read the title and message after signing in. Browser notifications never include this text.') }}
                    </p>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i>{{ __('Send notification') }}
                        </button>
                        <a href="{{ route('notifications.index') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                    </div>
                </section>
            </div>

            <div class="col-lg-4">
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('Recipients') }}</h2>

                    <div class="mt-3">
                        <label class="form-label" for="notification-audience">{{ __('Audience') }}</label>
                        <select id="notification-audience" name="audience" class="form-select @error('audience') is-invalid @enderror" data-audience>
                            <option value="students" @selected(old('audience', 'students') === 'students')>{{ __('Students') }}</option>
                            <option value="teachers" @selected(old('audience') === 'teachers')>{{ __('Teachers') }}</option>
                            <option value="staff" @selected(old('audience') === 'staff')>{{ __('Staff (including teachers)') }}</option>
                        </select>
                        @error('audience')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mt-3">
                        <label class="form-label" for="notification-scope">{{ __('Who should receive it?') }}</label>
                        <select id="notification-scope" name="recipient_scope" class="form-select @error('recipient_scope') is-invalid @enderror" data-recipient-scope>
                            <option value="all" @selected(old('recipient_scope', 'all') === 'all')>{{ __('Everyone in this audience') }}</option>
                            <option value="selected" @selected(old('recipient_scope') === 'selected')>{{ __('Selected people') }}</option>
                        </select>
                        @error('recipient_scope')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mt-3" data-recipient-group="students">
                        <label class="form-label" for="notification-students">{{ __('Choose students') }}</label>
                        <select id="notification-students" name="recipient_ids[]" class="form-select" multiple size="8">
                            @forelse ($options['students'] as $studentUser)
                                <option value="{{ $studentUser->id }}" @selected(in_array($studentUser->id, (array) old('recipient_ids', [])))>
                                    {{ $studentUser->name }}@if ($studentUser->student?->student_id) - {{ $studentUser->student->student_id }}@endif
                                </option>
                            @empty
                                <option value="" disabled>{{ __('No active student accounts') }}</option>
                            @endforelse
                        </select>
                    </div>

                    <div class="mt-3" data-recipient-group="teachers">
                        <label class="form-label" for="notification-teachers">{{ __('Choose teachers') }}</label>
                        <select id="notification-teachers" name="recipient_ids[]" class="form-select" multiple size="8">
                            @forelse ($options['teachers'] as $teacherUser)
                                <option value="{{ $teacherUser->id }}" @selected(in_array($teacherUser->id, (array) old('recipient_ids', [])))>
                                    {{ $teacherUser->name }}@if ($teacherUser->teacher?->designation) - {{ $teacherUser->teacher->designation }}@endif
                                </option>
                            @empty
                                <option value="" disabled>{{ __('No active teacher accounts') }}</option>
                            @endforelse
                        </select>
                    </div>

                    <div class="mt-3" data-recipient-group="staff">
                        <label class="form-label" for="notification-staff">{{ __('Choose staff') }}</label>
                        <select id="notification-staff" name="recipient_ids[]" class="form-select" multiple size="8">
                            @forelse ($options['staff'] as $staffUser)
                                <option value="{{ $staffUser->id }}" @selected(in_array($staffUser->id, (array) old('recipient_ids', [])))>
                                    {{ $staffUser->name }} - {{ $staffUser->primaryRoleLabel() }}
                                </option>
                            @empty
                                <option value="" disabled>{{ __('No active staff accounts') }}</option>
                            @endforelse
                        </select>
                    </div>

                    @error('recipient_ids')<div class="text-danger mt-2" style="font-size:.8rem;">{{ $message }}</div>@enderror
                    @error('recipient_ids.*')<div class="text-danger mt-2" style="font-size:.8rem;">{{ $message }}</div>@enderror

                    <p class="text-muted-2 mt-3 mb-0" style="font-size:.78rem;">
                        <i class="bi bi-info-circle"></i>
                        {{ __('The sender is never included, and inactive accounts are skipped.') }}
                    </p>
                </section>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const audience = document.querySelector('[data-audience]');
            const scope = document.querySelector('[data-recipient-scope]');
            const groups = document.querySelectorAll('[data-recipient-group]');

            function syncRecipientGroups() {
                const selects = scope.value === 'selected';

                groups.forEach(function (group) {
                    const active = selects && group.dataset.recipientGroup === audience.value;

                    group.hidden = !active;

                    group.querySelectorAll('select').forEach(function (select) {
                        select.disabled = !active;
                    });
                });
            }

            audience.addEventListener('change', syncRecipientGroups);
            scope.addEventListener('change', syncRecipientGroups);
            syncRecipientGroups();
        });
    </script>
@endpush
