</div><!-- /container -->
</div><!-- /pt-14 -->

<?php if (!empty($_SESSION['user_id'])): ?>
<!-- ── Session Timeout Warning Modal ─────────────────────────────────── -->
<div id="sessionTimeoutModal"
     class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4 no-print"
     aria-modal="true" role="alertdialog" aria-labelledby="sessionModalTitle">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <!-- Card -->
    <div class="relative bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-sm p-8 text-center">
        <div class="w-14 h-14 bg-amber-100 rounded-2xl grid place-items-center mx-auto mb-4">
            <i class="bi bi-clock-history text-amber-500 text-3xl"></i>
        </div>
        <h2 id="sessionModalTitle" class="text-lg font-bold text-slate-800 mb-1">Session Expiring Soon</h2>
        <p class="text-sm text-slate-500 mb-4">You'll be logged out automatically in</p>
        <div id="sessionCountdown"
             class="text-5xl font-bold text-amber-500 tabular-nums mb-1 transition-colors">
            2:00
        </div>
        <p class="text-xs text-slate-400 mb-6">seconds of inactivity</p>
        <div class="flex gap-3">
            <button id="sessionStayBtn"
                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3
                           bg-emerald-600 hover:bg-emerald-700 text-white font-bold
                           rounded-xl transition-colors shadow-sm text-sm">
                <i class="bi bi-check-circle-fill"></i> Stay Logged In
            </button>
            <a href="<?= BASE_URL ?>/logout.php"
               class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3
                      bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold
                      rounded-xl transition-colors text-sm">
                <i class="bi bi-box-arrow-right"></i> Log Out
            </a>
        </div>
    </div>
</div>

<?php if (!empty($_SESSION['prompt_saved_sig'])): unset($_SESSION['prompt_saved_sig']); ?>
<!-- ── Pre-Saved Signature Prompt Modal ───────────────────────────────── -->
<div id="savedSigPromptModal"
     class="fixed inset-0 z-[9900] flex items-center justify-center p-4 no-print"
     aria-modal="true" role="dialog" aria-labelledby="savedSigPromptTitle">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
         id="savedSigPromptBackdrop"></div>
    <!-- Card -->
    <div class="relative bg-white rounded-2xl shadow-2xl border border-slate-100 w-full max-w-md overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-emerald-600 to-teal-500 px-6 py-5 flex items-center gap-4">
            <div class="w-12 h-12 bg-white/20 rounded-2xl grid place-items-center shrink-0">
                <i class="bi bi-pen-fill text-white text-2xl"></i>
            </div>
            <div>
                <h2 id="savedSigPromptTitle" class="text-white font-extrabold text-lg leading-tight">Set Up Your Signature</h2>
                <p class="text-emerald-100 text-xs mt-0.5">Draw once — auto-fills every form you complete</p>
            </div>
        </div>
        <!-- Body -->
        <div class="px-6 py-5">
            <p class="text-slate-600 text-sm mb-4">Hi <strong><?= h($_SESSION['full_name'] ?? '') ?></strong> — you don&rsquo;t have a pre-saved signature yet.</p>
            <ul class="space-y-2 mb-5">
                <li class="flex items-start gap-2.5 text-sm text-slate-600">
                    <span class="w-5 h-5 bg-emerald-100 text-emerald-600 rounded-full grid place-items-center shrink-0 mt-0.5 text-xs"><i class="bi bi-lightning-charge-fill"></i></span>
                    Forms auto-fill your MA signature — no manual signing each time
                </li>
                <li class="flex items-start gap-2.5 text-sm text-slate-600">
                    <span class="w-5 h-5 bg-emerald-100 text-emerald-600 rounded-full grid place-items-center shrink-0 mt-0.5 text-xs"><i class="bi bi-clock-fill"></i></span>
                    Saves time on every patient visit
                </li>
                <li class="flex items-start gap-2.5 text-sm text-slate-600">
                    <span class="w-5 h-5 bg-emerald-100 text-emerald-600 rounded-full grid place-items-center shrink-0 mt-0.5 text-xs"><i class="bi bi-shield-check-fill"></i></span>
                    You can update or remove it anytime from your Profile
                </li>
            </ul>
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="<?= BASE_URL ?>/profile.php#savedSigSection"
                   class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-3
                          bg-emerald-600 hover:bg-emerald-700 text-white font-bold
                          rounded-xl transition-colors shadow-sm text-sm">
                    <i class="bi bi-pen-fill"></i> Set Up Now
                </a>
                <button type="button" id="savedSigPromptDismiss"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-3
                               bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold
                               rounded-xl transition-colors text-sm">
                    <i class="bi bi-x-circle"></i> Remind Me Later
                </button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var modal   = document.getElementById('savedSigPromptModal');
    var dismiss = document.getElementById('savedSigPromptDismiss');
    var backdrop = document.getElementById('savedSigPromptBackdrop');
    function close() { if (modal) modal.remove(); }
    dismiss  && dismiss.addEventListener('click', close);
    backdrop && backdrop.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
