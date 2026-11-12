/**
 * Teacher Dashboard Navigation & In-place Panel Manager.
 *
 * STRICT UI RULE: Teacher dashboard MUST NEVER have a sidebar.
 * All teacher activities switch inside this single dashboard page without
 * full page reloads or opening sidebars.
 */
(function () {
    'use strict';

    const root = document.getElementById('teacher-dashboard');

    if (! root) {
        return;
    }

    const allowed = (root.getAttribute('data-td-panels') || '').split(' ').filter(Boolean);
    const errorBox = root.querySelector('[data-td-error]');
    const overview = document.getElementById('td-overview');
    const panels = new Map();

    root.querySelectorAll('[data-td-panel]').forEach(function (section) {
        const body = section.querySelector('.td-panel-body');

        panels.set(section.getAttribute('data-td-panel'), {
            key: section.getAttribute('data-td-panel'),
            section: section,
            body: body,
            url: body ? (body.getAttribute('data-td-url') || '') : '',
            lastUrl: '',
            loaded: false,
            busy: false,
        });
    });

    function panelOf(element) {
        const section = element.closest('[data-td-panel]');

        return section ? panels.get(section.getAttribute('data-td-panel')) : null;
    }

    function toUrl(value) {
        try {
            return new URL(value, window.location.href);
        } catch (error) {
            return null;
        }
    }

    function hostable(url) {
        if (! url || url.origin !== window.location.origin) {
            return false;
        }

        return allowed.some(function (key) {
            const base = toUrl(panels.get(key) ? panels.get(key).url : '');

            if (! base) {
                return false;
            }

            const prefix = base.pathname.replace(/\/$/, '');

            return url.pathname === base.pathname || url.pathname.startsWith(prefix + '/');
        });
    }

    function downloadable(url) {
        return /\/(file|attachment|export|print|download)$/.test(url.pathname)
            || url.pathname.endsWith('/report');
    }

    function syncNavActive(activeKey) {
        document.querySelectorAll('[data-td-open]').forEach(function (el) {
            const key = el.getAttribute('data-td-open');
            const isActive = key === activeKey;

            el.classList.toggle('is-active', isActive);

            if (isActive) {
                el.setAttribute('aria-current', 'page');
                el.setAttribute('aria-expanded', 'true');
            } else {
                el.removeAttribute('aria-current');
                el.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function rememberState(panel, url) {
        const base = toUrl(panel.url);
        const isDefault = base && url.pathname === base.pathname && url.search === base.search;
        const query = new URLSearchParams();

        query.set('panel', panel.key);

        if (! isDefault) {
            query.set('reopen', url.pathname + url.search);
        }

        window.history.pushState({ panel: panel.key }, '', window.location.pathname + '?' + query.toString());
    }

    function bindWithin(scope) {
        scope.querySelectorAll('script').forEach(function (old) {
            const fresh = document.createElement('script');

            if (old.src) {
                fresh.src = old.src;
            } else {
                fresh.textContent = old.textContent;
            }

            old.replaceWith(fresh);
        });

        scope.querySelectorAll('[data-auto-submit]').forEach(function (field) {
            field.addEventListener('change', function () {
                if (field.form) {
                    submitForm(field.form);
                }
            });
        });

        scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
            if (window.bootstrap && window.bootstrap.Tooltip) {
                new window.bootstrap.Tooltip(element);
            }
        });
    }

    function showError(message) {
        if (! errorBox) {
            window.alert(message);
            return;
        }

        errorBox.textContent = message;
        errorBox.hidden = false;
    }

    function inject(panel, href) {
        const url = toUrl(href);

        if (! url || ! hostable(url) || panel.busy) {
            return;
        }

        url.searchParams.set('fragment', '1');
        url.searchParams.set('panel', panel.key);

        panel.busy = true;
        panel.body.innerHTML = '<div class="td-loading d-flex align-items-center justify-content-center py-5 gap-3 text-muted">'
            + '<span class="spinner-border spinner-border-sm text-primary" role="status"></span>'
            + '<span class="fw-medium">Loading module content…</span></div>';

        fetch(url.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (! response.ok) {
                    throw new Error('unavailable');
                }

                return response.text();
            })
            .then(function (html) {
                const parsed = new DOMParser().parseFromString(html, 'text/html');

                panel.body.replaceChildren(...Array.from(parsed.body.children).map(function (node) {
                    return document.importNode(node, true);
                }));

                bindWithin(panel.body);
                panel.loaded = true;
                panel.busy = false;
                panel.lastUrl = url.pathname + url.search;
                if (errorBox) {
                    errorBox.hidden = true;
                }
                rememberState(panel, url);
            })
            .catch(function () {
                panel.busy = false;
                panel.loaded = false;
                panel.body.innerHTML = '<div class="alert alert-danger my-3"><i class="bi bi-exclamation-triangle me-2"></i>'
                    + 'This screen could not be loaded. Please click Reload or refresh your browser.</div>';
                showError('This module screen could not be opened. Use Reload, or ask the administrator to check your class assignments.');
            });
    }

    function openPanel(key, href, force) {
        if (! key || key === 'dashboard') {
            closePanels();
            return;
        }

        const panel = panels.get(key);

        if (! panel) {
            return;
        }

        // Hide overview section so the module gets full screen focus
        if (overview) {
            overview.hidden = true;
            overview.style.display = 'none';
        }

        panels.forEach(function (other) {
            const active = other === panel;

            other.section.hidden = ! active;
            other.section.style.display = active ? 'block' : 'none';
            other.section.classList.toggle('is-open', active);
        });

        syncNavActive(key);

        const target = href || panel.lastUrl || panel.url;

        if (! target) {
            return;
        }

        if (force || ! panel.loaded) {
            inject(panel, target);
        } else {
            rememberState(panel, toUrl(target));
        }
    }

    function closePanels() {
        panels.forEach(function (panel) {
            panel.section.hidden = true;
            panel.section.style.display = 'none';
            panel.section.classList.remove('is-open');
        });

        if (overview) {
            overview.hidden = false;
            overview.style.display = '';
        }

        syncNavActive('dashboard');

        if (errorBox) {
            errorBox.hidden = true;
        }

        window.history.pushState({ panel: '' }, '', window.location.pathname);
    }

    function submitForm(form) {
        const panel = panelOf(form);

        if (! panel) {
            return;
        }

        if (form.method.toUpperCase() === 'GET') {
            const url = toUrl(form.getAttribute('action') || window.location.href);
            const data = new FormData(form);

            if (! url) {
                return;
            }

            data.forEach(function (value, name) {
                if (typeof value === 'string') {
                    url.searchParams.set(name, value);
                }
            });

            inject(panel, url);
            return;
        }

        if (! form.querySelector('input[name="workspace"]')) {
            [['workspace', 'teacher'], ['panel', panel.key]].forEach(function (pair) {
                const input = document.createElement('input');

                input.type = 'hidden';
                input.name = pair[0];
                input.value = pair[1];
                form.appendChild(input);
            });
        }

        const message = form.getAttribute('data-confirm');

        if (message && window.uiConfirm) {
            // Panels are re-injected markup, so the shared modal is used here
            // too; it honours the same "type DELETE" rule as the admin pages.
            window.uiConfirm(message, function () {
                form.submit();
            }, {
                requireType: form.dataset.confirmType === '1',
                buttonLabel: form.dataset.confirmLabel
                    || (form.dataset.confirmDelete === '1' ? 'Permanently Delete' : undefined)
            });

            return;
        }

        if (message && ! window.confirm(message)) {
            return;
        }

        form.submit();
    }

    // Intercept form submissions inside panels
    root.addEventListener('submit', function (event) {
        if (panelOf(event.target)) {
            event.preventDefault();
            submitForm(event.target);
        }
    });

    // Global click dispatcher for teacher module navigation
    document.addEventListener('click', function (event) {
        // 1. Navigation items & Cards
        const opener = event.target.closest('[data-td-open]');

        if (opener) {
            const key = opener.getAttribute('data-td-open');
            event.preventDefault();

            if (key === 'dashboard') {
                closePanels();
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            openPanel(key, opener.getAttribute('href') || null, !! opener.getAttribute('href'));
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        // 2. Close / Back to Dashboard button
        if (event.target.closest('[data-td-close]')) {
            event.preventDefault();
            closePanels();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        // 3. Reload module button
        const reload = event.target.closest('[data-td-reload]');

        if (reload) {
            event.preventDefault();
            const reloadPanel = panelOf(reload);

            if (reloadPanel) {
                inject(reloadPanel, reloadPanel.lastUrl || reloadPanel.url);
            }
            return;
        }

        // 4. Panel internal links (pagination, view, tabs)
        const link = event.target.closest('a[href]');
        const panel = link ? panelOf(link) : null;

        if (! panel || link.hasAttribute('download') || link.target === '_blank') {
            return;
        }

        const url = toUrl(link.getAttribute('href'));

        if (! url || ! hostable(url) || downloadable(url)) {
            return;
        }

        event.preventDefault();

        const message = link.getAttribute('data-confirm');

        if (message && ! window.confirm(message)) {
            return;
        }

        inject(panel, url);
    });

    // Handle browser Back / Forward buttons without full page reloads
    window.addEventListener('popstate', function () {
        const params = new URLSearchParams(window.location.search);
        const panel = params.get('panel');

        if (panel && panels.has(panel)) {
            openPanel(panel, params.get('reopen') || null, false);
        } else {
            closePanels();
        }
    });

    // Initialize panel state on initial page load
    const active = root.getAttribute('data-td-active');
    const reopen = root.getAttribute('data-td-reopen');

    if (active && panels.has(active)) {
        openPanel(active, reopen || null, true);
    } else {
        syncNavActive('dashboard');
    }
})();
