(function () {
    'use strict';

    const ui = document.querySelector('[data-pwa-ui]');

    if (!ui) {
        return;
    }

    const meta = function (name) {
        const node = document.querySelector('meta[name="' + name + '"]');

        return node ? node.getAttribute('content') || '' : '';
    };

    const swUrl = meta('pwa-sw-url');
    const versionUrl = meta('pwa-version-url');
    const logoutUrl = meta('pwa-logout-url');
    const unsubscribeUrl = meta('pwa-push-unsubscribe-url');
    const csrf = meta('pwa-csrf');
    const currentVersion = meta('pwa-version');

    const offlineBanner = ui.querySelector('[data-pwa-offline]');
    const onlineBanner = ui.querySelector('[data-pwa-online]');
    const updatePanel = ui.querySelector('[data-pwa-update]');
    const installCard = ui.querySelector('[data-pwa-install]');
    const installText = ui.querySelector('[data-pwa-install-text]');
    const installAction = ui.querySelector('[data-pwa-install-action]');
    const sheet = ui.querySelector('[data-pwa-sheet]');

    let deferredInstallPrompt = null;
    let registration = null;
    let updateVisible = false;
    let updateReloading = false;
    let updateReloadTimer = null;
    let installAvailable = false;
    let wasOffline = !navigator.onLine;
    let onlineBannerTimer = null;
    let lastVersionCheck = 0;

    function storageGet(key) {
        try {
            return window.sessionStorage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function storageSet(key, value) {
        try {
            window.sessionStorage.setItem(key, value);
        } catch (error) {
            /* storage can be unavailable in private mode */
        }
    }

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: window-controls-overlay)').matches
            || window.navigator.standalone === true;
    }

    function isIOS() {
        const ua = window.navigator.userAgent || '';

        return /iPad|iPhone|iPod/i.test(ua)
            || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
    }

    function preferredInstallVariant() {
        const ua = window.navigator.userAgent || '';
        const isFirefox = /Firefox|FxiOS/i.test(ua);

        if (isIOS()) {
            return 'ios';
        }

        if (/Android/i.test(ua)) {
            return isFirefox ? 'unsupported' : 'android';
        }

        if (isFirefox) {
            return 'unsupported';
        }

        return 'desktop';
    }

    function installDismissed() {
        return storageGet('edunest.pwa.install.dismissed') === '1';
    }

    function syncInstallTriggers() {
        const standalone = isStandalone();

        document.body.classList.toggle('pwa-standalone', standalone);

        document.querySelectorAll('[data-pwa-install-trigger]').forEach(function (trigger) {
            // No install button on an installed app, and no dead button on a
            // browser that has not offered an install path.
            trigger.hidden = standalone || !installAvailable;
        });
    }

    function canUsePush() {
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    }

    function hideInstallCard() {
        if (installCard) {
            installCard.hidden = true;
        }
    }

    function showInstallHint(text) {
        syncInstallTriggers();

        if (!installCard || isStandalone() || installDismissed()) {
            return;
        }

        if (installText && text) {
            installText.textContent = text;
        }

        installCard.hidden = false;
    }

    function refreshInstallState() {
        if (isStandalone()) {
            installAvailable = false;
            hideInstallCard();
            syncInstallTriggers();
            return;
        }

        installAvailable = deferredInstallPrompt !== null || isIOS();

        if (deferredInstallPrompt) {
            showInstallHint();
            return;
        }

        if (isIOS()) {
            showInstallHint(ui.getAttribute('data-pwa-ios-hint') || undefined);
            return;
        }

        hideInstallCard();
        syncInstallTriggers();
    }

    function updateSheetVariant(variant) {
        if (!sheet) {
            return;
        }

        const selected = variant || preferredInstallVariant();

        sheet.querySelectorAll('[data-pwa-sheet-variant]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-pwa-sheet-variant') !== selected;
        });
    }

    function openSheet(variant) {
        if (!sheet) {
            return;
        }

        updateSheetVariant(variant);
        sheet.hidden = false;
        document.body.classList.add('pwa-sheet-open');
        const first = sheet.querySelector('[data-pwa-sheet-close]');

        if (first) {
            first.focus();
        }
    }

    function requestInstall() {
        if (isStandalone()) {
            installAvailable = false;
            hideInstallCard();
            syncInstallTriggers();
            return;
        }

        // Only the click that is happening now may open the browser prompt.
        // A stored event is consumed exactly once and then dropped, so the
        // same prompt can never be requested twice.
        const prompt = deferredInstallPrompt;

        if (!prompt) {
            if (isIOS()) {
                openSheet('ios');
            } else {
                installAvailable = false;
                hideInstallCard();
                syncInstallTriggers();
            }

            return;
        }

        deferredInstallPrompt = null;

        try {
            prompt.prompt();
        } catch (error) {
            installAvailable = false;
            hideInstallCard();
            syncInstallTriggers();
            return;
        }

        prompt.userChoice.then(function (choice) {
            installAvailable = false;
            hideInstallCard();
            syncInstallTriggers();

            if (choice && choice.outcome === 'accepted') {
                storageSet('edunest.pwa.install.dismissed', '1');
            }
        }).catch(function () {
            installAvailable = false;
            hideInstallCard();
            syncInstallTriggers();
        });
    }

    function closeSheet() {
        if (!sheet) {
            return;
        }

        sheet.hidden = true;
        document.body.classList.remove('pwa-sheet-open');
    }

    function showOffline() {
        wasOffline = true;

        if (offlineBanner) {
            offlineBanner.hidden = false;
        }

        if (onlineBanner) {
            onlineBanner.hidden = true;
        }
    }

    function showOnline() {
        if (!wasOffline) {
            return;
        }

        wasOffline = false;

        if (offlineBanner) {
            offlineBanner.hidden = true;
        }

        if (!onlineBanner) {
            return;
        }

        onlineBanner.hidden = false;

        if (onlineBannerTimer !== null) {
            window.clearTimeout(onlineBannerTimer);
        }

        onlineBannerTimer = window.setTimeout(function () {
            onlineBanner.hidden = true;
            onlineBannerTimer = null;
        }, 4500);
    }

    function showUpdateAvailable() {
        if (updateVisible || !updatePanel) {
            return;
        }

        updateVisible = true;
        updatePanel.hidden = false;
        hideInstallCard();
    }

    function hideUpdateAvailable() {
        if (updatePanel) {
            updatePanel.hidden = true;
        }
    }

    function reloadOnce() {
        if (updateReloading) {
            return;
        }

        const lastReload = parseInt(storageGet('edunest.pwa.reload') || '0', 10);

        if (Date.now() - lastReload < 8000) {
            return;
        }

        updateReloading = true;
        storageSet('edunest.pwa.reload', String(Date.now()));

        if (updateReloadTimer !== null) {
            window.clearTimeout(updateReloadTimer);
            updateReloadTimer = null;
        }

        window.location.reload();
    }

    function activateUpdate() {
        hideUpdateAvailable();

        if (registration && registration.waiting) {
            updateReloading = true;
            registration.waiting.postMessage({ type: 'SKIP_WAITING' });

            // Fallback for browsers that do not deliver controllerchange.
            updateReloadTimer = window.setTimeout(function () {
                reloadOnce();
            }, 4000);

            return;
        }

        reloadOnce();
    }

    function watchRegistration(value) {
        registration = value;

        if (registration.waiting && navigator.serviceWorker.controller) {
            showUpdateAvailable();
        }

        registration.addEventListener('updatefound', function () {
            const installing = registration.installing;

            if (!installing) {
                return;
            }

            installing.addEventListener('statechange', function () {
                if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                    showUpdateAvailable();
                }
            });
        });

        window.setInterval(function () {
            if (document.visibilityState === 'visible') {
                registration.update().catch(function () {
                    /* offline or the server is unreachable */
                });
            }
        }, 60 * 60 * 1000);
    }

    function checkAppVersion(force) {
        if (!versionUrl || document.visibilityState !== 'visible') {
            return;
        }

        if (!force && Date.now() - lastVersionCheck < 5 * 60 * 1000) {
            return;
        }

        lastVersionCheck = Date.now();

        fetch(versionUrl, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(function (data) {
                if (data && typeof data.version === 'string'
                    && currentVersion !== ''
                    && data.version !== currentVersion) {
                    showUpdateAvailable();

                    if (registration) {
                        registration.update().catch(function () {
                            /* the release may only change app assets */
                        });
                    }
                }
            })
            .catch(function () {
                /* offline: the update check will retry later */
            });
    }

    function clearBrowserCaches() {
        if (!('caches' in window)) {
            return Promise.resolve();
        }

        return caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                return caches.delete(key);
            }));
        }).catch(function () {
            return undefined;
        });
    }

    function clearPushSubscription() {
        if (!canUsePush()) {
            return Promise.resolve();
        }

        return navigator.serviceWorker.getRegistration()
            .then(function (value) {
                if (!value) {
                    return null;
                }

                return value.pushManager.getSubscription();
            })
            .then(function (subscription) {
                if (!subscription || !unsubscribeUrl) {
                    return undefined;
                }

                const endpoint = subscription.endpoint;

                return fetch(unsubscribeUrl, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    keepalive: true,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ endpoint: endpoint })
                }).catch(function () {
                    return undefined;
                }).then(function () {
                    return subscription.unsubscribe().catch(function () {
                        return undefined;
                    });
                });
            })
            .catch(function () {
                return undefined;
            });
    }

    function clearBeforeLogout() {
        const work = [
            clearPushSubscription(),
            clearBrowserCaches()
        ];

        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({ type: 'CLEAR_PRIVATE' });
        }

        return Promise.race([
            Promise.allSettled(work),
            new Promise(function (resolve) {
                window.setTimeout(resolve, 900);
            })
        ]);
    }

    function interceptLogout() {
        if (!logoutUrl) {
            return;
        }

        document.addEventListener('submit', function (event) {
            const form = event.target;

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            let action;

            try {
                action = new URL(form.getAttribute('action') || form.action, window.location.href);
            } catch (error) {
                return;
            }

            let logout;

            try {
                logout = new URL(logoutUrl, window.location.href);
            } catch (error) {
                return;
            }

            if (action.origin !== logout.origin || action.pathname !== logout.pathname) {
                return;
            }

            if (form.getAttribute('data-pwa-logout') === '1') {
                return;
            }

            // Stop values such as a password field from being lost while the
            // device subscription and runtime caches are cleared.
            event.preventDefault();
            form.setAttribute('data-pwa-logout', '1');

            clearBeforeLogout().finally(function () {
                form.submit();
            });
        }, true);
    }

    if ('serviceWorker' in navigator && (window.isSecureContext || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')) {
        navigator.serviceWorker.register(swUrl, { updateViaCache: 'none' })
            .then(function (value) {
                watchRegistration(value);
                checkAppVersion(true);
            })
            .catch(function () {
                /* the app keeps working online without a service worker */
            });

        navigator.serviceWorker.addEventListener('controllerchange', function () {
            if (updateReloading) {
                reloadOnce();
            }
        });
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        // Holding the event back is the browser's install banner being
        // replaced by ours. If ours may not appear - the app is already
        // installed, or the user closed it before - the event is left alone so
        // the browser can do whatever it was going to do, and no prompt is
        // accepted and then silently dropped.
        if (isStandalone() || installDismissed()) {
            return;
        }

        event.preventDefault();
        deferredInstallPrompt = event;
        installAvailable = true;
        refreshInstallState();
    });

    window.addEventListener('appinstalled', function () {
        deferredInstallPrompt = null;
        installAvailable = false;
        hideInstallCard();
        syncInstallTriggers();
        storageSet('edunest.pwa.install.dismissed', '1');
    });

    // The page is going away; the event cannot be turned into a prompt any
    // more, so do not keep it around.
    window.addEventListener('pagehide', function () {
        deferredInstallPrompt = null;
        installAvailable = false;
    });

    window.addEventListener('offline', showOffline);
    window.addEventListener('online', showOnline);

    if (!navigator.onLine) {
        showOffline();
    } else {
        wasOffline = false;
    }

    if (installAction) {
        installAction.addEventListener('click', requestInstall);
    }

    document.querySelectorAll('[data-pwa-install-trigger]').forEach(function (trigger) {
        trigger.addEventListener('click', requestInstall);
    });

    syncInstallTriggers();

    const installClose = ui.querySelector('[data-pwa-install-close]');

    if (installClose) {
        installClose.addEventListener('click', function () {
            hideInstallCard();
            storageSet('edunest.pwa.install.dismissed', '1');
        });
    }

    ui.querySelectorAll('[data-pwa-sheet-close]').forEach(function (element) {
        element.addEventListener('click', closeSheet);
    });

    const updateNow = ui.querySelector('[data-pwa-update-now]');
    const updateLater = ui.querySelector('[data-pwa-update-later]');

    if (updateNow) {
        updateNow.addEventListener('click', activateUpdate);
    }

    if (updateLater) {
        updateLater.addEventListener('click', hideUpdateAvailable);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sheet && !sheet.hidden) {
            closeSheet();
        }
    });

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            checkAppVersion(false);
        }
    });

    window.setInterval(function () {
        checkAppVersion(false);
    }, 5 * 60 * 1000);

    window.setTimeout(function () {
        refreshInstallState();
    }, 2500);

    window.setTimeout(function () {
        checkAppVersion(true);
    }, 15000);

    interceptLogout();

    // The notification permission itself is requested by the existing bell
    // menu only after the user clicks its enable button. This keeps permission
    // prompts tied to an explicit user action.
    window.EduNestPwa = {
        isStandalone: isStandalone,
        clearBeforeLogout: clearBeforeLogout
    };
})();
