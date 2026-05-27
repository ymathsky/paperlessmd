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

<?php if (!empty($_SESSION['prompt_saved_sig']) || !empty($_promptSavedSig)): unset($_SESSION['prompt_saved_sig']); ?>
<!-- ── Pre-Saved Signature — BLOCKING Modal ───────────────────────── -->
<div id="savedSigPromptModal"
     class="fixed inset-0 z-[9900] flex items-center justify-center p-4 no-print"
     aria-modal="true" role="dialog" aria-labelledby="savedSigPromptTitle">
    <!-- Non-clickable backdrop -->
    <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"></div>
    <!-- Card -->
    <div class="relative bg-white rounded-2xl shadow-2xl border border-slate-100 w-full max-w-md overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-emerald-600 to-teal-500 px-6 py-5 flex items-center gap-4">
            <div class="w-12 h-12 bg-white/20 rounded-2xl grid place-items-center shrink-0">
                <i class="bi bi-pen-fill text-white text-2xl"></i>
            </div>
            <div>
                <h2 id="savedSigPromptTitle" class="text-white font-extrabold text-lg leading-tight">Signature Required</h2>
                <p class="text-emerald-100 text-xs mt-0.5">You must set up your signature before continuing</p>
            </div>
        </div>
        <!-- Body -->
        <div class="px-6 py-5">
            <p class="text-slate-600 text-sm mb-4">Hi <strong><?= h($_SESSION['full_name'] ?? '') ?></strong> — a pre-saved signature is required to use the system.</p>
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
            <a href="<?= BASE_URL ?>/profile.php#savedSigSection"
               class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5
                      bg-emerald-600 hover:bg-emerald-700 text-white font-bold
                      rounded-xl transition-colors shadow-sm text-sm">
                <i class="bi bi-pen-fill"></i> Set Up Signature Now
            </a>
        </div>
    </div>
</div>
<script>
// Blocking modal — prevent Escape and any keyboard shortcut from bypassing it
document.addEventListener('keydown', function (e) {
    if (document.getElementById('savedSigPromptModal')) {
        e.stopImmediatePropagation();
        if (e.key === 'Escape' || e.key === 'Backspace') e.preventDefault();
    }
}, true);
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

<!-- ── Floating Menu Toggle ──────────────────────────────────────────── -->
<button id="floatMenuToggle"
        title="Show / hide quick actions"
        onclick="floatMenuToggleFn()"
        class="no-print"
        style="display:none;position:fixed;bottom:88px;right:20px;z-index:8500;
               width:30px;height:30px;border-radius:50%;border:1.5px solid #e2e8f0;
               background:rgba(255,255,255,0.92);backdrop-filter:blur(6px);
               cursor:pointer;align-items:center;justify-content:center;
               box-shadow:0 2px 8px rgba(0,0,0,.14);transition:transform .15s;">
    <i id="floatMenuToggleIcon" class="bi bi-chevron-up" style="font-size:12px;color:#64748b;pointer-events:none;"></i>
</button>
<script>
(function () {
    var IDS  = ['wpFloatBtn', 'qnFloatBtn', 'rxPadFloatBtn'];
    var KEY  = 'floatMenuHidden';
    var hidden = localStorage.getItem(KEY) === '1';

    function applyState() {
        var toggleBtn = document.getElementById('floatMenuToggle');
        var icon      = document.getElementById('floatMenuToggleIcon');
        if (!toggleBtn) return;
        var anyExist  = IDS.some(function (id) { return !!document.getElementById(id); });
        if (!anyExist) { toggleBtn.style.display = 'none'; return; }
        toggleBtn.style.display = 'flex';
        IDS.forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.style.transition  = 'opacity .2s ease, transform .2s ease';
            el.style.opacity     = hidden ? '0' : '1';
            el.style.transform   = hidden ? 'scale(0.6)' : 'scale(1)';
            el.style.pointerEvents = hidden ? 'none' : '';
        });
        icon.className = hidden
            ? 'bi bi-grid-3x3-gap-fill'
            : 'bi bi-chevron-up';
        icon.style.color = hidden ? '#7c3aed' : '#64748b';
    }

    window.floatMenuToggleFn = function () {
        hidden = !hidden;
        localStorage.setItem(KEY, hidden ? '1' : '0');
        applyState();
    };

    document.addEventListener('DOMContentLoaded', applyState);
}());
</script>

