/**
 * PaperlessMD — Form Wizard
 * Step-by-step tab wizard for all consent / clinical forms.
 * Features: progress bar, auto-save to localStorage, completion checkmarks,
 *           keyboard shortcuts (Alt+→ / Alt+← for next/back).
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const form     = document.getElementById('mainForm');
        const steps    = Array.from(document.querySelectorAll('.wiz-step'));
        if (!steps.length || !form) return;

        const storageKey = 'wiz_draft_' + (document.getElementById('wiz-form-key')?.value || location.pathname);

        // ── Build progress header ──────────────────────────────────────
        const header = document.getElementById('wiz-header');
        if (header) {
            header.innerHTML = buildHeader(steps);
        }

        let current = 0;

        // ── Restore saved draft & last step ───────────────────────────
        restoreDraft();

        // Start on last saved step if available
        const savedStep = parseInt(sessionStorage.getItem(storageKey + '_step') || '0', 10);
        if (savedStep > 0 && savedStep < steps.length) {
            // Mark earlier steps as done
            for (let i = 0; i < savedStep; i++) markDone(i);
            showStep(savedStep);
        } else {
            showStep(0);
        }

        // ── Auto-save every field change ───────────────────────────────
        form.addEventListener('input', debounce(saveDraft, 600));
        form.addEventListener('change', debounce(saveDraft, 300));

        // ── Next / Back buttons ────────────────────────────────────────
        document.addEventListener('click', function (e) {
            if (e.target.closest('#wiz-next'))  goNext();
            if (e.target.closest('#wiz-back'))  goBack();
        });

        // ── Keyboard shortcuts: Alt+→ / Alt+← ─────────────────────────
        document.addEventListener('keydown', function (e) {
            if (e.altKey && e.key === 'ArrowRight') { e.preventDefault(); goNext(); }
            if (e.altKey && e.key === 'ArrowLeft')  { e.preventDefault(); goBack(); }
        });

        // ── Click on completed step pill to jump back ──────────────────
        document.addEventListener('click', function (e) {
            const pill = e.target.closest('.wiz-pill');
            if (!pill) return;
            const idx = parseInt(pill.dataset.step, 10);
            if (!isNaN(idx) && idx < current) {
                markDone(current);
                showStep(idx);
            }
        });

        // ── Clear draft after successful form submission ───────────────
        form.addEventListener('submit', function () {
            localStorage.removeItem(storageKey);
            sessionStorage.removeItem(storageKey + '_step');
        });

        // ─────────────────────────────────────────────────────────────
        function goNext() {
            if (!validateStep(current)) return;
            markDone(current);
            if (current < steps.length - 1) showStep(current + 1);
        }

        function goBack() {
            if (current > 0) showStep(current - 1);
        }

        function showStep(idx) {
            steps.forEach((s, i) => s.classList.toggle('hidden', i !== idx));
            current = idx;
            sessionStorage.setItem(storageKey + '_step', idx);
            updateHeader();
            updateNavButtons();
            scrollToTop();
            // Reinitialize signature canvases that may have been in a hidden step on load
            window.dispatchEvent(new Event('resize'));
        }

        function markDone(idx) {
            const pill = document.querySelector('.wiz-pill[data-step="' + idx + '"]');
            if (pill) pill.dataset.done = '1';
            updateHeader();
        }

        function updateHeader() {
            steps.forEach(function (s, i) {
                const pill = document.querySelector('.wiz-pill[data-step="' + i + '"]');
                if (!pill) return;
                const isActive = i === current;
                const isDone   = pill.dataset.done === '1' && !isActive;

                pill.classList.toggle('wiz-pill--active', isActive);
                pill.classList.toggle('wiz-pill--done',   isDone);
                pill.classList.toggle('wiz-pill--future', !isActive && !isDone);

                const numEl  = pill.querySelector('.wiz-num');
                const iconEl = pill.querySelector('.wiz-check');
                if (numEl)  numEl.classList.toggle('hidden', isDone);
                if (iconEl) iconEl.classList.toggle('hidden', !isDone);
            });

            // Progress bar width
            const pct = steps.length > 1 ? (current / (steps.length - 1)) * 100 : 100;
            const bar = document.getElementById('wiz-progress-bar');
            if (bar) bar.style.width = Math.max(pct, 4) + '%';
        }

        function updateNavButtons() {
            const nextBtn = document.getElementById('wiz-next');
            const backBtn = document.getElementById('wiz-back');
            const subBtn  = document.getElementById('submitBtn');
            const isLast  = current === steps.length - 1;

            if (backBtn) backBtn.classList.toggle('hidden', current === 0);
            if (nextBtn) nextBtn.classList.toggle('hidden', isLast);
            if (subBtn)  subBtn.classList.toggle('hidden', !isLast);
        }

        function validateStep(idx) {
            const step     = steps[idx];
            const required = step.querySelectorAll('[required]');
            let ok         = true;
            required.forEach(function (el) {
                el.classList.remove('wiz-invalid');
                if (!el.value.trim()) {
                    el.classList.add('wiz-invalid');
                    if (ok) { el.focus(); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                    ok = false;
                }
            });

            // Radio groups: check at least one selected
            const radioGroups = {};
            step.querySelectorAll('input[type="radio"][required]').forEach(function (r) {
                radioGroups[r.name] = radioGroups[r.name] || [];
                radioGroups[r.name].push(r);
            });
            Object.keys(radioGroups).forEach(function (name) {
                const group = radioGroups[name];
                const checked = group.some(r => r.checked);
                if (!checked) {
                    group.forEach(r => r.closest('label')?.classList.add('wiz-radio-invalid'));
                    ok = false;
                } else {
                    group.forEach(r => r.closest('label')?.classList.remove('wiz-radio-invalid'));
                }
            });

            return ok;
        }

        // ── localStorage auto-save ─────────────────────────────────────
        function saveDraft() {
            const data = {};
            new FormData(form).forEach(function (val, key) {
                if (!['csrf_token', 'patient_signature', 'ma_signature'].includes(key)) {
                    if (data[key]) {
                        data[key] = [].concat(data[key], val);
                    } else {
                        data[key] = val;
                    }
                }
            });
            try { localStorage.setItem(storageKey, JSON.stringify(data)); } catch (e) {}
        }

        function restoreDraft() {
            let data;
            try { data = JSON.parse(localStorage.getItem(storageKey) || 'null'); } catch (e) {}
            if (!data) return;

            // Show resume banner if draft exists
            const banner = document.getElementById('wiz-resume-banner');
            if (banner) {
                banner.classList.remove('hidden');
                document.getElementById('wiz-resume-yes')?.addEventListener('click', function () {
                    applyDraft(data);
                    banner.remove();
                });
                document.getElementById('wiz-resume-no')?.addEventListener('click', function () {
                    localStorage.removeItem(storageKey);
                    banner.remove();
                });
            } else {
                applyDraft(data);
            }
        }

        function applyDraft(data) {
            Object.keys(data).forEach(function (key) {
                const vals   = [].concat(data[key]);
                const els    = form.querySelectorAll('[name="' + CSS.escape(key) + '"]');
                if (!els.length) return;
                const type   = els[0].type;

                if (type === 'checkbox') {
                    els.forEach(el => { el.checked = vals.includes(el.value); });
                } else if (type === 'radio') {
                    els.forEach(el => { el.checked = vals.includes(el.value); });
                } else if (els[0].tagName === 'SELECT') {
                    els[0].value = vals[0] || '';
                } else {
                    if (els.length === 1) els[0].value = vals[0] || '';
                }
            });
        }

        function scrollToTop() {
            const header = document.getElementById('wiz-header');
            if (header) header.scrollIntoView({ behavior: 'smooth', block: 'start' });
            else window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // ── Build header HTML ──────────────────────────────────────────
        function buildHeader(steps) {
            let html = '';
            // Progress bar track
            html += '<div class="relative mb-6">';
            html += '<div class="absolute inset-0 flex items-center" aria-hidden="true">';
            html += '  <div class="w-full bg-slate-200 rounded-full h-1.5">';
            html += '    <div id="wiz-progress-bar" class="bg-blue-500 h-1.5 rounded-full transition-all duration-500" style="width:4%"></div>';
            html += '  </div>';
            html += '</div>';
            // Pills
            html += '<ol class="relative flex justify-between">';
            steps.forEach(function (s, i) {
                const title = s.dataset.title || ('Step ' + (i + 1));
                const icon  = s.dataset.icon  || 'bi-circle';
                html += '<li class="flex flex-col items-center flex-1 ' + (i === 0 ? '' : '') + '">';
                html += '  <button type="button" class="wiz-pill flex flex-col items-center gap-1 focus:outline-none group" data-step="' + i + '">';
                html += '    <span class="relative flex items-center justify-center w-10 h-10 rounded-full border-2 transition-all duration-200 bg-white shadow-sm">';
                html += '      <span class="wiz-num text-sm font-bold">' + (i + 1) + '</span>';
                html += '      <i class="wiz-check bi bi-check-lg text-sm font-bold hidden"></i>';
                html += '    </span>';
                html += '    <span class="hidden sm:block text-xs font-semibold mt-0.5 text-center leading-tight max-w-[72px]">' + title + '</span>';
                html += '  </button>';
                html += '</li>';
            });
            html += '</ol>';
            html += '</div>';
            return html;
        }

        function debounce(fn, ms) {
            let t;
            return function () { clearTimeout(t); t = setTimeout(fn, ms); };
        }
    });
})();
