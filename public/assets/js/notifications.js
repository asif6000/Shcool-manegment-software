(function () {
    'use strict';

    const menus = Array.from(document.querySelectorAll('[data-notification-menu]'));

    if (menus.length === 0) {
        return;
    }

    function post(menu, url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': menu.getAttribute('data-csrf') || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body || {})
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Request failed with status ' + response.status);
            }

            return response.json();
        });
    }

    function safeUrl(value) {
        if (typeof value !== 'string' || value === '') {
            return null;
        }

        try {
            const url = new URL(value, window.location.origin);

            return url.origin === window.location.origin ? url.href : null;
        } catch (error) {
            return null;
        }
    }

    function safeIcon(value) {
        return typeof value === 'string' && /^bi-[a-z0-9-]+$/i.test(value) ? value : 'bi-bell';
    }

    function setup(menu) {
        const badge = menu.querySelector('[data-notification-badge]');
        const list = menu.querySelector('[data-notification-list]');
        const summary = menu.querySelector('[data-notification-summary]');
        const readAll = menu.querySelector('[data-notification-read-all]');
        const feedUrl = menu.getAttribute('data-feed-url');
        const readUrlTemplate = menu.getAttribute('data-read-url');
        const readAllUrl = menu.getAttribute('data-read-all-url');
        const unreadLabel = menu.getAttribute('data-label-unread') || 'unread';
        const emptyLabel = menu.getAttribute('data-label-empty') || 'No notifications yet';
        const errorLabel = menu.getAttribute('data-label-error') || 'Could not load notifications.';

        if (!feedUrl || !readUrlTemplate || !readAllUrl) {
            return;
        }

        function setUnread(count) {
            const value = Math.max(0, parseInt(count, 10) || 0);

            if (badge) {
                badge.textContent = value > 9 ? '9+' : String(value);
                badge.hidden = value === 0;
            }

            if (summary) {
                summary.textContent = value + ' ' + unreadLabel;
            }

            if (readAll) {
                readAll.hidden = value === 0;
            }
        }

        function renderEmpty(message) {
            if (!list) {
                return;
            }

            list.replaceChildren();
            const empty = document.createElement('div');
            empty.className = 'notification-panel-empty';
            empty.textContent = message;
            list.appendChild(empty);
        }

        function markRead(id, done) {
            const url = readUrlTemplate.replace('__ID__', encodeURIComponent(id));

            post(menu, url)
                .then(function (data) {
                    setUnread(data.unread_count);
                })
                .catch(function () {
                    /* the item stays unread; the next refresh reconciles it */
                })
                .finally(function () {
                    if (typeof done === 'function') {
                        done();
                    }
                });
        }

        function render(items) {
            if (!list) {
                return;
            }

            list.replaceChildren();

            if (!Array.isArray(items) || items.length === 0) {
                renderEmpty(emptyLabel);
                return;
            }

            items.forEach(function (item) {
                const target = safeUrl(item.url);
                const row = document.createElement('li');
                row.className = 'notification-panel-item' + (item.read ? '' : ' is-unread');

                const link = document.createElement('a');
                link.className = 'notification-panel-link';
                link.href = target || '#';
                if (!target) {
                    link.setAttribute('role', 'button');
                }

                const icon = document.createElement('span');
                icon.className = 'notification-panel-icon';
                const glyph = document.createElement('i');
                glyph.className = 'bi ' + safeIcon(item.icon);
                glyph.setAttribute('aria-hidden', 'true');
                icon.appendChild(glyph);

                const body = document.createElement('span');
                body.className = 'notification-panel-body';

                const title = document.createElement('strong');
                title.textContent = item.title || '';
                const message = document.createElement('span');
                message.className = 'notification-panel-message';
                message.textContent = item.message || '';
                const time = document.createElement('time');
                time.className = 'notification-panel-time';
                time.textContent = item.created_at_human || '';

                body.append(title, message, time);

                const chevron = document.createElement('i');
                chevron.className = 'bi bi-chevron-right notification-panel-chevron';
                chevron.setAttribute('aria-hidden', 'true');

                link.append(icon, body, chevron);

                if (!item.read) {
                    const dot = document.createElement('span');
                    dot.className = 'unread-dot';
                    dot.setAttribute('aria-label', 'Unread');
                    link.appendChild(dot);
                }

                link.addEventListener('click', function (event) {
                    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                        return;
                    }

                    if (item.read && target) {
                        return;
                    }

                    event.preventDefault();
                    markRead(item.id, function () {
                        if (target) {
                            window.location.href = target;
                        } else {
                            refresh();
                        }
                    });
                });

                row.appendChild(link);
                list.appendChild(row);
            });
        }

        function refresh() {
            return fetch(feedUrl, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Request failed with status ' + response.status);
                    }

                    return response.json();
                })
                .then(function (data) {
                    setUnread(data.unread_count);
                    render(data.items);
                })
                .catch(function () {
                    renderEmpty(errorLabel);
                });
        }

        if (readAll) {
            readAll.addEventListener('click', function () {
                readAll.disabled = true;

                post(menu, readAllUrl)
                    .then(function (data) {
                        setUnread(data.unread_count);
                        refresh();
                    })
                    .catch(function () {
                        renderEmpty(errorLabel);
                    })
                    .finally(function () {
                        readAll.disabled = false;
                    });
            });
        }

        menu.addEventListener('show.bs.dropdown', refresh);

        refresh();

        window.setInterval(function () {
            if (document.visibilityState === 'visible') {
                fetch(feedUrl, {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (data) { if (data) { setUnread(data.unread_count); } })
                    .catch(function () { /* transient */ });
            }
        }, 60000);

        setupPush(menu);
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(base64);
        const output = new Uint8Array(raw.length);

        for (let i = 0; i < raw.length; i += 1) {
            output[i] = raw.charCodeAt(i);
        }

        return output;
    }

    function subscriptionPayload(subscription) {
        const json = typeof subscription.toJSON === 'function' ? subscription.toJSON() : {};

        return {
            endpoint: subscription.endpoint,
            keys: json.keys || {},
            content_encoding: typeof subscription.getContentEncoding === 'function'
                ? subscription.getContentEncoding()
                : 'aes128gcm'
        };
    }

    function setupPush(menu) {
        const toggle = menu.querySelector('[data-push-toggle]');
        const label = menu.querySelector('[data-push-label]');
        const hint = menu.querySelector('[data-push-hint]');
        const enabled = menu.getAttribute('data-push-enabled') === '1';
        const publicKey = menu.getAttribute('data-push-public-key') || '';
        const subscribeUrl = menu.getAttribute('data-push-subscribe-url');
        const unsubscribeUrl = menu.getAttribute('data-push-unsubscribe-url');
        const swUrl = menu.getAttribute('data-sw-url');
        const enabledLabel = menu.getAttribute('data-push-enabled-label') || 'Push notifications are on.';
        const disabledLabel = menu.getAttribute('data-push-disabled-label') || 'Enable browser notifications';
        const serverDisabledLabel = menu.getAttribute('data-push-server-disabled-label') || 'Push notifications are disabled.';
        const errorLabel = menu.getAttribute('data-push-error-label') || 'Push notifications could not be enabled.';
        const offLabel = menu.getAttribute('data-push-unsubscribed-label') || 'Push notifications are off.';

        if (!toggle || !label || !hint) {
            return;
        }

        const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

        if (!supported) {
            hint.hidden = false;
            hint.textContent = menu.getAttribute('data-push-unsupported-label') || '';
            return;
        }

        if (!enabled || !publicKey || !subscribeUrl || !unsubscribeUrl || !swUrl) {
            return;
        }

        let registration = null;

        function setState(state) {
            if (state === 'denied') {
                toggle.hidden = true;
                hint.hidden = false;
                hint.textContent = menu.getAttribute('data-push-denied-label') || '';
                return;
            }

            toggle.hidden = false;
            hint.hidden = true;
            toggle.classList.toggle('is-on', state === 'on');
            label.textContent = state === 'on' ? enabledLabel : (state === 'off' ? offLabel : disabledLabel);
            const icon = toggle.querySelector('i');
            if (icon) {
                icon.className = state === 'on' ? 'bi bi-bell-fill' : 'bi bi-bell-slash';
            }
        }

        function register() {
            if (registration) {
                return Promise.resolve(registration);
            }

            return navigator.serviceWorker.register(swUrl).then(function (value) {
                registration = value;
                return value;
            });
        }

        function currentSubscription() {
            return register().then(function (value) {
                return value.pushManager.getSubscription();
            });
        }

        function syncSubscription(subscription) {
            return post(menu, subscribeUrl, subscriptionPayload(subscription));
        }

        function subscribe() {
            return register()
                .then(function (value) {
                    return value.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(publicKey)
                    });
                })
                .then(function (subscription) {
                    return syncSubscription(subscription).then(function () {
                        setState('on');
                    });
                });
        }

        if (Notification.permission === 'denied') {
            setState('denied');
            return;
        }

        currentSubscription()
            .then(function (subscription) {
                if (subscription) {
                    return syncSubscription(subscription).then(function () {
                        setState('on');
                    });
                }

                setState(Notification.permission === 'granted' ? 'off' : 'idle');
            })
            .catch(function () {
                setState('idle');
            });

        toggle.addEventListener('click', function () {
            toggle.disabled = true;

            currentSubscription()
                .then(function (subscription) {
                    if (subscription) {
                        return subscription.unsubscribe().then(function () {
                            return fetch(unsubscribeUrl, {
                                method: 'DELETE',
                                credentials: 'same-origin',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': menu.getAttribute('data-csrf') || '',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({ endpoint: subscription.endpoint })
                            });
                        }).then(function () {
                            setState('off');
                        });
                    }

                    if (Notification.permission === 'denied') {
                        setState('denied');
                        return null;
                    }

                    return Notification.requestPermission().then(function (permission) {
                        if (permission !== 'granted') {
                            setState('denied');
                            return null;
                        }

                        return subscribe();
                    });
                })
                .catch(function () {
                    hint.hidden = false;
                    hint.textContent = errorLabel;
                })
                .finally(function () {
                    toggle.disabled = false;
                });
        });

        navigator.serviceWorker.addEventListener('message', function (event) {
            if (event.data && event.data.type === 'push-subscription-expired') {
                currentSubscription()
                    .then(function (subscription) {
                        if (subscription) {
                            return syncSubscription(subscription);
                        }

                        return subscribe();
                    })
                    .catch(function () { /* the user can retry from the menu */ });
            }
        });
    }

    menus.forEach(setup);
})();
