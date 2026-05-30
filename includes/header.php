<?php
header('Content-Type: text/html; charset=UTF-8');
header('Permissions-Policy: geolocation=(self)');
// Each page sets $pageTitle and $activeNav before including this.
// Build esign count once (used in sidebar)
$_esignCount = 0;
if (!isBilling() && !isMa()) {
    if (isAdmin()) {
        $_esignCount = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE status IN ('signed','uploaded') AND (provider_signature IS NULL OR provider_signature = '')")->fetchColumn();
    } else {
        $__esignStmt = $pdo->prepare("SELECT COUNT(*) FROM form_submissions WHERE status IN ('signed','uploaded') AND (provider_signature IS NULL OR provider_signature = '') AND ma_id = ?");
        $__esignStmt->execute([$_SESSION['user_id']]);
        $_esignCount = (int)$__esignStmt->fetchColumn();
    }
}
// Notification data
$_pendingUpload = 0;
$_oldDrafts     = 0;
if (!isBilling()) {
    try {
        $_pendingUpload = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE status = 'signed'")->fetchColumn();
        $_oldDrafts     = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE status = 'draft' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    } catch (PDOException $e) {}
}
// Always read dark mode + signature status from DB
$_darkMode      = false;
$_promptSavedSig = false;
if (!empty($_SESSION['user_id'])) {
    try {
        $__dmStmt = $pdo->prepare("SELECT dark_mode, saved_signature FROM staff WHERE id = ?");
        $__dmStmt->execute([$_SESSION['user_id']]);
        $__dmRow = $__dmStmt->fetch(PDO::FETCH_ASSOC);
        $_darkMode = !empty($__dmRow['dark_mode']);
        // Block navigation until MA/admin saves their pre-saved signature
        // (skip check on profile.php so they can actually save it)
        if (in_array($_SESSION['role'] ?? '', ['ma', 'admin'])
            && empty($__dmRow['saved_signature'])
            && basename($_SERVER['PHP_SELF']) !== 'profile.php') {
            $_promptSavedSig = true;
        }
    } catch (PDOException $e) {
        $_darkMode = !empty($_SESSION['dark_mode']);
    }
}
?><!DOCTYPE html>
<html lang="en"<?= $_darkMode ? ' class="dark"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= isset($pageTitle) ? h($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=16">
    <!-- Alpine.js (declarative UI, replaces inline JS patterns) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.php">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/apple-touch-icon.png">
    <meta name="theme-color" content="#1e3a8a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="PaperlessMD">
    <style>
        /* Sidebar width token */
        :root { --sidebar-w: 240px; }
        @media (min-width: 768px) {
            body.has-sidebar { padding-left: var(--sidebar-w); }
        }
    </style>
</head>
<body class="bg-slate-50 font-sans min-h-screen has-sidebar">

