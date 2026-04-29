/**
 * assets/js/form-helpers.js
 * UX quality-of-life helpers for all PaperlessMD forms.
 *
 * Features:
 *   1. Auto-fill Time In to current time when field is empty
 *   2. Auto-fill Time Out when the Sign step becomes visible (MA/Provider about to sign)
 *   3. "Set Normal Vitals" quick-fill button
 *   4. Frequency quick-pick pills (QD / BID / TID / QID / PRN / QHS / Q8H)
 *   5. "Add Row" / remove-row for medication table
 */
(function () {
    'use strict';

    function currentTimeHHMM() {
        var now = new Date();
        return String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
    }

    document.addEventListener('DOMContentLoaded', function () {

        /* ── 1. Auto-fill Time In ────────────────────────────────────────── */
        var timeInField  = document.querySelector('[name="time_in"]');
        var timeOutField = document.querySelector('[name="time_out"]');

        if (timeInField && !timeInField.value) {
            timeInField.value = currentTimeHHMM();
        }

        /* ── 2. Auto-fill Time Out when Sign step becomes visible ────────── */
        // Watches the last wizard step (Sign & Submit). When it un-hides,
        // stamps the current time into time_out (if still empty).
        if (timeOutField) {
            var wizSteps = document.querySelectorAll('.wiz-step');
            var signStep = wizSteps.length ? wizSteps[wizSteps.length - 1] : null;

            if (signStep) {
                var signObs = new MutationObserver(function () {
                    if (!signStep.classList.contains('hidden') && !timeOutField.value) {
                        timeOutField.value = currentTimeHHMM();
                    }
                });
                signObs.observe(signStep, { attributes: true, attributeFilter: ['class'] });
            } else {
                // Non-wizard single-page form: fill time_out when sig pad is first touched
                var sigCanvas = document.getElementById('signaturePad') || document.getElementById('maSigPad');
                if (sigCanvas) {
                    sigCanvas.addEventListener('pointerdown', function fillOnce() {
                        if (!timeOutField.value) timeOutField.value = currentTimeHHMM();
                        sigCanvas.removeEventListener('pointerdown', fillOnce);
                    });
                }
            }
        }

        /* ── 2. Normal Vitals quick-fill ─────────────────────────────────── */
        var vitalsGrid = document.querySelector('.vitals-quick-grid');
        if (vitalsGrid) {
            var normalBtn = document.createElement('button');
            normalBtn.type = 'button';
            normalBtn.className =
                'inline-flex items-center gap-1.5 px-4 py-2 ' +
                'bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 ' +
                'text-emerald-700 font-semibold text-sm rounded-xl transition-all no-print mb-3';
            normalBtn.innerHTML = '<i class="bi bi-check2-all"></i> Set Normal Values';
            vitalsGrid.parentNode.insertBefore(normalBtn, vitalsGrid);

            var NORMALS = {
                bp:      '120/80',
                pulse:   '72',
                temp:    '98.6',
                o2sat:   '98',
                resp:    '16',
            };

            normalBtn.addEventListener('click', function () {
                var anyFilled = false;
                Object.keys(NORMALS).forEach(function (name) {
                    var el = document.querySelector('[name="' + name + '"]');
                    if (el && el.value.trim()) anyFilled = true;
                });

                if (anyFilled && !confirm('Some vitals are already filled. Overwrite with normal values?')) return;

                Object.keys(NORMALS).forEach(function (name) {
                    var el = document.querySelector('[name="' + name + '"]');
                    if (el) {
                        el.value = NORMALS[name];
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        // Brief highlight
                        el.style.transition = 'border-color .25s, background .25s';
                        el.style.borderColor = '#6ee7b7';
                        el.style.background  = '#ecfdf5';
                        (function (f) {
                            setTimeout(function () {
                                f.style.borderColor = '';
                                f.style.background  = '';
                            }, 1500);
                        })(el);
                    }
                });
            });
        }

        /* ── 3. Frequency quick-pick pills ──────────────────────────────── */
        var FREQ_OPTS = ['QD', 'BID', 'TID', 'QID', 'Q8H', 'QHS', 'PRN'];

        document.querySelectorAll('[name^="med_freq_"]').forEach(function (input) {
            var wrap = document.createElement('div');
            wrap.className = 'flex flex-wrap gap-1 mt-1';

            FREQ_OPTS.forEach(function (freq) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className =
                    'freq-pill px-2 py-0.5 text-xs font-semibold rounded-lg border ' +
                    'border-slate-200 bg-white text-slate-500 ' +
                    'hover:border-indigo-400 hover:text-indigo-700 hover:bg-indigo-50 ' +
                    'transition-colors no-print';
                btn.textContent = freq;
                btn.title       = 'Click to fill: ' + freq;

                btn.addEventListener('click', function () {
                    input.value = freq;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    // Highlight active pill briefly
                    wrap.querySelectorAll('.freq-pill').forEach(function (p) {
                        p.classList.remove('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
                    });
                    btn.classList.add('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
                    input.focus();
                });
                wrap.appendChild(btn);
            });

            input.parentNode.insertBefore(wrap, input.nextSibling);
        });

        /* ── Mark pill active when input already has a matching value ────── */
        document.querySelectorAll('[name^="med_freq_"]').forEach(function (input) {
            if (!input.value) return;
            var nextWrap = input.nextSibling;
            if (!nextWrap || !nextWrap.classList) return;
            nextWrap.querySelectorAll('.freq-pill').forEach(function (btn) {
                if (btn.textContent === input.value.trim().toUpperCase()) {
                    btn.classList.add('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
                }
            });
        });

        /* ── 4. Dynamic medication rows (Add Row / Remove Row) ───────────── */
        var medTable = document.querySelector('.med-rows-tbody');
        var medCount = document.querySelector('[name="med_count"]');
        var addRowBtn = document.getElementById('medAddRow');

        if (medTable && medCount && addRowBtn) {
            // Show remove button on each empty row (don't remove prefilled rows)
            refreshRemoveBtns();

            addRowBtn.addEventListener('click', function () {
                var newIdx = medTable.querySelectorAll('tr').length + 1;
                var row    = buildMedRow(newIdx, false);
                medTable.appendChild(row);
                medCount.value = parseInt(medCount.value || '0', 10) + 1;
                // Attach freq pills to the new row's freq input
                var freqInput = row.querySelector('[name^="med_freq_"]');
                if (freqInput) attachFreqPills(freqInput);
                refreshRemoveBtns();
                row.querySelector('[name^="med_name_"]').focus();
            });
        }

        function refreshRemoveBtns() {
            if (!medTable) return;
            var rows = medTable.querySelectorAll('tr');
            rows.forEach(function (tr) {
                var isPrefilled = tr.classList.contains('med-prefilled');
                var removeBtn   = tr.querySelector('.med-remove-btn');
                if (isPrefilled) {
                    if (removeBtn) removeBtn.style.display = 'none';
                } else {
                    if (removeBtn) removeBtn.style.display = '';
                }
            });
        }

        function buildMedRow(idx, prefilled) {
            var tr = document.createElement('tr');
            tr.className = prefilled ? 'med-prefilled' : '';

            // hidden med_id
            var hidId = document.createElement('input');
            hidId.type = 'hidden'; hidId.name = 'med_id_' + idx; hidId.value = '0';
            tr.appendChild(hidId);

            // Type cell
            var tdType = document.createElement('td');
            tdType.className = 'px-3 py-2';
            tdType.innerHTML =
                '<select name="med_type_' + idx + '" ' +
                '        class="w-full px-2 py-2 border border-slate-200 rounded-lg text-xs bg-white ' +
                '               focus:outline-none focus:ring-2 focus:ring-indigo-400">' +
                '  <option value="">&mdash;</option>' +
                '  <option>New</option><option>Refill</option><option>D/C</option>' +
                '</select>';

            // Name cell
            var tdName = document.createElement('td');
            tdName.className = 'px-3 py-2';
            tdName.innerHTML =
                '<input type="text" name="med_name_' + idx + '" value="" ' +
                '       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white ' +
                '              focus:outline-none focus:ring-2 focus:ring-indigo-400" ' +
                '       placeholder="Medication name and dose">';

            // Freq cell
            var tdFreq = document.createElement('td');
            tdFreq.className = 'px-3 py-2';
            tdFreq.innerHTML =
                '<input type="text" name="med_freq_' + idx + '" value="" ' +
                '       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white ' +
                '              focus:outline-none focus:ring-2 focus:ring-indigo-400" ' +
                '       placeholder="e.g. BID">';

            // Remove cell
            var tdRm = document.createElement('td');
            tdRm.className = 'px-2 py-2 w-8';
            var rmBtn = document.createElement('button');
            rmBtn.type = 'button';
            rmBtn.className = 'med-remove-btn text-slate-300 hover:text-red-500 transition-colors no-print';
            rmBtn.title = 'Remove row';
            rmBtn.innerHTML = '<i class="bi bi-x-circle text-base"></i>';
            rmBtn.addEventListener('click', function () {
                tr.remove();
                renumberMedRows();
                medCount.value = Math.max(parseInt(medCount.value || '0', 10) - 1, 0);
            });
            tdRm.appendChild(rmBtn);

            tr.appendChild(tdType);
            tr.appendChild(tdName);
            tr.appendChild(tdFreq);
            tr.appendChild(tdRm);
            return tr;
        }

        function renumberMedRows() {
            if (!medTable) return;
            var rows = medTable.querySelectorAll('tr');
            rows.forEach(function (tr, i) {
                var n = i + 1;
                ['med_id_','med_type_','med_name_','med_freq_'].forEach(function (pfx) {
                    var el = tr.querySelector('[name^="' + pfx + '"]');
                    if (el) el.name = pfx + n;
                });
            });
        }

        function attachFreqPills(input) {
            var wrap = document.createElement('div');
            wrap.className = 'flex flex-wrap gap-1 mt-1';
            FREQ_OPTS.forEach(function (freq) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className =
                    'freq-pill px-2 py-0.5 text-xs font-semibold rounded-lg border ' +
                    'border-slate-200 bg-white text-slate-500 ' +
                    'hover:border-indigo-400 hover:text-indigo-700 hover:bg-indigo-50 ' +
                    'transition-colors no-print';
                btn.textContent = freq;
                btn.addEventListener('click', function () {
                    input.value = freq;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    wrap.querySelectorAll('.freq-pill').forEach(function (p) {
                        p.classList.remove('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
                    });
                    btn.classList.add('border-indigo-500', 'bg-indigo-50', 'text-indigo-700');
                });
                wrap.appendChild(btn);
            });
            input.parentNode.insertBefore(wrap, input.nextSibling);
        }

    });
})();