<!-- ── AI Assistant Bubble ───────────────────────────────────────────── -->
<div id="aiChatWrap" class="fixed bottom-6 right-6 z-[9000] no-print flex flex-col items-end gap-3">

    <!-- Panel (hidden by default) -->
    <div id="aiPanel"
         class="hidden flex-col bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden"
         style="width:min(480px,calc(100vw - 24px));max-height:min(680px,calc(100vh - 80px))"
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
                            Hi! I'm your clinical AI assistant. Ask me about documentation, ICD-10 coding, wound care, or how to use any feature in PaperlessMD.
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
            class="w-10 h-10 rounded-full text-white shadow-lg hover:shadow-xl
                   hover:scale-105 transition-all duration-200 flex items-center justify-center"
            style="background:linear-gradient(135deg,#1d4ed8,#2563eb)"
            aria-label="Open AI Assistant" title="Clinical AI Assistant">
        <i class="bi bi-heart-pulse-fill text-sm"></i>
    </button>
</div>
<?php endif; ?>

<footer class="bg-white border-t border-slate-100 py-4 text-center text-xs text-slate-400 no-print">
    <?= APP_NAME ?> &copy; <?= date('Y') ?> &mdash; HIPAA-Conscious Paperless Document System
</footer>

<script src="<?= BASE_URL ?>/assets/js/signature_pad.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=3"></script>
<script src="<?= BASE_URL ?>/assets/js/form-wizard.js"></script>
<script src="<?= BASE_URL ?>/assets/js/handwriting.js?v=2"></script>
<script src="<?= BASE_URL ?>/assets/js/form-helpers.js?v=8"></script>
<script>
window._pdBase = '<?= BASE_URL ?>';
window._pdCompany = '<?= htmlspecialchars(($patient['company'] ?? PRACTICE_NAME), ENT_QUOTES, 'UTF-8') ?>';
window._pdTimezone = '<?= htmlspecialchars(APP_TIMEZONE, ENT_QUOTES, 'UTF-8') ?>';
<?php if (!empty($_SESSION['user_id'])): ?>
window._pdCsrf = '<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>';
<?php endif; ?>
</script>
<script src="<?= BASE_URL ?>/assets/js/autosave.js" defer></script>
<script src="<?= BASE_URL ?>/assets/js/voice.js" defer></script>
<script src="<?= BASE_URL ?>/assets/js/offline.js" defer></script>
<script src="<?= BASE_URL ?>/assets/js/ai-assistant.js" defer></script>
<?php if (!empty($_SESSION['user_id'])): ?>
<!-- ── Push Notification Permission Banner ──────────────────────────────────
     Slides up once when Notification.permission === 'default'.
     Permanently dismissed via localStorage key pd_push_dismissed.
──────────────────────────────────────────────────────────────────────────── -->
<div id="pushPromptBanner"
     class="no-print"
     style="display:none;position:fixed;left:50%;
            bottom:calc(76px + env(safe-area-inset-bottom,0px));
            z-index:9997;width:100%;max-width:380px;padding:0 12px;
            transition:opacity 0.3s ease,transform 0.3s ease;
            opacity:0;transform:translateX(-50%) translateY(16px);">
    <div id="pushPromptCard"
         class="bg-white rounded-2xl shadow-2xl border border-slate-200 p-4 flex items-start gap-3">
        <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-sky-500 flex items-center justify-center">
            <i class="bi bi-bell-fill text-white text-lg"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-slate-800">Enable push notifications</p>
            <p class="text-xs text-slate-500 mt-1">
                Get real-time alerts for messages, visits, form signatures, and more.
            </p>
            <div class="flex gap-2 mt-3">
                <button id="pushPromptEnable"
                        class="px-3 py-2 rounded-lg bg-sky-500 text-white text-xs font-semibold"
                        style="border:none;cursor:pointer;">
                    Enable notifications
                </button>
                <button id="pushPromptDismiss"
                        class="px-3 py-2 rounded-lg bg-slate-100 text-slate-600 text-xs font-semibold"
                        style="border:none;cursor:pointer;">
                    Not now
                </button>
            </div>
        </div>
        <button id="pushPromptClose"
                class="flex-shrink-0 text-slate-400"
                style="background:none;border:none;cursor:pointer;padding:2px 0 0;line-height:1;">
            <i class="bi bi-x-lg" style="font-size:14px;"></i>
        </button>
    </div>