<?php endif; ?>

<!-- ── Global Search Modal ──────────────────────────────────────────── -->
<div id="searchModal"
     class="hidden fixed inset-0 z-[9800] items-start justify-center pt-[10vh] px-4 no-print"
     aria-modal="true" role="dialog" aria-label="Global search">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-lg overflow-hidden">
        <!-- Input -->
        <div class="flex items-center gap-3 px-4 py-3.5 border-b border-slate-100">
            <i class="bi bi-search text-slate-400 text-lg shrink-0"></i>
            <input id="searchInput" type="text" placeholder="Search patients, forms&hellip;"
                   class="flex-1 text-sm text-slate-800 placeholder-slate-400 bg-transparent focus:outline-none"
                   autocomplete="off" spellcheck="false">
            <kbd class="hidden sm:inline-flex items-center px-2 py-1 text-[10px] font-medium text-slate-400
                        bg-slate-100 rounded-lg border border-slate-200">ESC</kbd>
        </div>
        <!-- Results -->
        <div id="searchResults" class="overflow-y-auto max-h-[400px] py-1">
            <div class="text-center py-8 text-slate-400">
                <i class="bi bi-search text-3xl opacity-20 block mb-2"></i>
                <p class="text-sm">Type at least 2 characters to search</p>
            </div>
        </div>
        <!-- Hint bar -->
        <div class="flex items-center justify-between px-4 py-2 bg-slate-50 border-t border-slate-100">
            <div class="flex items-center gap-3 text-[11px] text-slate-400">
                <span><kbd class="px-1.5 py-0.5 bg-white rounded border border-slate-200 font-mono text-[10px]">&uarr;&darr;</kbd> Navigate</span>
                <span><kbd class="px-1.5 py-0.5 bg-white rounded border border-slate-200 font-mono text-[10px]">&crarr;</kbd> Open</span>
                <span><kbd class="px-1.5 py-0.5 bg-white rounded border border-slate-200 font-mono text-[10px]">Esc</kbd> Close</span>
            </div>
            <div class="flex items-center gap-1 text-[11px] text-slate-400">
                <kbd class="px-1.5 py-0.5 bg-white rounded border border-slate-200 font-mono text-[10px]">Ctrl</kbd>
                <span>+</span>
                <kbd class="px-1.5 py-0.5 bg-white rounded border border-slate-200 font-mono text-[10px]">K</kbd>
            </div>
        </div>
    </div>
</div>

<!-- ── Notification Drawer backdrop ─────────────────────────────────── -->
<div id="notifBackdrop"
     class="fixed inset-0 z-[9600] bg-slate-900/40 backdrop-blur-sm no-print
            opacity-0 pointer-events-none transition-opacity duration-300"></div>