<!-- ■ Sidebar ■ -->
<aside id="sidebar"
       class="no-print fixed inset-y-0 left-0 z-50 flex flex-col
              bg-gradient-to-b from-blue-950 via-blue-900 to-blue-800
              shadow-2xl transition-transform duration-300
              w-[240px] -translate-x-full md:translate-x-0">


    <!-- Brand -->
    <a href="<?= BASE_URL ?>/dashboard.php"
       class="flex items-center gap-3 px-5 py-5 border-b border-white/10 shrink-0 group">
        <div class="w-9 h-9 bg-white/20 group-hover:bg-white/30 rounded-xl grid place-items-center transition-colors shrink-0">
            <i class="bi bi-clipboard2-heart-fill text-white text-base leading-none"></i>
        </div>
        <div class="leading-tight overflow-hidden">
            <div class="text-white font-bold text-sm truncate"><?= APP_NAME ?></div>
            <div class="text-blue-300 text-xs truncate"><?= h(PRACTICE_NAME) ?></div>
        </div>
    </a>

    <!-- Nav links -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
        <?php
        // Unread messages count (try/catch: safe before migration runs)
        $_unreadMessages = 0;
        try {
            if (!empty($_SESSION['user_id'])) {
                $umStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM messages m
                    WHERE  (m.to_user_id = ? OR m.to_user_id IS NULL)
                      AND  m.from_user_id != ?
                      AND  NOT EXISTS (
                          SELECT 1 FROM message_reads mr
                          WHERE mr.message_id = m.id AND mr.user_id = ?
                      )
                ");
                $umStmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
                $_unreadMessages = (int)$umStmt->fetchColumn();
            }
        } catch (PDOException $e) { /* messages table not yet migrated */ }

        $_totalNotifCount = ($_unreadMessages  > 0 ? 1 : 0)
                          + ($_pendingUpload  > 0 ? 1 : 0)
                          + ($_esignCount     > 0 ? 1 : 0)
                          + ($_oldDrafts      > 0 ? 1 : 0);

        $navItems = [
            ['href' => '/dashboard.php', 'key' => 'dashboard', 'icon' => 'bi-speedometer2',    'label' => 'Dashboard'],
            ['href' => '/patients.php',  'key' => 'patients',  'icon' => 'bi-people-fill',     'label' => 'Patients'],
            ['href' => '/schedule.php',      'key' => 'schedule',      'icon' => 'bi-calendar3',           'label' => 'Schedule',       'billingHide' => true],
            ['href' => '/daily_summary.php', 'key' => 'daily_summary', 'icon' => 'bi-bar-chart-line-fill', 'label' => 'Daily Summary',  'billingHide' => true],
            ['href' => '/esign_queue.php',   'key' => 'esign',         'icon' => 'bi-pen-fill',            'label' => 'Sign Queue',     'billingHide' => true, 'maHide' => true, 'badge' => $_esignCount, 'badgeCls' => 'bg-violet-500'],
            ['href' => '/messages.php',  'key' => 'messages',  'icon' => 'bi-chat-dots-fill',  'label' => 'Messages',   'badge' => $_unreadMessages, 'badgeCls' => 'bg-emerald-500'],
            ['href' => '/whats_new.php', 'key' => 'whats_new', 'icon' => 'bi-rocket-takeoff-fill', 'label' => "What's New"],
        ];
        ?>
        <!-- Search trigger -->
        <button data-search-trigger
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium
                       text-blue-200 hover:bg-white/10 hover:text-white transition-all duration-150 w-full text-left mb-1">
            <i class="bi bi-search text-base w-5 shrink-0 text-center"></i>
            <span class="flex-1">Search</span>
            <kbd class="text-[9px] text-blue-400/70 bg-white/10 px-1.5 py-0.5 rounded leading-none font-mono">Ctrl K</kbd>
        </button>
        <?php foreach ($navItems as $n):
            if (!empty($n['billingHide']) && isBilling()) continue;
            if (!empty($n['maHide']) && isMa()) continue;
            $active = ($activeNav ?? '') === $n['key'];
        ?>
        <a href="<?= BASE_URL . $n['href'] ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  <?= $active ? 'bg-white/20 text-white shadow-sm' : 'text-blue-200 hover:bg-white/10 hover:text-white' ?>">
            <i class="bi <?= $n['icon'] ?> text-base w-5 shrink-0 text-center"></i>
            <span class="flex-1"><?= $n['label'] ?></span>
            <?php if (!empty($n['badge']) && $n['badge'] > 0): ?>
            <span class="<?= $n['badgeCls'] ?> text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none min-w-[18px] text-center">
                <?= (int)$n['badge'] ?>
            </span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <!-- Notifications bell -->
        <button data-notif-trigger
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium
                       text-blue-200 hover:bg-white/10 hover:text-white transition-all duration-150 w-full text-left mt-0.5">
            <i class="bi bi-bell-fill text-base w-5 shrink-0 text-center"></i>
            <span class="flex-1">Notifications</span>
            <span id="notifBadge" class="hidden bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none min-w-[18px] text-center">0</span>
        </button>

        <?php if (isMa()): ?>
        <!-- MA quick links -->
        <a href="<?= BASE_URL ?>/admin/all_forms.php"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 mt-0.5
                  <?= ($activeNav ?? '') === 'all_forms' ? 'bg-white/20 text-white shadow-sm' : 'text-blue-200 hover:bg-white/10 hover:text-white' ?>">
            <i class="bi bi-folder2-open text-base w-5 shrink-0 text-center"></i>
            <span>All Forms</span>
        </a>
        <?php endif; ?>

        <?php if (isScheduler()): ?>
        <!-- Scheduler section divider -->
        <div class="pt-3 pb-1 px-3">
            <span class="text-[10px] font-bold uppercase tracking-widest text-blue-400/70">Scheduling</span>
        </div>
        <?php foreach ([
            ['href' => '/admin/schedule_manage.php',  'key' => 'schedule_manage',  'icon' => 'bi-calendar-week-fill', 'label' => 'Manage Schedule'],
            ['href' => '/admin/recurring_schedule.php','key' => 'recurring_schedule','icon' => 'bi-arrow-repeat',      'label' => 'Recurring Schedule'],
        ] as $n):
            $active = ($activeNav ?? '') === $n['key'];
        ?>
        <a href="<?= BASE_URL . $n['href'] ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  <?= $active ? 'bg-white/20 text-white shadow-sm' : 'text-blue-200 hover:bg-white/10 hover:text-white' ?>">
            <i class="bi <?= $n['icon'] ?> text-base w-5 shrink-0 text-center"></i>
            <span><?= $n['label'] ?></span>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (isAdmin()): ?>
        <!-- Admin section divider -->
        <div class="pt-3 pb-1 px-3">
            <span class="text-[10px] font-bold uppercase tracking-widest text-blue-400/70">Admin</span>
        </div>
        <?php foreach ([
            ['href' => '/admin/all_forms.php',         'key' => 'all_forms',          'icon' => 'bi-folder2-open',            'label' => 'All Forms'],
            ['href' => '/admin/schedule_manage.php',  'key' => 'schedule_manage',  'icon' => 'bi-calendar-week-fill', 'label' => 'Manage Schedule'],
            ['href' => '/admin/recurring_schedule.php','key' => 'recurring_schedule','icon' => 'bi-arrow-repeat',      'label' => 'Recurring Schedule'],
            ['href' => '/admin/wound_photos.php',    'key' => 'wound_photos',     'icon' => 'bi-camera-fill',         'label' => 'Wound Photos'],
            ['href' => '/admin/ma_productivity.php', 'key' => 'ma_report',         'icon' => 'bi-graph-up-arrow',     'label' => 'Productivity Report'],
            ['href' => '/admin/ma_locations.php',    'key' => 'ma_locations',       'icon' => 'bi-geo-alt-fill',        'label' => 'MA Locations'],
            ['href' => '/admin/users.php',           'key' => 'users',           'icon' => 'bi-gear-fill',            'label' => 'Manage Staff'],
            ['href' => '/admin/roles.php',            'key' => 'roles',           'icon' => 'bi-person-badge-fill',    'label' => 'Roles & Permissions'],
            ['href' => '/admin/audit_log.php',       'key' => 'audit_log',       'icon' => 'bi-shield-lock-fill',     'label' => 'Audit Log'],
            ['href' => '/admin/settings.php',        'key' => 'settings',        'icon' => 'bi-sliders2-vertical',    'label' => 'Settings'],
            ['href' => '/admin/push_test.php',       'key' => 'push_test',       'icon' => 'bi-bell-fill',            'label' => 'Push Notifications'],
        ] as $n):
            $active = ($activeNav ?? '') === $n['key'];
        ?>
        <a href="<?= BASE_URL . $n['href'] ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  <?= $active ? 'bg-white/20 text-white shadow-sm' : 'text-blue-200 hover:bg-white/10 hover:text-white' ?>">
            <i class="bi <?= $n['icon'] ?> text-base w-5 shrink-0 text-center"></i>
            <span><?= $n['label'] ?></span>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </nav>

    <!-- User section (bottom) -->
    <div class="shrink-0 border-t border-white/10 px-3 py-3">
        <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-0.5">
            <div class="relative shrink-0">
                <div class="w-8 h-8 bg-blue-600 rounded-lg grid place-items-center text-xs font-bold text-white">
                    <?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'U', 0, 2)) ?>
                </div>
                <span id="offlinePendingBadge" class="hidden absolute -top-1.5 -right-1.5 w-4 h-4 bg-amber-400 text-blue-950 text-[9px] font-bold rounded-full grid place-items-center leading-none">0</span>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-white text-sm font-semibold truncate"><?= h($_SESSION['full_name'] ?? '') ?></div>
                <div class="flex items-center gap-1.5">
                    <span id="onlineStatusDot" class="w-2 h-2 rounded-full bg-emerald-400 ring-2 ring-emerald-400/30 shrink-0" title="Online"></span>
                    <span class="text-blue-300 text-xs capitalize"><?= $_SESSION['role'] ?? '' ?></span>
                </div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/profile.php"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  <?= ($activeNav ?? '') === 'profile' ? 'bg-white/20 text-white shadow-sm' : 'text-blue-200 hover:bg-white/10 hover:text-white' ?>">
            <i class="bi bi-person-circle text-base w-5 shrink-0 text-center"></i>
            <span>My Profile</span>
        </a>
        <button id="sidebarDarkToggle"
                onclick="sidebarToggleDark()"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-blue-200 hover:bg-white/10 hover:text-white transition-all duration-150 w-full text-left">
            <i id="sidebarDarkIcon" class="bi <?= $_darkMode ? 'bi-sun-fill' : 'bi-moon-fill' ?> text-base w-5 shrink-0 text-center"></i>
            <span id="sidebarDarkLabel"><?= $_darkMode ? 'Light Mode' : 'Dark Mode' ?></span>
        </button>
        <a href="<?= BASE_URL ?>/logout.php"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-blue-200 hover:bg-red-500/20 hover:text-red-300 transition-all duration-150">
            <i class="bi bi-box-arrow-right text-base w-5 shrink-0 text-center"></i>
            <span>Sign Out</span>
        </a>
        <!-- PWA install button — shown by JS only on supporting browsers (Chrome/Edge/Android) -->
        <button id="pwa-install-btn" style="display:none"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-blue-200 hover:bg-white/10 hover:text-white transition-all duration-150 w-full text-left">
            <i class="bi bi-download text-base w-5 shrink-0 text-center"></i>
            <span>Install App</span>
        </button>
    </div>