</div>

<script>
/* ── Web Push subscription ────────────────────────────────────────────────────
   On load: re-syncs existing subscription or auto-subscribes if permission was
   already granted. Shows the banner if permission is still 'default'.
──────────────────────────────────────────────────────────────────────────── */
(function () {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
    if (!window._pdCsrf || !window._pdBase) return;

    var BASE   = window._pdBase;
    var LS_KEY = 'pd_push_dismissed';

    // ── Dark mode: tint the card if html.dark is active ──────────────────────
    (function () {
        var card = document.getElementById('pushPromptCard');
        if (!card) return;
        if (document.documentElement.classList.contains('dark')) {
            card.style.backgroundColor = '#1e293b';
            card.style.borderColor     = '#334155';
            card.querySelector('p.text-slate-800').style.color = '#f1f5f9';
            card.querySelector('p.text-slate-500').style.color = '#94a3b8';
        }
    }());

    function urlBase64ToUint8Array(b64) {
        var padding = '='.repeat((4 - b64.length % 4) % 4);
        var base64  = (b64 + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw     = atob(base64);
        var arr     = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
        return arr;
    }

    function sendSubscription(sub) {
        var json = sub.toJSON();
        return fetch(BASE + '/api/push_subscribe.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                csrf:     window._pdCsrf,
                action:   'subscribe',
                endpoint: json.endpoint,
                keys:     json.keys,
            }),
        });
    }

    function doSubscribe(reg, appServerKey) {
        return reg.pushManager.subscribe({
            userVisibleOnly:      true,
            applicationServerKey: appServerKey,
        }).then(function (sub) {
            sendSubscription(sub).catch(function () {});
        });
    }

    var banner = document.getElementById('pushPromptBanner');

    function showBanner() {
        if (!banner) return;
        banner.style.display = 'block';
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                banner.style.opacity   = '1';
                banner.style.transform = 'translateX(-50%) translateY(0)';
            });
        });
    }

    function hideBanner() {
        if (!banner) return;
        banner.style.opacity   = '0';
        banner.style.transform = 'translateX(-50%) translateY(16px)';
        setTimeout(function () { banner.style.display = 'none'; }, 320);
    }

    ['pushPromptDismiss', 'pushPromptClose'].forEach(function (id) {
        var btn = document.getElementById(id);
        if (btn) btn.addEventListener('click', function () {
            localStorage.setItem(LS_KEY, '1');
            hideBanner();
        });
    });

    navigator.serviceWorker.ready.then(function (reg) {
        fetch(BASE + '/api/push_subscribe.php?vapid')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.publicKey) return;
                var appServerKey = urlBase64ToUint8Array(data.publicKey);

                reg.pushManager.getSubscription().then(function (existing) {
                    if (existing) {
                        // Already subscribed — re-sync to server (idempotent upsert)
                        sendSubscription(existing).catch(function () {});
                        return;
                    }

                    var perm = Notification.permission;

                    if (perm === 'granted') {
                        // Permission already granted but no subscription — subscribe silently
                        doSubscribe(reg, appServerKey).catch(function (e) {
                            console.warn('[Push] auto-subscribe failed:', e);
                        });
                    } else if (perm === 'default' && !localStorage.getItem(LS_KEY)) {
                        // Show opt-in banner
                        showBanner();
                        var enableBtn = document.getElementById('pushPromptEnable');
                        if (enableBtn) {
                            enableBtn.addEventListener('click', function () {
                                hideBanner();
                                Notification.requestPermission().then(function (p) {
                                    if (p !== 'granted') return;
                                    doSubscribe(reg, appServerKey).catch(function (e) {
                                        console.warn('[Push] subscribe failed:', e);
                                    });
                                });
                            });
                        }
                    }
                    // permission === 'denied' → do nothing
                });
            })
            .catch(function () { /* vapid endpoint not yet available */ });
    });
}());
</script>
<?php endif; ?>
<?php if (in_array($_SESSION['role'] ?? '', ['ma', 'admin'])): ?>
<script>
/* ── MA Location Tracking ─────────────────────────────────────────────
   Uses watchPosition (continuous) + throttle instead of setInterval.
   Queues each update in IndexedDB and registers a Background Sync tag
   so the service worker delivers it even when the tab is backgrounded
   or the connection is briefly lost.
   Falls back to a direct fetch() if Background Sync is unavailable.
──────────────────────────────────────────────────────────────────── */
(function () {
    if (!navigator.geolocation || !window._pdCsrf) return;

    var INTERVAL_MS   = 60 * 1000; // max 1 update per minute
    var BASE          = window._pdBase || '';
    var LOC_SYNC_TAG  = 'pd-location-sync';
    var LOC_IDB_NAME  = 'pd-location-queue';
    var LOC_IDB_STORE = 'queue';
    var lastSentAt    = 0;

    /* ── Update the GPS status badge (schedule page) ── */
    function setGpsBadge(state) {
        var badge = document.getElementById('gpsStatusBadge');
        if (!badge) return;
        if (state === 'active') {
            badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200';
            badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse inline-block"></span> Location On';
        } else if (state === 'denied') {
            badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700 border border-red-200 cursor-pointer';
            badge.innerHTML = '<i class="bi bi-geo-alt-fill"></i> Location Off — tap to enable';
            badge.title = 'Location access was denied. Please enable it in your browser settings.';
        } else {
            badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-500 border border-slate-200';
            badge.innerHTML = '<i class="bi bi-geo-alt"></i> Locating…';
        }
    }

    /* ── IndexedDB helpers ── */
    function openLocDB() {
        return new Promise(function (resolve, reject) {
            var req = indexedDB.open(LOC_IDB_NAME, 1);
            req.onupgradeneeded = function (e) {
                e.target.result.createObjectStore(LOC_IDB_STORE, { keyPath: 'id', autoIncrement: true });
            };
            req.onsuccess = function (e) { resolve(e.target.result); };
            req.onerror   = function (e) { reject(e.target.error); };
        });
    }
    function locDbAdd(db, entry) {
        return new Promise(function (resolve, reject) {
            var req = db.transaction(LOC_IDB_STORE, 'readwrite').objectStore(LOC_IDB_STORE).add(entry);
            req.onsuccess = function () { resolve(); };
            req.onerror   = function (e) { reject(e.target.error); };
        });
    }

    /* ── Deliver via Background Sync (or direct fetch fallback) ── */
    function queueAndSync(payload) {
        var url = BASE + '/api/update_location.php';

        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            openLocDB()
                .then(function (db) { return locDbAdd(db, { url: url, payload: payload, ts: Date.now() }); })
                .then(function ()   { return navigator.serviceWorker.ready; })
                .then(function (reg){ return reg.sync.register(LOC_SYNC_TAG); })
                .catch(function ()  { directFetch(url, payload); });
        } else {
            directFetch(url, payload);
        }
    }

    function directFetch(url, payload) {
        fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload)
        }).catch(function () {});
    }

    /* ── watchPosition handler (throttled) ── */
    function onPosition(pos) {
        setGpsBadge('active');
        var now = Date.now();
        if (now - lastSentAt < INTERVAL_MS) return;
        lastSentAt = now;
        queueAndSync({
            csrf:     window._pdCsrf,
            lat:      pos.coords.latitude,
            lng:      pos.coords.longitude,
            accuracy: pos.coords.accuracy || null
        });
    }

    function onError(err) {
        setGpsBadge(err.code === 1 ? 'denied' : 'unavailable');
    }

    setGpsBadge('searching');

    // watchPosition gives continuous updates efficiently — no polling needed
    var watchId = navigator.geolocation.watchPosition(
        onPosition,
        onError,
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 }
    );

    // When the tab comes back into focus, force an immediate update
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { lastSentAt = 0; }
    });

    // Expose a force-send function so Navigate buttons can push immediately
    window._pdSendLocation = function () {
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                lastSentAt = 0;
                onPosition(pos);
            },
            function () {},
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    };
})();
</script>
<?php endif; ?>
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
    // Show warning modal after 15 min of user inactivity,
    // provided at least 2 min remain on the server session.
    var TIMEOUT_MS      = <?= (int)SESSION_TIMEOUT ?> * 1000;
    var INACTIVITY_MS   = 15 * 60 * 1000; // 15 min idle → show modal
    var GRACE_MS        =  2 * 60 * 1000; // 2 min countdown once modal appears
    var lastActive      = <?= (int)($_SESSION['last_active'] ?? time()) ?> * 1000;
    var csrf            = '<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>';

    var modal           = document.getElementById('sessionTimeoutModal');
    var countdownEl     = document.getElementById('sessionCountdown');
    var stayBtn         = document.getElementById('sessionStayBtn');
    var tickId          = null;    // 1-second server-expiry watchdog
    var inactivityTimer = null;   // fires after INACTIVITY_MS of idle
    var graceDeadline   = null;   // Date.now() + GRACE_MS when modal shown
    var isVisible       = false;

    function msLeft() { return (lastActive + TIMEOUT_MS) - Date.now(); }

    function fmt(ms) {
        if (ms <= 0) return '0:00';
        var s = Math.ceil(ms / 1000);
        var m = Math.floor(s / 60);
        s = s % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    function showModal() {
        if (!modal || isVisible) return;
        // Guard: only show if at least GRACE_MS remain on the server session
        if (msLeft() < GRACE_MS) {
            window.location.href = (window._pdBase || '') + '/index.php?msg=timeout';
            return;
        }
        modal.classList.remove('hidden');
        isVisible    = true;
        graceDeadline = Date.now() + GRACE_MS;
    }

    function hideModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        isVisible     = false;
        graceDeadline = null;
        if (countdownEl) {
            countdownEl.textContent = '2:00';
            countdownEl.classList.remove('text-red-500');
            countdownEl.classList.add('text-amber-500');
        }
    }

    // 1-second watchdog: enforces hard server-expiry redirect
    // and updates the countdown while the modal is visible.
    function tick() {
        if (msLeft() <= 0) {
            clearInterval(tickId);
            window.location.href = (window._pdBase || '') + '/index.php?msg=timeout';
            return;
        }
        if (isVisible && graceDeadline) {
            var left = Math.max(0, graceDeadline - Date.now());
            if (countdownEl) {
                countdownEl.textContent = fmt(left);
                if (left <= 30000) {
                    countdownEl.classList.remove('text-amber-500');
                    countdownEl.classList.add('text-red-500');
                }
            }
            if (left <= 0) {
                clearInterval(tickId);
                window.location.href = (window._pdBase || '') + '/index.php?msg=timeout';
            }
        }
    }

    // Schedule the inactivity alarm
    function scheduleAlarm() {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(function () {
            if (!isVisible) showModal();
        }, INACTIVITY_MS);
    }

    // Any user interaction resets the idle clock (but not while modal is open)
    function onActivity() {
        if (isVisible) return;
        scheduleAlarm();
    }

    if (stayBtn) {
        stayBtn.addEventListener('click', async function () {
            var btn  = stayBtn;
            var orig = btn.innerHTML;
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
                    btn.disabled  = false;
                    btn.innerHTML = orig;
                    scheduleAlarm();
                } else {
                    window.location.href = (window._pdBase || '') + '/index.php?msg=timeout';
                }
            } catch (e) {
                btn.disabled  = false;
                btn.innerHTML = orig;
            }
        });
    }

    ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'].forEach(function (evt) {
        document.addEventListener(evt, onActivity, { passive: true });
    });

    tickId = setInterval(tick, 1000);
    scheduleAlarm(); // start idle countdown
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

