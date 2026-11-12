/**
 * Profile picture self-service for the student and teacher portals.
 *
 * The widget stays a plain form, so it still uploads when this file never runs.
 * With JavaScript it adds a preview, an optional square crop with pan and zoom,
 * and a real progress bar. Cropping is only ever what the user frames:
 * "Upload original" sends the bytes exactly as the camera made them, so the
 * shape of a picture nobody cropped is never touched.
 */
(function () {
    'use strict';

    var MAX_CROP_SIDE = 2000;
    var JPEG_QUALITY = 0.92;
    var NOTICE_KEY = 'edunest.photoNotice';

    function init(root) {
        var strings = {};
        try {
            strings = JSON.parse(root.getAttribute('data-strings') || '{}');
        } catch (error) {
            strings = {};
        }

        function t(key, replacements) {
            var text = strings[key] || key;
            Object.keys(replacements || {}).forEach(function (name) {
                text = text.replace('{' + name + '}', replacements[name]);
            });
            return text;
        }

        var image = root.querySelector('[data-ppw-image]');
        var initials = root.querySelector('[data-ppw-initials]');
        var gallery = root.querySelector('[data-ppw-file]');
        var camera = root.querySelector('[data-ppw-camera]');
        var feedback = root.querySelector('[data-ppw-feedback]');
        var editor = root.querySelector('[data-ppw-editor]');
        var canvas = root.querySelector('[data-ppw-canvas]');
        var ring = root.querySelector('.ppw-crop-ring');
        var zoomRange = root.querySelector('[data-ppw-zoom]');
        var progress = root.querySelector('[data-ppw-progress]');
        var bar = root.querySelector('[data-ppw-bar]');
        var barRole = root.querySelector('[data-ppw-bar-role]');
        var percent = root.querySelector('[data-ppw-percent]');
        var plain = root.querySelector('[data-ppw-plain]');
        var uploadForm = root.querySelector('.ppw-upload');
        var removeOpen = root.querySelector('[data-ppw-remove-open]');
        var removeSubmit = root.querySelector('[data-ppw-remove-submit]');
        var maxKb = parseInt(root.getAttribute('data-max-kb'), 10) || 5120;

        if (plain) {
            plain.classList.add('is-hidden');
        }

        var state = { file: null, bitmap: null, zoom: 1, ox: 0, oy: 0, side: 280, busy: false };
        var ctx = canvas.getContext('2d');

        function show(text, kind) {
            feedback.textContent = text;
            feedback.classList.remove('is-hidden', 'is-success', 'is-error');
            feedback.classList.add(kind || '');
        }

        function hideMessage() {
            feedback.textContent = '';
            feedback.classList.add('is-hidden');
            feedback.classList.remove('is-success', 'is-error');
        }

        function setProgress(fraction) {
            var value = Math.max(0, Math.min(100, Math.round(fraction * 100)));
            progress.hidden = false;
            bar.style.width = value + '%';
            percent.textContent = value + '%';
            barRole.setAttribute('aria-valuenow', String(value));
        }

        function clearProgress() {
            progress.hidden = true;
            bar.style.width = '0%';
            percent.textContent = '0%';
            barRole.setAttribute('aria-valuenow', '0');
        }

        function sizeText(kb) {
            return kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : Math.round(kb) + ' KB';
        }

        function accepts(file) {
            var type = (file.type || '').toLowerCase();
            if (['image/jpeg', 'image/png', 'image/webp'].indexOf(type) === -1) {
                show(t('Only JPEG, PNG and WebP images are allowed.'), 'is-error');
                return false;
            }
            if (file.size > maxKb * 1024) {
                show(t('That image is {size}. The limit is {limit}. Crop it, or pick a smaller file.', {
                    size: sizeText(file.size / 1024),
                    limit: sizeText(maxKb),
                }), 'is-error');
                return false;
            }
            hideMessage();
            return true;
        }

        // ---- preview and crop ------------------------------------------------
        function measure() {
            var frame = canvas.parentElement;
            var css = Math.max(180, Math.min(320, frame.clientWidth || 280));
            var dpr = window.devicePixelRatio || 1;
            state.side = css;
            canvas.style.width = css + 'px';
            canvas.style.height = css + 'px';
            canvas.width = Math.round(css * dpr);
            canvas.height = Math.round(css * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

            if (ring) {
                ring.style.width = css + 'px';
            }
        }

        function scaleFor(zoom) {
            if (!state.bitmap) {
                return 1;
            }
            return Math.max(state.side / state.bitmap.width, state.side / state.bitmap.height) * zoom;
        }

        function clampOffsets() {
            if (!state.bitmap) {
                return;
            }
            var scale = scaleFor(state.zoom);
            state.ox = Math.min(0, Math.max(state.side - state.bitmap.width * scale, state.ox));
            state.oy = Math.min(0, Math.max(state.side - state.bitmap.height * scale, state.oy));
        }

        function paint() {
            if (!state.bitmap) {
                return;
            }
            var scale = scaleFor(state.zoom);
            ctx.clearRect(0, 0, state.side, state.side);
            ctx.drawImage(state.bitmap, state.ox, state.oy, state.bitmap.width * scale, state.bitmap.height * scale);
        }

        function centre() {
            var scale = scaleFor(state.zoom);
            state.ox = (state.side - state.bitmap.width * scale) / 2;
            state.oy = (state.side - state.bitmap.height * scale) / 2;
            paint();
        }

        function openEditor(file) {
            var url = URL.createObjectURL(file);
            var loader = new Image();
            loader.addEventListener('load', function () {
                URL.revokeObjectURL(url);
                state.bitmap = loader;
                state.file = file;
                state.zoom = 1;
                zoomRange.value = '100';
                editor.hidden = false;
                measure();
                centre();
            });
            loader.addEventListener('error', function () {
                URL.revokeObjectURL(url);
                show(t('That image could not be read. Pick another file.'), 'is-error');
            });
            loader.src = url;
        }

        function closeEditor() {
            editor.hidden = true;
            state.bitmap = null;
            state.file = null;
        }

        zoomRange.addEventListener('input', function () {
            state.zoom = Math.max(1, parseInt(zoomRange.value, 10) / 100);
            clampOffsets();
            paint();
        });

        var drag = null;
        canvas.addEventListener('pointerdown', function (event) {
            if (!state.bitmap) {
                return;
            }
            drag = { x: event.clientX, y: event.clientY, ox: state.ox, oy: state.oy };
            if (canvas.setPointerCapture) {
                canvas.setPointerCapture(event.pointerId);
            }
        });
        canvas.addEventListener('pointermove', function (event) {
            if (!drag || !state.bitmap) {
                return;
            }
            state.ox = drag.ox + (event.clientX - drag.x);
            state.oy = drag.oy + (event.clientY - drag.y);
            clampOffsets();
            paint();
        });
        ['pointerup', 'pointercancel'].forEach(function (name) {
            canvas.addEventListener(name, function () {
                drag = null;
            });
        });

        function croppedFile() {
            var scale = scaleFor(state.zoom);
            var sourceSide = state.side / scale;
            var out = Math.min(Math.round(sourceSide), MAX_CROP_SIDE);
            var type = state.file.type === 'image/png'
                ? 'image/png'
                : (state.file.type === 'image/webp' ? 'image/webp' : 'image/jpeg');
            var work = document.createElement('canvas');
            work.width = out;
            work.height = out;
            var area = work.getContext('2d');
            area.imageSmoothingQuality = 'high';
            area.drawImage(state.bitmap, -state.ox / scale, -state.oy / scale, sourceSide, sourceSide, 0, 0, out, out);

            return new Promise(function (resolve, reject) {
                work.toBlob(function (blob) {
                    if (!blob) {
                        reject(new Error('encode'));
                        return;
                    }
                    var extension = type === 'image/png' ? '.png' : (type === 'image/webp' ? '.webp' : '.jpg');
                    resolve(new File([blob], 'profile-' + Date.now() + extension, { type: type }));
                }, type, JPEG_QUALITY);
            });
        }

        // ---- upload ----------------------------------------------------------
        function send(file, useCrop) {
            if (state.busy) {
                return;
            }
            state.busy = true;
            setProgress(0);

            function post(body) {
                var data = new FormData();
                var token = uploadForm.querySelector('input[name="_token"]');
                if (token) {
                    data.append('_token', token.value);
                }
                data.append('image', body.file, body.name);

                var request = new XMLHttpRequest();
                request.open('POST', uploadForm.getAttribute('action'), true);
                request.setRequestHeader('Accept', 'application/json');
                request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                request.upload.addEventListener('progress', function (event) {
                    if (event.lengthComputable) {
                        setProgress(event.loaded / event.total);
                    }
                });
                request.addEventListener('load', function () {
                    state.busy = false;
                    clearProgress();

                    var payload = {};
                    try {
                        payload = JSON.parse(request.responseText);
                    } catch (error) {
                        payload = {};
                    }

                    if (request.status >= 200 && request.status < 300 && payload.photo) {
                        show(payload.message || t('Your profile photo has been updated.'), 'is-success');
                        closeEditor();
                        // The header avatar and the Remove button come from the
                        // server, so one reload proves the write landed and puts
                        // the new picture everywhere at once.
                        try {
                            window.sessionStorage.setItem(NOTICE_KEY, payload.message || t('Your profile photo has been updated.'));
                        } catch (error) {}
                        window.setTimeout(function () {
                            window.location.reload();
                        }, 1200);
                        return;
                    }

                    var reason = payload.message;
                    if (payload.errors && payload.errors.image) {
                        reason = payload.errors.image[0];
                    }
                    if (request.status === 429) {
                        reason = t('Too many uploads in a row. Wait a minute and try again.');
                    } else if (!reason) {
                        reason = t('The upload did not go through. Please try again.');
                    }
                    show(reason, 'is-error');
                });
                request.addEventListener('error', function () {
                    state.busy = false;
                    clearProgress();
                    show(t('The upload did not go through. Check your connection and try again.'), 'is-error');
                });
                request.send(data);
            }

            if (!useCrop) {
                post({ file: file, name: file.name || 'profile.jpg' });
                return;
            }

            croppedFile().then(function (cropped) {
                post({ file: cropped, name: cropped.name });
            }).catch(function () {
                state.busy = false;
                clearProgress();
                show(t('The crop could not be prepared. Use Upload original instead.'), 'is-error');
            });
        }

        // ---- wiring ----------------------------------------------------------
        [gallery, camera].forEach(function (input) {
            input.addEventListener('change', function () {
                var file = input.files && input.files[0];
                if (!file) {
                    return;
                }
                if (!accepts(file)) {
                    input.value = '';
                    return;
                }
                openEditor(file);
            });
        });

        root.querySelectorAll('[data-ppw-pick]').forEach(function (button) {
            button.addEventListener('click', function () {
                gallery.value = '';
                gallery.click();
            });
        });
        root.querySelectorAll('[data-ppw-camera-pick]').forEach(function (button) {
            button.addEventListener('click', function () {
                camera.value = '';
                camera.click();
            });
        });
        root.querySelector('[data-ppw-upload-crop]').addEventListener('click', function () {
            if (state.file) {
                send(state.file, true);
            }
        });
        root.querySelector('[data-ppw-upload-original]').addEventListener('click', function () {
            if (state.file) {
                send(state.file, false);
            }
        });
        root.querySelectorAll('[data-ppw-cancel]').forEach(function (button) {
            button.addEventListener('click', closeEditor);
        });
        root.querySelectorAll('[data-ppw-remove-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (removeSubmit) {
                    removeSubmit.click();
                }
            });
        });

        uploadForm.addEventListener('submit', function (event) {
            if (state.busy) {
                event.preventDefault();
            }
        });

        window.addEventListener('resize', function () {
            if (editor.hidden || !state.bitmap) {
                return;
            }
            measure();
            clampOffsets();
            paint();
        });

        try {
            var notice = window.sessionStorage.getItem(NOTICE_KEY);
            if (notice) {
                window.sessionStorage.removeItem(NOTICE_KEY);
                show(notice, 'is-success');
            }
        } catch (error) {}
    }

    document.querySelectorAll('[data-profile-photo]').forEach(init);
})();
