@php
    $pwaVersion = (string) config('pwa.version', '1.3.0');
    $pwaAppName = (string) config('pwa.short_name', 'DRMS');
    $installVariants = ['android', 'ios', 'desktop', 'unsupported'];
@endphp
<div class="pwa-ui" data-pwa-ui data-pwa-ios-hint="{{ __('pwa.ios_hint') }}">
    <div class="pwa-status pwa-status-offline" data-pwa-offline hidden role="status" aria-live="polite">
        <i class="bi bi-wifi-off" aria-hidden="true"></i>
        <span data-pwa-offline-text>{{ __('You are offline. School records need an internet connection.') }}</span>
    </div>

    <div class="pwa-status pwa-status-online" data-pwa-online hidden role="status" aria-live="polite">
        <i class="bi bi-wifi" aria-hidden="true"></i>
        <span>{{ __('Back online. Your connection has been restored.') }}</span>
    </div>

    <section class="pwa-toast pwa-update" data-pwa-update hidden aria-labelledby="pwa-update-title">
        <span class="pwa-toast-icon"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></span>
        <div class="pwa-toast-copy">
            <strong id="pwa-update-title">{{ __('Update available') }}</strong>
            <p>{{ __('A newer version of :app is ready.', ['app' => $pwaAppName]) }}</p>
        </div>
        <div class="pwa-toast-actions">
            <button type="button" class="pwa-btn pwa-btn-primary" data-pwa-update-now>
                {{ __('Update now') }}
            </button>
            <button type="button" class="pwa-btn pwa-btn-ghost" data-pwa-update-later>
                {{ __('Later') }}
            </button>
        </div>
    </section>

    <section class="pwa-install-card" data-pwa-install hidden aria-labelledby="pwa-install-title">
        <button type="button" class="pwa-install-close" data-pwa-install-close aria-label="{{ __('Close install prompt') }}">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
        <span class="pwa-install-icon">
            <img src="{{ asset('icons/icon-192.png') }}" alt="" width="44" height="44">
        </span>
        <div class="pwa-install-copy">
            <strong id="pwa-install-title">{{ __('pwa.install_title') }}</strong>
            <p data-pwa-install-text>{{ __('pwa.install_copy') }}</p>
        </div>
        <div class="pwa-install-actions">
            <button type="button" class="pwa-btn pwa-btn-primary" data-pwa-install-action>
                <i class="bi bi-download" aria-hidden="true"></i>{{ __('pwa.install_app') }}
            </button>
            @auth
                <a class="pwa-btn pwa-btn-ghost" href="{{ route('notifications.index') }}">
                    <i class="bi bi-bell" aria-hidden="true"></i>{{ __('Notifications') }}
                </a>
            @endauth
        </div>
    </section>

    <div class="pwa-sheet" data-pwa-sheet hidden role="dialog" aria-modal="true" aria-labelledby="pwa-sheet-title">
        <div class="pwa-sheet-backdrop" data-pwa-sheet-close></div>
        <div class="pwa-sheet-panel">
            <button type="button" class="pwa-install-close" data-pwa-sheet-close aria-label="{{ __('pwa.close_instructions') }}">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
            <span class="pwa-sheet-icon"><i class="bi bi-phone" aria-hidden="true"></i></span>
            <h2 id="pwa-sheet-title">{{ __('pwa.sheet_title') }}</h2>
            <p class="pwa-sheet-intro">{{ __('pwa.sheet_intro') }}</p>

            <div class="pwa-sheet-variants">
                @foreach ($installVariants as $variant)
                    <div class="pwa-sheet-variant" data-pwa-sheet-variant="{{ $variant }}" @if ($variant !== 'android') hidden @endif>
                        <h3 class="pwa-sheet-variant-title">{{ __('pwa.'.$variant.'_title') }}</h3>
                        <ol class="pwa-sheet-steps">
                            @foreach (__('pwa.'.$variant.'_steps') as $step)
                                <li>{{ $step }}</li>
                            @endforeach
                        </ol>
                    </div>
                @endforeach
            </div>

            <p class="pwa-sheet-hint">{{ __('Installation availability depends on your browser and device.') }}</p>
            <button type="button" class="pwa-btn pwa-btn-primary w-100" data-pwa-sheet-close>
                {{ __('pwa.got_it') }}
            </button>
        </div>
    </div>
</div>
<script src="{{ asset('assets/js/pwa.js') }}?v={{ $pwaVersion }}" defer></script>
