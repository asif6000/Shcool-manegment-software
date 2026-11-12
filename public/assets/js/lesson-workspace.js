/**
 * Lesson workspace.
 *
 * Three jobs, all inside the teacher dashboard so nothing reloads: the rich text
 * editor, the live preview, and the writes (create, update, publish, unpublish,
 * delete) sent to the real module routes as JSON.
 *
 * The server is the authority — every route re-checks permission, timetable
 * scope and authorship, and the stored body passes through an allowlist
 * sanitiser. What is here exists so the teacher sees their formatting while
 * typing and does not lose work to a page reload.
 */
(function () {
    if (window.__lessonWorkspace) {
        return;
    }

    window.__lessonWorkspace = true;

    const CSRF = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.getAttribute('content') : '';
    };

    /* ------------------------------------------------------------------ *
     * The preview is built from what the teacher has typed, which the
     * server has not cleaned yet, so the markup is filtered here to the
     * same allowlist before it touches the document.
     * ------------------------------------------------------------------ */
    const ALLOWED = ['P', 'BR', 'HR', 'DIV', 'SPAN', 'H2', 'H3', 'H4', 'STRONG', 'B', 'EM', 'I', 'U', 'S',
        'UL', 'OL', 'LI', 'BLOCKQUOTE', 'CODE', 'PRE', 'SUB', 'SUP',
        'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'CAPTION', 'A', 'IMG', 'FONT'];

    const DROPPED = ['SCRIPT', 'STYLE', 'IFRAME', 'FRAME', 'OBJECT', 'EMBED', 'FORM', 'INPUT', 'TEXTAREA',
        'SELECT', 'BUTTON', 'LINK', 'META', 'BASE', 'SVG', 'MATH', 'TEMPLATE', 'NOSCRIPT', 'VIDEO', 'AUDIO'];

    const ATTRIBUTES = { A: ['href'], IMG: ['src', 'alt', 'width', 'height'], FONT: [] };

    function escapeText(value) {
        const entities = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

        return String(value).replace(/[&<>"']/g, function (character) {
            return entities[character];
        });
    }

    function safeAddress(value, isImage) {        const url = String(value || '').trim();

        if (url === '') {
            return null;
        }

        if (/^(\/|\.\/|https?:\/\/)/i.test(url)) {
            return url;
        }

        if (!isImage && /^mailto:/i.test(url)) {
            return url;
        }

        return null;
    }

    function cleanNode(node) {
        Array.prototype.slice.call(node.children).forEach(function (child) {
            const tag = child.tagName.toUpperCase();

            if (DROPPED.indexOf(tag) !== -1) {
                child.remove();

                return;
            }

            cleanNode(child);

            if (ALLOWED.indexOf(tag) === -1) {
                while (child.firstChild) {
                    node.insertBefore(child.firstChild, child);
                }

                child.remove();

                return;
            }

            const keep = ATTRIBUTES[tag] || [];

            Array.prototype.slice.call(child.attributes).forEach(function (attribute) {
                const name = attribute.name.toLowerCase();

                if (keep.indexOf(name) === -1) {
                    child.removeAttribute(attribute.name);

                    return;
                }

                if (name === 'href' || name === 'src') {
                    const clean = safeAddress(attribute.value, name === 'src');

                    if (clean === null) {
                        child.removeAttribute(attribute.name);
                    } else {
                        child.setAttribute(name, clean);
                    }
                }
            });

            if (tag === 'A') {
                child.setAttribute('rel', 'noopener noreferrer nofollow');
                child.setAttribute('target', '_blank');
            }
        });
    }

    function cleanHtml(html) {
        const doc = new DOMParser().parseFromString('<div id="lsn-fresh">' + html + '</div>', 'text/html');
        const host = doc.getElementById('lsn-fresh');

        if (!host) {
            return '';
        }

        cleanNode(host);

        return host.innerHTML;
    }

    /* ------------------------------------------------------------------ *
     * Panel navigation: the dashboard already knows how to fetch one of
     * its module screens. A synthetic opener asks it to, so this file
     * never duplicates the fragment machinery.
     * ------------------------------------------------------------------ */
    function panelRoot() {
        return document.getElementById('teacher-dashboard');
    }

    function goTo(url) {
        const root = panelRoot();

        if (!root || !url) {
            if (url) {
                window.location.href = url;
            }

            return;
        }

        const opener = document.createElement('a');

        opener.setAttribute('data-td-open', 'lessons');
        opener.href = url;
        opener.style.display = 'none';
        root.appendChild(opener);
        opener.click();
        opener.remove();
    }

    function refreshInPlace(scope, fallbackUrl) {
        const panel = scope.closest('[data-td-panel]');
        const refresher = panel ? panel.querySelector('[data-lsn-refresh]') : null;

        if (refresher) {
            refresher.click();

            return;
        }

        if (fallbackUrl) {
            window.location.href = fallbackUrl;

            return;
        }

        window.location.reload();
    }

    /* ------------------------------------------------------------------ *
     * Feedback
     * ------------------------------------------------------------------ */
    // A row action re-fetches the panel a moment later so the four counts stay
    // the server's own numbers; the message is kept so it survives that refresh.
    let notice = null;

    function hostOf(scope) {
        return scope.closest('[data-lsn-form]') || scope.closest('.tp-panel-inner') || scope.closest('body');
    }

    function paint(banner, message, ok) {
        banner.textContent = message;
        banner.classList.remove('is-ok', 'is-bad');
        banner.classList.add('is-show', ok ? 'is-ok' : 'is-bad');
        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function flushNotice(scope) {
        if (!notice || Date.now() - notice.at > 5000) {
            return;
        }

        const banner = (scope && scope.querySelector ? scope.querySelector('[data-lsn-banner]') : null)
            || (panelRoot() ? panelRoot().querySelector('[data-lsn-banner]') : null);

        if (!banner) {
            return;
        }

        const pending = notice;
        notice = null;
        paint(banner, pending.message, pending.ok);
    }

    function say(scope, message, ok) {
        const host = hostOf(scope);
        const banner = host.querySelector('[data-lsn-banner]');

        if (!banner) {
            window.alert(message);

            return;
        }

        notice = { message: message, ok: ok, at: Date.now() };
        paint(banner, message, ok);
    }

    function clearErrors(scope) {
        const host = hostOf(scope);

        host.querySelectorAll('.is-invalid').forEach(function (field) {
            field.classList.remove('is-invalid');
        });

        host.querySelectorAll('[data-lsn-message]').forEach(function (note) {
            note.remove();
        });

        const global = host.querySelector('[data-lsn-global-error]');

        if (global) {
            global.textContent = '';
            global.classList.remove('is-show');
        }
    }

    function showErrors(scope, errors) {
        const host = hostOf(scope);
        const leftovers = [];

        Object.keys(errors || {}).forEach(function (field) {
            const inputs = host.querySelectorAll('[name="' + field + '"], [name="' + field + '[]"]');

            (Array.prototype.slice.call(errors[field] || [])).forEach(function (message) {
                let shown = false;

                if (inputs.length) {
                    const input = inputs[0];

                    input.classList.add('is-invalid');

                    if (input.tagName === 'DIV') {
                        return;
                    }

                    const note = document.createElement('div');

                    note.className = 'invalid-feedback d-block';
                    note.setAttribute('data-lsn-message', '1');
                    note.textContent = message;
                    input.insertAdjacentElement('afterend', note);
                    shown = true;
                }

                if (!shown) {
                    leftovers.push(message);
                }
            });
        });

        const global = host.querySelector('[data-lsn-global-error]');

        if (global && leftovers.length) {
            global.textContent = leftovers.join(' ');
            global.classList.add('is-show');
        }

        const first = host.querySelector('.is-invalid');

        if (first && first.focus) {
            first.focus();
        }
    }

    function busy(scope, on) {
        const host = hostOf(scope);

        host.querySelectorAll('[data-lsn-submit], [data-lsn-action]').forEach(function (button) {
            if (on) {
                button.dataset.lsnWasDisabled = button.disabled ? '1' : '0';
                button.disabled = true;
            } else {
                button.disabled = button.dataset.lsnWasDisabled === '1';
            }
        });
    }

    /* ------------------------------------------------------------------ *
     * The editor
     * ------------------------------------------------------------------ */
    function wordsIn(text) {
        const trimmed = String(text || '').replace(/\s+/g, ' ').trim();

        return trimmed === '' ? 0 : trimmed.split(' ').length;
    }

    function editorOf(form) {
        return form ? form.querySelector('[data-lsn-editor]') : null;
    }

    function syncField(scope) {
        const form = scope.closest('[data-lsn-form]');
        const editor = editorOf(form);
        const field = form ? form.querySelector('input[name="content"]') : null;

        if (!editor || !field) {
            return;
        }

        field.value = editor.innerHTML.trim() === '<div><br></div>' ? '' : editor.innerHTML;

        const count = wordsIn(editor.textContent);
        const foot = form.querySelector('[data-lsn-wordcount]');

        if (foot) {
            foot.textContent = count + ' ' + (count === 1 ? 'word' : 'words');
        }

        editor.classList.toggle('is-blank', count === 0 && editor.querySelector('img') === null);

        // An open preview follows the typing; a closed one is left alone.
        const pane = form.querySelector('[data-lsn-preview]');
        const body = pane ? pane.querySelector('[data-lsn-preview-body]') : null;

        if (pane && body && !pane.hidden) {
            body.innerHTML = cleanHtml(editor.innerHTML);
        }
    }

    function edit(scope, command, value) {
        const form = scope.closest('[data-lsn-form]');
        const editor = editorOf(form);

        if (!editor) {
            return;
        }

        editor.focus();

        try {
            document.execCommand('styleWithCSS', false, false);
            document.execCommand(command, false, value || null);
        } catch (error) {
            /* the browser refused; the text is still readable without it */
        }

        syncField(form);
    }

    document.addEventListener('mousedown', function (event) {
        const tool = event.target.closest('.lsn-toolbar [data-lsn-cmd], .lsn-toolbar [data-lsn-link], .lsn-toolbar [data-lsn-image]');

        // The toolbar must not steal the selection the command works on.
        if (tool) {
            event.preventDefault();
        }
    }, true);

    document.addEventListener('click', function (event) {
        const tool = event.target.closest('.lsn-toolbar button');

        if (!tool) {
            return;
        }

        const form = tool.closest('[data-lsn-form]');

        if (tool.dataset.lsnCmd) {
            if (tool.dataset.lsnCmd === 'insertTable') {
                edit(tool, 'insertHTML', '<table><tbody><tr><td></td><td></td><td></td></tr><tr><td></td><td></td><td></td></tr><tr><td></td><td></td><td></td></tr></tbody></table><p><br></p>');

                return;
            }

            edit(tool, tool.dataset.lsnCmd);

            return;
        }

        if (tool.dataset.lsnLink !== undefined) {
            const url = window.prompt('Link address (https://…)', 'https://');

            if (url && safeAddress(url, false) !== null) {
                edit(tool, 'createLink', url);
            }

            return;
        }

        if (tool.dataset.lsnImage !== undefined) {
            const picker = form ? form.querySelector('[data-lsn-file-input]') : null;

            if (picker) {
                picker.click();
            }
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-lsn-block]')) {
            edit(event.target, 'formatBlock', '<' + event.target.value + '>');
        }

        if (event.target.matches('[data-lsn-file-input]')) {
            uploadImage(event.target);
        }
    });

    function uploadImage(input) {
        const form = input.closest('[data-lsn-form]');
        const file = input.files && input.files[0];

        if (!form || !file) {
            return;
        }

        const maxKb = parseInt(form.getAttribute('data-lsn-image-max') || '0', 10);

        if (maxKb && file.size > maxKb * 1024) {
            say(form, 'That picture is larger than ' + Math.round(maxKb / 1024) + ' MB. Use a smaller one.', false);
            input.value = '';

            return;
        }

        const body = new FormData();

        body.append('file', file);

        busy(form, true);

        fetch(form.getAttribute('data-lsn-image-url'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': CSRF(), 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.data.url) {
                    throw new Error((result.data && result.data.message) || 'The picture could not be uploaded.');
                }

                const editor = editorOf(form);

                if (editor) {
                    editor.focus();
                    document.execCommand('insertHTML', false,
                        '<img src="' + escapeText(result.data.url) + '" alt="' + escapeText(result.data.name || '') + '"><p><br></p>');
                }

                syncField(form);
            })
            .catch(function (error) {
                say(form, error.message || 'The picture could not be uploaded.', false);
            })
            .then(function () {
                busy(form, false);
                input.value = '';
            });
    }

    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-lsn-editor]')) {
            syncField(event.target);
        }
    });

    document.addEventListener('paste', function (event) {
        const editor = event.target.closest('[data-lsn-editor]');

        if (!editor) {
            return;
        }

        event.preventDefault();

        const text = (event.clipboardData || window.clipboardData).getData('text/plain');

        document.execCommand('insertText', false, text);
    });

    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('[data-lsn-preview-toggle]');

        if (!toggle) {
            return;
        }

        const form = toggle.closest('[data-lsn-form]');
        const pane = form ? form.querySelector('[data-lsn-preview]') : null;
        const body = pane ? pane.querySelector('[data-lsn-preview-body]') : null;
        const editor = editorOf(form);

        if (!pane || !body || !editor) {
            return;
        }

        const open = pane.hidden;

        if (open) {
            body.innerHTML = cleanHtml(editor.innerHTML);
        }

        pane.hidden = !open;
        toggle.setAttribute('aria-pressed', open ? 'true' : 'false');
    });

    /* ------------------------------------------------------------------ *
     * Saving the lesson
     * ------------------------------------------------------------------ */
    document.addEventListener('click', function (event) {
        const pressed = event.target.closest('[data-lsn-submit]');

        if (pressed && pressed.form) {
            const radio = pressed.form.querySelector('input[name="status"][value="' + pressed.dataset.lsnSubmit + '"]');

            if (radio) {
                radio.checked = true;
            }
        }
    });

    // Capture phase, before the dashboard's own submit handler can decide to
    // post the form the ordinary way.
    document.addEventListener('submit', function (event) {
        const form = event.target.closest('[data-lsn-form]');

        if (!form || !panelRoot() || !form.closest('#teacher-dashboard')) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        syncField(form);

        const body = new FormData(form);
        const host = form.closest('[data-td-panel]');

        body.append('workspace', 'teacher');
        body.append('panel', host ? host.getAttribute('data-td-panel') : 'lessons');

        busy(form, true);
        clearErrors(form);

        fetch(form.getAttribute('action'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': CSRF(), 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            body: body,
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    let data = {};

                    try {
                        data = JSON.parse(text);
                    } catch (error) {
                        data = {};
                    }

                    return { status: response.status, ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.status === 422 && result.data.errors) {
                    showErrors(form, result.data.errors);

                    return;
                }

                if (!result.ok || !result.data.lesson) {
                    say(form, (result.data && result.data.message) || 'The lesson could not be saved. Reload the page and try again.', false);

                    return;
                }

                say(form, result.data.message, true);
                window.setTimeout(function () {
                    goTo(result.data.lesson.urls.show);
                }, 350);
            })
            .catch(function () {
                say(form, 'The lesson could not be saved. Check your connection and try again.', false);
            })
            .then(function () {
                busy(form, false);
            });
    }, true);

    /* ------------------------------------------------------------------ *
     * Publish, unpublish, delete
     * ------------------------------------------------------------------ */
    function paintRow(row, lesson) {
        const published = lesson.status === 'published';

        ['badge-published', 'badge-draft', 'publish', 'unpublish', 'delete'].forEach(function (part) {
            const node = row.querySelector('[data-lsn-part="' + part + '"]');

            if (!node) {
                return;
            }

            const show = {
                'badge-published': published,
                'badge-draft': !published,
                publish: !published,
                unpublish: published,
                delete: !published || row.getAttribute('data-lsn-delete-published') === '1',
            }[part];

            node.classList.toggle('d-none', !show);
        });

        const set = function (name, value) {
            const cell = row.querySelector('[data-lsn-field="' + name + '"]');

            if (cell) {
                cell.textContent = value || '—';
            }
        };

        set('class', lesson.class);
        set('section', lesson.section);
        set('subject', lesson.subject);
        set('chapter', lesson.chapter);

        // The cell also names the teacher, which does not change, so only the
        // timestamp is rewritten.
        const when = row.querySelector('[data-lsn-when]');

        if (when) {
            when.textContent = lesson.updated_at || '—';
        }
    }

    const CONFIRM = {
        unpublish: function (title) { return 'Unpublish “' + title + '”? The class stops seeing it and it returns to your drafts.' },
        delete: function (title) { return 'Delete the draft “' + title + '”? The lesson and its files leave the list.' },
    };

    function runAction(button, action) {
        busy(button, true);

        fetch(button.dataset.lsnUrl, {
            // The withdraw route really is a DELETE; the two status routes are
            // plain posts. Either way the CSRF token travels in the header.
            method: action === 'delete' ? 'DELETE' : 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': CSRF(), 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            body: action === 'delete' ? undefined : new URLSearchParams({ workspace: 'teacher', panel: 'lessons' }),
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    let data = {};

                    try {
                        data = JSON.parse(text);
                    } catch (error) {
                        data = {};
                    }

                    return { status: response.status, ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    const message = result.status === 422 && result.data.errors
                        ? Object.keys(result.data.errors)
                            .map(function (key) { return (result.data.errors[key] || []).join(' '); })
                            .join(' ')
                        : (result.data.message || 'That change could not be made.');

                    say(button, message, false);

                    // The row on screen no longer matches the server, so ask for
                    // the honest version rather than guess at it.
                    if (result.status !== 422) {
                        refreshInPlace(button, null);
                    }

                    return;
                }

                const row = button.closest('tr[data-lsn-row]');

                if (action === 'delete') {
                    if (row) {
                        say(row, result.data.message || 'The lesson was deleted.', true);
                        row.classList.add('is-leaving');
                        window.setTimeout(function () { row.remove(); }, 260);
                        window.setTimeout(function () { refreshInPlace(button, null); }, 320);

                        return;
                    }

                    const index = (button.closest('[data-td-panel]') || document).querySelector('[data-lsn-index]');

                    goTo(index ? index.getAttribute('data-lsn-index') : null);

                    return;
                }

                if (row) {
                    paintRow(row, result.data.lesson);
                    say(row, result.data.message, true);

                    // The four counts are the server's, so the panel is asked for
                    // them again once the row has been patched in place.
                    window.setTimeout(function () { refreshInPlace(button, null); }, 1200);

                    return;
                }

                refreshInPlace(button, result.data.lesson ? result.data.lesson.urls.show : null);
            })
            .catch(function () {
                say(button, 'The change did not reach the server. Reload and try again.', false);
            })
            .then(function () {
                busy(button, false);
            });
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-lsn-action]');

        if (!button) {
            return;
        }

        const action = button.dataset.lsnAction;
        const makeSure = CONFIRM[action];

        if (!makeSure) {
            runAction(button, action);

            return;
        }

        const deleting = action === 'delete';
        const message = deleting
            ? 'Are you sure you want to permanently delete this record? This action cannot be undone.'
            : makeSure(button.dataset.lsnTitle || 'this lesson');

        if (window.uiConfirm) {
            window.uiConfirm(message, function () {
                runAction(button, action);
            }, deleting ? { buttonLabel: 'Permanently Delete' } : {});

            return;
        }

        if (window.confirm(message)) {
            runAction(button, action);
        }
    });

    /* Class, section and subject are really one decision. The period map the
       server drew the form from is handed over, so changing one rewrites the
       ones after it and no combination can be picked that the save would refuse. */
    function primePeriods(form) {
        const session = form.querySelector('#lsn-session');
        const klass = form.querySelector('#lsn-class');
        const section = form.querySelector('#lsn-section');
        const subject = form.querySelector('#lsn-subject');
        const raw = form.getAttribute('data-lsn-periods');

        if (!raw || !klass || !section || !subject || form.dataset.lsnPeriodsBound) {
            return;
        }

        form.dataset.lsnPeriodsBound = '1';

        let map;

        try {
            map = JSON.parse(raw);
        } catch (error) {
            return;
        }

        const wholeClass = (section.querySelector('option[value=""]') || {}).textContent
            || 'Whole class (all sections)';

        function unique(rows, key) {
            const seen = {};

            return rows.filter(function (row) {
                const value = String(row[key]);

                if (seen[value]) {
                    return false;
                }

                seen[value] = true;

                return true;
            });
        }

        function fill(select, rows, valueKey, labelKey, blankLabel) {
            const had = select.value;
            select.innerHTML = '';

            if (blankLabel) {
                const blank = document.createElement('option');
                blank.value = '';
                blank.textContent = blankLabel;
                select.appendChild(blank);
            }

            rows.forEach(function (row) {
                const option = document.createElement('option');
                option.value = row[valueKey];
                option.textContent = row[labelKey];
                select.appendChild(option);
            });

            // The blank "whole class" choice is a real answer, so it survives a
            // repaint instead of being snapped back onto the first section.
            const still = had === '' && blankLabel
                ? true
                : rows.some(function (row) {
                    return String(row[valueKey]) === had;
                });

            select.value = still ? had : (rows.length ? String(rows[0][valueKey]) : '');
        }

        function repaint() {
            const all = (map[String(session ? session.value : '')] || []);

            // Each list is filled before the next one is worked out, so a class
            // change reads the section it just landed on rather than the old one.
            fill(klass, unique(all, 'class'), 'class', 'class_label', null);

            const chosenClass = Number(klass.value);
            const inClass = all.filter(function (row) {
                return row.class === chosenClass;
            });

            fill(section, unique(inClass, 'section'), 'section', 'section_label', wholeClass);

            const chosenSection = section.value === '' ? 0 : Number(section.value);
            const inSection = chosenSection
                ? inClass.filter(function (row) {
                    return row.section === chosenSection;
                })
                : inClass;

            fill(subject, unique(inSection, 'subject'), 'subject', 'subject_label', null);
        }

        [session, klass, section].forEach(function (select) {
            if (select) {
                select.addEventListener('change', repaint);
            }
        });
    }

    /* Every editor on the page starts life in step with its hidden field. */
    function prime(scope) {
        (scope || document).querySelectorAll('[data-lsn-form]').forEach(function (form) {
            const field = form.querySelector('input[name="content"]');
            const editor = editorOf(form);

            if (editor && field && editor.innerHTML.trim() === '' && field.value !== '') {
                editor.innerHTML = field.value;
            }

            primePeriods(form);
            syncField(editor || form);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { prime(document); });
    } else {
        prime(document);
    }

    // A fragment the dashboard injects arrives after this file has run, so the
    // editor in it has to be wired as it appears.
    if (window.MutationObserver) {
        new MutationObserver(function (records) {
            records.forEach(function (record) {
                Array.prototype.forEach.call(record.addedNodes, function (node) {
                    if (node.nodeType !== 1) {
                        return;
                    }

                    if (node.matches('[data-lsn-form]') || node.querySelector('[data-lsn-form]')) {
                        prime(document);
                    }

                    flushNotice(node);
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }
}());
