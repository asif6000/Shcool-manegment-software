@push('scripts')
    <script src="{{ asset('assets/js/work-live.js') }}?v=1" defer
            data-work-live
            data-work-live-updated="{{ __('Updated') }}"
            data-work-live-retry="{{ __('Reconnecting') }}"
            data-work-live-watching="{{ __('Watching for changes') }}"></script>
@endpush
