(function () {
    'use strict';

    const host = document.querySelector('[data-work-live]');

    if (!host) {
        return;
    }

    const url = host.getAttribute('data-work-live') || window.location.href;
    const interval = Math.max(5000, parseInt(host.getAttribute('data-work-live-interval'), 10) || 25000);
    const labels = {
        updated: host.getAttribute('data-work-live-updated') || 'Updated',
        retry: host.getAttribute('data-work-live-retry') || 'Reconnecting',
        watching: host.getAttribute('data-work-live-watching') || 'Watching for changes',
    };
    const selector = '[data-work-live-list]';
    const target = document.querySelector(selector);

    if (!target) {
        return;
    }

    let delay = interval;
    let timer = null;

    function hint(text) {
        document.querySelectorAll('[data-work-live-state]').forEach(function (node) {
            node.textContent = text;
        });
    }

    function stamp() {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function poll() {
        return fetch(url, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('status ' + response.status);
                }

                return response.text();
            })
            .then(function (text) {
                const fresh = new DOMParser().parseFromString(text, 'text/html').querySelector(selector);

                if (!fresh) {
                    throw new Error('list region missing');
                }

                if (fresh.innerHTML !== target.innerHTML) {
                    target.innerHTML = fresh.innerHTML;
                }

                hint(labels.updated + ' ' + stamp());
                delay = interval;
            })
            .catch(function () {
                // A dropped poll is routine when the laptop sleeps, so back off
                // quietly and return to the normal cadence on the next success.
                delay = Math.min(delay * 2, 300000);
                hint(labels.retry);
            })
            .then(schedule);
    }

    function schedule() {
        window.clearTimeout(timer);
        timer = window.setTimeout(poll, delay);
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            poll();
        }
    });

    window.addEventListener('online', poll);

    hint(labels.watching + ' - ' + Math.round(interval / 1000) + 's');
    timer = window.setTimeout(poll, delay);
})();
