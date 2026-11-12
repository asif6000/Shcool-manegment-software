<script>
(function () {
    const form = document.getElementById('attendance-form');
    if (!form) return;

    const selects = Array.from(form.querySelectorAll('select[data-attendance-status]'));
    const chips = Array.from(form.querySelectorAll('[data-count-status]'));
    const fieldset = document.getElementById('attendance-fieldset');
    const override = document.getElementById('attendance-override');

    function refreshCounts() {
        const counts = {};
        chips.forEach(function (chip) {
            counts[chip.getAttribute('data-count-status')] = 0;
        });
        selects.forEach(function (select) {
            if (counts[select.value] !== undefined) {
                counts[select.value] += 1;
            }
        });
        chips.forEach(function (chip) {
            chip.textContent = counts[chip.getAttribute('data-count-status')];
        });
    }

    selects.forEach(function (select) {
        select.addEventListener('change', function () {
            refreshCounts();
            refreshCorrectionHint();
        });
    });

    const reasonInput = document.getElementById('correction-reason');

    function refreshCorrectionHint() {
        if (! reasonInput) return;

        const submitted = reasonInput.getAttribute('data-submitted') === '1';

        const needsReason = selects.some(function (select) {
            const changed = select.value !== select.getAttribute('data-original');
            return changed && (submitted || select.hasAttribute('data-locked'));
        });

        reasonInput.required = needsReason;
        reasonInput.classList.toggle('is-invalid', false);
    }

    form.querySelectorAll('[data-bulk]').forEach(function (button) {
        button.addEventListener('click', function () {
            const value = button.getAttribute('data-bulk');
            selects.forEach(function (select) {
                select.value = value;
            });
            refreshCounts();
            refreshCorrectionHint();
        });
    });

    if (override && fieldset) {
        override.addEventListener('change', function () {
            fieldset.disabled = ! override.checked;
        });
    }

    const search = document.getElementById('attendance-search');
    const tableRows = Array.from(form.querySelectorAll('tbody tr'));

    if (search) {
        search.addEventListener('input', function () {
            const query = search.value.trim().toLowerCase();

            tableRows.forEach(function (row) {
                row.hidden = query !== '' && ! row.textContent.toLowerCase().includes(query);
            });
        });
    }

    refreshCounts();
    refreshCorrectionHint();
})();
</script>
