/*
 * DRMS (Dream Roots Millennium School) service worker.
 *
 * Security rules:
 *  - Only public application shell files are cached.
 *  - Authenticated HTML, API responses, notices, fees, attendance and results
 *    are never written to a shared browser cache.
 *  - Push payloads contain a generic message; private details stay behind the
 *    authenticated login.
 *
 * Bump SW_VERSION together with PWA_VERSION in the environment for every
 * production release so installed clients receive the new shell.
 */
'use strict';

const SW_VERSION = '1.3.2';
const BASE_URL = new URL('./', self.location).href;
const OFFLINE_URL = new URL('offline.html', BASE_URL).href;
const MANIFEST_URL = new URL('manifest.webmanifest', BASE_URL).href;

const CACHE_PREFIX = 'edunest-';
const SHELL_CACHE = `${CACHE_PREFIX}shell-v${SW_VERSION}`;
const RUNTIME_CACHE = `${CACHE_PREFIX}runtime-v${SW_VERSION}`;
const FONT_CACHE = `${CACHE_PREFIX}fonts-v${SW_VERSION}`;
const CURRENT_CACHES = [SHELL_CACHE, RUNTIME_CACHE, FONT_CACHE];
const FONT_CACHE_LIMIT = 24;

const PRECACHE_URLS = [
    OFFLINE_URL,
    MANIFEST_URL,
    new URL('favicon.ico', BASE_URL).href,
    new URL('favicon.svg', BASE_URL).href,
    new URL('icons/icon-192.png', BASE_URL).href,
    new URL('icons/icon-512.png', BASE_URL).href,
    new URL('icons/maskable-192.png', BASE_URL).href,
    new URL('icons/maskable-512.png', BASE_URL).href,
    new URL('icons/apple-touch-icon.png', BASE_URL).href,
    new URL('assets/css/app.css', BASE_URL).href,
    new URL('assets/css/bengali.css', BASE_URL).href,
    new URL('assets/css/student-portal.css', BASE_URL).href,
    new URL('assets/css/landing.css', BASE_URL).href,
    new URL('assets/css/pwa.css', BASE_URL).href,
    new URL('assets/vendor/bootstrap/css/bootstrap.min.css', BASE_URL).href,
    new URL('assets/vendor/bootstrap-icons/bootstrap-icons.min.css', BASE_URL).href,
    new URL('assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2', BASE_URL).href,
    new URL('assets/fonts/noto-sans-bengali-var.woff2', BASE_URL).href,
    new URL('assets/js/app.js', BASE_URL).href,
    new URL('assets/js/notifications.js', BASE_URL).href,
    new URL('assets/js/pwa.js', BASE_URL).href,
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(SHELL_CACHE).then(function (cache) {
            // Add one by one: a single optional asset must not fail the whole
            // installation, and the offline page must always be available.
            return Promise.allSettled(
                PRECACHE_URLS.map(function (url) {
                    return cache.add(new Request(url, { cache: 'reload' }));
                })
            );
        })
        // No skipWaiting here. An update waits for the user to press
        // "Update now" so an open form is not interrupted mid-edit.
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys
                    .filter(function (key) {
                        return key.indexOf(CACHE_PREFIX) === 0
                            && CURRENT_CACHES.indexOf(key) === -1;
                    })
                    .map(function (key) {
                        return caches.delete(key);
                    })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('message', function (event) {
    const data = event.data || {};

    if (data.type === 'SKIP_WAITING') {
        self.skipWaiting();
        return;
    }

    if (data.type === 'CLEAR_PRIVATE') {
        event.waitUntil(caches.delete(RUNTIME_CACHE));
        return;
    }

    if (data.type === 'CLEAR_ALL_RUNTIME') {
        event.waitUntil(caches.delete(RUNTIME_CACHE));
        return;
    }

    if (event.ports && event.ports[0]) {
        if (data.type === 'GET_VERSION') {
            event.ports[0].postMessage({ version: SW_VERSION });
        }
    }
});

self.addEventListener('fetch', function (event) {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstNavigation(request));
        return;
    }

    if (url.origin === self.location.origin) {
        if (url.href === MANIFEST_URL) {
            event.respondWith(networkFirstManifest(request));
            return;
        }

        if (isPublicStaticAsset(url)) {
            event.respondWith(cacheFirst(request, SHELL_CACHE));
        }

        // Every other same-origin request (API, inbox, portal pages, uploads,
        // form posts, signed file links) is left to the network. This is what
        // keeps authenticated data out of shared browser caches.
        return;
    }

    if (isPublicFont(url)) {
        event.respondWith(cacheFirst(request, FONT_CACHE, FONT_CACHE_LIMIT));
    }
});

