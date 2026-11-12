@if (session('success') || session('status') || session('warning') || session('info') || session('error') || $errors->any())
    <div class="flash-stack" role="status" aria-live="polite">
        @if (session('success'))
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-octagon-fill"></i>
                <div>
                    @if (is_array(session('error')))
                        <ul class="mb-0 ps-3">
                            @foreach (session('error') as $errorLine)
                                <li>{{ $errorLine }}</li>
                            @endforeach
                        </ul>
                    @else
                        {{ session('error') }}
                    @endif
                </div>
            </div>
        @endif
        @if (session('status'))
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div>{{ session('status') }}</div>
            </div>
        @endif
        @if (session('info'))
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div>{{ session('info') }}</div>
            </div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    @if (is_array(session('warning')))
                        <ul class="mb-0 ps-3">
                            @foreach (session('warning') as $warningLine)
                                <li>{{ $warningLine }}</li>
                            @endforeach
                        </ul>
                    @else
                        {{ session('warning') }}
                    @endif
                </div>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-octagon-fill"></i>
                <div>
                    <strong>{{ __('Please check the form below.') }}</strong>
                    <div class="mt-1" style="font-size:.8rem;">{{ __('Some fields need your attention before this can be saved.') }}</div>
                </div>
            </div>
        @endif
    </div>
@endif
