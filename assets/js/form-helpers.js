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
        var tz = (window._pdTimezone && window._pdTimezone !== '') ? window._pdTimezone : undefined;
        try {
            var parts = new Intl.DateTimeFormat('en-US', {
                timeZone: tz,
                hour:     '2-digit',
                minute:   '2-digit',
                hour12:   false
            }).formatToParts(new Date());
            var h = '', m = '';
            parts.forEach(function (p) {
                if (p.type === 'hour')   h = p.value;
                if (p.type === 'minute') m = p.value;
            });
            // Intl may return '24' for midnight — normalise to '00'
            if (h === '24') h = '00';
            return h.padStart(2,'0') + ':' + m.padStart(2,'0');
        } catch (e) {
            // Fallback: browser local time
            var now = new Date();
            return String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');
        }
    }

    function init() {

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

        /* ── 3. Interactive Vitals Numpad ────────────────────────────────── */
        initVitalsNumpad();

        /* ── 4. Frequency quick-pick pills ──────────────────────────────── */
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
            tdType.setAttribute('data-label', 'Type');
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
            tdName.setAttribute('data-label', 'Medication & Dose');
            tdName.innerHTML =
                '<input type="text" name="med_name_' + idx + '" value="" ' +
                '       class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white ' +
                '              focus:outline-none focus:ring-2 focus:ring-indigo-400" ' +
                '       placeholder="Medication name and dose">';

            // Freq cell
            var tdFreq = document.createElement('td');
            tdFreq.className = 'px-3 py-2';
            tdFreq.setAttribute('data-label', 'Frequency');
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

    }

    /* ── Vitals Numpad ──────────────────────────────────────────────────
     * Opens an inline touch-friendly numpad below the vitals grid when any
     * vital card is tapped. Preset buttons one-tap commit + auto-advance.
     * Desktop keyboard also works while the panel is open.
     * ─────────────────────────────────────────────────────────────────── */
    function initVitalsNumpad() {
        var grid = document.querySelector('.vitals-quick-grid');
        if (!grid) return;

        var CFG = {
            bp:      { label:'Blood Pressure', unit:'mmHg',  type:'bp',
                       presets:['100/60','110/70','120/80','130/80','140/90','150/90','160/90'] },
            pulse:   { label:'Pulse',          unit:'bpm',   type:'num',
                       presets:['52','60','68','72','76','80','88','96','100','110'] },
            temp:    { label:'Temperature',    unit:'\u00b0F',  type:'decimal',
                       presets:['97.6','97.8','98.0','98.4','98.6','99.0','99.5','100.4'] },
            o2sat:   { label:'O\u2082 Saturation', unit:'%', type:'num',
                       presets:['88','90','92','94','95','96','97','98','99','100'] },
            glucose: { label:'Blood Glucose',  unit:'mg/dL', type:'num',
                       presets:['70','80','90','100','110','120','140','160','200'] },
            height:  { label:'Height',         unit:'',      type:'text',
                       presets:["4'10\"",'5\'0"','5\'2"','5\'4"','5\'6"','5\'8"','5\'10"','6\'0"','6\'2"'] },
            weight:  { label:'Weight',         unit:'lbs',   type:'num',
                       presets:['100','110','120','130','140','150','160','175','200','225','250','300'] },
            resp:    { label:'Respirations',   unit:'/min',  type:'num',
                       presets:['12','14','16','18','20','22','24'] },
        };
        var ORDER = ['bp','pulse','temp','o2sat','glucose','height','weight','resp'];
        var activeInput = null, activeKey = null, draft = '';

        /* ── Build panel HTML ───────────────────────────────────────────── */
        var panel = document.createElement('div');
        panel.id  = 'pd-vnp';
        panel.style.cssText = 'display:none;margin-top:14px;background:#fff;border:2px solid #818cf8;border-radius:20px;padding:16px 18px;box-shadow:0 8px 32px rgba(99,102,241,.15)';
        panel.innerHTML =
            '<style>' +
            '@keyframes pdVnpIn{from{opacity:0;transform:translateY(-5px)}to{opacity:1;transform:translateY(0)}}' +
            '@keyframes pdBlink{0%,100%{opacity:1}50%{opacity:0}}' +
            '#pd-vnp{animation:pdVnpIn .15s ease}' +
            '.vnp-preset{padding:7px 15px;border:1.5px solid #c7d2fe;border-radius:10px;background:#eef2ff;color:#4338ca;font-size:13px;font-weight:700;cursor:pointer;transition:.12s all;white-space:nowrap;touch-action:manipulation;-webkit-user-select:none;user-select:none}' +
            '.vnp-preset:hover,.vnp-preset.vnp-active{background:#4f46e5;color:#fff;border-color:#4f46e5}' +
            '.vnp-key{height:56px;border:1.5px solid #e2e8f0;border-radius:14px;background:#f8fafc;font-size:22px;font-weight:700;color:#1e293b;cursor:pointer;transition:.1s all;display:flex;align-items:center;justify-content:center;-webkit-user-select:none;user-select:none;touch-action:manipulation}' +
            '.vnp-key:hover{background:#eef2ff;border-color:#a5b4fc}' +
            '.vnp-key:active{transform:scale(.91);background:#4f46e5;color:#fff;border-color:#4f46e5}' +
            '.vnp-key-del{background:#fef2f2!important;border-color:#fecaca!important;color:#ef4444!important}' +
            '.vnp-key-del:hover{background:#fee2e2!important}' +
            '.vnp-key-del:active{background:#ef4444!important;color:#fff!important}' +
            '.vnp-card-active{border-color:#818cf8!important;box-shadow:0 0 0 3px rgba(129,140,248,.18)!important}' +
            '</style>' +
            // Header
            '<div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">' +
            '  <span id="pd-vnp-label" style="font-size:12px;font-weight:800;color:#4f46e5;text-transform:uppercase;letter-spacing:.08em;flex:1"></span>' +
            '  <div id="pd-vnp-dots" style="display:flex;gap:5px;align-items:center"></div>' +
            '  <button id="pd-vnp-close" type="button" title="Close" style="width:28px;height:28px;border-radius:50%;border:1.5px solid #e2e8f0;background:#f8fafc;color:#64748b;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;touch-action:manipulation">&#x2715;</button>' +
            '</div>' +
            // Value display
            '<div style="background:#f1f5f9;border:1.5px solid #e2e8f0;border-radius:14px;padding:10px 18px;margin-bottom:12px;display:flex;align-items:baseline;gap:8px;min-height:62px">' +
            '  <span id="pd-vnp-display" style="font-size:42px;font-weight:900;color:#1e293b;letter-spacing:.01em;min-width:60px;line-height:1"></span>' +
            '  <span id="pd-vnp-unit" style="font-size:15px;color:#94a3b8;font-weight:600;line-height:1;padding-bottom:2px"></span>' +
            '  <span style="flex:1"></span>' +
            '  <span id="pd-vnp-cur" style="width:3px;height:38px;background:#4f46e5;border-radius:2px;animation:pdBlink 1s step-end infinite;flex-shrink:0;align-self:center"></span>' +
            '</div>' +
            // Presets
            '<div id="pd-vnp-presets" style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:12px"></div>' +
            // Numpad 3x4
            '<div id="pd-vnp-keys" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px">' +
            '  <button type="button" class="vnp-key" data-k="7">7</button><button type="button" class="vnp-key" data-k="8">8</button><button type="button" class="vnp-key" data-k="9">9</button>' +
            '  <button type="button" class="vnp-key" data-k="4">4</button><button type="button" class="vnp-key" data-k="5">5</button><button type="button" class="vnp-key" data-k="6">6</button>' +
            '  <button type="button" class="vnp-key" data-k="1">1</button><button type="button" class="vnp-key" data-k="2">2</button><button type="button" class="vnp-key" data-k="3">3</button>' +
            '  <button type="button" class="vnp-key" data-k=".">&#xb7;</button><button type="button" class="vnp-key" data-k="0">0</button><button type="button" class="vnp-key vnp-key-del" data-k="back">&#x232B;</button>' +
            '</div>' +
            // Action row
            '<div style="display:grid;grid-template-columns:auto auto 1fr;gap:8px">' +
            '  <button type="button" id="pd-vnp-clear" style="padding:12px 16px;border:1.5px solid #fecaca;background:#fef2f2;color:#dc2626;border-radius:12px;font-weight:700;font-size:13px;cursor:pointer;touch-action:manipulation;white-space:nowrap">&#x2715; Clear</button>' +
            '  <button type="button" id="pd-vnp-skip"  style="padding:12px 16px;border:1.5px solid #e2e8f0;background:#f8fafc;color:#64748b;border-radius:12px;font-weight:700;font-size:13px;cursor:pointer;touch-action:manipulation;white-space:nowrap">Skip &#x2192;</button>' +
            '  <button type="button" id="pd-vnp-ok"    style="padding:12px;border:none;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border-radius:12px;font-weight:800;font-size:14px;cursor:pointer;width:100%;display:flex;align-items:center;justify-content:center;gap:6px;touch-action:manipulation">&#x2713; Confirm &amp; Next</button>' +
            '</div>';

        grid.parentNode.insertBefore(panel, grid.nextSibling);

        /* ── Helpers ─────────────────────────────────────────────────────── */
        function updateDisplay() {
            var el = document.getElementById('pd-vnp-display');
            if (el) el.textContent = draft;
            var cur = document.getElementById('pd-vnp-cur');
            if (cur) cur.style.opacity = draft === '' ? '1' : '0';
        }

        function buildDots() {
            var el = document.getElementById('pd-vnp-dots');
            if (!el) return;
            el.innerHTML = '';
            ORDER.forEach(function (key) {
                var inp = document.querySelector('[name="' + key + '"]');
                var dot = document.createElement('div');
                var isActive = key === activeKey;
                var isDone   = inp && inp.value.trim() !== '';
                dot.style.cssText = 'height:8px;border-radius:4px;transition:.2s all;flex-shrink:0;' +
                    'width:' + (isActive ? '18px' : '8px') + ';' +
                    'background:' + (isActive ? '#4f46e5' : isDone ? '#22c55e' : '#e2e8f0') + ';';
                el.appendChild(dot);
            });
        }

        function syncPresets() {
            panel.querySelectorAll('.vnp-preset').forEach(function (b) {
                b.classList.toggle('vnp-active', b.dataset.val === draft);
            });
        }

        function handleKey(k) {
            var cfg = CFG[activeKey] || { type: 'num' };
            if (k === 'back') {
                draft = draft.slice(0, -1);
            } else if (k === '.') {
                if (cfg.type === 'decimal' && draft.indexOf('.') === -1) {
                    draft += (draft === '' ? '0.' : '.');
                } else if (cfg.type === 'bp' && draft.indexOf('/') === -1 && draft.length > 0) {
                    draft += '/';
                }
            } else if (cfg.type !== 'text') {
                // BP: auto-insert slash after 3 systolic digits
                if (cfg.type === 'bp' && draft.indexOf('/') === -1 && draft.replace(/\D/g,'').length === 3) {
                    draft += '/';
                }
                draft += k;
            }
            updateDisplay();
            syncPresets();
        }

        function commitValue() {
            if (activeInput && draft.trim() !== '') {
                activeInput.value = draft.trim();
                activeInput.dispatchEvent(new Event('input',  { bubbles: true }));
                activeInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        function setCardActive(key, on) {
            var inp = key && document.querySelector('[name="' + key + '"]');
            if (!inp) return;
            var card = inp.closest('[class*="rounded-xl"]') || inp.parentElement;
            if (card) { on ? card.classList.add('vnp-card-active') : card.classList.remove('vnp-card-active'); }
        }

        function openFor(input, key) {
            if (activeKey) { setCardActive(activeKey, false); if (activeInput) activeInput.removeAttribute('readonly'); }
            activeInput = input;
            activeKey   = key;
            draft       = input.value || '';
            // Suppress mobile virtual keyboard — numpad replaces it
            input.setAttribute('readonly', '');

            var cfg = CFG[key] || { label: key, unit: '', presets: [], type: 'num' };
            document.getElementById('pd-vnp-label').textContent = cfg.label;
            document.getElementById('pd-vnp-unit').textContent  = cfg.unit;
            updateDisplay();

            var presetsEl = document.getElementById('pd-vnp-presets');
            presetsEl.innerHTML = '';
            cfg.presets.forEach(function (val) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'vnp-preset' + (val === draft ? ' vnp-active' : '');
                b.dataset.val = val;
                b.textContent = val + (cfg.unit ? '\u00a0' + cfg.unit : '');
                b.addEventListener('click', function () {
                    draft = val;
                    commitValue();
                    advanceToNext();
                });
                presetsEl.appendChild(b);
            });

            var keysEl = document.getElementById('pd-vnp-keys');
            if (keysEl) keysEl.style.display = cfg.type === 'text' ? 'none' : 'grid';

            setCardActive(key, true);
            buildDots();
            panel.style.display = 'block';
            setTimeout(function () { panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 60);
        }

        function advanceToNext() {
            var idx = ORDER.indexOf(activeKey);
            for (var i = idx + 1; i < ORDER.length; i++) {
                var ni = document.querySelector('[name="' + ORDER[i] + '"]');
                if (ni) { openFor(ni, ORDER[i]); return; }
            }
            closePanel();
        }

        function closePanel() {
            setCardActive(activeKey, false);
            if (activeInput) activeInput.removeAttribute('readonly');
            panel.style.display = 'none';
            activeInput = null; activeKey = null; draft = '';
        }

        /* ── Wire numpad buttons ────────────────────────────────────────── */
        panel.querySelectorAll('.vnp-key').forEach(function (btn) {
            btn.addEventListener('click', function () { handleKey(btn.dataset.k); });
        });
        document.getElementById('pd-vnp-close').addEventListener('click', closePanel);
        document.getElementById('pd-vnp-clear').addEventListener('click', function () { draft = ''; updateDisplay(); syncPresets(); });
        document.getElementById('pd-vnp-skip').addEventListener('click', advanceToNext);
        document.getElementById('pd-vnp-ok').addEventListener('click', function () { commitValue(); advanceToNext(); });

        /* ── Desktop keyboard support while panel is open ───────────────── */
        document.addEventListener('keydown', function (e) {
            if (panel.style.display === 'none') return;
            // Prevent these keys from propagating to the wizard nav
            if (/^[0-9]$/.test(e.key))                           { e.preventDefault(); handleKey(e.key); }
            else if (e.key === 'Backspace')                       { e.preventDefault(); handleKey('back'); }
            else if (e.key === '.' || e.key === '/')              { e.preventDefault(); handleKey('.'); }
            else if (e.key === 'Enter' || e.key === 'Tab')        { e.preventDefault(); commitValue(); advanceToNext(); }
            else if (e.key === 'Escape')                          { e.preventDefault(); closePanel(); }
        });

        /* ── Wire vital card clicks ─────────────────────────────────────── */
        ORDER.forEach(function (key) {
            var input = document.querySelector('[name="' + key + '"]');
            if (!input) return;
            var card = input.closest('[class*="rounded-xl"]') || input.parentElement;
            if (card) card.style.cursor = 'pointer';
            // Open numpad when the input is focused (click or tab)
            input.addEventListener('focus', function () { openFor(input, key); });
            // Clicking the card background (not the input) also opens it
            if (card && card !== input) {
                card.addEventListener('click', function (e) {
                    if (e.target !== input) openFor(input, key);
                });
            }
        });
    }

    // defer guarantees DOM is ready; fall back to DOMContentLoaded just in case
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