<!-- ── Notification Drawer ──────────────────────────────────────────── -->
<div id="notifDrawer"
     class="fixed top-0 right-0 bottom-0 z-[9700] w-full sm:w-80 flex flex-col bg-white shadow-2xl no-print
            translate-x-full transition-transform duration-300 ease-in-out"
     aria-hidden="true" aria-label="Notifications">
    <!-- Header -->
    <div class="flex items-center justify-between px-5 py-4 shrink-0"
         style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8)">
        <div class="flex items-center gap-2.5 text-white">
            <i class="bi bi-bell-fill text-base"></i>
            <span class="font-bold text-sm">Notifications</span>
        </div>
        <button id="notifDrawerClose" aria-label="Close notifications"
                class="text-white/70 hover:text-white transition text-xl leading-none">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <!-- Body -->
    <div id="notifBody" class="flex-1 overflow-y-auto py-2"></div>
    <!-- Footer -->
    <div class="shrink-0 px-4 py-3 border-t border-slate-100 bg-slate-50 text-center">
        <p class="text-xs text-slate-400">Auto-refreshes every 60 seconds</p>
    </div>
</div>

<!-- ── AI Assistant Bubble ───────────────────────────────────────────── -->
<div id="aiChatWrap" class="fixed bottom-6 right-6 z-[9000] no-print flex flex-col items-end gap-3">

    <!-- Panel (hidden by default) -->
    <div id="aiPanel"
         class="hidden flex-col bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden"
         style="width:360px;max-height:500px"
         role="dialog" aria-label="AI Assistant">

        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 shrink-0"
             style="background:linear-gradient(135deg,#1d4ed8,#2563eb)">
            <span class="font-semibold text-sm text-white flex items-center gap-2">
                <i class="bi bi-heart-pulse-fill"></i> Clinical AI Assistant
            </span>
            <button id="aiClose" aria-label="Close"
                    class="text-white/80 hover:text-white text-lg leading-none transition">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-slate-100 shrink-0 bg-white">
            <button class="ai-tab ai-tab--active flex-1 py-2 text-xs font-semibold" data-tab="chat">
                <i class="bi bi-chat-dots"></i> Chat
            </button>
            <button class="ai-tab flex-1 py-2 text-xs font-semibold text-slate-500 hover:text-blue-600 transition" data-tab="quick">
                <i class="bi bi-lightning"></i> Quick Actions
            </button>
        </div>

        <!-- Chat tab -->
        <div id="aiTabChat" class="flex flex-col flex-1 min-h-0" style="overflow:hidden">
            <div id="aiMessages" class="flex-1 overflow-y-auto px-3 py-3 flex flex-col gap-3"
                 style="background:#f8fafc">
                <div class="ai-msg-row ai-msg-row--bot">
                    <span class="ai-avatar"><i class="bi bi-heart-pulse-fill"></i></span>
                    <div>
                        <div class="ai-msg ai-msg-bot">
                            Hi! I'm your clinical AI assistant. Ask me about documentation, ICD-10 coding, wound care, or anything else.
                        </div>
                        <span class="ai-ts"></span>
                    </div>
                </div>
            </div>
            <div class="flex gap-2 p-3 border-t border-slate-100 shrink-0 bg-white">
                <input id="aiChatInput" type="text" placeholder="Ask a question…"
                       class="flex-1 px-3 py-2 text-sm border border-slate-200 rounded-xl
                              focus:outline-none focus:ring-2 focus:ring-blue-400 bg-slate-50">
                <button id="aiSend" aria-label="Send"
                        class="shrink-0 w-9 h-9 flex items-center justify-center
                               text-white rounded-xl transition shadow-sm"
                        style="background:#2563eb">
                    <i class="bi bi-send-fill text-xs"></i>
                </button>
            </div>
        </div>

        <!-- Quick Actions tab -->
        <div id="aiTabQuick" class="hidden flex-col gap-2 p-4 text-sm" style="background:#f8fafc">
            <p class="text-xs text-slate-400 font-semibold uppercase tracking-wide mb-1">Form Actions</p>
            <p id="aiQuickNotAvail" class="text-xs italic text-slate-400">
                Open a patient Visit (Vitals &amp; CS) form to use these actions.
            </p>
            <button id="aiQuickIcd"
                    class="hidden items-center gap-2 px-3 py-2.5 bg-white hover:bg-blue-50
                           border border-slate-200 hover:border-blue-300 rounded-xl
                           text-slate-700 text-sm font-semibold transition">
                <i class="bi bi-clipboard2-pulse text-blue-500"></i> AI Suggest ICD-10 Codes
            </button>
            <button id="aiQuickSoap"
                    class="hidden items-center gap-2 px-3 py-2.5 bg-white hover:bg-blue-50
                           border border-slate-200 hover:border-blue-300 rounded-xl
                           text-slate-700 text-sm font-semibold transition">
                <i class="bi bi-file-medical text-blue-500"></i> Draft SOAP Note
            </button>
        </div>
    </div>

    <!-- Floating bubble button -->
    <button id="aiBubble"
            class="w-14 h-14 rounded-full text-white shadow-lg hover:shadow-xl
                   hover:scale-105 transition-all duration-200 flex items-center justify-center"
            style="background:linear-gradient(135deg,#1d4ed8,#2563eb)"
            aria-label="Open AI Assistant" title="Clinical AI Assistant">
        <i class="bi bi-heart-pulse-fill text-xl"></i>
    </button>
