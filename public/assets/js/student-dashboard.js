(function () {
    'use strict';

    var modules = Array.prototype.slice.call(document.querySelectorAll('.portal-module'));
    var targets = Array.prototype.slice.call(document.querySelectorAll('[data-module-target]'));
    var currentSlot = document.querySelector('[data-portal-current]');

    function syncCurrentLabel(name) {
        if (!currentSlot) {
            return;
        }

        var card = document.querySelector('.module-grid .module-card[data-module-target="' + name + '"]');

        if (!card) {
            return;
        }

        var icon = currentSlot.querySelector('i');
        var cardIcon = card.querySelector('.module-card-icon i');

        if (icon && cardIcon) {
            icon.className = cardIcon.className;
        }

        currentSlot.querySelector('.label').textContent = card.querySelector('.module-card-label').textContent;
    }

    function showModule(name) {
        var found = false;

        modules.forEach(function (section) {
            var active = section.getAttribute('data-module') === name;
            section.hidden = !active;
            found = found || active;
        });

        if (!found) {
            name = 'dashboard';
            modules.forEach(function (section) {
                section.hidden = section.getAttribute('data-module') !== name;
            });
        }

        targets.forEach(function (target) {
            var active = target.getAttribute('data-module-target') === name;
            target.classList.toggle('is-active', active);
            target.setAttribute('aria-pressed', active ? 'true' : 'false');

            if (!target.classList.contains('module-card')) {
                return;
            }

            if (active) {
                target.setAttribute('aria-current', 'true');
            } else {
                target.removeAttribute('aria-current');
            }
        });

        syncCurrentLabel(name);

        try {
            if (name === 'dashboard') {
                window.location.hash = '';
            } else {
                window.location.hash = name;
            }
        } catch (error) {}
    }

    targets.forEach(function (target) {
        target.addEventListener('click', function () {
            showModule(target.getAttribute('data-module-target'));
        });
    });

    var hash = window.location.hash.length > 1 ? window.location.hash.slice(1) : null;
    var forced = document.querySelector('[data-open-module]');
    var OPEN_KEY = 'edunest.openModule';

    function storedModule() {
        try {
            var value = sessionStorage.getItem(OPEN_KEY);

            if (value) {
                sessionStorage.removeItem(OPEN_KEY);
            }

            return value;
        } catch (error) {
            return null;
        }
    }

    document.querySelectorAll('[data-remember-module]').forEach(function (form) {
        form.addEventListener('submit', function () {
            try {
                sessionStorage.setItem(OPEN_KEY, form.getAttribute('data-remember-module'));
            } catch (error) {}
        });
    });

    if (hash) {
        showModule(hash);
    } else if (forced) {
        showModule(forced.getAttribute('data-open-module'));
    } else {
        var remembered = storedModule();

        if (remembered) {
            showModule(remembered);
        } else {
            syncCurrentLabel('dashboard');
        }
    }

    var homeworkStatusFilter = document.querySelector('[data-homework-filter]');
    var homeworkTypeFilter = document.querySelector('[data-homework-type]');

    if (homeworkStatusFilter && homeworkTypeFilter) {
        var applyHomeworkFilters = function () {
            var status = homeworkStatusFilter.value;
            var type = homeworkTypeFilter.value;

            document.querySelectorAll('[data-hw-row]').forEach(function (row) {
                var statusMatches = status === 'all' || row.getAttribute('data-hw-status') === status;
                var typeMatches = type === 'all' || row.getAttribute('data-hw-type') === type;

                row.hidden = ! (statusMatches && typeMatches);
            });
        };

        [homeworkStatusFilter, homeworkTypeFilter].forEach(function (control) {
            control.addEventListener('change', applyHomeworkFilters);
        });
    }

    if (!window.Chart) {
        return;
    }

    var donutColors = ['#10b981', '#ef4444', '#f59e0b', '#3b82f6'];

    document.querySelectorAll('canvas[id^="attendanceDonut"]').forEach(function (canvas) {
        var values = JSON.parse(canvas.getAttribute('data-values') || '[0,0,0,0]');

        if (values.reduce(function (a, b) { return a + b; }, 0) === 0) {
            return;
        }

        new window.Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late', 'Leave'],
                datasets: [{ data: values, backgroundColor: donutColors, borderWidth: 0 }]
            },
            options: {
                cutout: '72%',
                plugins: { legend: { display: false }, tooltip: { enabled: true } },
                maintainAspectRatio: false
            }
        });
    });

    var trend = document.getElementById('attendanceTrend');

    if (trend) {
        var labels = JSON.parse(trend.getAttribute('data-labels') || '[]');
        var points = JSON.parse(trend.getAttribute('data-values') || '[]').map(function (v) {
            return v === null ? null : Number(v);
        });

        new window.Chart(trend, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Attendance %',
                    data: points,
                    backgroundColor: '#2563eb',
                    borderRadius: 6,
                    maxBarThickness: 36
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + '%'; } } },
                    x: { grid: { display: false } }
                },
                plugins: { legend: { display: false } },
                maintainAspectRatio: true
            }
        });
    }
})();