</aside>
<?php
// ── Active-visit tracking & navigation control ──────────────────────────
if (!empty($_GET['visit_id'])):
    $_avId        = (int)$_GET['visit_id'];
    $_avPatientId = (int)($_GET['patient_id'] ?? 0);
    $_avName      = '';
    if ($_avPatientId) {
        try {
            $_avStmt = $pdo->prepare("SELECT first_name, last_name FROM patients WHERE id = ? LIMIT 1");
            $_avStmt->execute([$_avPatientId]);
            $_avRow  = $_avStmt->fetch(PDO::FETCH_ASSOC);
            if ($_avRow) $_avName = trim(($_avRow['first_name'] ?? '') . ' ' . ($_avRow['last_name'] ?? ''));
        } catch (PDOException $e) {}
    }
    $_avType = $_GET['sched_visit_type'] ?? '';
?>
<script>
/* Save active visit so the floating chip knows where to send the MA back */
sessionStorage.setItem('pdActiveVisit', JSON.stringify({
    visitId:     <?= $_avId ?>,
    patientId:   <?= $_avPatientId ?>,
    patientName: <?= json_encode($_avName) ?>,
    visitType:   <?= json_encode($_avType) ?>,
    formUrl:     window.location.href
}));
</script>
<?php if (($_SESSION['role'] ?? '') !== 'ma'): ?>
<!-- Visit-lock toast (non-MA roles) -->
<div id="visitLockToast"
     class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 z-[9999]
            bg-amber-500 text-white px-5 py-3 rounded-xl shadow-2xl
            flex items-center gap-3 text-sm font-semibold pointer-events-none">
    <i class="bi bi-shield-lock-fill text-base shrink-0"></i>
    <span>Active visit in progress &mdash; end the visit before navigating.</span>
