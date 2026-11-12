(function () {
    'use strict';

    const root = document.querySelector('[data-ticker]');

    if (!root) {
        return;
    }

    const viewport = root.querySelector('[data-ticker-viewport]');
    const track = root.querySelector('[data-ticker-track]');
    const toggle = root.querySelector('[data-ticker-toggle]');
    const toggleIcon = root.querySelector('[data-ticker-icon]');
    const dotsHost = root.querySelector('[data-ticker-dots]');
    const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

    if (!viewport || !track) {
        return;
    }

    const firstGroup = track.querySelector('[data-ticker-group]');
    const items = firstGroup ? Array.prototype.slice.call(firstGroup.querySelectorAll('[data-ticker-item]')) : [];
    const speed = 70;
    const dots = [];

    let userPaused = false;
    let groupWidth = 0;
    let activeIndex = -1;
    let syncTimer = null;

    function isStaticMode() {
        return motionQuery.matches;
    }

    function removeClones() {
        Array.prototype.forEach.call(track.querySelectorAll('[data-ticker-clone]'), function (node) {
            node.remove();
        });
    }

    function padTrack() {
        if (!groupWidth) {
            return;
        }

        const needed = Math.ceil(viewport.clientWidth / groupWidth) + 1;

        for (let i = 1; i < needed; i++) {
            const clone = firstGroup.cloneNode(true);
            clone.setAttribute('aria-hidden', 'true');
            clone.setAttribute('data-ticker-clone', '');

            Array.prototype.forEach.call(clone.querySelectorAll('a, button'), function (link) {
                link.setAttribute('tabindex', '-1');
            });

            track.appendChild(clone);
        }
    }

    function measure() {
        removeClones();

        const rect = firstGroup ? firstGroup.getBoundingClientRect() : null;
        groupWidth = rect ? rect.width : 0;

        if (!groupWidth || isStaticMode()) {
            return;
        }

        track.style.setProperty('--pub-ticker-distance', groupWidth + 'px');
        track.style.setProperty('--pub-ticker-duration', Math.max(groupWidth / speed, 14).toFixed(1) + 's');
        padTrack();

        const groupDistance = groupWidth / speed;
        let delay = 0;

        try {
            delay = parseFloat(window.getComputedStyle(track).animationDelay) || 0;
        } catch (error) {
            delay = 0;
        }

        // Keep the current position stable across a re-measure.
        if (delay < 0) {
            track.style.animationDelay = (-((Math.abs(delay) % groupDistance))).toFixed(2) + 's';
        }
    }

    function setPaused(paused) {
        root.classList.toggle('is-paused', paused);

        if (!toggle) {
            return;
        }

        toggle.setAttribute('aria-pressed', paused ? 'true' : 'false');
        toggle.setAttribute('aria-label', paused ? 'নোটিশ স্ক্রল চালু করুন' : 'নোটিশ স্ক্রল থামান');

        if (toggleIcon) {
            toggleIcon.className = paused ? 'bi bi-play-fill' : 'bi bi-pause-fill';
        }
    }

    function buildDots() {
        if (!dotsHost) {
            return;
        }

        dotsHost.innerHTML = '';
        dots.length = 0;

        if (items.length < 2) {
            dotsHost.hidden = true;

            return;
        }

        dotsHost.hidden = false;

        items.forEach(function (item, index) {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'pub-ticker-dot';
            dot.setAttribute('aria-label', (index + 1) + ' নম্বর নোটিশ দেখুন');
            dot.addEventListener('click', function () {
                jumpTo(index);
            });

            dotsHost.appendChild(dot);
            dots.push(dot);
        });
    }

    function jumpTo(index) {
        const item = items[index];

        if (!item) {
            return;
        }

        if (isStaticMode()) {
            viewport.scrollTo({ left: item.offsetLeft, behavior: 'auto' });
            setActive(index);

            return;
        }

        const distance = Math.max(item.offsetLeft, 0);

        track.style.animationDelay = (-(distance / speed)).toFixed(2) + 's';
        syncDots();
    }

    function setActive(index) {
        if (index === activeIndex) {
            return;
        }

        activeIndex = index;

        dots.forEach(function (dot, dotIndex) {
            dot.classList.toggle('is-active', dotIndex === index);
            dot.setAttribute('aria-current', dotIndex === index ? 'true' : 'false');
        });
    }

    function trackOffset() {
        const transform = window.getComputedStyle(track).transform;

        if (!transform || transform === 'none') {
            return 0;
        }

        const matrix = transform.match(/matrix3?d?\((.+)\)/);

        if (!matrix) {
            return 0;
        }

        const parts = matrix[1].split(',').map(function (part) {
            return parseFloat(part);
        });

        return parts.length >= 13 ? parts[12] : parts[4];
    }

    function currentIndex() {
        if (isStaticMode()) {
            const left = viewport.scrollLeft;
            let index = 0;

            items.forEach(function (item, itemIndex) {
                if (item.offsetLeft <= left + 4) {
                    index = itemIndex;
                }
            });

            return index;
        }

        if (!groupWidth) {
            return 0;
        }

        const position = ((-trackOffset() % groupWidth) + groupWidth) % groupWidth;
        let index = 0;

        items.forEach(function (item, itemIndex) {
            if (item.offsetLeft <= position + 4) {
                index = itemIndex;
            }
        });

        return index;
    }

    function syncDots() {
        if (!dots.length) {
            return;
        }

        setActive(currentIndex());
    }

    function startSync() {
        stopSync();
        syncTimer = window.setInterval(syncDots, 350);
    }

    function stopSync() {
        if (syncTimer !== null) {
            window.clearInterval(syncTimer);
            syncTimer = null;
        }
    }

    function applyMode() {
        const staticMode = isStaticMode();
        root.classList.toggle('is-static', staticMode);

        if (toggle) {
            toggle.hidden = staticMode;
        }

        if (staticMode) {
            stopSync();
            removeClones();
            track.style.removeProperty('animation-delay');
            track.style.removeProperty('--pub-ticker-distance');
            track.style.removeProperty('--pub-ticker-duration');
            syncDots();

            return;
        }

        setPaused(userPaused);
        measure();
        syncDots();
        startSync();
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            userPaused = !userPaused;
            setPaused(userPaused);
        });
    }

    viewport.addEventListener('scroll', function () {
        if (isStaticMode()) {
            syncDots();
        }
    }, { passive: true });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopSync();
        } else if (!isStaticMode()) {
            startSync();
        }
    });

    if (typeof ResizeObserver === 'function') {
        let resizeTimer = null;

        new ResizeObserver(function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(function () {
                if (!isStaticMode()) {
                    measure();
                }
            }, 150);
        }).observe(viewport);
    }

    if (typeof motionQuery.addEventListener === 'function') {
        motionQuery.addEventListener('change', applyMode);
    } else if (typeof motionQuery.addListener === 'function') {
        motionQuery.addListener(applyMode);
    }

    buildDots();
    applyMode();

    // The track width is measured before the webfont has necessarily been
    // applied, so re-measure once it swaps in and the text reflows.
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () {
            if (!isStaticMode()) {
                measure();
                syncDots();
            }
        });
    }
})();
