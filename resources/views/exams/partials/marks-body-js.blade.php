    <script>
        document.querySelectorAll('[data-absent-toggle]').forEach(function (checkbox) {
            const input = document.getElementById(checkbox.getAttribute('data-absent-toggle'));

            if (!input) {
                return;
            }

            const sync = function () {
                input.disabled = checkbox.checked || checkbox.disabled;

                if (checkbox.checked) {
                    input.value = '';
                }
            };

            checkbox.addEventListener('change', sync);
            sync();
        });
    </script>