<?php include __DIR__ . '/videocall.php'; ?>

<?php
// ── Mobile Bottom Tab Bar ─────────────────────────────────────────────────
// Visible only on small screens (md:hidden). Mirrors the sidebar nav links.
// $activeNav is set by the page before including header/footer.
$_bnActive = $activeNav ?? '';
$_bnItems = [
    ['href' => '/dashboard.php', 'key' => 'dashboard', 'icon' => 'bi-speedometer2',   'label' => 'Home'],
    ['href' => '/schedule.php',  'key' => 'schedule',  'icon' => 'bi-calendar3',      'label' => 'Schedule',  'billingHide' => true],
    ['href' => '/patients.php',  'key' => 'patients',  'icon' => 'bi-people-fill',    'label' => 'Patients'],
    ['href' => '/messages.php',  'key' => 'messages',  'icon' => 'bi-chat-dots-fill', 'label' => 'Messages',  'badge' => $_unreadMessages ?? 0],
];
?>
<nav id="bottomNav"
     class="md:hidden no-print fixed bottom-0 left-0 right-0 z-50 flex items-stretch"
     style="background:#0f1f3d;border-top:1px solid rgba(255,255,255,0.08);
            padding-bottom:env(safe-area-inset-bottom,0px);height:calc(60px + env(safe-area-inset-bottom,0px));">

    <?php foreach ($_bnItems as $_item):
        if (!empty($_item['billingHide']) && isBilling()) continue;
        $_isActive = $_bnActive === $_item['key'];
    ?>
    <a href="<?= BASE_URL . $_item['href'] ?>"
       style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;
              text-decoration:none;transition:background 0.15s;position:relative;
              <?= $_isActive ? 'background:rgba(255,255,255,0.10);' : '' ?>"
       onmouseover="this.style.background='rgba(255,255,255,0.07)'"
       onmouseout="this.style.background='<?= $_isActive ? 'rgba(255,255,255,0.10)' : 'transparent' ?>'">
        <?php if (!empty($_item['badge']) && $_item['badge'] > 0): ?>
        <span style="position:absolute;top:8px;right:calc(50% - 16px);
                     background:#ef4444;color:#fff;font-size:9px;font-weight:800;
                     min-width:16px;height:16px;border-radius:8px;display:flex;align-items:center;
                     justify-content:center;padding:0 3px;line-height:1;border:2px solid #0f1f3d;">
            <?= min((int)$_item['badge'], 99) ?>
        </span>
        <?php endif; ?>
        <i class="bi <?= $_item['icon'] ?>"
           style="font-size:20px;line-height:1;color:<?= $_isActive ? '#fff' : '#64748b' ?>;
                  transition:color 0.15s;<?= $_isActive ? 'filter:drop-shadow(0 0 6px rgba(99,102,241,0.6));' : '' ?>"></i>
        <span style="font-size:10px;font-weight:<?= $_isActive ? '700' : '500' ?>;
                     color:<?= $_isActive ? '#e2e8f0' : '#475569' ?>;letter-spacing:0.02em;line-height:1;">
            <?= $_item['label'] ?>
        </span>
        <?php if ($_isActive): ?>
        <span style="position:absolute;top:0;left:50%;transform:translateX(-50%);
                     width:32px;height:2px;background:#6366f1;border-radius:0 0 3px 3px;"></span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>

    <!-- "More" tab — opens sidebar -->
    <button onclick="document.getElementById('sidebar').classList.toggle('-translate-x-full');document.getElementById('sidebarBackdrop').classList.toggle('hidden');"
            style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;
                   background:transparent;border:none;cursor:pointer;transition:background 0.15s;position:relative;"
            onmouseover="this.style.background='rgba(255,255,255,0.07)'"
            onmouseout="this.style.background='transparent'">
        <?php if (!empty($_totalNotifCount) && $_totalNotifCount > 0): ?>
        <span style="position:absolute;top:8px;right:calc(50% - 16px);
                     background:#ef4444;color:#fff;font-size:9px;font-weight:800;
                     min-width:16px;height:16px;border-radius:8px;display:flex;align-items:center;
                     justify-content:center;padding:0 3px;line-height:1;border:2px solid #0f1f3d;">
            <?= min((int)$_totalNotifCount, 9) ?>
        </span>
        <?php endif; ?>
        <i class="bi bi-grid-3x3-gap-fill" style="font-size:20px;line-height:1;color:#64748b;"></i>
        <span style="font-size:10px;font-weight:500;color:#475569;letter-spacing:0.02em;line-height:1;">More</span>
    </button>
