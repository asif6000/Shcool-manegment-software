@php
    $unread = (int) ($unread ?? 0);
    $pushEnabled = (bool) config('webpush.enabled') && filled(config('webpush.public_key'));
@endphp
<div
    class="dropdown notification-menu"
    data-notification-menu
    data-feed-url="{{ route('notifications.feed') }}"
    data-read-url="{{ route('notifications.read', ['notification' => '__ID__']) }}"
    data-read-all-url="{{ route('notifications.read-all') }}"
    data-push-enabled="{{ $pushEnabled ? '1' : '0' }}"
    data-push-public-key="{{ $pushEnabled ? config('webpush.public_key') : '' }}"
    data-push-subscribe-url="{{ route('notifications.push.subscribe') }}"
    data-push-unsubscribe-url="{{ route('notifications.push.unsubscribe') }}"
    data-push-denied-label="{{ __('Push notifications are blocked in this browser. Enable them in the browser settings.') }}"
    data-push-unsupported-label="{{ __('This browser does not support push notifications.') }}"
    data-push-enabled-label="{{ __('Push notifications are on for this browser.') }}"
    data-push-disabled-label="{{ __('Enable browser notifications') }}"
    data-push-server-disabled-label="{{ __('Browser notifications are disabled by the school office.') }}"
    data-push-error-label="{{ __('Push notifications could not be enabled. Try again in a moment.') }}"
    data-push-unsubscribed-label="{{ __('Push notifications are off for this browser.') }}"
    data-sw-url="{{ asset('sw.js') }}"
    data-label-unread="{{ __('unread') }}"
    data-label-empty="{{ __('No notifications yet') }}"
    data-label-error="{{ __('Could not load notifications.') }}"
    data-csrf="{{ csrf_token() }}"
>
    <button
        class="icon-button notification-trigger"
        type="button"
        data-bs-toggle="dropdown"
        data-bs-auto-close="outside"
        aria-expanded="false"
        aria-label="{{ __('Notifications') }}"
    >
        <x-icon name="bell" :size="19" />
        <span class="notification-dot" data-notification-badge @if ($unread === 0) hidden @endif>
            {{ $unread > 9 ? '9+' : $unread }}
        </span>
    </button>

    <div class="dropdown-menu dropdown-menu-end notification-panel">
        <div class="notification-panel-head">
            <div>
                <strong>{{ __('Notifications') }}</strong>
                <span class="text-muted-2" data-notification-summary>
                    {{ trans_choice(':count unread|:count unread', $unread, ['count' => $unread]) }}
                </span>
            </div>
            <button type="button" class="btn btn-sm btn-light-soft" data-notification-read-all @if ($unread === 0) hidden @endif>
                <x-icon name="check" :size="15" />{{ __('Mark all read') }}
            </button>
        </div>

        <div class="notification-panel-list" data-notification-list aria-live="polite">
            <div class="notification-panel-empty">{{ __('Loading…') }}</div>
        </div>

        <div class="notification-panel-foot">
            <a href="{{ route('notifications.index') }}" class="notification-view-all">
                {{ __('View all notifications') }}<x-icon name="arrow-right" :size="15" />
            </a>
            <button type="button" class="notification-push-toggle" data-push-toggle hidden>
                <i class="bi bi-bell-slash"></i><span data-push-label>{{ __('Enable browser notifications') }}</span>
            </button>
            <p class="notification-push-hint" data-push-hint hidden></p>
        </div>
    </div>
</div>

