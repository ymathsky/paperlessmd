<?php
header('Content-Type: text/html; charset=UTF-8');
// Each page sets $pageTitle and $activeNav before including this.
// Build esign count once (used in sidebar)
$_esignCount = 0;
if (!isBilling()) {
    $_esignCount = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE status IN ('signed','uploaded') AND (provider_signature IS NULL OR provider_signature = '')")->fetchColumn();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= isset($pageTitle) ? h($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                colors: {
                    brand: {
                        50:  '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe',
                        500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8',
                        800: '#1e40af', 900: '#1e3a8a', 950: '#172554'
                    }
                }
            }
        }
    }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
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

        $navItems = [
            ['href' => '/dashboard.php', 'key' => 'dashboard', 'icon' => 'bi-speedometer2',    'label' => 'Dashboard'],
            ['href' => '/patients.php',  'key' => 'patients',  'icon' => 'bi-people-fill',     'label' => 'Patients'],
            ['href' => '/schedule.php',  'key' => 'schedule',  'icon' => 'bi-calendar3',       'label' => 'Schedule',   'billingHide' => true],
            ['href' => '/esign_queue.php','key'=> 'esign',     'icon' => 'bi-pen-fill',        'label' => 'Sign Queue', 'billingHide' => true, 'badge' => $_esignCount, 'badgeCls' => 'bg-violet-500'],
            ['href' => '/messages.php',  'key' => 'messages',  'icon' => 'bi-chat-dots-fill',  'label' => 'Messages',   'badge' => $_unreadMessages, 'badgeCls' => 'bg-emerald-500'],
        ];
        foreach ($navItems as $n):
            if (!empty($n['billingHide']) && isBilling()) continue;
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

        <?php if (isAdmin()): ?>
        <!-- Admin section divider -->
        <div class="pt-3 pb-1 px-3">
            <span class="text-[10px] font-bold uppercase tracking-widest text-blue-400/70">Admin</span>
        </div>
        <?php foreach ([
            ['href' => '/admin/schedule_manage.php',  'key' => 'schedule_manage',  'icon' => 'bi-calendar-week-fill', 'label' => 'Manage Schedule'],
            ['href' => '/admin/recurring_schedule.php','key' => 'recurring_schedule','icon' => 'bi-arrow-repeat',      'label' => 'Recurring Schedule'],
            ['href' => '/admin/ma_productivity.php', 'key' => 'ma_report',         'icon' => 'bi-graph-up-arrow',     'label' => 'Productivity Report'],
            ['href' => '/admin/users.php',           'key' => 'users',           'icon' => 'bi-gear-fill',            'label' => 'Manage Staff'],
            ['href' => '/admin/audit_log.php',       'key' => 'audit_log',       'icon' => 'bi-shield-lock-fill',     'label' => 'Audit Log'],
            ['href' => '/admin/settings.php',        'key' => 'settings',        'icon' => 'bi-sliders2-vertical',    'label' => 'Settings'],
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
        <a href="<?= BASE_URL ?>/logout.php"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-blue-200 hover:bg-red-500/20 hover:text-red-300 transition-all duration-150">
            <i class="bi bi-box-arrow-right text-base w-5 shrink-0 text-center"></i>
            <span>Sign Out</span>
        </a>
    </div>
</aside>

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
    <button id="mBtn" class="bg-white/15 hover:bg-white/25 text-white p-2 rounded-xl transition-colors">
        <i class="bi bi-list text-xl leading-none"></i>
    </button>
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
<?php if (!empty($fullHeight)): ?>
<div class="md:pt-0 pt-14 h-screen overflow-hidden flex flex-col">
<div class="flex-1 overflow-hidden">
<?php else: ?>
<div class="md:pt-0 pt-14 pb-12 min-h-screen">
<div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-6 page-fade">
<?php endif; ?>
