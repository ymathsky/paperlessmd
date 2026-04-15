'use strict';
/* ─────────────────────────────────────────────────────────────────────────
 * PaperlessMD — Auto-Save Draft
 *
 * Targets any page with #mainForm (all standard form pages).
 * Serialises form fields → localStorage every 30 seconds.
 * Restores on page load. Clears on final form submit.
 * ──────────────────────────────────────────────────────────────────────── */
(function () {

    var INTERVAL_MS  = 15000;
    var EXPIRY_MS    = 86400000; // discard drafts older than 24 h
    var SKIP_NAMES   = ['csrf_token', 'patient_signature', 'poa_signature', '__ts'];

    document.addEventListener('DOMContentLoaded', function () {

        var form = document.getElementById('mainForm');
        if (!form) return;

        var patientId = (form.querySelector('[name="patient_id"]') || {}).value || '';
        var formType  = (form.querySelector('[name="form_type"]')  || {}).value || '';
        if (!patientId || !formType) return;

        var STORAGE_KEY = 'pd_draft_' + formType + '_' + patientId;

        /* ── Status badge (fixed, bottom-right) ────────────────────────── */
        var badge = document.createElement('div');
        badge.id = 'autoSaveBadge';
        badge.style.cssText = [
            'position:fixed',
            'bottom:1.25rem',
            'right:1.25rem',
            'z-index:9999',
            'background:#1e293b',
            'color:#e2e8f0',
            'font-size:0.72rem',
            'font-weight:500',
            'padding:0.35rem 0.75rem',
            'border-radius:999px',
            'box-shadow:0 2px 8px rgba(0,0,0,0.25)',
            'opacity:0',
            'transition:opacity 0.3s ease',
            'pointer-events:none',
        ].join(';');
        document.body.appendChild(badge);

        var hideTimer = null;
        function showBadge(msg, persist) {
            clearTimeout(hideTimer);
            badge.textContent = msg;
            badge.style.opacity = '1';
            if (!persist) {
                hideTimer = setTimeout(function () {
                    badge.style.opacity = '0';
                }, 3000);
            }
        }
        function hideBadge() {
            clearTimeout(hideTimer);
            badge.style.opacity = '0';
        }

        /* ── Serialise form (skip meta / sig / file fields) ─────────────── */
        function serialize() {
            var data = {};
            var els  = form.elements;
            for (var i = 0; i < els.length; i++) {
                var el = els[i];
                if (!el.name) continue;
                if (SKIP_NAMES.indexOf(el.name) !== -1) continue;
                if (el.type === 'file' || el.type === 'submit' || el.type === 'button') continue;

                if (el.type === 'checkbox') {
                    if (!el.checked) continue;
                    if (!Array.isArray(data[el.name])) data[el.name] = [];
                    data[el.name].push(el.value);
                } else if (el.type === 'radio') {
                    if (el.checked) data[el.name] = el.value;
                } else {
                    data[el.name] = el.value;
                }
            }
            return data;
        }

        /* ── Restore saved draft ─────────────────────────────────────────── */
        function restore(saved) {
            /* Reset all checkboxes first so unchecked ones don't linger */
            var els = form.elements;
            for (var i = 0; i < els.length; i++) {
                if (els[i].type === 'checkbox') els[i].checked = false;
            }

            for (var name in saved) {
                if (!Object.prototype.hasOwnProperty.call(saved, name)) continue;
                if (SKIP_NAMES.indexOf(name) !== -1) continue;
                var val  = saved[name];
                var matches = form.querySelectorAll('[name="' + name.replace(/"/g, '\\"') + '"]');
                if (!matches.length) continue;
                var first = matches[0];

                if (first.type === 'radio') {
                    for (var j = 0; j < matches.length; j++) {
                        matches[j].checked = (matches[j].value === val);
                    }
                } else if (first.type === 'checkbox') {
                    var vals = Array.isArray(val) ? val : [val];
                    for (var k = 0; k < matches.length; k++) {
                        matches[k].checked = vals.indexOf(matches[k].value) !== -1;
                    }
                } else {
                    first.value = val;
                    /* Fire change so dependent UI (e.g. POA toggle) reacts */
                    first.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }

        /* ── Try to restore on load ─────────────────────────────────────── */
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (raw) {
                var saved = JSON.parse(raw);
                var ts    = saved.__ts ? new Date(saved.__ts).getTime() : 0;
                if (ts && (Date.now() - ts) < EXPIRY_MS) {
                    restore(saved);
                    var ageMin = Math.round((Date.now() - ts) / 60000);
                    var ageStr = ageMin < 1 ? 'just now' : ageMin + ' min ago';
                    showBadge('Draft restored (' + ageStr + ')', false);
                } else if (ts) {
                    /* Draft too old — discard silently */
                    localStorage.removeItem(STORAGE_KEY);
                }
            }
        } catch (e) { /* ignore storage errors */ }

        /* ── Periodic save ───────────────────────────────────────────────── */
        var lastJson = null;

        function doSave(quiet) {
            try {
                var data = serialize();
                var json = JSON.stringify(data);
                if (json === lastJson) return; /* nothing changed */
                data.__ts = new Date().toISOString();
                localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
                lastJson = json;
                if (!quiet) {
                    showBadge('Draft saved', false);
                }
            } catch (e) { /* quota exceeded or private mode — ignore */ }
        }

        /* Run once immediately (no badge) to capture baseline */
        doSave(true);

        setInterval(function () { doSave(false); }, INTERVAL_MS);

        /* Save when tab/phone hides (lock screen, app switch) */
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') doSave(true);
        });

        /* ── Clear draft on final submit ────────────────────────────────── */
        form.addEventListener('submit', function () {
            try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
            hideBadge();
        });

    });

})();
