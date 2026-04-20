/**
 * ai-assistant.js — Gemini AI Integration for PaperlessMD
 *
 * Features:
 *  - Floating chat bubble + panel (staff assistant)
 *  - callAI(action, payload) — centralized API caller
 *  - ICD-10 suggest from chief complaint (vital_cs.php)
 *  - SOAP note draft (vital_cs.php)
 *  - Quick-action tab bridging chat bubble → form buttons
 */
(function () {
    'use strict';

    // ── Core API caller ────────────────────────────────────────────────────────
    var _aiCooldownUntil = 0; // epoch ms — shared across all buttons

    async function callAI(action, payload) {
        // Client-side cooldown guard
        var now = Date.now();
        if (now < _aiCooldownUntil) {
            var secs = Math.ceil((_aiCooldownUntil - now) / 1000);
            throw new Error('Please wait ' + secs + ' second' + (secs === 1 ? '' : 's') + ' before making another AI request.');
        }

        var csrf = window._pdCsrf || '';
        var base = window._pdBase || '';
        var body = Object.assign({ action: action, csrf_token: csrf }, payload);

        var res = await fetch(base + '/api/ai.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(body),
            signal:  AbortSignal.timeout(55000), // 55 s client-side timeout
        });

        var json = await res.json().catch(function () {
            throw new Error('Could not connect to AI service. Check your internet connection.');
        });
        if (!json.ok) {
            var wait = json.retry_after || 30;
            if (res.status === 429) {
                _aiCooldownUntil = Date.now() + wait * 1000;
                startCooldownBadge(wait);
                throw new Error('AI busy \u2014 auto-retrying in ' + wait + 's. Try again shortly.');
            }
            throw new Error(json.error || 'AI request failed');
        }
        return json.text;
    }

    // Floating cooldown badge on the bubble
    function startCooldownBadge(seconds) {
        var badge = document.getElementById('aiCooldownBadge');
        if (!badge) {
            badge = document.createElement('span');
            badge.id = 'aiCooldownBadge';
            badge.style.cssText =
                'position:absolute;top:-4px;right:-4px;min-width:22px;height:22px;' +
                'background:#ef4444;color:#fff;border-radius:999px;font-size:11px;' +
                'font-weight:700;display:flex;align-items:center;justify-content:center;' +
                'padding:0 4px;line-height:1;z-index:1;';
            if (bubble) {
                bubble.style.position = 'relative';
                bubble.appendChild(badge);
            }
        }
        var remaining = seconds;
        badge.textContent = remaining + 's';
        var t = setInterval(function () {
            remaining--;
            if (remaining <= 0) {
                clearInterval(t);
                if (badge.parentNode) badge.remove();
            } else {
                badge.textContent = remaining + 's';
            }
        }, 1000);
    }

    // ── DOM refs ──────────────────────────────────────────────────────────────
    var bubble   = document.getElementById('aiBubble');
    var panel    = document.getElementById('aiPanel');
    var closeBtn = document.getElementById('aiClose');
    var messages = document.getElementById('aiMessages');
    var chatInput = document.getElementById('aiChatInput');
    var sendBtn  = document.getElementById('aiSend');

    if (!bubble || !panel) return; // not logged in

    // ── Panel open / close ────────────────────────────────────────────────────
    var isOpen = false;

    function openPanel() {
        panel.classList.remove('hidden');
        panel.classList.add('flex');
        requestAnimationFrame(function () {
            panel.classList.add('ai-panel--open');
        });
        isOpen = true;
        if (chatInput) chatInput.focus();
    }

    function closePanel() {
        panel.classList.remove('ai-panel--open');
        isOpen = false;
        // Hide after transition
        setTimeout(function () {
            if (!isOpen) {
                panel.classList.remove('flex');
                panel.classList.add('hidden');
            }
        }, 250);
    }

    bubble.addEventListener('click', function () {
        if (isOpen) { closePanel(); } else { openPanel(); }
    });

    if (closeBtn) closeBtn.addEventListener('click', closePanel);

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (isOpen && !panel.contains(e.target) && !bubble.contains(e.target)) {
            closePanel();
        }
    });

    // ── Tab switching ─────────────────────────────────────────────────────────
    var tabBtns  = panel.querySelectorAll('.ai-tab');
    var tabChat  = document.getElementById('aiTabChat');
    var tabQuick = document.getElementById('aiTabQuick');

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = btn.dataset.tab;
            tabBtns.forEach(function (b) { b.classList.remove('ai-tab--active'); });
            btn.classList.add('ai-tab--active');
            if (tabChat)  tabChat.classList.toggle('hidden', target !== 'chat');
            if (tabQuick) tabQuick.classList.toggle('hidden', target !== 'quick');
        });
    });

    // ── Chat messaging ────────────────────────────────────────────────────────
    function appendMsg(role, text) {
        var div = document.createElement('div');
        div.className = 'ai-msg ai-msg-' + role;
        div.textContent = text;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    function setLoading(on) {
        if (sendBtn) sendBtn.disabled = on;
        if (on) {
            var ld = document.createElement('div');
            ld.className = 'ai-msg ai-msg-bot ai-loading';
            ld.id        = 'aiLoadingMsg';
            ld.innerHTML = '<span class="ai-dot"></span>'
                         + '<span class="ai-dot"></span>'
                         + '<span class="ai-dot"></span>';
            messages.appendChild(ld);
            messages.scrollTop = messages.scrollHeight;
        } else {
            var ex = document.getElementById('aiLoadingMsg');
            if (ex) ex.remove();
        }
    }

    async function doChat() {
        if (!chatInput) return;
        var q = chatInput.value.trim();
        if (!q) return;
        chatInput.value = '';
        appendMsg('user', q);
        setLoading(true);
        try {
            var ans = await callAI('chat', { question: q });
            setLoading(false);
            appendMsg('bot', ans);
        } catch (e) {
            setLoading(false);
            appendMsg('bot', '\u26a0\ufe0f Error: ' + e.message);
        }
    }

    if (sendBtn)  sendBtn.addEventListener('click', doChat);
    if (chatInput) chatInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); doChat(); }
    });

    // ── Toast helper ──────────────────────────────────────────────────────────
    function showToast(msg, ms) {
        var t = document.createElement('div');
        t.className  = 'ai-toast';
        t.textContent = msg;
        document.body.appendChild(t);
        requestAnimationFrame(function () { t.classList.add('ai-toast--show'); });
        setTimeout(function () {
            t.classList.remove('ai-toast--show');
            setTimeout(function () { if (t.parentNode) t.remove(); }, 350);
        }, ms || 4000);
    }

    // ── SOAP Note Modal ───────────────────────────────────────────────────────
    function showSoapModal(noteText) {
        var existing = document.getElementById('aiSoapModal');
        if (existing) existing.remove();

        var overlay = document.createElement('div');
        overlay.id  = 'aiSoapModal';
        overlay.className = 'ai-soap-modal-overlay';

        overlay.innerHTML =
            '<div class="ai-soap-modal">' +
            '  <div class="ai-soap-modal-header">' +
            '    <span><i class="bi bi-file-medical"></i>&ensp;AI SOAP Note Draft</span>' +
            '    <button id="aiSoapClose" aria-label="Close"><i class="bi bi-x-lg"></i></button>' +
            '  </div>' +
            '  <p class="ai-soap-disclaimer">' +
            '    &#9888; AI-generated draft &mdash; review carefully before signing. ' +
            '    Fields marked [REVIEW] require clinician input.' +
            '  </p>' +
            '  <textarea id="aiSoapText" class="ai-soap-textarea">' + escHtml(noteText) + '</textarea>' +
            '  <div class="ai-soap-actions">' +
            '    <button id="aiSoapCopy"><i class="bi bi-clipboard"></i> Copy</button>' +
            '    <button id="aiSoapFill"><i class="bi bi-arrow-down-circle"></i> Fill Notes Field</button>' +
            '    <button id="aiSoapDismiss">Dismiss</button>' +
            '  </div>' +
            '</div>';

        document.body.appendChild(overlay);

        function closeModal() { if (overlay.parentNode) overlay.remove(); }

        document.getElementById('aiSoapClose').addEventListener('click', closeModal);
        document.getElementById('aiSoapDismiss').addEventListener('click', closeModal);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });

        document.getElementById('aiSoapCopy').addEventListener('click', function () {
            var ta = document.getElementById('aiSoapText');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(ta.value).then(function () {
                    showToast('Copied to clipboard.', 2500);
                });
            }
        });

        document.getElementById('aiSoapFill').addEventListener('click', function () {
            var ta = document.getElementById('aiSoapText');
            var ccEl = document.querySelector('[name="chief_complaint"]');
            if (ccEl) {
                ccEl.value = ta.value;
                showToast('Chief Complaint / Notes field filled. Review before submitting.', 4000);
                closeModal();
            } else {
                showToast('No notes field found on this page.', 3000);
            }
        });
    }

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── ICD-10 Suggest (vital_cs.php form button) ─────────────────────────────
    var icdBtn = document.getElementById('aiIcdSuggestBtn');
    if (icdBtn) {
        icdBtn.addEventListener('click', async function () {
            var ccEl = document.querySelector('[name="chief_complaint"]');
            var cc   = ccEl ? ccEl.value.trim() : '';
            if (!cc) {
                showToast('Please enter a Chief Complaint first.', 3500);
                if (ccEl) ccEl.focus();
                return;
            }
            var origHtml = icdBtn.innerHTML;
            icdBtn.disabled = true;
            icdBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Suggesting\u2026';

            try {
                var text  = await callAI('icd_suggest', { chief_complaint: cc });
                var codes = [];
                // Extract JSON array from response
                var match = text.match(/\[[\s\S]*?\]/);
                if (match) {
                    try { codes = JSON.parse(match[0]); } catch (_) { /* fallback below */ }
                }

                if (codes.length > 0 && typeof window.icdAddChip === 'function') {
                    var added = 0;
                    codes.forEach(function (item) {
                        if (item && item.code) {
                            window.icdAddChip(item.code, item.description || item.code, item.cat || '');
                            added++;
                        }
                    });
                    showToast('Added ' + added + ' AI-suggested code(s). Review before submitting.', 5000);
                } else {
                    // Show raw text in chat panel
                    openPanel();
                    appendMsg('bot', 'ICD-10 suggestions:\n' + text);
                }
            } catch (e) {
                showToast('AI error: ' + e.message, 5000);
            } finally {
                icdBtn.disabled = false;
                icdBtn.innerHTML = origHtml;
            }
        });
    }

    // ── SOAP Draft (vital_cs.php sign step button) ────────────────────────────
    var soapBtn = document.getElementById('aiSoapBtn');
    if (soapBtn) {
        soapBtn.addEventListener('click', async function () {
            // Collect vitals
            var vitalFields = ['bp_systolic', 'bp_diastolic', 'pulse', 'resp_rate', 'temp', 'o2_sat', 'weight'];
            var vitalsArr   = [];
            vitalFields.forEach(function (f) {
                var el = document.querySelector('[name="' + f + '"]');
                if (el && el.value.trim()) {
                    vitalsArr.push(f.replace(/_/g, ' ') + ': ' + el.value.trim());
                }
            });

            var cc = '';
            var ccEl = document.querySelector('[name="chief_complaint"]');
            if (ccEl) cc = ccEl.value.trim();

            var origHtml = soapBtn.innerHTML;
            soapBtn.disabled = true;
            soapBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Drafting\u2026';

            try {
                var note = await callAI('soap_draft', {
                    chief_complaint: cc || 'wound care follow-up visit',
                    vitals:          vitalsArr.join(', '),
                });
                showSoapModal(note);
            } catch (e) {
                showToast('AI error: ' + e.message, 5000);
            } finally {
                soapBtn.disabled = false;
                soapBtn.innerHTML = origHtml;
            }
        });
    }

    // ── Quick-action buttons in chat panel (mirroring in-form buttons) ─────────
    var quickIcd  = document.getElementById('aiQuickIcd');
    var quickSoap = document.getElementById('aiQuickSoap');
    var quickNA   = document.getElementById('aiQuickNotAvail');

    // Show/hide depending on whether form buttons exist on page
    if (quickIcd && icdBtn) {
        quickIcd.classList.remove('hidden');
        quickIcd.classList.add('flex');
        if (quickNA) quickNA.classList.add('hidden');
        quickIcd.addEventListener('click', function () { icdBtn.click(); });
    }
    if (quickSoap && soapBtn) {
        quickSoap.classList.remove('hidden');
        quickSoap.classList.add('flex');
        if (quickNA) quickNA.classList.add('hidden');
        quickSoap.addEventListener('click', function () { soapBtn.click(); });
    }

    // ── Expose globals for other scripts ─────────────────────────────────────
    window._aiCall      = callAI;
    window._aiShowToast = showToast;
}());
