(function () {
    'use strict';

    const body = document.body;
    const root = document.documentElement;
    const sidebarNodes = '.app-sidebar, [data-sidebar-toggle], [data-sidebar-close], .app-backdrop';
    const isTeacher = body.classList.contains('teacher-shell') || body.classList.contains('is-teacher');
    const isStudent = body.classList.contains('student-shell');
    const mobileSidebar = window.matchMedia('(max-width: 991.98px)');

    function setTheme(theme) {
        root.setAttribute('data-theme', theme === 'dark' ? 'dark' : 'light');
        root.style.colorScheme = theme === 'dark' ? 'dark' : 'light';

        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            const isDark = theme === 'dark';
            const label = isDark
                ? (button.dataset.themeDarkLabel || 'Switch to light mode')
                : (button.dataset.themeLightLabel || 'Switch to dark mode');
            button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
        });

        try {
            localStorage.setItem('edunest.theme', theme === 'dark' ? 'dark' : 'light');
        } catch (error) {
            /* storage can be unavailable in private mode */
        }
    }

    setTheme(root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');

    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            setTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        });
    });

    if (isTeacher || isStudent) {
        body.classList.remove('sidebar-open', 'sidebar-collapsed');
        document.querySelectorAll(sidebarNodes).forEach(function (el) {
            el.remove();
        });
    }

    if (!isStudent && !isTeacher) {
        function setSidebarCollapsed(collapsed) {
            body.classList.toggle('sidebar-collapsed', collapsed);
            try {
                localStorage.setItem('edunest.sidebar.collapsed', collapsed ? '1' : '0');
            } catch (error) {
                /* storage can be unavailable in private mode */
            }
        }

        try {
            setSidebarCollapsed(localStorage.getItem('edunest.sidebar.collapsed') === '1');
        } catch (error) {
            /* ignore */
        }

        document.querySelectorAll('[data-sidebar-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (mobileSidebar.matches) {
                    body.classList.toggle('sidebar-open');
                    return;
                }

                setSidebarCollapsed(!body.classList.contains('sidebar-collapsed'));
            });
        });

        function directChild(parent, selector) {
            return Array.prototype.find.call(parent.children, function (child) {
                return child.matches(selector);
            }) || null;
        }

        function setGroupOpen(group, open) {
            group.classList.toggle('is-open', open);

            const toggle = directChild(group, '[data-nav-toggle]');
            const submenu = directChild(group, '.nav-submenu');

            if (toggle) {
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            if (submenu) {
                submenu.setAttribute('aria-hidden', open ? 'false' : 'true');
            }
        }

        function expandActiveGroup() {
            const activeLink = document.querySelector('.app-sidebar .nav-sub-link.active')
                || document.querySelector('.app-sidebar .nav-link[aria-current="page"]');

            if (!activeLink) {
                return;
            }

            let parent = activeLink.closest('[data-nav-group]');

            while (parent) {
                parent.classList.add('is-active');
                setGroupOpen(parent, true);
                parent = parent.parentElement ? parent.parentElement.closest('[data-nav-group]') : null;
            }
        }

        document.querySelectorAll('[data-nav-group]').forEach(function (group) {
            setGroupOpen(group, group.classList.contains('is-open'));
        });

        expandActiveGroup();

        const sidebar = document.querySelector('.app-sidebar');

        if (sidebar && sidebar.dataset.navReady !== '1') {
            sidebar.dataset.navReady = '1';

            sidebar.addEventListener('click', function (event) {
                const target = event.target;

                if (!target || typeof target.closest !== 'function') {
                    return;
                }

                const toggle = target.closest('[data-nav-toggle]');

                if (!toggle || !sidebar.contains(toggle)) {
                    return;
                }

                const group = toggle.closest('[data-nav-group]');

                if (!group || directChild(group, '[data-nav-toggle]') !== toggle) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                // On desktop, if sidebar was collapsed, expand sidebar and open group
                if (!mobileSidebar.matches && body.classList.contains('sidebar-collapsed')) {
                    setSidebarCollapsed(false);
                    setGroupOpen(group, true);
                    return;
                }

                setGroupOpen(group, !group.classList.contains('is-open'));
            });
        }

        document.querySelectorAll('[data-sidebar-close]').forEach(function (element) {
            element.addEventListener('click', function () {
                if (element.hasAttribute('data-nav-toggle') || element.closest('[data-nav-toggle]')) {
                    return;
                }
                body.classList.remove('sidebar-open');
            });
        });

        document.addEventListener('click', function (event) {
            if (!body.classList.contains('sidebar-open')) {
                return;
            }

            const target = event.target;
            if (target && typeof target.closest === 'function') {
                if (target.closest('.app-sidebar') || target.closest('[data-sidebar-toggle]')) {
                    return;
                }
            }

            body.classList.remove('sidebar-open');
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                body.classList.remove('sidebar-open');
            }
        });
    }

    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.getElementById(button.getAttribute('data-toggle-password'));

            if (!target) {
                return;
            }

            const show = target.type === 'password';
            target.type = show ? 'text' : 'password';
            button.setAttribute('aria-label', show ? button.dataset.hideLabel : button.dataset.showLabel);
            button.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        });
    });

    const confirmElement = document.getElementById('uiConfirmDialog');
    const confirmModal = confirmElement && window.bootstrap && window.bootstrap.Modal
        ? window.bootstrap.Modal.getOrCreateInstance(confirmElement)
        : null;
    const confirmMessage = confirmElement ? confirmElement.querySelector('[data-confirm-message]') : null;
    const confirmAccept = confirmElement ? confirmElement.querySelector('[data-confirm-accept]') : null;
    const confirmTypeWrap = confirmElement ? confirmElement.querySelector('.ui-confirm-type-wrap') : null;
    const confirmTypeInput = confirmElement ? confirmElement.querySelector('#uiConfirmTypeInput') : null;
    let pendingAction = null;
    let requireTypedConfirm = false;

    function askForConfirmation(message, action, options) {
        if (!message || !confirmModal || !confirmMessage || !confirmAccept) {
            action();
            return;
        }

        options = options || {};
        requireTypedConfirm = !!options.requireType;

        pendingAction = action;
        confirmMessage.textContent = message;

        // Update button label
        var label = options.buttonLabel || confirmAccept.dataset.defaultLabel || 'Confirm';
        confirmAccept.textContent = label;

        // Show/hide typed confirmation input
        if (confirmTypeWrap) {
            if (requireTypedConfirm) {
                confirmTypeWrap.classList.remove('d-none');
                if (confirmTypeInput) { confirmTypeInput.value = ''; }
                confirmAccept.disabled = true;
            } else {
                confirmTypeWrap.classList.add('d-none');
                confirmAccept.disabled = false;
            }
        }

        confirmModal.show();
    }

    // Fetch-driven panels (the lesson workspace) ask for confirmation from
    // JavaScript instead of a form submit, so they reuse this same modal.
    window.uiConfirm = function (message, action, options) {
        askForConfirmation(message, action, options || {});
    };

    // Enable accept button only when "DELETE" is typed
    if (confirmTypeInput && confirmAccept) {
        confirmTypeInput.addEventListener('input', function () {
            if (requireTypedConfirm) {
                confirmAccept.disabled = confirmTypeInput.value.trim() !== 'DELETE';
            }
        });
    }

    if (confirmElement) {
        confirmElement.addEventListener('hidden.bs.modal', function () {
            pendingAction = null;
            requireTypedConfirm = false;
            if (confirmTypeWrap) { confirmTypeWrap.classList.add('d-none'); }
            if (confirmTypeInput) { confirmTypeInput.value = ''; }
            if (confirmAccept) { confirmAccept.disabled = false; }
        });
    }

    if (confirmAccept) {
        confirmAccept.addEventListener('click', function () {
            if (confirmAccept.disabled) { return; }
            const action = pendingAction;
            pendingAction = null;
            confirmModal.hide();

            if (action) {
                window.setTimeout(action, 120);
            }
        });
    }

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmBypass === '1') {
                delete form.dataset.confirmBypass;
                return;
            }

            event.preventDefault();
            var isDelete = form.dataset.confirmDelete === '1';
            var buttonLabel = form.dataset.confirmLabel || (isDelete ? 'Permanently Delete' : undefined);
            askForConfirmation(form.getAttribute('data-confirm'), function () {
                form.dataset.confirmBypass = '1';

                if (form.requestSubmit) {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }, {
                // Critical records ask for the word DELETE as well; the form
                // carries a hidden permanent_delete field, so the server checks
                // it too and a scripted request cannot skip the dialog.
                requireType: form.dataset.confirmType === '1',
                buttonLabel: buttonLabel
            });
        });
    });

    document.querySelectorAll('a[data-confirm]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank' || link.hasAttribute('download')) {
                return;
            }

            event.preventDefault();
            askForConfirmation(link.getAttribute('data-confirm'), function () {
                window.location.href = link.href;
            });
        });
    });

    document.querySelectorAll('[data-auto-submit]').forEach(function (field) {
        field.addEventListener('change', function () {
            if (field.form) {
                field.form.submit();
            }
        });
    });

    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            const text = button.getAttribute('data-copy');

            if (!text || !navigator.clipboard) {
                return;
            }

            navigator.clipboard.writeText(text).then(function () {
                const original = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check2"></i>';
                window.setTimeout(function () {
                    button.innerHTML = original;
                }, 1400);
            });
        });
    });

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
        if (window.bootstrap && window.bootstrap.Tooltip) {
            new window.bootstrap.Tooltip(element);
        }
    });

    document.querySelectorAll('.flash-stack .alert').forEach(function (alert, index) {
        window.setTimeout(function () {
            alert.classList.add('is-leaving');
            window.setTimeout(function () {
                alert.remove();
            }, 260);
        }, 5200 + (index * 350));
    });

    document.addEventListener('keydown', function (event) {
        if (!(event.metaKey || event.ctrlKey) || event.key.toLowerCase() !== 'k') {
            return;
        }

        const search = document.querySelector('.topbar-search input[name="q"]');

        if (!search) {
            return;
        }

        event.preventDefault();
        search.focus();
        search.select();
    });
})();
