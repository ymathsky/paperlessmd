<?php
// Each page sets $pageTitle and $activeNav before including this.
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
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <meta name="theme-color" content="#1e3a8a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="PaperlessMD">
</head>
<body class="bg-slate-50 font-sans min-h-screen">

<!-- ■ Navigation ■ -->
<nav class="fixed inset-x-0 top-0 z-50 bg-gradient-to-r from-blue-950 via-blue-900 to-blue-800 shadow-xl no-print">
    <div class="max-w-screen-xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">

            <!-- Brand -->
            <a href="<?= BASE_URL ?>/dashboard.php" class="flex items-center gap-3 group shrink-0">
                <div class="w-10 h-10 bg-white/20 group-hover:bg-white/30 rounded-xl grid place-items-center transition-colors">
                    <i class="bi bi-clipboard2-heart-fill text-white text-lg leading-none"></i>
                </div>
                <div class="hidden sm:block leading-tight">
                    <div class="text-white font-bold text-sm"><?= APP_NAME ?></div>
                    <div class="text-blue-300 text-xs truncate max-w-[160px]"><?= h(PRACTICE_NAME) ?></div>
                </div>
            </a>

            <!-- Nav links -->
            <div class="hidden md:flex items-center gap-1">
                <?php foreach ([
                    ['href' => '/dashboard.php', 'key' => 'dashboard', 'icon' => 'bi-speedometer2',      'label' => 'Dashboard'],
                    ['href' => '/patients.php',  'key' => 'patients',  'icon' => 'bi-people-fill',       'label' => 'Patients'],
                    ['href' => '/schedule.php',  'key' => 'schedule',  'icon' => 'bi-calendar3',         'label' => 'Schedule', 'billingHide' => true],
                ] as $n):
                    if (!empty($n['billingHide']) && isBilling()) continue;
                    $active = ($activeNav ?? '') === $n['key'];
                ?>
                <a href="<?= BASE_URL . $n['href'] ?>"
                   class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all duration-150
                          <?= $active ? 'bg-white/20 text-white shadow-inner' : 'text-blue-200 hover:bg-white/10 hover:text-white' ?>">
                    <i class="bi <?= $n['icon'] ?>"></i><?= $n['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Right side -->
            <div class="flex items-center gap-2">
                <div class="relative">
                    <button id="uBtn"
                            class="flex items-center gap-2 bg-white/15 hover:bg-white/25 text-white px-3 py-2 rounded-xl text-sm font-medium transition-colors">
                        <div class="relative">
                            <div class="w-7 h-7 bg-blue-600 rounded-lg grid place-items-center text-xs font-bold">
                                <?= strtoupper(mb_substr($_SESSION['full_name'] ?? 'U', 0, 2)) ?>
                            </div>
                            <!-- Offline pending badge -->
                            <span id="offlinePendingBadge" class="hidden absolute -top-1.5 -right-1.5 w-4 h-4 bg-amber-400 text-blue-950 text-[9px] font-bold rounded-full grid place-items-center leading-none">0</span>
                        </div>
                        <span class="hidden sm:block max-w-[120px] truncate text-sm">
                            <?= h($_SESSION['full_name'] ?? '') ?>
                        </span>
                        <!-- Online/offline dot -->
                        <span id="onlineStatusDot" class="w-2 h-2 rounded-full bg-emerald-400 ring-2 ring-emerald-400/30" title="Online"></span>
                        <i class="bi bi-chevron-down text-xs opacity-70"></i>
                    </button>
                    <div id="uDrop"
                         class="hidden absolute right-0 top-full mt-2 w-52 bg-white rounded-2xl shadow-2xl border border-slate-100 overflow-hidden z-50">
                        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100">
                            <div class="font-semibold text-slate-800 text-sm truncate"><?= h($_SESSION['full_name'] ?? '') ?></div>
                            <div class="text-xs text-slate-500 capitalize"><?= $_SESSION['role'] ?? '' ?></div>
                        </div>
                        <?php if (isAdmin()): ?>
                        <a href="<?= BASE_URL ?>/admin/schedule_manage.php"
                           class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 transition-colors">
                            <i class="bi bi-calendar-week-fill text-indigo-400 text-base"></i> Manage Schedule
                        </a>
                        <a href="<?= BASE_URL ?>/admin/users.php"
                           class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 transition-colors">
                            <i class="bi bi-gear-fill text-blue-400 text-base"></i> Manage Staff
                        </a>
                        <a href="<?= BASE_URL ?>/admin/audit_log.php"
                           class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 transition-colors">
                            <i class="bi bi-shield-lock-fill text-emerald-500 text-base"></i> Audit Log
                        </a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/logout.php"
                           class="flex items-center gap-3 px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors border-t border-slate-100">
                            <i class="bi bi-box-arrow-right text-base"></i> Sign Out
                        </a>
                    </div>
                </div>
                <button id="mBtn" class="md:hidden bg-white/15 hover:bg-white/25 text-white p-2 rounded-xl transition-colors">
                    <i class="bi bi-list text-xl leading-none"></i>
                </button>
            </div>
        </div>

        <!-- Mobile menu -->
        <div id="mMenu" class="hidden md:hidden border-t border-white/20 pt-3 pb-4 space-y-1">
            <a href="<?= BASE_URL ?>/dashboard.php"
               class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium
                      <?= ($activeNav??'') === 'dashboard' ? 'bg-white/20 text-white' : 'text-blue-200' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="<?= BASE_URL ?>/patients.php"
               class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium
                      <?= ($activeNav??'') === 'patients' ? 'bg-white/20 text-white' : 'text-blue-200' ?>">
                <i class="bi bi-people-fill"></i> Patients
            </a>
            <?php if (!isBilling()): ?>
            <a href="<?= BASE_URL ?>/schedule.php"
               class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium
                      <?= ($activeNav??'') === 'schedule' ? 'bg-white/20 text-white' : 'text-blue-200' ?>">
                <i class="bi bi-calendar3"></i> Schedule
            </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Offline Banner -->
<div id="offlineBanner" class="hidden fixed inset-x-0 z-40 no-print" style="top:64px">
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

<!-- Offset for fixed nav + page wrapper -->
<div class="pt-20 pb-12 min-h-screen">
<div class="max-w-screen-xl mx-auto px-4 page-fade">
