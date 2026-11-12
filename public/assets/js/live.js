(function () {
    'use strict';

    const script = document.querySelector('[data-live-url]');

    if (!script) {
        return;
    }

    const url = script.getAttribute('data-live-url');
    const intervalMs = 20000;
    const statNodes = Array.prototype.slice.call(document.querySelectorAll('[data-live-stat]'));
    const noticeHost = document.querySelector('[data-live-notices]');
    const updatedHint = document.querySelector('[data-live-updated]');
    const updatedTime = document.querySelector('[data-live-updated-time]');

    let noticeSignature = '';
    let failures = 0;
    let timer = null;

    function noticeRow(notice) {
        const row = document.createElement('a');
        row.className = 'pub-notice-row';
        row.href = notice.url;

        const date = document.createElement('span');
        date.className = 'pub-notice-date';
        const day = document.createElement('strong');
        day.textContent = notice.day;
        const month = document.createElement('small');
        month.textContent = notice.month;
        date.append(day, month);

        const text = document.createElement('span');
        text.className = 'pub-notice-text';
        const title = document.createElement('strong');
        title.textContent = notice.title;
        const ago = document.createElement('small');
        ago.textContent = notice.ago;
        text.append(title, ago);

        const chevron = document.createElement('i');
        chevron.className = 'bi bi-chevron-right pub-notice-chevron';

        row.append(date, text, chevron);

        return row;
    }

    function renderNotices(notices) {
        if (!noticeHost || !Array.isArray(notices)) {
            return;
        }

        const signature = notices.map(function (notice) {
            return [notice.id, notice.title, notice.day, notice.month, notice.ago, notice.pinned].join('|');
        }).join('#');

        if (signature === noticeSignature) {
            return;
        }

        noticeSignature = signature;
        noticeHost.textContent = '';

        if (notices.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'pub-empty';
            empty.textContent = 'এখনো কোনো নোটিস প্রকাশিত হয়নি।';
            noticeHost.appendChild(empty);

            return;
        }

        notices.forEach(function (notice) {
            noticeHost.appendChild(noticeRow(notice));
        });
    }

    function renderStats(stats) {
        if (!stats) {
            return;
        }

        statNodes.forEach(function (node) {
            const value = Number(stats[node.getAttribute('data-live-stat')]);

            if (!Number.isFinite(value) || node.textContent.trim() === String(value)) {
                return;
            }

            node.textContent = String(value);
            node.classList.add('is-live-updated');
            window.setTimeout(function () {
                node.classList.remove('is-live-updated');
            }, 1400);
        });
    }

    function markUpdated() {
        if (!updatedHint || !updatedTime) {
            return;
        }

        updatedTime.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
        updatedTime.setAttribute('datetime', new Date().toISOString());
        updatedHint.classList.remove('invisible');
    }

    function tick() {
        window.fetch(url, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('live request failed');
                }

                failures = 0;

                return response.json();
            })
            .then(function (payload) {
                renderStats(payload.stats);
                renderNotices(payload.notices);
                markUpdated();
            })
            .catch(function () {
                failures += 1;

                if (failures >= 3 && timer !== null) {
                    window.clearInterval(timer);
                    timer = null;
                }
            });
    }

    function start() {
        tick();

        if (timer === null) {
            timer = window.setInterval(tick, intervalMs);
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            if (timer !== null) {
                window.clearInterval(timer);
                timer = null;
            }

            return;
        }

        start();
    });

    start();
})();