</nav>

<!-- ── Alpine Toast Notifications ──────────────────────────────────────────── -->
<div x-data
     class="fixed z-[99998] flex flex-col-reverse gap-2 no-print"
     style="bottom:calc(76px + env(safe-area-inset-bottom,0px));right:12px;
            max-width:320px;width:calc(100vw - 24px);pointer-events:none;">
    <template x-for="toast in $store.pdToasts.items" :key="toast.id">
        <div x-show="toast.show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-3 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             style="pointer-events:auto;display:flex;align-items:center;gap:11px;
                    padding:11px 12px;border-radius:16px;cursor:default;
                    background:#0f172a;border:1px solid rgba(255,255,255,0.07);
                    box-shadow:0 12px 36px rgba(0,0,0,0.45);">
            <!-- Colored icon bubble -->
            <div :style="'flex-shrink:0;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:' + toast.bgAccent">
                <i :class="toast.icon" style="font-size:16px;color:#fff;"></i>
            </div>
            <!-- Message -->
            <span x-text="toast.message"
                  style="flex:1;font-size:13.5px;font-weight:600;color:#f1f5f9;line-height:1.4;"></span>
            <!-- Close -->
            <button @click="$store.pdToasts.remove(toast.id)"
                    style="background:rgba(255,255,255,0.08);border:none;color:#94a3b8;cursor:pointer;
                           width:24px;height:24px;border-radius:50%;display:flex;align-items:center;
                           justify-content:center;flex-shrink:0;font-size:14px;line-height:1;
                           transition:background 0.15s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.16)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.08)'">&times;</button>
        </div>
    </template>
