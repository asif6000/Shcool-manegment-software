/*
 * Class video cards on the school home page. The card carries the player address
 * the server built, and only two shapes are accepted: our own YouTube embed or a
 * file on this site. Nothing is written into the page as HTML, so a title can
 * never become markup.
 */
(function () {
    'use strict';

    var modalElement = document.getElementById('pubVideoModal');
    var stage = document.getElementById('pubVideoStage');
    var titleTarget = document.getElementById('pubVideoTitle');
    var cards = document.querySelectorAll('.pub-video-card');

    if (!modalElement || !stage || !cards.length) {
        return;
    }

    var modal = null;

    function modalInstance() {
        if (modal === null && window.bootstrap && window.bootstrap.Modal) {
            modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
        }

        return modal;
    }

    function youtubeEmbed(src) {
        var isOurs = src.indexOf('https://www.youtube.com/embed/') === 0;

        if (!isOurs) {
            return null;
        }

        var frame = document.createElement('iframe');
        frame.className = 'pub-video-embed';
        frame.src = src + (src.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1';
        frame.title = 'ভিডিও পাঠ';
        frame.loading = 'lazy';
        frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        frame.referrerPolicy = 'strict-origin-when-cross-origin';
        frame.setAttribute('allowfullscreen', '');

        return frame;
    }

    function filePlayer(src) {
        var isOurs = src.indexOf('/') === 0 || src.indexOf(window.location.origin) === 0;

        if (!isOurs) {
            return null;
        }

        var player = document.createElement('video');
        player.className = 'pub-video-file';
        player.src = src;
        player.controls = true;
        player.playsInline = true;
        player.preload = 'metadata';
        player.setAttribute('controlsList', 'nodownload');

        var starting = player.play();

        if (starting && typeof starting.catch === 'function') {
            starting.catch(function () {
                /* The visitor can still press play inside the player. */
            });
        }

        return player;
    }

    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            var src = card.getAttribute('data-video-src') || '';
            var type = card.getAttribute('data-video-type') === 'file' ? 'file' : 'youtube';
            var player = type === 'file' ? filePlayer(src) : youtubeEmbed(src);

            if (!player) {
                return;
            }

            stage.innerHTML = '';
            stage.appendChild(player);

            if (titleTarget) {
                titleTarget.textContent = card.getAttribute('data-video-title') || '';
            }

            var instance = modalInstance();

            if (instance) {
                instance.show();
            }
        });
    });

    /* Closing must stop the clip, or a hidden YouTube frame keeps talking. */
    modalElement.addEventListener('hidden.bs.modal', function () {
        stage.innerHTML = '';
    });
})();