</div>
<script>
(function () {
    function showLockToast() {
        var t = document.getElementById('visitLockToast');
        if (!t) return;
        t.classList.remove('hidden');
        clearTimeout(t._hide);
        t._hide = setTimeout(function () { t.classList.add('hidden'); }, 3000);
    }
    document.addEventListener('click', function (e) {
        var link  = e.target.closest('#sidebar a');
        var notif = e.target.closest('[data-notif-trigger]');
        if (link || notif) { e.preventDefault(); e.stopImmediatePropagation(); showLockToast(); }
    }, true);
    history.pushState(null, '', location.href);
    window.addEventListener('popstate', function () {
        history.pushState(null, '', location.href);
        showLockToast();
    });
    window.addEventListener('beforeunload', function (e) {
        if (window._pdSubmitting) return;
        e.preventDefault();
        e.returnValue = '';
    });
})();
</script>
<?php endif; ?>
<?php endif; ?>

<!-- ── Floating Return-to-Visit chip (MA) ─────────────────────────────── -->
<style>
@keyframes avc-slide-in {
    from { opacity: 0; transform: translateX(20px) scale(.94); }
    to   { opacity: 1; transform: translateX(0)    scale(1);   }
}
#activeVisitChip:not(.hidden) { animation: avc-slide-in .35s cubic-bezier(.34,1.56,.64,1) both; }
#activeVisitChip { cursor: grab; user-select: none; }
#activeVisitChip.dragging { cursor: grabbing; transition: none !important; opacity: .92; }
/* Force text colours regardless of dark-mode overrides */
#activeVisitChip #avcAvatar,
#activeVisitChip #avcName          { color: #ffffff !important; }
#activeVisitChip .avc-label,
#activeVisitChip .avc-return-label { color: #6ee7b7 !important; } /* emerald-300 */
#activeVisitChip .avc-arrow        { color: #34d399 !important; } /* emerald-400 */
</style>
<div id="activeVisitChip" class="hidden fixed top-4 right-6 no-print" style="z-index:2147483647">
    <button id="avcBtn"
            class="flex items-center gap-4 pl-4 pr-5 py-3.5 rounded-2xl text-white font-semibold shadow-2xl"
            style="background:linear-gradient(135deg,#0f1f2e 0%,#0d2a1f 100%);
                   border:1px solid rgba(52,211,153,.35);
                   box-shadow:0 12px 40px rgba(0,0,0,.55),0 0 0 1px rgba(52,211,153,.15)">
        <!-- drag handle hint -->
        <span class="flex-none text-white/30 text-xs leading-none pr-1" style="cursor:grab">
            <i class="bi bi-grip-vertical"></i>
        </span>
        <!-- pulsing live dot -->
        <span class="relative flex-none w-3 h-3">
            <span class="absolute inset-0 rounded-full bg-emerald-400 animate-ping opacity-50"></span>
            <span class="relative block w-3 h-3 rounded-full bg-emerald-400"></span>
        </span>
        <!-- initials avatar -->
        <span id="avcAvatar"
              class="w-11 h-11 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500
                     flex items-center justify-center font-bold text-sm text-white flex-none shadow-md">?</span>
        <!-- text -->
        <span class="flex flex-col leading-tight text-left">
            <span class="avc-label text-[11px] font-bold tracking-widest uppercase mb-0.5">Active Visit</span>
            <span id="avcName" class="text-base font-bold max-w-[200px] truncate">Patient</span>
        </span>
        <!-- return label + arrow -->
        <span class="avc-return-label flex items-center gap-1.5 ml-1 text-sm font-semibold border-l border-white/10 pl-4">
            Return <i class="avc-arrow bi bi-arrow-right-circle-fill text-xl"></i>
        </span>
    </button>