</div>

<!-- ── Alpine Confirm Dialog ───────────────────────────────────────────────── -->
<div x-data
     x-show="$store.pdConfirm.visible"
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-100"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 flex items-center justify-center p-6 no-print"
     style="background:rgba(0,0,0,0.45);display:none;z-index:99999;"
     @keydown.escape.window="$store.pdConfirm.answer(false)">
    <div x-show="$store.pdConfirm.visible"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         style="background:#fff;border-radius:16px;max-width:360px;width:100%;
                box-shadow:0 8px 40px rgba(0,0,0,0.18);position:relative;padding:28px 24px 22px;">

        <!-- Close × -->
        <button @click="$store.pdConfirm.answer(false)"
                style="position:absolute;top:14px;right:16px;width:28px;height:28px;
                       background:none;border:none;cursor:pointer;font-size:18px;
                       color:#9ca3af;line-height:1;display:flex;align-items:center;justify-content:center;
                       border-radius:6px;transition:color 0.12s,background 0.12s;"
                onmouseover="this.style.color='#374151';this.style.background='#f3f4f6'"
                onmouseout="this.style.color='#9ca3af';this.style.background='none'"
                aria-label="Close">
            &times;
        </button>

        <!-- Title -->
        <p x-text="$store.pdConfirm.message"
           style="font-size:17px;font-weight:700;color:#111827;margin:0 24px 8px 0;
                  line-height:1.35;text-align:center;"></p>

        <!-- Subtext -->
        <p x-text="$store.pdConfirm.subtext"
           x-show="$store.pdConfirm.subtext"
           style="font-size:13px;color:#6b7280;margin:0 0 20px;line-height:1.55;text-align:center;"></p>

        <!-- Buttons -->
        <div style="display:flex;gap:10px;margin-top:20px;">
            <button @click="$store.pdConfirm.answer(false)"
                    style="flex:1;padding:11px 16px;background:#fff;
                           border:1.5px solid #d1d5db;border-radius:50px;
                           font-size:14px;font-weight:600;color:#374151;cursor:pointer;
                           transition:background 0.12s;"
                    onmouseover="this.style.background='#f9fafb'"
                    onmouseout="this.style.background='#fff'">
                Cancel
            </button>
            <button @click="$store.pdConfirm.answer(true)"
                    :style="$store.pdConfirm.confirmStyle || 'background:#2563eb;'"
                    style="flex:1;padding:11px 16px;border:none;border-radius:50px;
                           font-size:14px;font-weight:700;cursor:pointer;color:#fff;
                           box-shadow:0 4px 14px rgba(0,0,0,0.22);
                           transition:filter 0.12s,transform 0.1s;"
                    onmouseover="this.style.filter='brightness(1.1)';this.style.transform='translateY(-1px)'"
                    onmouseout="this.style.filter='';this.style.transform=''">
                <span x-text="$store.pdConfirm.confirmLabel"></span>
            </button>
        </div>
    </div>
