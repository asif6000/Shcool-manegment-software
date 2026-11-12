@extends($layout ?? 'layouts.app')

@section('title', __('Notifications'))

@section('content')
    @php
        $categoryLabels = [
            'general' => __('General'),
            'attendance' => __('Attendance'),
            'fees' => __('Fees'),
            'exams' => __('Exams & results'),
            'notices' => __('Notices'),
            'homework' => __('Homework'),
            'classwork' => __('Classwork'),
            'routine' => __('Class routine'),
            'admission' => __('Admissions'),
        ];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-bell"></i>{{ __('Inbox') }}</span>
            <h1 class="page-title">{{ __('Notifications') }}</h1>
            <p class="page-subtitle">{{ __('School updates for your account. Notifications are private to you.') }}</p>
        </div>
        @if ($unreadCount > 0)
            <div class="page-actions">
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn btn-light-soft"><i class="bi bi-check2-all"></i>{{ __('Mark all as read') }}</button>
                </form>
            </div>
        @endif
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="ui-card table-card">
                <div class="table-toolbar">
                    <div class="d-flex align-items-center gap-2">
                        <h2 class="card-title mb-0">{{ __('Your inbox') }}</h2>
                        @if ($unreadCount > 0)
                            <span class="badge-soft is-brand"><span class="dot"></span>{{ trans_choice(':count unread|:count unread', $unreadCount, ['count' => $unreadCount]) }}</span>
                        @else
                            <span class="badge-soft is-success"><i class="bi bi-check2"></i>{{ __('All caught up') }}</span>
                        @endif
                    </div>
                    <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count notification|:count notifications', $notifications->total(), ['count' => $notifications->total()]) }}</span>
                </div>

                @if ($notifications->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-bell-slash"></i></div>
                        <h3>{{ __('No notifications yet') }}</h3>
                        <p>{{ __('School updates about attendance, fees, results and admissions will appear here.') }}</p>
                    </div>
                @else
                    <ul class="notification-list">
                        @foreach ($notifications as $notification)
                            @php($data = $notification->data)
                            <li class="notification-item {{ $notification->read_at ? '' : 'is-unread' }}">
                                <span class="notification-icon">
                                    <i class="bi {{ $data['icon'] ?? 'bi-bell' }}"></i>
                                </span>
                                <div class="notification-body">
                                    <p class="notification-title">
                                        {{ $data['title'] ?? __('Notification') }}
                                        <span class="badge-soft is-brand ms-1">{{ $categoryLabels[$notification->category] ?? __('General') }}</span>
                                    </p>
                                    <p class="notification-text">{{ $data['message'] ?? '' }}</p>
                                    <div class="notification-time">
                                        <i class="bi bi-clock"></i> {{ $notification->created_at?->diffForHumans() }}
                                        @if ($notification->read_at)
                                            · {{ __('Read') }}
                                        @endif
                                    </div>
                                </div>
                                <div class="text-end">
                                    @if (! $notification->read_at)
                                        <span class="unread-dot" aria-label="{{ __('Unread') }}"></span>
                                    @endif
                                    <div class="mt-2">
                                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-light-soft">
                                                <i class="bi bi-arrow-right"></i>{{ $notification->read_at ? __('Open') : __('Open & mark read') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="table-footer">
                        <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $notifications->firstItem(), 'to' => $notifications->lastItem(), 'total' => $notifications->total()]) }}</span>
                        {{ $notifications->links() }}
                    </div>
                @endif
            </section>
        </div>

        <div class="col-xl-4">
            <section class="ui-card">
                <div class="table-toolbar">
                    <h2 class="card-title mb-0">{{ __('Delivery preferences') }}</h2>
                </div>
                <div class="p-3">
                    <p class="text-muted-2" style="font-size:.8rem;">
                        {{ __('In-app notifications are always recorded. Choose which categories may also be delivered as browser push notifications.') }}
                    </p>

                    <form method="POST" action="{{ route('notifications.preferences.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="notification-preferences">
                            @foreach ($categories as $category)
                                <label class="notification-preference-row">
                                    <span>
                                        <strong>{{ $categoryLabels[$category] ?? ucfirst($category) }}</strong>
                                        <small class="text-muted-2">{{ __('Browser push') }}</small>
                                    </span>
                                    <span class="form-check form-switch m-0">
                                        <input type="hidden" name="preferences[{{ $category }}][webpush]" value="0">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            role="switch"
                                            name="preferences[{{ $category }}][webpush]"
                                            value="1"
                                            @checked($preferences[$category]['webpush'] ?? true)
                                        >
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mt-3">
                            <i class="bi bi-check2"></i>{{ __('Save preferences') }}
                        </button>
                    </form>

                    <hr class="my-3">

                    <p class="text-muted-2 mb-0" style="font-size:.78rem;">
                        <i class="bi bi-info-circle"></i>
                        {{ __('To turn browser notifications on or off for this device, use the bell menu and select “Enable browser notifications”.') }}
                    </p>
                </div>
            </section>
        </div>
    </div>
@endsection
