/* PaperlessMD — app.js */

document.addEventListener('DOMContentLoaded', function () {

    // ── Signature Pad Setup ──────────────────────────────────────────
    const canvas  = document.getElementById('signaturePad');
    const sigData = document.getElementById('sigData');
    let sigPad    = null;
    let _pdSigKey = null;

    if (canvas && typeof SignaturePad !== 'undefined') {
        const wrapper = canvas.closest('.sig-wrapper') || canvas.parentElement;

        sigPad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor:        'rgb(0, 0, 100)',
            minWidth:        1.5,
            maxWidth:        3.5,
        });
        canvas.style.touchAction = 'none';

        // Compute session-storage key so signature survives accidental reloads
        _pdSigKey = (function () {
            var mf = document.getElementById('mainForm');
            if (!mf) return null;
            var ft  = (mf.querySelector('[name="form_type"]')  || {}).value || '';
            var pid = (mf.querySelector('[name="patient_id"]') || {}).value || '';
            return (ft && pid) ? 'pd_sig_' + ft + '_' + pid : null;
        }());

        // On each stroke end: keep hidden input + sessionStorage current
        sigPad.addEventListener('endStroke', function () {
            if (sigData) sigData.value = sigPad.toDataURL('image/png');
            if (_pdSigKey) { try { sessionStorage.setItem(_pdSigKey, sigPad.toDataURL('image/png')); } catch(e) {} }
        });

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
            } else {
                // Pre-fill from saved patient signature after canvas is sized
                if (window._patientSavedSignature && sigData) {
                    sigData.value = window._patientSavedSignature;
                    var pPadArea = document.getElementById('patientSigPadArea');
                    if (pPadArea && !pPadArea.classList.contains('hidden')) {
                        sigPad.fromDataURL(window._patientSavedSignature);
                    }
                } else if (_pdSigKey) {
                    // Restore from sessionStorage (survives accidental page reload)
                    try {
                        var _stored = sessionStorage.getItem(_pdSigKey);
                        if (_stored) {
                            if (sigData) sigData.value = _stored;
                            sigPad.fromDataURL(_stored);
                        }
                    } catch(e) {}
                }
            }
        })(0);
        // Only re-size on actual window resize (not layout shifts during drawing)
        window.addEventListener('resize', function() { resizeCanvas(true); });

        const clearBtn = document.getElementById('clearSig');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                if (sigPad.isEmpty()) return;
                if (confirm('Clear the patient signature? This cannot be undone.')) {
                    sigPad.clear();
                    if (sigData) sigData.value = '';
                    if (_pdSigKey) { try { sessionStorage.removeItem(_pdSigKey); } catch(e) {} }
                }
            });
        }

        // "Sign manually" button for patient signature
        const manualPatientBtn = document.getElementById('useManualPatientSig');
        if (manualPatientBtn) {
            manualPatientBtn.addEventListener('click', function () {
                var banner = document.getElementById('patientSavedBanner');
                var padArea = document.getElementById('patientSigPadArea');
                if (banner) banner.style.display = 'none';
                if (padArea) { padArea.classList.remove('hidden'); resizeCanvas(false); }
                if (sigData) sigData.value = '';
                window._patientSavedSignature = null;
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
        const editOverrideEl = mainForm.querySelector('input[name="edit_override"]');
        const isEditOverride = !!(editOverrideEl && String(editOverrideEl.value) === '1');

        // Clear stale signature alerts before any early-return validations.
        const sigAlert = document.getElementById('sigAlert');
        const maSigAlert = document.getElementById('maSigAlert');
        const patientPadArea = document.getElementById('patientSigPadArea');
        const maPadArea = document.getElementById('maSigPadArea');
        const usingSavedPatientSig = !!(sigData && sigData.value && (!patientPadArea || patientPadArea.classList.contains('hidden')));
        const usingSavedMaSig = !!(maSigData && maSigData.value && (!maPadArea || maPadArea.classList.contains('hidden')));
        if (sigAlert && (isEditOverride || usingSavedPatientSig)) sigAlert.classList.add('hidden');
        if (maSigAlert && (isEditOverride || usingSavedMaSig)) maSigAlert.classList.add('hidden');

        // ── Required field check first ────────────────────────────────
        if (!validateRequiredFields()) return;

        // Validate patient signature
        let valid = true;

        if ((!sigPad || !maSigPad) && !isEditOverride && !window._pdMissedVisit) {
            alert('Signature pad failed to load. Please refresh the page and try again.');
            return;
        }

        if (sigPad) {
            if (!usingSavedPatientSig && sigPad.isEmpty()) {
                if (!isEditOverride && !window._pdMissedVisit) {
                    if (sigAlert) { sigAlert.classList.remove('hidden'); sigAlert.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                    valid = false;
                } else if (sigAlert) {
                    sigAlert.classList.add('hidden');
                }
            } else {
                if (sigAlert) sigAlert.classList.add('hidden');
                if (!usingSavedPatientSig) sigData.value = sigPad.toDataURL('image/png');
            }
        }

        // Validate MA signature
        if (maSigPad) {
            // If using saved signature the hidden input is already populated; pad may be hidden
            if (!usingSavedMaSig && maSigPad.isEmpty()) {
                if (!isEditOverride && !window._pdMissedVisit) {
                    if (maSigAlert) { maSigAlert.classList.remove('hidden'); if (valid) maSigAlert.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                    valid = false;
                } else if (maSigAlert) {
                    maSigAlert.classList.add('hidden');
                }
            } else {
                if (maSigAlert) maSigAlert.classList.add('hidden');
                if (!usingSavedMaSig) maSigData.value = maSigPad.toDataURL('image/png');
            }
        }

        if (!valid) return;

        // Page-level extra validation hook (e.g. provider sig in new_patient_pocket)
        if (typeof window._pdValidateExtra === 'function' && !window._pdValidateExtra()) return;

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-arrow-repeat text-xl" style="display:inline-block;animation:spin 1s linear infinite"></i> Saving…';
        }

        // Clear session cache — form is being submitted
        if (_pdSigKey) { try { sessionStorage.removeItem(_pdSigKey); } catch(e) {} }
        window._pdSubmitting = true;

        // ── Upload progress overlay ───────────────────────────────────
        var _ovr = document.createElement('div');
        _ovr.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.65);display:flex;align-items:center;justify-content:center;';
        _ovr.innerHTML =
            '<div style="background:#1e293b;border-radius:1.25rem;padding:2rem 2.5rem;width:90%;max-width:340px;text-align:center;box-shadow:0 25px 60px rgba(0,0,0,0.5);">' +
                '<div style="width:52px;height:52px;background:rgba(239,68,68,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">' +
                    '<i class="bi bi-cloud-arrow-up" style="font-size:1.6rem;color:#ef4444;"></i>' +
                '</div>' +
                '<p style="color:#f1f5f9;font-weight:700;font-size:1.05rem;margin:0 0 0.35rem;">Saving Visit…</p>' +
                '<p id="_uplPct" style="color:#94a3b8;font-size:0.82rem;margin:0 0 1.1rem;">Preparing…</p>' +
                '<div style="background:#334155;border-radius:999px;height:8px;overflow:hidden;">' +
                    '<div id="_uplBar" style="height:100%;background:linear-gradient(90deg,#ef4444,#f97316);border-radius:999px;width:0%;transition:width 0.25s ease;"></div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(_ovr);

        var _xhr = new XMLHttpRequest();
        _xhr.open('POST', mainForm.action);
        _xhr.upload.addEventListener('progress', function (e) {
            if (!e.lengthComputable) return;
            var pct = Math.round((e.loaded / e.total) * 100);
            var bar = document.getElementById('_uplBar');
            var lbl = document.getElementById('_uplPct');
            if (bar) bar.style.width = pct + '%';
            if (lbl) lbl.textContent = pct + '% uploaded';
        });
        _xhr.onload = function () {
            var bar = document.getElementById('_uplBar');
            var lbl = document.getElementById('_uplPct');
            if (bar) bar.style.width = '100%';
            if (lbl) lbl.textContent = 'Done! Redirecting…';
            // Clear PDF annotation session data on successful submit
            var _pidEl = mainForm.querySelector('input[name="patient_id"]');
            if (_pidEl) { try { sessionStorage.removeItem('pd_pdf_annot_' + _pidEl.value); } catch(e) {} }
            window.location.href = _xhr.responseURL || mainForm.action;
        };
        _xhr.onerror = function () {
            if (_ovr && _ovr.parentNode) _ovr.parentNode.removeChild(_ovr);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-stop-circle-fill text-xl"></i> End Visit';
            }
            window._pdSubmitting = false;
            alert('Upload failed. Please check your connection and try again.');
        };
        _xhr.send(new FormData(mainForm));
    }

    // submitBtn is type="button" — wire click directly
    if (submitBtn) {
        submitBtn.addEventListener('click', captureAndSubmit);
    }

    // Also block accidental native submissions (Enter key in text fields)
    if (mainForm) {
        mainForm.addEventListener('submit', function (e) {
            e.preventDefault();
            captureAndSubmit();
        });
    }
});