</div>
<script>
(function () {
    var _raw = sessionStorage.getItem('pdActiveVisit');
    if (!_raw) return;
    var _v; try { _v = JSON.parse(_raw); } catch (e) { return; }

    /* Already on this exact visit form? No chip needed. */
    var _cur = <?= (int)($_GET['visit_id'] ?? 0) ?>;
    if (_cur && _cur === _v.visitId) return;

    /* Verify visit is still en_route before showing */
    fetch((window._pdBase || '') + '/api/visit_check.php?id=' + _v.visitId)
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.active) { sessionStorage.removeItem('pdActiveVisit'); return; }
            var chip  = document.getElementById('activeVisitChip');
            var avEl  = document.getElementById('avcAvatar');
            var nmEl  = document.getElementById('avcName');
            var btn   = document.getElementById('avcBtn');
            if (!chip || !avEl || !nmEl || !btn) return;
            var initials = (_v.patientName || '?').split(' ').filter(Boolean)
                               .map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase();
            avEl.textContent = initials;
            nmEl.textContent = _v.patientName || 'Patient';

            /* ── Restore saved position ── */
            var _pos = null;
            try { _pos = JSON.parse(localStorage.getItem('pdAvcPos')); } catch(e){}
            if (_pos && typeof _pos.top === 'number' && typeof _pos.left === 'number') {
                chip.style.top    = Math.max(0, Math.min(_pos.top,  window.innerHeight - 80)) + 'px';
                chip.style.right  = 'auto';
                chip.style.left   = Math.max(0, Math.min(_pos.left, window.innerWidth  - 80)) + 'px';
            }
            chip.classList.remove('hidden');

            /* ── Drag logic ── */
            var _dragging = false, _startX, _startY, _origLeft, _origTop, _moved;

            function _getLeft() {
                var r = chip.getBoundingClientRect();
                return r.left;
            }
            function _getTop() {
                var r = chip.getBoundingClientRect();
                return r.top;
            }

            function onDown(ex, ey) {
                _dragging = true;
                _moved    = false;
                _startX   = ex;
                _startY   = ey;
                _origLeft = _getLeft();
                _origTop  = _getTop();
                chip.classList.add('dragging');
            }
            function onMove(ex, ey) {
                if (!_dragging) return;
                var dx = ex - _startX, dy = ey - _startY;
                if (Math.abs(dx) > 4 || Math.abs(dy) > 4) _moved = true;
                var newLeft = Math.max(0, Math.min(_origLeft + dx, window.innerWidth  - chip.offsetWidth));
                var newTop  = Math.max(0, Math.min(_origTop  + dy, window.innerHeight - chip.offsetHeight));
                chip.style.left  = newLeft + 'px';
                chip.style.top   = newTop  + 'px';
                chip.style.right = 'auto';
            }
            function onUp(ex, ey) {
                if (!_dragging) return;
                _dragging = false;
                chip.classList.remove('dragging');
                onMove(ex, ey);
                /* Save position */
                try { localStorage.setItem('pdAvcPos', JSON.stringify({ left: _getLeft(), top: _getTop() })); } catch(e){}
            }

            /* Mouse */
            chip.addEventListener('mousedown',  function(e) { onDown(e.clientX, e.clientY); e.preventDefault(); });
            document.addEventListener('mousemove', function(e) { onMove(e.clientX, e.clientY); });
            document.addEventListener('mouseup',   function(e) { onUp(e.clientX,   e.clientY); });

            /* Touch */
            chip.addEventListener('touchstart', function(e) { var t=e.touches[0]; onDown(t.clientX, t.clientY); }, { passive: true });
            document.addEventListener('touchmove',  function(e) { if(_dragging){ var t=e.touches[0]; onMove(t.clientX, t.clientY); e.preventDefault(); } }, { passive: false });
            document.addEventListener('touchend',   function(e) { var t=e.changedTouches[0]; onUp(t.clientX, t.clientY); });

            /* Click only fires if not dragged */
            btn.addEventListener('click', function(e) {
                if (_moved) { e.preventDefault(); e.stopImmediatePropagation(); _moved = false; return; }
                window.location.href = _v.formUrl;
            });
        })
        .catch(function () {});
})();
</script>

