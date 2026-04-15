'use strict';
/* ─────────────────────────────────────────────────────────────────────────
 * PaperlessMD — Voice-to-Text (Web Speech API)
 *
 * Auto-attaches a mic button to every <textarea> and matched <input> on the
 * page.  Appends dictated text to existing content (cursor-aware).
 * Falls back silently on browsers that don't support SpeechRecognition.
 * ──────────────────────────────────────────────────────────────────────── */
(function () {

    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) return; // browser doesn't support it — bail quietly

    /* Selectors that get a mic button */
    var TEXTAREA_SEL = 'textarea[name]';
    var INPUT_SEL    = 'input[type="text"][name*="description"], input[type="text"][name*="notes"], input[type="text"][name*="complaint"]';

    /* Active recognition instance */
    var activeRec = null;
    var activeBtn = null;

    /* ── Create mic button ────────────────────────────────────────────────── */
    function createMicBtn() {
        var btn = document.createElement('button');
        btn.type        = 'button';
        btn.title       = 'Dictate (voice-to-text)';
        btn.className   = 'pd-mic-btn';
        btn.innerHTML   = micIcon();
        btn.setAttribute('aria-label', 'Start dictation');
        return btn;
    }

    function micIcon()     { return '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1a4 4 0 0 1 4 4v7a4 4 0 0 1-8 0V5a4 4 0 0 1 4-4zm6 11a6 6 0 0 1-12 0H4a8 8 0 0 0 7 7.93V22h2v-2.07A8 8 0 0 0 20 12h-2z"/></svg>'; }
    function stopIcon()    { return '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><rect x="5" y="5" width="14" height="14" rx="2"/></svg>'; }

    /* ── Wrap a target element ────────────────────────────────────────────── */
    function attachMic(el) {
        if (el.dataset.voiceAttached) return;
        el.dataset.voiceAttached = '1';

        var btn = createMicBtn();

        /* For textarea: overlay button at top-right */
        if (el.tagName === 'TEXTAREA') {
            var parent = el.parentElement;
            /* Ensure wrapper has position:relative */
            var cs = window.getComputedStyle(parent);
            if (cs.position === 'static') parent.style.position = 'relative';

            btn.style.cssText = [
                'position:absolute',
                'top:0.5rem',
                'right:0.5rem',
                'z-index:10',
            ].join(';');
            parent.appendChild(btn);
            /* Give textarea right-padding so text doesn't vanish under button */
            el.style.paddingRight = '2.75rem';

        } else {
            /* For inline text inputs: place button as a sibling after the input */
            var wrapper = document.createElement('span');
            wrapper.style.cssText = 'position:relative;display:block;';
            el.parentNode.insertBefore(wrapper, el);
            wrapper.appendChild(el);
            btn.style.cssText = [
                'position:absolute',
                'top:50%',
                'right:0.5rem',
                'transform:translateY(-50%)',
                'z-index:10',
            ].join(';');
            wrapper.appendChild(btn);
            el.style.paddingRight = '2.75rem';
        }

        btn.addEventListener('click', function () {
            if (activeRec && activeBtn === btn) {
                stopListening();
            } else {
                if (activeRec) stopListening();
                startListening(el, btn);
            }
        });
    }

    /* ── Speech recognition ──────────────────────────────────────────────── */
    function startListening(target, btn) {
        var rec = new SpeechRecognition();
        rec.lang           = 'en-US';
        rec.continuous     = true;
        rec.interimResults = true;

        var interimStart = null; // caret position where interim text begins

        rec.onstart = function () {
            activeRec = rec;
            activeBtn = btn;
            btn.innerHTML = stopIcon();
            btn.classList.add('pd-mic-recording');
            btn.title = 'Stop dictation';
            showToast('Listening…');
        };

        rec.onresult = function (e) {
            var interim = '';
            var finalPart = '';
            for (var i = e.resultIndex; i < e.results.length; i++) {
                var t = e.results[i][0].transcript;
                if (e.results[i].isFinal) {
                    finalPart += t;
                } else {
                    interim += t;
                }
            }

            if (finalPart) {
                /* Remove any interim text we placed, append final */
                if (interimStart !== null) {
                    target.value = target.value.substring(0, interimStart);
                    interimStart = null;
                }
                /* Insert at cursor or append */
                var pos = target.selectionEnd != null ? target.selectionEnd : target.value.length;
                var before = target.value.substring(0, pos);
                var after  = target.value.substring(pos);
                /* Add a space separator if needed */
                var sep = (before.length > 0 && !/\s$/.test(before)) ? ' ' : '';
                target.value = before + sep + finalPart.trimStart();
                var newPos = target.value.length - after.length;
                target.setSelectionRange(newPos, newPos);
                /* Track where next interim text would start */
                interimStart = newPos;
                target.dispatchEvent(new Event('input', { bubbles: true }));
            }
        };

        rec.onerror = function (e) {
            if (e.error === 'no-speech') return;
            showToast('Microphone error: ' + e.error, true);
            stopListening();
        };

        rec.onend = function () {
            /* Auto-restart if still active (browser stops after silence) */
            if (activeRec === rec && btn.classList.contains('pd-mic-recording')) {
                try { rec.start(); } catch (ex) { /* already stopped */ }
            }
        };

        try {
            rec.start();
        } catch (ex) {
            showToast('Could not start microphone.', true);
        }
    }

    function stopListening() {
        if (activeRec) {
            try { activeRec.stop(); } catch (ex) {}
            activeRec = null;
        }
        if (activeBtn) {
            activeBtn.innerHTML = micIcon();
            activeBtn.classList.remove('pd-mic-recording');
            activeBtn.title = 'Dictate (voice-to-text)';
            activeBtn = null;
        }
        dismissToast();
    }

    /* ── Toast indicator ─────────────────────────────────────────────────── */
    var toast = null;
    function showToast(msg, isErr) {
        dismissToast();
        toast = document.createElement('div');
        toast.id = 'pdVoiceToast';
        toast.textContent = (isErr ? '⚠ ' : '🎙 ') + msg;
        toast.style.cssText = [
            'position:fixed',
            'bottom:5rem',
            'left:50%',
            'transform:translateX(-50%)',
            'z-index:99999',
            'background:' + (isErr ? '#dc2626' : '#1e293b'),
            'color:#f8fafc',
            'font-size:0.8rem',
            'font-weight:600',
            'padding:0.5rem 1.25rem',
            'border-radius:999px',
            'box-shadow:0 4px 16px rgba(0,0,0,0.25)',
            'pointer-events:none',
            'white-space:nowrap',
        ].join(';');
        document.body.appendChild(toast);
        if (isErr) setTimeout(dismissToast, 4000);
    }
    function dismissToast() {
        if (toast) { toast.remove(); toast = null; }
    }

    /* ── Inject styles ────────────────────────────────────────────────────── */
    var style = document.createElement('style');
    style.textContent = [
        '.pd-mic-btn {',
        '  display:inline-flex; align-items:center; justify-content:center;',
        '  width:2rem; height:2rem;',
        '  border-radius:50%;',
        '  border:none; cursor:pointer;',
        '  background:rgba(248,250,252,0.95);',
        '  color:#64748b;',
        '  box-shadow:0 1px 4px rgba(0,0,0,0.12);',
        '  transition:background 0.15s, color 0.15s, box-shadow 0.15s;',
        '  flex-shrink:0;',
        '}',
        '.pd-mic-btn:hover { background:#f1f5f9; color:#3b82f6; box-shadow:0 2px 8px rgba(59,130,246,0.2); }',
        '.pd-mic-btn.pd-mic-recording {',
        '  background:#ef4444; color:#fff;',
        '  box-shadow:0 0 0 0 rgba(239,68,68,0.4);',
        '  animation:pd-mic-pulse 1.2s ease-out infinite;',
        '}',
        '@keyframes pd-mic-pulse {',
        '  0%   { box-shadow:0 0 0 0 rgba(239,68,68,0.5); }',
        '  70%  { box-shadow:0 0 0 8px rgba(239,68,68,0); }',
        '  100% { box-shadow:0 0 0 0 rgba(239,68,68,0); }',
        '}',
    ].join('\n');
    document.head.appendChild(style);

    /* ── Initialise on DOMContentLoaded (and MutationObserver for dynamic forms) ─ */
    function attachAll() {
        document.querySelectorAll(TEXTAREA_SEL).forEach(attachMic);
        document.querySelectorAll(INPUT_SEL).forEach(attachMic);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachAll);
    } else {
        attachAll();
    }

    /* Pick up dynamically added textareas (e.g. wound_care row cloning) */
    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            m.addedNodes.forEach(function (n) {
                if (n.nodeType !== 1) return;
                if (n.matches && n.matches(TEXTAREA_SEL)) attachMic(n);
                n.querySelectorAll && n.querySelectorAll(TEXTAREA_SEL).forEach(attachMic);
                if (n.matches && n.matches(INPUT_SEL)) attachMic(n);
                n.querySelectorAll && n.querySelectorAll(INPUT_SEL).forEach(attachMic);
            });
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });

    /* ── Stop recognition when navigating away ────────────────────────────── */
    window.addEventListener('beforeunload', stopListening);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') stopListening();
    });

})();
