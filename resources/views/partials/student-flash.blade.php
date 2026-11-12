@php
    $studentAlerts = collect([
        ['key' => 'success', 'class' => 'text-bg-success', 'icon' => 'bi-check-circle-fill', 'label' => __('Saved')],
        ['key' => 'status', 'class' => 'text-bg-info', 'icon' => 'bi-info-circle-fill', 'label' => __('Notice')],
        ['key' => 'info', 'class' => 'text-bg-info', 'icon' => 'bi-info-circle-fill', 'label' => __('Notice')],
        ['key' => 'warning', 'class' => 'text-bg-warning', 'icon' => 'bi-exclamation-triangle-fill', 'label' => __('Careful')],
    ])->filter(fn (array $alert): bool => session($alert['key']) !== null);
@endphp

@if ($studentAlerts->isNotEmpty() || $errors->any())
    <div class="toast-stack" role="status" aria-live="polite">
        @foreach ($studentAlerts as $alert)
            <div class="toast align-items-center border-0 {{ $alert['class'] }}" role="alert" data-bs-autohide="true" data-bs-delay="6000">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi {{ $alert['icon'] }} me-1"></i>{{ session($alert['key']) }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('Close') }}"></button>
                </div>
            </div>
        @endforeach

        @if ($errors->any())
            <div class="toast align-items-center border-0 text-bg-danger" role="alert" data-bs-autohide="false">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong><i class="bi bi-exclamation-octagon-fill me-1"></i>{{ __('Please check the form.') }}</strong>
                        <div class="mt-1 small">{{ $errors->first() }}</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('Close') }}"></button>
                </div>
            </div>
        @endif
    </div>
@endif