<!-- Mobile top bar (hamburger + brand) — visible only on small screens -->
<header class="md:hidden no-print fixed inset-x-0 top-0 z-40 h-14
               bg-gradient-to-r from-blue-950 to-blue-800 shadow-lg
               flex items-center justify-between px-4">
    <a href="<?= BASE_URL ?>/dashboard.php" class="flex items-center gap-2.5">
        <div class="w-8 h-8 bg-white/20 rounded-xl grid place-items-center">
            <i class="bi bi-clipboard2-heart-fill text-white text-sm leading-none"></i>
        </div>
        <span class="text-white font-bold text-sm"><?= APP_NAME ?></span>
    </a>
    <div class="flex items-center gap-1">
        <button data-search-trigger
                class="bg-white/15 hover:bg-white/25 text-white p-2 rounded-xl transition-colors"
                title="Search (Ctrl+K)">
            <i class="bi bi-search text-lg leading-none"></i>
        </button>
        <button data-notif-trigger
                class="relative bg-white/15 hover:bg-white/25 text-white p-2 rounded-xl transition-colors"
                title="Notifications">
            <i class="bi bi-bell-fill text-lg leading-none"></i>
            <span id="notifBadgeMobile" class="hidden absolute -top-0.5 -right-0.5 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center leading-none">0</span>
        </button>
        <button id="mBtn" class="bg-white/15 hover:bg-white/25 text-white p-2 rounded-xl transition-colors">
            <i class="bi bi-list text-xl leading-none"></i>
        </button>
    </div>