</div>
<?php endif; ?>

<footer class="bg-white border-t border-slate-100 py-4 text-center text-xs text-slate-400 no-print">
    <?= APP_NAME ?> &copy; <?= date('Y') ?> &mdash; HIPAA-Conscious Paperless Document System
</footer>

<script src="<?= BASE_URL ?>/assets/js/signature_pad.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=2"></script>
<script src="<?= BASE_URL ?>/assets/js/form-wizard.js"></script>
<script>
window._pdBase = '<?= BASE_URL ?>';
window._pdCompany = '<?= htmlspecialchars(($patient['company'] ?? PRACTICE_NAME), ENT_QUOTES, 'UTF-8') ?>';
<?php if (!empty($_SESSION['user_id'])): ?>
window._pdCsrf = '<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>';
<?php endif; ?>
</script>
<script src="<?= BASE_URL ?>/assets/js/autosave.js" defer></script>
<script src="<?= BASE_URL ?>/assets/js/voice.js" defer></script>
<script src="<?= BASE_URL ?>/assets/js/offline.js" defer></script>
<script src="<?= BASE_URL ?>/assets/js/ai-assistant.js" defer></script>
<script>
(function () {
    // Sidebar toggle (mobile)
    var sidebar  = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    var mBtn     = document.getElementById('mBtn');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('-translate-x-full');
        if (backdrop) backdrop.classList.remove('hidden');
    }
    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('-translate-x-full');
        if (backdrop) backdrop.classList.add('hidden');
    }
    if (mBtn)     mBtn.addEventListener('click', openSidebar);
    if (backdrop) backdrop.addEventListener('click', closeSidebar);
})();
</script>
<?php if (isset($extraJs)) echo $extraJs; ?>
<?php if (!empty($_SESSION['user_id'])): ?>
<script>
(function () {
    'use strict';
    var TIMEOUT_MS  = <?= (int)SESSION_TIMEOUT ?> * 1000;
    var WARN_MS     = 120 * 1000; // show warning 2 min before expiry
    var lastActive  = <?= (int)($_SESSION['last_active'] ?? time()) ?> * 1000;
    var csrf        = '<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>';

    var modal       = document.getElementById('sessionTimeoutModal');
    var countdownEl = document.getElementById('sessionCountdown');
    var stayBtn     = document.getElementById('sessionStayBtn');
    var intervalId  = null;
    var isVisible   = false;

    function expiresAt() { return lastActive + TIMEOUT_MS; }
    function msLeft()    { return expiresAt() - Date.now(); }

    function fmt(ms) {
        if (ms <= 0) return '0:00';
        var s = Math.ceil(ms / 1000);
        var m = Math.floor(s / 60);
        s = s % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    function showModal() {
        if (!modal || isVisible) return;
        modal.classList.remove('hidden');
        isVisible = true;
    }

    function hideModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        isVisible = false;
        if (countdownEl) {
            countdownEl.classList.remove('text-red-500');
            countdownEl.classList.add('text-amber-500');
        }
    }

    function tick() {
        var left = msLeft();
        if (left <= 0) {
            clearInterval(intervalId);
            window.location.href = (window._pdBase || '') + '/index.php?msg=timeout';
            return;
        }
        if (left <= WARN_MS) {
            showModal();
            if (countdownEl) {
                countdownEl.textContent = fmt(left);
                if (left <= 30000) {
                    countdownEl.classList.remove('text-amber-500');
                    countdownEl.classList.add('text-red-500');
                }
            }
        }
    }

    if (stayBtn) {
        stayBtn.addEventListener('click', async function () {
            var btn   = stayBtn;
            var orig  = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Extending…';
            try {
                var res  = await fetch((window._pdBase || '') + '/api/session_ping.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ csrf: csrf }),
                });
                var json = await res.json();
                if (json.ok && json.lastActive) {
                    lastActive = json.lastActive * 1000;
                    hideModal();
                    btn.disabled = false;
                    btn.innerHTML = orig;
                } else {
                    // Session truly expired — go to login
                    window.location.href = (window._pdBase || '') + '/index.php?msg=timeout';
                }
            } catch (e) {
                btn.disabled = false;
                btn.innerHTML = orig;
            }
        });
    }

    intervalId = setInterval(tick, 1000);
    tick();
}());
</script>
<script>
/* ── Global Search ─────────────────────────────────────────────────── */
(function () {
    var modal   = document.getElementById('searchModal');
    var input   = document.getElementById('searchInput');
    var results = document.getElementById('searchResults');
    if (!modal) return;
    var timer;

    function defaultMsg() {
        return '<div class="text-center py-8 text-slate-400"><i class="bi bi-search text-3xl opacity-20 block mb-2"></i><p class="text-sm">Type at least 2 characters to search</p></div>';
    }
    function openSearch() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(function () { input.focus(); input.select(); }, 40);
    }
    function closeSearch() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        input.value = '';
        results.innerHTML = defaultMsg();
    }

    document.querySelectorAll('[data-search-trigger]').forEach(function (el) {
        el.addEventListener('click', openSearch);
    });
    modal.addEventListener('click', function (e) { if (e.target === modal) closeSearch(); });
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); modal.classList.contains('hidden') ? openSearch() : closeSearch(); }
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeSearch();
    });

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { results.innerHTML = defaultMsg(); return; }
        results.innerHTML = '<div class="text-center py-4 text-slate-400 text-sm"><i class="bi bi-arrow-repeat"></i> Searching&hellip;</div>';
        timer = setTimeout(function () { doSearch(q); }, 280);
    });

    /* Arrow-key nav between input and result items */
    input.addEventListener('keydown', function (e) {
        var items = results.querySelectorAll('a.search-result-item');
        if (!items.length) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); items[0].focus(); }
    });
    results.addEventListener('keydown', function (e) {
        var items = results.querySelectorAll('a.search-result-item');
        var idx   = Array.prototype.indexOf.call(items, document.activeElement);
        if (e.key === 'ArrowDown')  { e.preventDefault(); if (idx < items.length - 1) items[idx + 1].focus(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); idx > 0 ? items[idx - 1].focus() : input.focus(); }
        else if (e.key === 'Escape') closeSearch();
    });

    var FORM_LABELS = {
        'vital_cs':'Visit Consent','ccm_consent':'CCM Consent','informed_consent_wound':'Wound Consent',
        'new_patient':'New Patient','abn':'ABN','pf_signup':'PF Portal',
        'cognitive_wellness':'Cognitive Wellness','medicare_awv':'Medicare AWV',
        'il_disclosure':'IL Disclosure','rpm_consent':'RPM Consent',
        'wound_care_consent':'Wound Care Consent','new_patient_pocket':'New Patient Pocket'
    };

    function esc(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s || '')); return d.innerHTML; }
    function highlight(text, q) {
        var e = esc(text), qe = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return e.replace(new RegExp('(' + qe + ')', 'gi'), '<mark class="bg-yellow-100 text-yellow-800 rounded px-0.5">$1</mark>');
    }

    function doSearch(q) {
        fetch((window._pdBase || '') + '/api/global_search.php?q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (d) { renderResults(d, q); })
            .catch(function () { results.innerHTML = '<div class="text-center py-4 text-red-400 text-sm">Search failed. Try again.</div>'; });
    }

    function renderResults(data, q) {
        var p = data.patients || [], f = data.forms || [];
        if (!p.length && !f.length) {
            results.innerHTML = '<div class="text-center py-8 text-slate-400"><i class="bi bi-emoji-neutral text-3xl opacity-20 block mb-2"></i><p class="text-sm">No results for <strong>' + esc(q) + '</strong></p></div>';
            return;
        }
        var html = '';
        if (p.length) {
            html += '<div class="px-3 pt-3 pb-1"><span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Patients</span></div>';
            p.forEach(function (pt) {
                var init  = ((pt.first_name || '')[0] || '') + ((pt.last_name || '')[0] || '');
                var name  = (pt.last_name || '') + ', ' + (pt.first_name || '');
                var stCls = pt.status === 'active' ? 'bg-emerald-100 text-emerald-700' : pt.status === 'discharged' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700';
                var coTag = pt.company === 'Visiting Medical Physician Inc.'
                    ? '<span class="text-[9px] font-bold text-teal-600 bg-teal-50 px-1.5 py-0.5 rounded-full">VMP</span>'
                    : '<span class="text-[9px] font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded-full">BWC</span>';
                html += '<a href="' + (window._pdBase || '') + '/patient_view.php?id=' + pt.id + '" class="search-result-item flex items-center gap-3 px-3 py-2.5 hover:bg-slate-50 rounded-xl transition-colors">'
                    + '<div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-blue-700 grid place-items-center text-white text-xs font-bold shrink-0">' + esc(init.toUpperCase()) + '</div>'
                    + '<div class="flex-1 min-w-0"><div class="font-semibold text-slate-800 text-sm truncate">' + highlight(name, q) + '</div>'
                    + '<div class="text-xs text-slate-400">' + esc(pt.dob || '') + (pt.phone ? ' &middot; ' + esc(pt.phone) : '') + '</div></div>'
                    + '<div class="flex items-center gap-1.5 shrink-0">' + coTag
                    + '<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold ' + stCls + ' capitalize">' + esc(pt.status) + '</span></div>'
                    + '</a>';
            });
        }
        if (f.length) {
            html += '<div class="px-3 pt-3 pb-1 border-t border-slate-100 mt-1"><span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Recent Forms</span></div>';
            f.forEach(function (fm) {
                var pname = (fm.last_name || '') + ', ' + (fm.first_name || '');
                var ftype = FORM_LABELS[fm.form_type] || fm.form_type;
                var date  = fm.created_at ? new Date(fm.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '';
                var stCls = fm.status === 'signed' ? 'bg-blue-100 text-blue-700' : fm.status === 'uploaded' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600';
                html += '<a href="' + (window._pdBase || '') + '/view_document.php?id=' + fm.id + '" class="search-result-item flex items-center gap-3 px-3 py-2.5 hover:bg-slate-50 rounded-xl transition-colors">'
                    + '<div class="w-8 h-8 rounded-lg bg-slate-100 grid place-items-center text-slate-500 shrink-0"><i class="bi bi-file-text text-sm"></i></div>'
                    + '<div class="flex-1 min-w-0"><div class="font-semibold text-slate-800 text-sm truncate">' + esc(ftype) + '</div>'
                    + '<div class="text-xs text-slate-400 truncate">' + highlight(pname, q) + ' &middot; ' + date + '</div></div>'
                    + '<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold ' + stCls + ' capitalize shrink-0">' + esc(fm.status) + '</span>'
                    + '</a>';
            });
        }
        results.innerHTML = html;
    }
}());

