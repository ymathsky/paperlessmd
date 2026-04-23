/* PaperlessMD — app.js */

document.addEventListener('DOMContentLoaded', function () {

    // ── Signature Pad Setup ──────────────────────────────────────────
    const canvas  = document.getElementById('signaturePad');
    const sigData = document.getElementById('sigData');
    let sigPad    = null;

    if (canvas && typeof SignaturePad !== 'undefined') {
        const wrapper = canvas.closest('.sig-wrapper') || canvas.parentElement;

        sigPad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor:        'rgb(0, 0, 100)',
            minWidth:        1.5,
            maxWidth:        3.5,
        });
        canvas.style.touchAction = 'none';

        function resizeCanvas(restoreSig) {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const w     = wrapper.getBoundingClientRect().width || wrapper.offsetWidth;
            if (!w) return false;
            const h     = 200;
            const saved = (restoreSig && !sigPad.isEmpty()) ? sigPad.toData() : null;
            canvas.width        = w * ratio;
            canvas.height       = h * ratio;
            canvas.style.width  = w + 'px';
            canvas.style.height = h + 'px';
            canvas.getContext('2d').scale(ratio, ratio);
            sigPad.clear();
            if (saved) sigPad.fromData(saved);
            return true;
        }

        // One-shot init: retry via rAF until element has real width, then stop
        (function tryInit(attempts) {
            if (!resizeCanvas(false) && attempts < 30) {
                requestAnimationFrame(function() { tryInit(attempts + 1); });
            }
        })(0);
        // Only re-size on actual window resize (not layout shifts during drawing)
        window.addEventListener('resize', function() { resizeCanvas(true); });

        const clearBtn = document.getElementById('clearSig');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                if (sigPad.isEmpty()) return;
                if (confirm('Clear the patient signature? This cannot be undone.')) sigPad.clear();
            });
        }
    }

    // ── MA Signature Pad ──────────────────────────────────────────────
    const maCanvas  = document.getElementById('maSigPad');
    const maSigData = document.getElementById('maSigData');
    let maSigPad    = null;

    if (maCanvas && typeof SignaturePad !== 'undefined') {
        const maWrapper = document.getElementById('maSigWrapper') || maCanvas.parentElement;
        maSigPad = new SignaturePad(maCanvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor:        'rgb(30, 30, 120)',
            minWidth:        1.5,
            maxWidth:        3.5,
        });
        maCanvas.style.touchAction = 'none';

        function resizeMaCanvas(restoreSig) {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const w     = maWrapper.getBoundingClientRect().width || maWrapper.offsetWidth;
            if (!w) return false;
            const h     = 160;
            const saved = (restoreSig && !maSigPad.isEmpty()) ? maSigPad.toData() : null;
            maCanvas.width        = w * ratio;
            maCanvas.height       = h * ratio;
            maCanvas.style.width  = w + 'px';
            maCanvas.style.height = h + 'px';
            maCanvas.getContext('2d').scale(ratio, ratio);
            maSigPad.clear();
            if (saved) maSigPad.fromData(saved);
            return true;
        }

        // One-shot init: retry via rAF until element has real width, then stop
        (function tryMaInit(attempts) {
            if (!resizeMaCanvas(false) && attempts < 30) {
                requestAnimationFrame(function() { tryMaInit(attempts + 1); });
            }
        })(0);
        // Only re-size on actual window resize (not layout shifts during drawing)
        window.addEventListener('resize', function() { resizeMaCanvas(true); });

        const clearMaBtn = document.getElementById('clearMaSig');
        if (clearMaBtn) clearMaBtn.addEventListener('click', () => {
            if (maSigPad.isEmpty()) return;
            if (confirm('Clear the MA signature? This cannot be undone.')) maSigPad.clear();
        });
    }

    // ── POA Toggle ────────────────────────────────────────────────────
    const poaCheck  = document.getElementById('poaCheck');
    const poaFields = document.getElementById('poaFields');
    if (poaCheck && poaFields) {
        poaCheck.addEventListener('change', function () {
            poaFields.classList.toggle('hidden', !this.checked);
            if (this.checked) poaFields.querySelector('input')?.focus();
        });
    }

    // ── Form Submission — capture signature then submit ───────────────
    const mainForm  = document.getElementById('mainForm');
    const submitBtn = document.getElementById('submitBtn');

    function captureAndSubmit() {
        if (!mainForm) return;

        // Validate patient signature
        const sigAlert   = document.getElementById('sigAlert');
        const maSigAlert = document.getElementById('maSigAlert');
        let valid = true;

        if (!sigPad || !maSigPad) {
            alert('Signature pad failed to load. Please refresh the page and try again.');
            return;
        }

        if (sigPad) {
            if (sigPad.isEmpty()) {
                if (sigAlert) { sigAlert.classList.remove('hidden'); sigAlert.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                valid = false;
            } else {
                if (sigAlert) sigAlert.classList.add('hidden');
                sigData.value = sigPad.toDataURL('image/png');
            }
        }

        // Validate MA signature
        if (maSigPad) {
            if (maSigPad.isEmpty()) {
                if (maSigAlert) { maSigAlert.classList.remove('hidden'); if (valid) maSigAlert.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                valid = false;
            } else {
                if (maSigAlert) maSigAlert.classList.add('hidden');
                maSigData.value = maSigPad.toDataURL('image/png');
            }
        }

        if (!valid) return;

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-arrow-repeat text-xl" style="display:inline-block;animation:spin 1s linear infinite"></i> Saving…';
        }

        mainForm.submit();
    }

    if (submitBtn) {
        submitBtn.addEventListener('click', captureAndSubmit);
    }

    if (mainForm) {
        mainForm.addEventListener('submit', function () {
            if (sigPad && sigData && !sigPad.isEmpty()) {
                sigData.value = sigPad.toDataURL('image/png');
            }
            if (maSigPad && maSigData && !maSigPad.isEmpty()) {
                maSigData.value = maSigPad.toDataURL('image/png');
            }
        });
    }
});