</header>

<!-- Sidebar backdrop (mobile) -->
<div id="sidebarBackdrop"
     class="hidden md:hidden fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm no-print"></div>

<!-- Offline Banner -->
<div id="offlineBanner" class="hidden fixed z-40 no-print md:left-[240px] left-0 right-0" style="top:0">
    <div class="bg-amber-500 text-white px-4 py-2.5 flex items-center justify-between gap-3 shadow-lg">
        <div class="flex items-center gap-2 text-sm font-semibold">
            <i class="bi bi-wifi-off text-base"></i>
            <span>You're offline — forms are saved locally and will sync automatically when connected.</span>
        </div>
        <button id="offlineSyncBtn" class="hidden shrink-0 bg-white/25 hover:bg-white/40 px-3 py-1 rounded-lg text-xs font-bold transition">
            <i class="bi bi-cloud-upload-fill mr-1"></i>Sync Now
        </button>
    </div>
</div>

<!-- Page content wrapper (offset for sidebar on desktop, top bar on mobile) -->

<!-- ── Session Timeout Warning Modal ───────────────────────────────────── -->
<?php if (!empty($_SESSION['user_id'])): ?>
<div id="sessionWarnModal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,.65);backdrop-filter:blur(4px);align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:20px;padding:32px 28px;max-width:380px;width:90%;box-shadow:0 24px 60px rgba(0,0,0,.25);text-align:center">
        <div style="width:60px;height:60px;background:#fef3c7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
            <i class="bi bi-clock-history" style="font-size:28px;color:#d97706"></i>
        </div>
        <h3 style="margin:0 0 6px;font-size:18px;font-weight:700;color:#1e293b">Session expiring soon</h3>
        <p style="margin:0 0 6px;font-size:14px;color:#64748b">You'll be logged out automatically in</p>
        <div id="sessionCountdown" style="font-size:40px;font-weight:800;color:#ef4444;letter-spacing:2px;margin:10px 0">2:00</div>
        <p style="margin:0 0 24px;font-size:13px;color:#94a3b8">Any unsaved changes will be lost.</p>
        <div style="display:flex;gap:10px">
            <button onclick="sessionExtend()" style="flex:1;padding:11px;background:#2563eb;color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer">
                <i class="bi bi-arrow-repeat mr-1"></i> Stay logged in
            </button>
            <a href="<?= BASE_URL ?>/logout.php" style="flex:1;padding:11px;background:#f1f5f9;color:#475569;border:none;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center">
                Log out
            </a>
        </div>
    </div>
