/**
 * assets/js/handwriting.js
 * Freehand handwriting pad using SignaturePad.js.
 * Handles all .hw-pad-wrap elements on the page.
 */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof SignaturePad === 'undefined') return;

    document.querySelectorAll('.hw-pad-wrap').forEach(function (wrap) {
        var fieldId     = wrap.dataset.fieldId;
        var hiddenInput = document.getElementById(fieldId);
        if (!hiddenInput) return;

        var toggleBtn   = wrap.querySelector('.hw-toggle');
        var panel       = wrap.querySelector('.hw-panel');
        var canvasEl    = wrap.querySelector('.hw-canvas');
        var canvasWrap  = wrap.querySelector('.hw-canvas-wrap');
        var placeholder = wrap.querySelector('.hw-placeholder');
        var doneBtn     = wrap.querySelector('.hw-done');
        var clearBtn    = wrap.querySelector('.hw-clear');
        var cancelBtn   = wrap.querySelector('.hw-cancel');
        var undoBtn     = wrap.querySelector('.hw-undo');
        var penBtns     = wrap.querySelectorAll('.hw-pen');
        var previewWrap = wrap.querySelector('.hw-preview-wrap');
        var thumb       = wrap.querySelector('.hw-thumb');
        var removeThumb = wrap.querySelector('.hw-remove-thumb');

        var pad = null;

        // Show preview if there's a pre-existing value (initial PHP value or autosave restore)
        if (hiddenInput.value) {
            showPreview(hiddenInput.value);
        }

        // Re-show preview when autosave.js restores the hidden input's value after DOMContentLoaded
        hiddenInput.addEventListener('change', function () {
            if (hiddenInput.value) {
                showPreview(hiddenInput.value);
            } else {
                clearPreview();
            }
        });

        function initPad() {
            if (pad) return; // already initialised

            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            var w = canvasWrap.getBoundingClientRect().width || canvasWrap.offsetWidth || 600;
            canvasEl.width  = w * ratio;
            canvasEl.height = 220 * ratio;
            canvasEl.style.width  = w + 'px';
            canvasEl.style.height = '220px';
            canvasEl.getContext('2d').scale(ratio, ratio);

            pad = new SignaturePad(canvasEl, {
                penColor: 'rgb(15,23,42)',
                minWidth: 0.8,
                maxWidth: 1.5
            });

            pad.addEventListener('beginStroke', function () {
                if (placeholder) placeholder.style.display = 'none';
            });
        }

        function resizePad() {
            if (!pad) return;
            var data  = pad.toData();
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            var w = canvasWrap.getBoundingClientRect().width || canvasWrap.offsetWidth || 600;
            canvasEl.width  = w * ratio;
            canvasEl.height = 220 * ratio;
            canvasEl.style.width  = w + 'px';
            canvasEl.getContext('2d').scale(ratio, ratio);
            pad.clear();
            if (data.length) pad.fromData(data);
        }

        function showPreview(dataUrl) {
            if (thumb) { thumb.src = dataUrl; thumb.style.display = ''; }
            if (previewWrap) previewWrap.classList.remove('hidden');
            previewWrap && previewWrap.classList.add('flex');
        }

        function clearPreview() {
            if (thumb) { thumb.src = ''; thumb.style.display = 'none'; }
            if (previewWrap) { previewWrap.classList.add('hidden'); previewWrap.classList.remove('flex'); }
            hiddenInput.value = '';
        }

        // Toggle panel open/close
        toggleBtn && toggleBtn.addEventListener('click', function () {
            var isHidden = panel.classList.contains('hidden');
            panel.classList.toggle('hidden', !isHidden);
            if (!isHidden) return; // closing
            // Opening: init pad, restore existing drawing if any
            initPad();
            if (hiddenInput.value) {
                pad.fromDataURL(hiddenInput.value).catch(function () {});
                if (placeholder) placeholder.style.display = 'none';
            }
            window.addEventListener('resize', resizePad);
        });

        // Pen size buttons
        penBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!pad) return;
                penBtns.forEach(function (b) { b.classList.remove('hw-pen--active', 'border-indigo-500'); b.classList.add('border-transparent'); });
                btn.classList.add('hw-pen--active', 'border-indigo-500');
                btn.classList.remove('border-transparent');
                pad.minWidth = parseFloat(btn.dataset.min) || 0.8;
                pad.maxWidth = parseFloat(btn.dataset.max) || 1.5;
            });
        });

        // Undo last stroke
        undoBtn && undoBtn.addEventListener('click', function () {
            if (!pad) return;
            var data = pad.toData();
            if (data.length) {
                data.pop();
                pad.fromData(data);
                if (!data.length && placeholder) placeholder.style.display = '';
            }
        });

        // Clear canvas
        clearBtn && clearBtn.addEventListener('click', function () {
            if (pad) pad.clear();
            if (placeholder) placeholder.style.display = '';
        });

        // Save drawing → hidden input + show preview
        doneBtn && doneBtn.addEventListener('click', function () {
            if (!pad || pad.isEmpty()) {
                panel.classList.add('hidden');
                return;
            }
            // Render on white background
            var offCanvas = document.createElement('canvas');
            offCanvas.width  = canvasEl.width;
            offCanvas.height = canvasEl.height;
            var ctx = offCanvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, offCanvas.width, offCanvas.height);
            ctx.drawImage(canvasEl, 0, 0);
            var dataUrl = offCanvas.toDataURL('image/png');
            hiddenInput.value = dataUrl;
            showPreview(dataUrl);
            panel.classList.add('hidden');
            window.removeEventListener('resize', resizePad);
        });

        // Cancel without saving
        cancelBtn && cancelBtn.addEventListener('click', function () {
            panel.classList.add('hidden');
            window.removeEventListener('resize', resizePad);
        });

        // Remove existing handwriting
        removeThumb && removeThumb.addEventListener('click', function () {
            clearPreview();
            if (pad) { pad.clear(); if (placeholder) placeholder.style.display = ''; }
        });
    });
});
