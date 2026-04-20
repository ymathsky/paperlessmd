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
<?php endif; ?>

<footer class="bg-white border-t border-slate-100 py-4 text-center text-xs text-slate-400 no-print">
    <?= APP_NAME ?> &copy; <?= date('Y') ?> &mdash; HIPAA-Conscious Paperless Document System
</footer>

<script src="<?= BASE_URL ?>/assets/js/signature_pad.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=2"></script>
<script src="<?= BASE_URL ?>/assets/js/form-wizard.js"></script>
<script>window._pdBase = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>/assets/js/autosave.js" defer></script>
<script src="<?= BASE_URL ?>/assets/js/voice.js" defer></script>
<script src="<?= BASE_URL ?>/assets/js/offline.js" defer></script>
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
<?php endif; ?>
</body>
</html>