</div>
<script>
(function() {
    const TIMEOUT   = <?= SESSION_TIMEOUT ?>;   // seconds (e.g. 7200)
    const WARN_SECS = 120;                        // show warning this many seconds before expiry
    const CSRF      = <?= json_encode(csrfToken(), JSON_HEX_TAG) ?>;
    const BASE      = <?= json_encode(BASE_URL) ?>;

    let _lastActivity = Date.now(); // ms
    let _warnTimer = null, _expireTimer = null, _countdownInterval = null;
    let _warned = false;

    // Reset activity timestamp on any user interaction
    ['mousemove','keydown','click','touchstart','scroll'].forEach(ev =>
        document.addEventListener(ev, () => { _lastActivity = Date.now(); }, {passive:true})
    );

    function scheduleTimers() {
        clearTimeout(_warnTimer); clearTimeout(_expireTimer);
        const warnMs    = (TIMEOUT - WARN_SECS) * 1000;
        const expireMs  = TIMEOUT * 1000;
        _warnTimer   = setTimeout(showWarning, warnMs);
        _expireTimer = setTimeout(forceLogout,  expireMs);
    }

    function showWarning() {
        if (_warned) return;
        _warned = true;
        const modal = document.getElementById('sessionWarnModal');
        modal.style.display = 'flex';
        let remaining = WARN_SECS;
        updateCountdown(remaining);
        _countdownInterval = setInterval(() => {
            remaining--;
            updateCountdown(remaining);
            if (remaining <= 0) { clearInterval(_countdownInterval); forceLogout(); }
        }, 1000);
    }

    function updateCountdown(secs) {
        const m = Math.floor(secs / 60), s = secs % 60;
        const el = document.getElementById('sessionCountdown');
        if (el) el.textContent = m + ':' + String(s).padStart(2,'0');
    }

    function forceLogout() {
        window.location.href = BASE + '/logout.php';
    }

    window.sessionExtend = function() {
        fetch(BASE + '/api/session_ping.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({csrf: CSRF})
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                document.getElementById('sessionWarnModal').style.display = 'none';
                clearInterval(_countdownInterval);
                _warned = false;
                scheduleTimers();
            } else {
                forceLogout();
            }
        })
        .catch(() => forceLogout());
    };

    scheduleTimers();
})();

async function sidebarToggleDark() {
    const html   = document.documentElement;
    const isOn   = html.classList.contains('dark');
    const newVal = !isOn;
    const icon   = document.getElementById('sidebarDarkIcon');
    const label  = document.getElementById('sidebarDarkLabel');
    // Optimistic UI
    html.classList.toggle('dark', newVal);
    if (icon)  { icon.className  = 'bi ' + (newVal ? 'bi-sun-fill' : 'bi-moon-fill') + ' text-base w-5 shrink-0 text-center'; }
    if (label) { label.textContent = newVal ? 'Light Mode' : 'Dark Mode'; }
    try {
        const fd = new FormData();
        fd.append('action',     'toggle_dark_mode');
        fd.append('dark_mode',  newVal ? '1' : '0');
        fd.append('csrf_token', <?= json_encode(csrfToken(), JSON_HEX_TAG) ?>);
        const res  = await fetch(<?= json_encode(BASE_URL . '/profile.php', JSON_HEX_TAG) ?>, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error();
    } catch (e) {
        // Rollback
        html.classList.toggle('dark', isOn);
        if (icon)  { icon.className  = 'bi ' + (isOn ? 'bi-sun-fill' : 'bi-moon-fill') + ' text-base w-5 shrink-0 text-center'; }
        if (label) { label.textContent = isOn ? 'Light Mode' : 'Dark Mode'; }
    }
}
</script>
<?php endif; ?>
<?php if (!empty($fullHeight)): ?>
<div class="md:pt-0 pt-14 h-screen overflow-hidden flex flex-col">
<div class="flex-1 overflow-hidden">
<?php else: ?>
<div class="md:pt-0 pt-14 pb-24 md:pb-8 min-h-screen">
<div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-6 page-fade">
<?php endif; ?>
