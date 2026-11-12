(function () {
    'use strict';

    var root = document.documentElement;
    var STORAGE_KEY = 'edunest.theme';
    var labels = {
        dark: document.body ? document.body.getAttribute('data-dark-label') || 'Dark mode' : 'Dark mode',
        light: document.body ? document.body.getAttribute('data-light-label') || 'Light mode' : 'Light mode'
    };

    function current() {
        return root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    }

    function remember(value) {
        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch (error) {
            /* storage may be unavailable in private mode */
        }
    }

    function paint(button) {
        if (!button) {
            return;
        }
        var dark = current() === 'dark';
        button.setAttribute('aria-pressed', dark ? 'true' : 'false');
        button.setAttribute('title', dark ? labels.light : labels.dark);
        button.setAttribute('aria-label', dark ? labels.light : labels.dark);
    }

    function paintSetters() {
        document.querySelectorAll('[data-theme-set]').forEach(function (button) {
            var active = button.getAttribute('data-theme-set') === current();
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function apply(next) {
        root.setAttribute('data-bs-theme', next);
        remember(next);
        document.querySelectorAll('[data-theme-toggle]').forEach(paint);
        paintSetters();
    }

    document.querySelectorAll('[data-theme-toggle]').forEach(function (toggle) {
        paint(toggle);
        toggle.addEventListener('click', function () {
            apply(current() === 'dark' ? 'light' : 'dark');
        });
    });

    document.querySelectorAll('[data-theme-set]').forEach(function (button) {
        button.addEventListener('click', function () {
            apply(button.getAttribute('data-theme-set'));
        });
    });

    paintSetters();

    var back = document.querySelector('[data-topbar-back]');

    if (back) {
        var backLabels = {
            back: back.getAttribute('data-back-label') || 'Back',
            home: back.getAttribute('data-home-label') || 'Overview'
        };
        var canGoBack = false;

        if (document.referrer && document.referrer.indexOf(window.location.origin + '/') === 0) {
            try {
                var from = new URL(document.referrer);
                // Only a portal page is worth going back to; sign-in and the
                // public site would bounce the student straight back here.
                canGoBack = /\/(student|notifications)(\/|$)/.test(from.pathname)
                    && from.pathname !== window.location.pathname;
            } catch (error) {
                canGoBack = false;
            }
        }

        if (!canGoBack) {
            var icon = back.querySelector('i');

            if (icon) {
                icon.className = 'bi bi-house-door';
            }
            back.setAttribute('aria-label', backLabels.home);
            back.setAttribute('title', backLabels.home);
        } else {
            back.addEventListener('click', function (event) {
                event.preventDefault();
                window.history.back();
            });
        }
    }

    if (window.bootstrap && window.bootstrap.Toast) {
        document.querySelectorAll('.toast').forEach(function (element) {
            new window.bootstrap.Toast(element).show();
        });
    }
})();
