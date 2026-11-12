(function () {
    'use strict';

    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        var input = document.getElementById(button.getAttribute('data-password-toggle'));

        if (!input) {
            return;
        }

        var icon = button.querySelector('i');
        var showLabel = button.getAttribute('data-show-label') || 'Show password';
        var hideLabel = button.getAttribute('data-hide-label') || 'Hide password';

        button.addEventListener('click', function () {
            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';

            if (icon) {
                icon.className = 'bi ' + (reveal ? 'bi-eye-slash' : 'bi-eye');
            }

            button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            button.setAttribute('aria-label', reveal ? hideLabel : showLabel);
            button.setAttribute('title', reveal ? hideLabel : showLabel);
            input.focus();
        });
    });

    document.querySelectorAll('[data-password-form]').forEach(function (form) {
        var submit = form.querySelector('.pw-submit');
        var fresh = form.querySelector('[data-password-new]');
        var confirm = form.querySelector('[data-password-confirm]');
        var note = form.querySelector('[data-password-mismatch]');

        if (!fresh || !confirm || !note) {
            return;
        }

        var message = note.textContent;

        function clearBusy() {
            delete form.dataset.busy;

            if (submit) {
                submit.classList.remove('is-busy');
                submit.removeAttribute('aria-busy');
            }
        }

        function mismatched() {
            return confirm.value !== '' && fresh.value !== '' && confirm.value !== fresh.value;
        }

        function sync() {
            var bad = mismatched();
            note.hidden = !bad;
            confirm.classList.toggle('is-invalid', bad);
            confirm.setAttribute('aria-invalid', bad ? 'true' : 'false');

            if (!bad && note.textContent !== message) {
                note.textContent = message;
            }
        }

        [fresh, confirm].forEach(function (field) {
            field.addEventListener('input', sync);
        });

        form.addEventListener('submit', function (event) {
            // A disabled submitter cancels the submission in some engines, so the
            // busy state is visual only and repeats are swallowed by this flag.
            if (form.dataset.busy === '1') {
                event.preventDefault();

                return;
            }

            if (mismatched()) {
                event.preventDefault();
                note.textContent = message;
                sync();
                confirm.focus();

                return;
            }

            form.dataset.busy = '1';

            if (submit) {
                submit.classList.add('is-busy');
                submit.setAttribute('aria-busy', 'true');
            }
        });

        // Restored pages keep the button disabled unless the state is reset.
        window.addEventListener('pageshow', clearBusy);
    });
})();
