<div class="modal fade ui-confirm" id="uiConfirmDialog" tabindex="-1" aria-labelledby="uiConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body">
                <span class="ui-confirm-icon"><x-icon name="circle-alert" :size="24" /></span>
                <h2 class="modal-title" id="uiConfirmTitle">{{ __('Please confirm') }}</h2>
                <p class="ui-confirm-message" data-confirm-message style="white-space: pre-line;"></p>
                {{-- Typed-confirmation input (hidden by default; shown for critical deletes) --}}
                <div class="ui-confirm-type-wrap d-none mt-2">
                    <label for="uiConfirmTypeInput" class="form-label small text-danger fw-semibold">
                        {{ __('Type DELETE to confirm') }}
                    </label>
                    <input type="text" id="uiConfirmTypeInput" class="form-control form-control-sm"
                           placeholder="DELETE" autocomplete="off">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-danger" data-confirm-accept data-default-label="{{ __('Confirm') }}">
                    {{ __('Confirm') }}
                </button>
            </div>
        </div>
    </div>
</div>