</div>


<script>
/* ── Alpine Stores: Toasts + Confirm Dialog ─────────────────────────────── */
document.addEventListener('alpine:init', () => {

    Alpine.store('pdToasts', {
        items: [],
        _styles: {
            success: { bgAccent: '#059669', icon: 'bi bi-check-circle-fill' },
            error:   { bgAccent: '#dc2626', icon: 'bi bi-x-circle-fill' },
            warning: { bgAccent: '#d97706', icon: 'bi bi-exclamation-triangle-fill' },
            info:    { bgAccent: '#2563eb', icon: 'bi bi-info-circle-fill' },
            loading: { bgAccent: '#475569', icon: 'bi bi-hourglass-split' },
        },
        add(message, type = 'success', duration = 4000) {
            const id  = Date.now() + Math.random();
            const cfg = this._styles[type] || this._styles.success;
            this.items.push({ id, message, show: true, ...cfg });
            if (duration > 0) setTimeout(() => this.remove(id), duration);
            return id;
        },
        remove(id) {
            const t = this.items.find(t => t.id === id);
            if (t) t.show = false;
            setTimeout(() => { this.items = this.items.filter(t => t.id !== id); }, 300);
        },
    });

    Alpine.store('pdConfirm', {
        visible:      false,
        message:      '',
        subtext:      '',
        confirmLabel: 'Confirm',
        confirmIcon:  'bi bi-check-lg',
        confirmStyle: 'background:#2563eb;',
        iconBg:       '#eff6ff',
        iconColor:    '#2563eb',
        _resolve:     null,
        show(opts) {
            if (typeof opts === 'string') opts = { message: opts };
            this.message      = opts.message      || 'Are you sure?';
            this.subtext      = opts.subtext      || '';
            this.confirmLabel = opts.confirmLabel  || 'Confirm';
            this.confirmIcon  = opts.confirmIcon   || 'bi bi-check-lg';
            this.confirmStyle = opts.confirmStyle  || 'background:#2563eb;';
            this.iconColor    = opts.iconColor     || '#2563eb';
            // Auto-derive iconBg tint from confirmStyle colour if not explicitly provided
            if (opts.iconBg) {
                this.iconBg = opts.iconBg;
            } else {
                const cs = (opts.confirmStyle || '').toLowerCase();
                if      (cs.includes('#dc2626') || cs.includes('#ef4444') || cs.includes('#b91c1c')) this.iconBg = '#fef2f2';
                else if (cs.includes('#059669') || cs.includes('#10b981') || cs.includes('#16a34a')) this.iconBg = '#f0fdf4';
                else if (cs.includes('#d97706') || cs.includes('#f59e0b') || cs.includes('#b45309')) this.iconBg = '#fffbeb';
                else if (cs.includes('#7c3aed') || cs.includes('#8b5cf6') || cs.includes('#6d28d9')) this.iconBg = '#f5f3ff';
                else this.iconBg = '#eff6ff';
            }
            this.visible      = true;
            return new Promise(resolve => { this._resolve = resolve; });
        },
        answer(val) {
            this.visible = false;
            if (this._resolve) { this._resolve(val); this._resolve = null; }
        },
    });

});

/* ── Global helpers ─────────────────────────────────────────────────── */
window.pdToast   = (msg, type = 'success', duration = 4000) => Alpine.store('pdToasts').add(msg, type, duration);
window.pdConfirm = (opts) => Alpine.store('pdConfirm').show(opts);
</script>

<!-- Flowbite JS (component interactions) -->
<script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.2/dist/flowbite.min.js"></script>

</body>
</html>
