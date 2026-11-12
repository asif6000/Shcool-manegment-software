/* Draws the signed verification QR onto every card on the sheet before print. */
(function () {
    function render() {
        const targets = document.querySelectorAll('[data-qr]');

        if (typeof qrcode !== 'function') {
            targets.forEach(function (target) {
                target.innerHTML = '<span style="font-size:.4rem;">' + target.getAttribute('data-qr') + '</span>';
            });

            return;
        }

        targets.forEach(function (target) {
            const code = qrcode(0, 'M');
            code.addData(target.getAttribute('data-qr'));
            code.make();
            target.innerHTML = code.createSvgTag({ cellSize: 3, margin: 1, scalable: true });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', render);
    } else {
        render();
    }
})();
