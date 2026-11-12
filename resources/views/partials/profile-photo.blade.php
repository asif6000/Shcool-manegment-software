@php
    /**
     * Self-service profile picture widget for the student and teacher portals.
     *
     * Everything is a real form, so picking a file and submitting still works
     * without JavaScript; profile-photo.js only upgrades it with a preview, an
     * optional crop, a progress bar and inline messages.
     */
    $ppwAccept = isset($photoAccept) && $photoAccept !== []
        ? implode(',', array_map(fn (string $ext): string => '.'.$ext, $photoAccept))
        : '.jpg,.jpeg,.png,.webp';
    $ppwMaxKb = (int) ($photoMaxKb ?? 5120);
    $ppwHasPhoto = $photoSrc !== null && $photoSrc !== '';
    $ppwStrings = json_encode([
        'Only JPEG, PNG and WebP images are allowed.' => __('Only JPEG, PNG and WebP images are allowed.'),
        'That image is {size}. The limit is {limit}. Crop it, or pick a smaller file.' => __('That image is {size}. The limit is {limit}. Crop it, or pick a smaller file.'),
        'That image could not be read. Pick another file.' => __('That image could not be read. Pick another file.'),
        'Too many uploads in a row. Wait a minute and try again.' => __('Too many uploads in a row. Wait a minute and try again.'),
        'The upload did not go through. Please try again.' => __('The upload did not go through. Please try again.'),
        'The upload did not go through. Check your connection and try again.' => __('The upload did not go through. Check your connection and try again.'),
        'The crop could not be prepared. Use Upload original instead.' => __('The crop could not be prepared. Use Upload original instead.'),
        'Your profile photo has been updated.' => __('Your profile photo has been updated.'),
    ], JSON_UNESCAPED_UNICODE);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/profile-photo.css') }}?v=4">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/profile-photo.js') }}?v=5"></script>
@endpush

<div class="ppw {{ $ppwHasPhoto ? 'has-photo' : 'no-photo' }}"
     data-profile-photo
     data-photo-url="{{ $photoSrc }}"
     data-max-kb="{{ $ppwMaxKb }}"
     data-strings="{{ $ppwStrings }}"
     data-accept="{{ $ppwAccept }}">
    <div class="ppw-stage">
        <div class="ppw-avatar-wrap">
            <div class="ppw-avatar" data-ppw-avatar>
                <img class="ppw-avatar-img {{ $ppwHasPhoto ? '' : 'is-hidden' }}"
                     data-ppw-image
                     alt="{{ $fullName }}"
                     @if ($ppwHasPhoto) src="{{ $photoSrc }}" @endif>
                <span class="ppw-avatar-initials {{ $ppwHasPhoto ? 'is-hidden' : '' }}" data-ppw-initials>{{ $initials }}</span>
                <button type="button" class="ppw-cam" data-ppw-camera-pick aria-label="{{ __('Upload profile photo') }}">
                    <i class="bi bi-camera-fill" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="ppw-copy">
            <h2 class="ppw-title">{{ __('Profile photo') }}</h2>
            <p class="ppw-hint">{{ __('Any size or shape works — portrait, landscape or square. The picture is kept exactly as you send it.') }}</p>
            <p class="ppw-limits">
                <i class="bi bi-card-image" aria-hidden="true"></i>
                {{ __('JPG, PNG or WebP · up to :size MB', ['size' => \App\Services\ProfilePhotoService::formatMb($ppwMaxKb)]) }}
            </p>

            <div class="ppw-actions">
                <button type="button" class="btn btn-primary btn-sm" data-ppw-pick>
                    <i class="bi bi-upload" aria-hidden="true"></i>{{ $ppwHasPhoto ? __('Change photo') : __('Upload image') }}
                </button>
                @if ($ppwHasPhoto)
                    <button type="button" class="btn btn-outline-danger btn-sm" data-ppw-remove-open>
                        <i class="bi bi-trash3" aria-hidden="true"></i>{{ __('Remove photo') }}
                    </button>
                @endif
            </div>
        </div>
    </div>

    <form class="ppw-upload" action="{{ $uploadUrl }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="file"
               class="visually-hidden"
               name="image"
               accept="{{ $ppwAccept }}"
               data-ppw-file
               aria-label="{{ __('Choose an image from your device') }}">
        <input type="file"
               class="visually-hidden"
               accept="{{ $ppwAccept }}"
               capture="user"
               data-ppw-camera
               tabindex="-1"
               aria-label="{{ __('Take a photo with your camera') }}">
        <button type="submit" class="btn btn-light-soft btn-sm" data-ppw-plain>{{ __('Upload now') }}</button>
    </form>

    @if ($ppwHasPhoto)
        <form class="ppw-remove" action="{{ $removeUrl }}" method="POST" data-confirm="{{ __('Remove your profile photo?') }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="visually-hidden" data-ppw-remove-submit>{{ __('Remove photo') }}</button>
        </form>
    @endif

    <div class="ppw-progress" data-ppw-progress hidden>
        <div class="ppw-progress-track"
             role="progressbar" aria-label="{{ __('Upload progress') }}"
             aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-ppw-bar-role>
            <span class="ppw-progress-bar" data-ppw-bar></span>
        </div>
        <span class="ppw-progress-label" data-ppw-percent>0%</span>
    </div>

    <p class="ppw-feedback is-hidden" data-ppw-feedback role="status" aria-live="polite"></p>

    <div class="ppw-editor" data-ppw-editor hidden>
        <div class="ppw-editor-head">
            <span class="ppw-editor-title">{{ __('Preview') }}</span>
            <button type="button" class="ppw-editor-close" data-ppw-cancel aria-label="{{ __('Cancel') }}">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>

        <div class="ppw-crop-frame">
            <canvas class="ppw-canvas" data-ppw-canvas width="280" height="280" role="img"
                    aria-label="{{ __('Drag the photo to reposition it') }}"></canvas>
            <span class="ppw-crop-ring" aria-hidden="true"></span>
        </div>

        <div class="ppw-editor-tools">
            <label class="ppw-zoom">
                <span>{{ __('Zoom') }}</span>
                <input type="range" min="100" max="300" value="100" step="1" data-ppw-zoom>
            </label>
            <p class="ppw-editor-note">{{ __('Cropping is optional. Uploading the original keeps every pixel and the exact shape of the picture.') }}</p>
        </div>

        <div class="ppw-editor-actions">
            <button type="button" class="btn btn-primary btn-sm" data-ppw-upload-crop>
                <i class="bi bi-crop" aria-hidden="true"></i>{{ __('Crop & upload') }}
            </button>
            <button type="button" class="btn btn-light-soft btn-sm" data-ppw-upload-original>
                <i class="bi bi-image" aria-hidden="true"></i>{{ __('Upload original') }}
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-ppw-cancel>{{ __('Cancel') }}</button>
        </div>
    </div>
</div>