function isPublicStaticAsset(url) {
    const path = url.pathname;

    return path.indexOf('/assets/') !== -1
        || path.indexOf('/icons/') !== -1
        || /\/favicon\.(ico|svg|png)$/.test(path);
}

function isPublicFont(url) {
    return url.hostname === 'fonts.googleapis.com'
        || url.hostname === 'fonts.gstatic.com';
}

async function networkFirstNavigation(request) {
    try {
        const response = await fetch(request);

        // Authenticated pages are never stored. A server error should still be
        // allowed to render its own error page rather than pretending offline.
        return response;
    } catch (error) {
        const cached = await caches.match(OFFLINE_URL);

        if (cached) {
            return cached;
        }

        return new Response(
            '<!doctype html><meta charset="utf-8"><title>Offline</title><p>DRMS is offline. Reconnect to continue.</p>',
            {
                status: 503,
                headers: { 'Content-Type': 'text/html; charset=utf-8' },
            }
        );
    }
}

async function networkFirstManifest(request) {
    try {
        const response = await fetch(request);

        if (response && response.ok) {
            const cache = await caches.open(SHELL_CACHE);
            cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        const cached = await caches.match(request);
        return cached || Response.error();
    }
}

function cacheKey(request) {
    const url = new URL(request.url);

    if (url.origin === self.location.origin) {
        // Versioned asset URLs (?v=...) must still match the precached shell.
        url.search = '';

        return new Request(url.href);
    }

    return request;
}

async function cacheFirst(request, cacheName, limit) {
    const cache = await caches.open(cacheName);
    const key = cacheKey(request);
    const cached = await cache.match(key);

    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);

        if (response && (response.ok || response.type === 'opaque')) {
            cache.put(key, response.clone());

            if (limit) {
                trimCache(cacheName, limit);
            }
        }

        return response;
    } catch (error) {
        return cached || new Response('', { status: 504, statusText: 'Offline' });
    }
}

function trimCache(cacheName, limit) {
    caches.open(cacheName).then(function (cache) {
        cache.keys().then(function (keys) {
            if (keys.length <= limit) {
                return;
            }

            Promise.all(
                keys.slice(0, keys.length - limit).map(function (request) {
                    return cache.delete(request);
                })
            );
        });
    });
}

self.addEventListener('push', function (event) {
    let payload = {};

    if (event.data) {
        try {
            payload = event.data.json();
        } catch (error) {
            payload = { body: event.data.text() };
        }
    }

    const title = typeof payload.title === 'string' && payload.title !== ''
        ? payload.title
        : 'DRMS notification';
    const body = typeof payload.body === 'string' ? payload.body : '';
    const target = safeInternalUrl(payload.url);
    const icon = safeAssetUrl(payload.icon) || new URL('icons/icon-192.png', BASE_URL).href;
    const badge = new URL('icons/icon-192.png', BASE_URL).href;

    event.waitUntil(
        self.registration.showNotification(title, {
            body: body,
            icon: icon,
            badge: badge,
            tag: typeof payload.tag === 'string' ? payload.tag : undefined,
            renotify: false,
            data: { url: target },
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const target = safeInternalUrl(event.notification.data && event.notification.data.url)
        || BASE_URL;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windows) {
            for (const client of windows) {
                if (new URL(client.url).origin !== self.location.origin) {
                    continue;
                }

                if ('navigate' in client) {
                    return client.navigate(target).then(function (navigated) {
                        return (navigated || client).focus();
                    }).catch(function () {
                        return client.focus();
                    });
                }

                if ('focus' in client) {
                    return client.focus();
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(target);
            }

            return undefined;
        })
    );
});

self.addEventListener('pushsubscriptionchange', function (event) {
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windows) {
            windows.forEach(function (client) {
                client.postMessage({ type: 'push-subscription-expired' });
            });
        })
    );
});

function safeInternalUrl(value) {
    if (typeof value !== 'string' || value === '') {
        return null;
    }

    try {
        const url = new URL(value, self.location.origin);

        return url.origin === self.location.origin ? url.href : null;
    } catch (error) {
        return null;
    }
}

function safeAssetUrl(value) {
    return safeInternalUrl(value);
}