/* ── Notifications ─────────────────────────────────────────────────── */
(function () {
    var drawer   = document.getElementById('notifDrawer');
    var backdrop = document.getElementById('notifBackdrop');
    var body     = document.getElementById('notifBody');
    var closeBtn = document.getElementById('notifDrawerClose');
    var badge    = document.getElementById('notifBadge');
    var badgeM   = document.getElementById('notifBadgeMobile');
    if (!drawer) return;

    var COLOR = {
        emerald: ['bg-emerald-100', 'text-emerald-600'],
        amber:   ['bg-amber-100',   'text-amber-600'],
        violet:  ['bg-violet-100',  'text-violet-600'],
        red:     ['bg-red-100',     'text-red-600'],
    };

    function openDrawer()  {
        backdrop.classList.remove('opacity-0', 'pointer-events-none');
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
        loadNotifs();
    }
    function closeDrawer() {
        backdrop.classList.add('opacity-0', 'pointer-events-none');
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('[data-notif-trigger]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.stopPropagation();
            drawer.classList.contains('translate-x-full') ? openDrawer() : closeDrawer();
        });
    });
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    backdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDrawer(); });

    function esc(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s || '')); return d.innerHTML; }

    function setBadge(n) {
        [badge, badgeM].forEach(function (el) {
            if (!el) return;
            if (n > 0) { el.textContent = n > 9 ? '9+' : n; el.classList.remove('hidden'); }
            else el.classList.add('hidden');
        });
    }

    function loadNotifs() {
        body.innerHTML = '<div class="text-center py-10 text-slate-400"><i class="bi bi-arrow-repeat text-2xl opacity-30 block mb-2"></i><p class="text-sm">Loading&hellip;</p></div>';
        fetch((window._pdBase || '') + '/api/notifications.php')
            .then(function (r) { return r.json(); })
            .then(function (d) { renderNotifs(d); setBadge(d.total); })
            .catch(function () { body.innerHTML = '<div class="text-center py-4 text-red-400 text-sm">Could not load notifications.</div>'; });
    }

    function renderNotifs(d) {
        if (!d.notifications || !d.notifications.length) {
            body.innerHTML = '<div class="text-center py-12 text-slate-400"><i class="bi bi-check-circle text-4xl opacity-30 block mb-3"></i><p class="font-semibold text-slate-500">All caught up!</p><p class="text-xs mt-1 text-slate-400">No pending items right now.</p></div>';
            return;
        }
        var html = '';
        d.notifications.forEach(function (n) {
            var c = COLOR[n.color] || ['bg-slate-100', 'text-slate-600'];
            html += '<a href="' + (window._pdBase || '') + esc(n.link) + '"'
                + ' class="flex items-start gap-3 px-4 py-3.5 hover:bg-slate-50 transition-colors border-b border-slate-50 last:border-0">'
                + '<div class="w-10 h-10 ' + c[0] + ' ' + c[1] + ' rounded-xl grid place-items-center shrink-0 text-lg"><i class="bi ' + esc(n.icon) + '"></i></div>'
                + '<div class="flex-1 min-w-0 pt-0.5">'
                + '<p class="text-sm font-semibold text-slate-800 leading-snug">' + esc(n.title) + '</p>'
                + '<p class="text-xs text-slate-400 mt-0.5">' + esc(n.body) + '</p>'
                + '</div>'
                + '<span class="' + c[0] + ' ' + c[1] + ' text-xs font-bold px-2 py-1 rounded-full shrink-0 mt-0.5">' + (n.count || '') + '</span>'
                + '</a>';
        });
        body.innerHTML = html;
    }

    /* Initial badge + 60s polling */
    function refreshBadge() {
        fetch((window._pdBase || '') + '/api/notifications.php')
            .then(function (r) { return r.json(); })
            .then(function (d) { setBadge(d.total); })
            .catch(function () {});
    }
    refreshBadge();
    setInterval(refreshBadge, 60000);
}());
</script>
<?php endif; ?>
</body>
</html>
