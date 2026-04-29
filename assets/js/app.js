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
            } else {
                // Pre-fill from saved signature after canvas is sized
                if (window._maSavedSignature && maSigData) {
                    maSigData.value = window._maSavedSignature;
                    // Pad is hidden when saved sig is active, so only load when visible
                    var padArea = document.getElementById('maSigPadArea');
                    if (padArea && !padArea.classList.contains('hidden')) {
                        maSigPad.fromDataURL(window._maSavedSignature);
                    }
                }
            }
        })(0);
        // Only re-size on actual window resize (not layout shifts during drawing)
        window.addEventListener('resize', function() { resizeMaCanvas(true); });

        const clearMaBtn = document.getElementById('clearMaSig');
        if (clearMaBtn) clearMaBtn.addEventListener('click', () => {
            if (maSigPad.isEmpty()) return;
            if (confirm('Clear the MA signature? This cannot be undone.')) maSigPad.clear();
        });

        // "Sign manually" button — show the pad and clear saved pre-fill
        const manualBtn = document.getElementById('useManualMaSig');
        if (manualBtn) {
            manualBtn.addEventListener('click', function () {
                var banner  = document.getElementById('maSavedBanner');
                var padArea = document.getElementById('maSigPadArea');
                if (banner)  banner.style.display  = 'none';
                if (padArea) { padArea.classList.remove('hidden'); resizeMaCanvas(false); }
                // Clear the hidden input so empty-check will fire
                if (maSigData) maSigData.value = '';
                window._maSavedSignature = null;
            });
        }
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

    // ── Required field validation ─────────────────────────────────────
    function getLabelText(field) {
        if (field.id) {
            var lbl = document.querySelector('label[for="' + field.id + '"]');
            if (lbl) return lbl.textContent.trim().replace(/\s*\*.*$/, '').trim();
        }
        var parent = field.closest('label');
        if (parent) return parent.textContent.trim().replace(/\s*\*.*$/, '').trim().substring(0, 60);
        var sibling = field.parentElement && field.parentElement.querySelector('label');
        if (sibling) return sibling.textContent.trim().replace(/\s*\*.*$/, '').trim();
        return field.getAttribute('data-label') || field.getAttribute('placeholder') || field.name;
    }

    function validateRequiredFields() {
        if (!mainForm) return true;

        // Clear previous error state
        mainForm.querySelectorAll('.pd-req-error').forEach(function (el) {
            el.classList.remove('pd-req-error', 'ring-2', 'ring-red-500', '!border-red-400');
        });
        var oldBanner = document.getElementById('pdValidationBanner');
        if (oldBanner) oldBanner.remove();

        var missing   = [];
        var firstEl   = null;
        var radiosDone = {};

        mainForm.querySelectorAll('[required]').forEach(function (field) {
            if (field.disabled || field.closest('.hidden')) return;

            // ── Radio groups ──────────────────────────────────────────
            if (field.type === 'radio') {
                if (radiosDone[field.name]) return;
                radiosDone[field.name] = true;
                var allR = mainForm.querySelectorAll('input[type="radio"][name="' + field.name.replace(/"/g, '\\"') + '"]');
                var anyChecked = false;
                allR.forEach(function (r) { if (r.checked) anyChecked = true; });
                if (!anyChecked) {
                    var wrap = field.closest('[data-req-group]') || field.closest('.grid') || field.parentElement;
                    if (wrap) {
                        wrap.classList.add('pd-req-error', 'ring-2', 'ring-red-500', 'rounded-xl');
                        allR.forEach(function (r) {
                            r.addEventListener('change', function () {
                                wrap.classList.remove('pd-req-error', 'ring-2', 'ring-red-500');
                            }, { once: true });
                        });
                    }
                    missing.push(field.getAttribute('data-label') || field.name);
                    if (!firstEl) firstEl = field;
                }
                return;
            }

            // ── Checkboxes ────────────────────────────────────────────
            if (field.type === 'checkbox') {
                if (!field.checked) {
                    var lbl = field.closest('label') || field.parentElement;
                    if (lbl) {
                        lbl.classList.add('pd-req-error', '!border-red-400', 'ring-2', 'ring-red-500');
                        field.addEventListener('change', function () {
                            lbl.classList.remove('pd-req-error', '!border-red-400', 'ring-2', 'ring-red-500');
                        }, { once: true });
                    }
                    missing.push(field.getAttribute('data-label') || getLabelText(field));
                    if (!firstEl) firstEl = field;
                }
                return;
            }

            // ── Text / textarea / date / time / tel / number / select ─
            if (!(field.value || '').trim()) {
                field.classList.add('pd-req-error', '!border-red-400', 'ring-2', 'ring-red-500');
                missing.push(field.getAttribute('data-label') || getLabelText(field));
                if (!firstEl) firstEl = field;
                field.addEventListener('input', function () {
                    field.classList.remove('pd-req-error', '!border-red-400', 'ring-2', 'ring-red-500');
                }, { once: true });
                field.addEventListener('change', function () {
                    field.classList.remove('pd-req-error', '!border-red-400', 'ring-2', 'ring-red-500');
                }, { once: true });
            }
        });

        if (!missing.length) return true;

        // Build summary banner
        var banner = document.createElement('div');
        banner.id = 'pdValidationBanner';
        banner.className = 'flex items-start gap-3 bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-xl text-sm mb-4';
        banner.innerHTML =
            '<i class="bi bi-exclamation-triangle-fill text-base shrink-0 mt-0.5"></i>' +
            '<div class="flex-1"><strong class="font-bold block mb-1">Please complete the highlighted fields before submitting:</strong>' +
            '<ul class="list-disc ml-4 space-y-0.5">' +
            missing.map(function (m) { return '<li>' + m + '</li>'; }).join('') +
            '</ul></div>' +
            '<button type="button" onclick="this.parentElement.remove()" ' +
            'class="shrink-0 text-red-400 hover:text-red-600 text-xl font-bold leading-none ml-2">&times;</button>';

        // Insert at top of the currently visible wizard step (or mainForm)
        var step = mainForm.querySelector('.wiz-step:not(.hidden)') || mainForm;
        step.insertBefore(banner, step.firstChild);
        banner.scrollIntoView({ behavior: 'smooth', block: 'start' });

        return false;
    }

    function captureAndSubmit() {
        if (!mainForm) return;

        // ── Required field check first ────────────────────────────────
        if (!validateRequiredFields()) return;

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
            // If using saved signature the hidden input is already populated; pad may be hidden
            var padArea = document.getElementById('maSigPadArea');
            var usingSaved = maSigData && maSigData.value && (!padArea || padArea.classList.contains('hidden'));
            if (!usingSaved && maSigPad.isEmpty()) {
                if (maSigAlert) { maSigAlert.classList.remove('hidden'); if (valid) maSigAlert.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                valid = false;
            } else {
                if (maSigAlert) maSigAlert.classList.add('hidden');
                if (!usingSaved) maSigData.value = maSigPad.toDataURL('image/png');
            }
        }

        if (!valid) return;

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-arrow-repeat text-xl" style="display:inline-block;animation:spin 1s linear infinite"></i> Saving…';
        }

        mainForm.submit();
    }

    // ── Gate all form submission through captureAndSubmit ────────────
    // submitBtn (type="submit") click fires the native 'submit' event;
    // we intercept it here. captureAndSubmit() calls mainForm.submit()
    // programmatically, which does NOT re-fire this event — no loop.
    if (mainForm) {
        mainForm.addEventListener('submit', function (e) {
            e.preventDefault();
            captureAndSubmit();
        });
    }
});
