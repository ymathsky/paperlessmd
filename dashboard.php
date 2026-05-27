<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$today     = date('Y-m-d');
$hour      = (int)date('H');
$greeting  = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', $_SESSION['full_name'] ?? 'Staff')[0];

if (isAdmin() || isBilling()) {
    $totalPatients = (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $formsToday    = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE DATE(created_at) = '$today'")->fetchColumn();
    $photosToday   = (int)$pdo->query("SELECT COUNT(*) FROM wound_photos WHERE DATE(created_at) = '$today'")->fetchColumn();
    $pendingUpload = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE status = 'signed'")->fetchColumn();
} else {
    $maId = (int)$_SESSION['user_id'];
    $s1 = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) FROM `schedule` WHERE ma_id = ?"); $s1->execute([$maId]); $totalPatients = (int)$s1->fetchColumn();
    $s2 = $pdo->prepare("SELECT COUNT(*) FROM form_submissions WHERE ma_id = ? AND DATE(created_at) = ?"); $s2->execute([$maId, $today]); $formsToday = (int)$s2->fetchColumn();
    $s3 = $pdo->prepare("SELECT COUNT(*) FROM wound_photos WHERE uploaded_by = ? AND DATE(created_at) = ?"); $s3->execute([$maId, $today]); $photosToday = (int)$s3->fetchColumn();
    $s4 = $pdo->prepare("SELECT COUNT(*) FROM form_submissions WHERE ma_id = ? AND status = 'signed'"); $s4->execute([$maId]); $pendingUpload = (int)$s4->fetchColumn();
}

// Billing-specific stats
$billingSignedForms   = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE status IN ('signed','uploaded')")->fetchColumn();
$billingSignedToday   = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE status IN ('signed','uploaded') AND DATE(created_at) = '$today'")->fetchColumn();
$billingPendingUpload = $pendingUpload;

// Provider filter — MAs can pin a specific provider's route
if (array_key_exists('filter_provider', $_GET)) {
    $_SESSION['dash_provider_filter'] = $_GET['filter_provider']; // '' clears it
}
$providerFilter = $_SESSION['dash_provider_filter'] ?? '';

// All active providers (for the filter dropdown)
$_pvStmt = $pdo->query("
    SELECT full_name FROM staff
    WHERE active = 1 AND role = 'provider'
    ORDER BY full_name ASC
");
$providerList = $_pvStmt->fetchAll(PDO::FETCH_COLUMN);

// Today's schedule for current user (MAs filter by provider when set, else own visits)
if (!isAdmin() && $providerFilter !== '') {
    $myScheduleStmt = $pdo->prepare("
        SELECT sc.*,
               CONCAT(p.first_name,' ',p.last_name) AS patient_name,
               p.address AS patient_address,
               p.id AS patient_id
        FROM `schedule` sc
        JOIN patients p ON p.id = sc.patient_id
        WHERE sc.visit_date = ? AND sc.provider_name = ?
        ORDER BY sc.visit_order ASC, sc.visit_time ASC
        LIMIT 6
    ");
    $myScheduleStmt->execute([$today, $providerFilter]);
} else {
    $myScheduleStmt = $pdo->prepare("
        SELECT sc.*,
               CONCAT(p.first_name,' ',p.last_name) AS patient_name,
               p.address AS patient_address,
               p.id AS patient_id
        FROM `schedule` sc
        JOIN patients p ON p.id = sc.patient_id
        WHERE sc.visit_date = ? AND sc.ma_id = ?
        ORDER BY sc.visit_order ASC, sc.visit_time ASC
        LIMIT 6
    ");
    $myScheduleStmt->execute([$today, $_SESSION['user_id']]);
}
$mySchedule = $myScheduleStmt->fetchAll();

if (!isAdmin() && $providerFilter !== '') {
    $scCountStmt = $pdo->prepare("SELECT COUNT(*) FROM `schedule` WHERE visit_date=? AND provider_name=?");
    $scCountStmt->execute([$today, $providerFilter]);
} else {
    $scCountStmt = $pdo->prepare("SELECT COUNT(*) FROM `schedule` WHERE visit_date=? AND ma_id=?");
    $scCountStmt->execute([$today, $_SESSION['user_id']]);
}
$scheduleTotalToday = (int)$scCountStmt->fetchColumn();

// Unsigned (draft) forms — admins see all, MAs see only their own
if (isAdmin()) {
    $draftStmt = $pdo->query("
        SELECT fs.id, fs.form_type, fs.created_at,
               CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.id AS patient_id,
               s.full_name AS ma_name
        FROM form_submissions fs
        JOIN patients p ON p.id = fs.patient_id
        LEFT JOIN staff s ON s.id = fs.ma_id
        WHERE fs.status = 'draft'
        ORDER BY fs.created_at ASC
        LIMIT 100
    ");
    $draftForms = $draftStmt->fetchAll();
} else {
    $draftStmt = $pdo->prepare("
        SELECT fs.id, fs.form_type, fs.created_at,
               CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.id AS patient_id,
               s.full_name AS ma_name
        FROM form_submissions fs
        JOIN patients p ON p.id = fs.patient_id
        LEFT JOIN staff s ON s.id = fs.ma_id
        WHERE fs.status = 'draft' AND fs.ma_id = ?
        ORDER BY fs.created_at ASC
        LIMIT 100
    ");
    $draftStmt->execute([$_SESSION['user_id']]);
    $draftForms = $draftStmt->fetchAll();
}
$draftCount = count($draftForms);

function draftAge(string $ts): array {
    $diff = time() - strtotime($ts);
    if ($diff < 3600)   return [(int)($diff/60).'m ago',  'text-amber-600',  'bg-amber-50'];
    if ($diff < 86400)  return [(int)($diff/3600).'h ago', 'text-orange-600', 'bg-orange-50'];
    return [(int)($diff/86400).'d ago',  'text-red-600',    'bg-red-50'];
}

// ── Weekly stats bar chart (admin only) ─────────────────────────────────────────
$weeklyStats = [];
if (isAdmin()) {
    // Build Mon–Sun labels for this week and last week
    $weekStart = strtotime('monday this week midnight');
    $prevStart = $weekStart - 7 * 86400;

    // Query counts per day for the last 14 days
    $wsStmt = $pdo->query("
        SELECT DATE(created_at) AS d, COUNT(*) AS cnt
        FROM form_submissions
        WHERE created_at >= '" . date('Y-m-d', $prevStart) . "'
          AND created_at <  '" . date('Y-m-d', $weekStart + 7 * 86400) . "'
        GROUP BY DATE(created_at)
    ");
    $wsRows = [];
    foreach ($wsStmt->fetchAll() as $r) $wsRows[$r['d']] = (int)$r['cnt'];

    for ($i = 0; $i < 7; $i++) {
        $tw = date('Y-m-d', $weekStart + $i * 86400);
        $lw = date('Y-m-d', $prevStart + $i * 86400);
        $weeklyStats[] = [
            'label'    => date('D', $weekStart + $i * 86400), // Mon, Tue…
            'thisWeek' => $wsRows[$tw] ?? 0,
            'lastWeek' => $wsRows[$lw] ?? 0,
        ];
    }
}
$weeklyStatsJson = json_encode($weeklyStats);

// ── Analytics charts data ─────────────────────────────────────────────────────
$chartData = [];
if (isAdmin()) {
    // 1. Visits per day — last 14 days (line chart)
    $v14Stmt = $pdo->query("
        SELECT DATE(visit_date) AS d, COUNT(*) AS cnt
        FROM `schedule`
        WHERE visit_date >= CURDATE() - INTERVAL 13 DAY
          AND visit_date <= CURDATE()
        GROUP BY DATE(visit_date)
        ORDER BY d ASC
    ");
    $v14Rows = [];
    foreach ($v14Stmt->fetchAll() as $r) $v14Rows[$r['d']] = (int)$r['cnt'];
    $visitsPerDay = [];
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $visitsPerDay[] = ['label' => date('M j', strtotime($d)), 'count' => $v14Rows[$d] ?? 0];
    }
    $chartData['visitsPerDay'] = $visitsPerDay;

    // 2. Forms by type — last 30 days (doughnut)
    $ftStmt = $pdo->query("
        SELECT form_type, COUNT(*) AS cnt
        FROM form_submissions
        WHERE created_at >= CURDATE() - INTERVAL 30 DAY
        GROUP BY form_type
        ORDER BY cnt DESC
        LIMIT 8
    ");
    $chartData['formsByType'] = $ftStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Visit status breakdown — current month
    $vsStmt = $pdo->query("
        SELECT status, COUNT(*) AS cnt
        FROM `schedule`
        WHERE YEAR(visit_date)  = YEAR(CURDATE())
          AND MONTH(visit_date) = MONTH(CURDATE())
        GROUP BY status
    ");
    $chartData['visitStatus'] = $vsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. MA productivity — completed visits this month
    $maStmt = $pdo->query("
        SELECT s.full_name AS ma_name, COUNT(sc.id) AS completed
        FROM staff s
        LEFT JOIN `schedule` sc ON sc.ma_id = s.id
              AND sc.status = 'completed'
              AND YEAR(sc.visit_date)  = YEAR(CURDATE())
              AND MONTH(sc.visit_date) = MONTH(CURDATE())
        WHERE s.active = 1 AND s.role IN ('ma','admin')
        GROUP BY s.id, s.full_name
        ORDER BY completed DESC
        LIMIT 10
    ");
    $chartData['maProductivity'] = $maStmt->fetchAll(PDO::FETCH_ASSOC);
}
$chartDataJson = json_encode($chartData);

// ── Right-sidebar data ────────────────────────────────────────────────────────

// Activity feed: last 18 audit entries (admin sees all, others see own)
if (isAdmin()) {
    $actStmt = $pdo->query("
        SELECT action, target_type, target_id, target_label, username, user_role, created_at, details
        FROM audit_log
        ORDER BY created_at DESC
        LIMIT 18
    ");
} else {
    $actStmt = $pdo->prepare("
        SELECT action, target_type, target_id, target_label, username, user_role, created_at, details
        FROM audit_log
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 18
    ");
    $actStmt->execute([(int)$_SESSION['user_id']]);
}
$activityFeed = $actStmt->fetchAll();

// Staff online: sessions active in last 15 min — approximate via audit_log recent entries
// We track last_active in session but can't query PHP sessions; use audit_log as proxy —
// show staff who have ANY audit event in the last 15 minutes.
$onlineStmt = $pdo->query("
    SELECT a.user_id, a.username, a.user_role, MAX(a.created_at) AS last_seen
    FROM audit_log a
    WHERE a.user_id IS NOT NULL
      AND a.created_at >= NOW() - INTERVAL 15 MINUTE
    GROUP BY a.user_id, a.username, a.user_role
    ORDER BY last_seen DESC
");
$staffOnline = $onlineStmt->fetchAll();

// Admin notes (graceful if table missing)
$adminNotes = [];
try {
    $adminNotes = $pdo->query("
        SELECT n.id, n.body, n.created_at,
               s.full_name AS author_name
        FROM admin_notes n
        LEFT JOIN staff s ON s.id = n.author_id
        WHERE n.pinned = 1
        ORDER BY n.created_at DESC
        LIMIT 20
    ")->fetchAll();
} catch (PDOException $e) { /* table not yet migrated */ }

// CSRF for admin note API calls
$noteCsrf = csrfToken();

if (isAdmin() || isBilling()) {
    $stmt = $pdo->query("
        SELECT fs.id, fs.form_type, fs.status, fs.created_at,
               CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.id AS patient_id,
               s.full_name AS ma_name
        FROM form_submissions fs
        JOIN patients p ON p.id = fs.patient_id
        LEFT JOIN staff s ON s.id = fs.ma_id
        ORDER BY fs.created_at DESC LIMIT 12
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT fs.id, fs.form_type, fs.status, fs.created_at,
               CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.id AS patient_id,
               s.full_name AS ma_name
        FROM form_submissions fs
        JOIN patients p ON p.id = fs.patient_id
        LEFT JOIN staff s ON s.id = fs.ma_id
        WHERE fs.ma_id = ?
        ORDER BY fs.created_at DESC LIMIT 12
    ");
    $stmt->execute([(int)$_SESSION['user_id']]);
}
$recent = $stmt->fetchAll();

$formMeta = [
    'vital_cs'           => ['label' => 'Visit Consent',           'icon' => 'bi-file-medical',        'bg' => 'bg-red-100',     'text' => 'text-red-700'],
    'new_patient'        => ['label' => 'New Patient Consent',     'icon' => 'bi-person-plus',         'bg' => 'bg-blue-100',    'text' => 'text-blue-600'],
    'abn'                => ['label' => 'ABN (CMS-R-131)',          'icon' => 'bi-file-earmark-ruled',  'bg' => 'bg-amber-100',   'text' => 'text-amber-600'],
    'pf_signup'          => ['label' => 'PF Portal Consent',        'icon' => 'bi-envelope-at',         'bg' => 'bg-cyan-100',    'text' => 'text-cyan-600'],
    'ccm_consent'        => ['label' => 'CCM Consent',              'icon' => 'bi-calendar2-heart',     'bg' => 'bg-emerald-100', 'text' => 'text-emerald-600'],
    'cognitive_wellness' => ['label' => 'Cognitive Wellness Exam',  'icon' => 'bi-brain',               'bg' => 'bg-violet-100',  'text' => 'text-violet-600'],
    'medicare_awv'       => ['label' => 'Medicare AWV',             'icon' => 'bi-clipboard2-pulse',    'bg' => 'bg-sky-100',     'text' => 'text-sky-600'],
    'il_disclosure'      => ['label' => 'IL Disclosure Auth.',       'icon' => 'bi-file-earmark-text',   'bg' => 'bg-slate-100',   'text' => 'text-slate-600'],
];

// ── FAB context: en_route patient first, then first scheduled, then no-patient
$_fabPid  = 0;
$_fabPArr = ['first_name' => '', 'last_name' => '', 'dob' => ''];
foreach ($mySchedule as $_fSv) {
    if ($_fSv['status'] === 'en_route') { $_fabPid = (int)$_fSv['patient_id']; break; }
}
if (!$_fabPid && !empty($mySchedule)) {
    $_fabPid = (int)$mySchedule[0]['patient_id'];
}
if ($_fabPid) {
    try {
        $_fpStmt = $pdo->prepare("SELECT first_name, last_name, dob FROM patients WHERE id = ? LIMIT 1");
        $_fpStmt->execute([$_fabPid]);
        $_fpRow = $_fpStmt->fetch(PDO::FETCH_ASSOC);
        if ($_fpRow) $_fabPArr = $_fpRow;
    } catch (PDOException $e) { /* non-fatal */ }
}
$patient_id = $_fabPid;
$patient    = $_fabPArr;

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="mb-7">
    <h2 class="text-2xl font-extrabold text-slate-800"><?= $greeting ?>, <?= h($firstName) ?> 👋</h2>
    <p class="text-slate-500 text-sm mt-1"><?= date('l, F j, Y') ?></p>
</div>

<div class="flex gap-6 items-start">
<!-- ═══════════════ LEFT / MAIN COLUMN ═══════════════════════════════════ -->
<div class="flex-1 min-w-0">

<!-- ── Today's Route ── TOP of main column ─────────────────────────── -->
<?php if (canAccessClinical()): ?>
<div class="bg-white rounded-2xl shadow-sm mb-7 <?= $scheduleTotalToday > 0 ? 'border-2 border-indigo-200 shadow-indigo-50' : 'border border-slate-100' ?>" style="overflow:visible">

    <!-- Header -->
    <div class="px-5 py-4 flex items-center gap-3 <?= $scheduleTotalToday > 0 ? 'bg-gradient-to-r from-indigo-600 to-violet-600' : 'bg-slate-50 border-b border-slate-100' ?>">
        <!-- Icon -->
        <div class="w-10 h-10 <?= $scheduleTotalToday > 0 ? 'bg-white/20' : 'bg-indigo-100' ?> rounded-xl grid place-items-center shrink-0">
            <i class="bi bi-calendar3 <?= $scheduleTotalToday > 0 ? 'text-white' : 'text-indigo-500' ?> text-lg"></i>
        </div>
        <!-- Title -->
        <div class="flex-1 min-w-0">
            <h3 class="font-extrabold <?= $scheduleTotalToday > 0 ? 'text-white' : 'text-slate-800' ?> text-base leading-tight flex items-center gap-2">
                Today's Route
                <?php if ($scheduleTotalToday): ?>
                <span class="px-2 py-0.5 <?= $scheduleTotalToday > 0 ? 'bg-white/25 text-white' : 'bg-indigo-100 text-indigo-700' ?> text-xs font-bold rounded-full">
                    <?= $scheduleTotalToday ?>
                </span>
                <?php endif; ?>
            </h3>
            <p class="text-xs <?= $scheduleTotalToday > 0 ? 'text-indigo-200' : 'text-slate-400' ?> mt-0.5"><?= date('l, F j') ?></p>
        </div>
        <!-- Full schedule link -->
        <a href="<?= BASE_URL ?>/schedule.php"
           class="text-xs font-semibold whitespace-nowrap transition shrink-0
                  <?= $scheduleTotalToday > 0 ? 'text-indigo-200 hover:text-white' : 'text-indigo-500 hover:text-indigo-700' ?>">
            Full schedule →
        </a>
    </div>

    <!-- Provider filter (MA only) -->
    <?php if (!isAdmin() && !empty($providerList)):
        $pvParts    = $providerFilter !== '' ? explode(' ', $providerFilter) : [];
        $pvInitials = $providerFilter !== '' ? strtoupper(substr($pvParts[0],0,1).(isset($pvParts[1])?substr($pvParts[1],0,1):'')) : '';
        $pvCount    = count($providerList);
    ?>
    <div class="px-4 pt-2 pb-3 border-b border-slate-100" id="pvFilterWrap">

        <!-- Trigger button -->
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Viewing schedule for</p>
        <button type="button" id="pvTriggerBtn" onclick="togglePvDropdown()"
                class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl border-2 transition-all duration-200 group
                       <?= $providerFilter !== ''
                           ? 'bg-indigo-600 border-indigo-600 text-white shadow-lg shadow-indigo-200'
                           : 'bg-white border-slate-200 text-slate-700 hover:border-indigo-400 hover:shadow-sm' ?>">
            <?php if ($providerFilter !== ''): ?>
            <!-- Selected provider avatar -->
            <span class="w-10 h-10 rounded-xl bg-white/25 text-white font-extrabold text-sm grid place-items-center shrink-0 ring-2 ring-white/30">
                <?= h($pvInitials) ?>
            </span>
            <span class="flex-1 text-left">
                <span class="block font-extrabold text-sm leading-tight"><?= h($providerFilter) ?></span>
                <span class="block text-xs text-indigo-200 mt-0.5">Active filter — tap to change</span>
            </span>
            <a href="?filter_provider=" onclick="event.stopPropagation()"
               class="shrink-0 w-8 h-8 rounded-xl bg-white/20 hover:bg-white/35 grid place-items-center transition-colors" title="Clear">
                <i class="bi bi-x-lg text-white text-sm"></i>
            </a>
            <?php else: ?>
            <!-- Default: my visits -->
            <span class="w-10 h-10 rounded-xl bg-indigo-50 group-hover:bg-indigo-100 grid place-items-center shrink-0 transition-colors">
                <i class="bi bi-calendar-check-fill text-indigo-500 text-base"></i>
            </span>
            <span class="flex-1 text-left">
                <span class="block font-extrabold text-sm leading-tight text-slate-800">My Visits</span>
                <span class="block text-xs text-slate-400 mt-0.5"><?= $pvCount ?> provider<?= $pvCount !== 1 ? 's' : '' ?> available</span>
            </span>
            <i class="bi bi-chevron-down text-slate-400 text-sm shrink-0 transition-transform duration-200" id="pvChevron"></i>
            <?php endif; ?>
        </button>

        <!-- Dropdown panel — fixed-position, escapes card overflow -->
        <div id="pvDropdown"
             class="z-[999] bg-white rounded-2xl border border-slate-200 overflow-hidden"
             style="position:fixed;display:none;opacity:0;transform:translateY(-6px);
                    transition:opacity .15s ease,transform .15s ease;
                    box-shadow:0 24px 64px -8px rgba(0,0,0,.22),0 4px 16px -4px rgba(0,0,0,.12);
                    width:340px;max-width:calc(100vw - 32px)">

            <!-- Panel header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-slate-50">
                <div class="flex items-center gap-2">
                    <i class="bi bi-people-fill text-indigo-500 text-sm"></i>
                    <span class="text-sm font-bold text-slate-700">Select Provider</span>
                    <span class="px-2 py-0.5 bg-indigo-100 text-indigo-600 text-xs font-bold rounded-full"><?= $pvCount ?></span>
                </div>
                <button type="button" onclick="closePvDropdown()"
                        class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 grid place-items-center transition-colors">
                    <i class="bi bi-x text-slate-500 text-base"></i>
                </button>
            </div>

            <!-- Search -->
            <div class="px-3 py-2.5 border-b border-slate-100">
                <div class="relative">
                    <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none"></i>
                    <input type="text" id="pvSearch" placeholder="Search by name…"
                           oninput="filterPvList(this.value)" autocomplete="off"
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200
                                  text-sm text-slate-700 placeholder-slate-400
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent focus:bg-white transition">
                </div>
            </div>

            <!-- Options -->
            <div id="pvOptionsList" style="overflow-y:auto;max-height:min(420px, 60vh)">

                <!-- My Visits row -->
                <a href="?filter_provider=" data-name="my visits"
                   class="pv-option flex items-center gap-3 px-4 py-3.5 transition-colors
                          <?= $providerFilter === '' ? 'bg-indigo-50' : 'hover:bg-slate-50' ?>">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center shrink-0
                                 <?= $providerFilter === '' ? 'bg-indigo-600 shadow-md shadow-indigo-300' : 'bg-slate-100' ?>">
                        <i class="bi bi-calendar-check-fill text-base <?= $providerFilter === '' ? 'text-white' : 'text-slate-500' ?>"></i>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="font-bold text-sm <?= $providerFilter === '' ? 'text-indigo-700' : 'text-slate-800' ?>">My Visits</div>
                        <div class="text-xs <?= $providerFilter === '' ? 'text-indigo-400' : 'text-slate-400' ?> mt-0.5">Only my assigned patients</div>
                    </div>
                    <?php if ($providerFilter === ''): ?>
                    <span class="shrink-0 w-6 h-6 bg-indigo-600 rounded-full grid place-items-center">
                        <i class="bi bi-check2 text-white text-xs"></i>
                    </span>
                    <?php endif; ?>
                </a>

                <!-- Divider -->
                <div class="flex items-center gap-2 px-4 py-1.5">
                    <div class="flex-1 h-px bg-slate-100"></div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Providers</span>
                    <div class="flex-1 h-px bg-slate-100"></div>
                </div>

                <?php foreach ($providerList as $pv):
                    $isSel  = ($providerFilter === $pv);
                    $p2     = explode(' ', $pv);
                    $ini2   = strtoupper(substr($p2[0],0,1).(isset($p2[1])?substr($p2[1],0,1):''));
                    // Pick a consistent color per initials
                    $hues   = ['bg-violet-100 text-violet-700','bg-pink-100 text-pink-700','bg-sky-100 text-sky-700','bg-teal-100 text-teal-700','bg-amber-100 text-amber-700','bg-rose-100 text-rose-700'];
                    $hue    = $isSel ? '' : $hues[abs(crc32($pv)) % count($hues)];
                ?>
                <a href="?filter_provider=<?= urlencode($pv) ?>"
                   data-name="<?= strtolower(h($pv)) ?>"
                   class="pv-option flex items-center gap-3 px-4 py-3 transition-colors
                          <?= $isSel ? 'bg-indigo-50' : 'hover:bg-slate-50' ?>">
                    <span class="w-11 h-11 rounded-2xl font-extrabold text-sm grid place-items-center shrink-0
                                 <?= $isSel ? 'bg-indigo-600 text-white shadow-md shadow-indigo-300' : $hue ?>">
                        <?= h($ini2) ?>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm <?= $isSel ? 'text-indigo-700' : 'text-slate-800' ?> truncate"><?= h($pv) ?></div>
                        <div class="text-xs text-slate-400 mt-0.5">Provider</div>
                    </div>
                    <?php if ($isSel): ?>
                    <span class="shrink-0 w-6 h-6 bg-indigo-600 rounded-full grid place-items-center">
                        <i class="bi bi-check2 text-white text-xs"></i>
                    </span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>

                <!-- No results -->
                <div id="pvNoResults" class="hidden px-4 py-8 text-center">
                    <i class="bi bi-search text-slate-300 text-3xl block mb-2"></i>
                    <p class="text-sm font-semibold text-slate-500">No providers found</p>
                    <p class="text-xs text-slate-400 mt-0.5">Try a different name</p>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var _open = false;
        function openPvDropdown() {
            var dd  = document.getElementById('pvDropdown');
            var btn = document.getElementById('pvTriggerBtn');
            var chv = document.getElementById('pvChevron');
            if (!dd || !btn || _open) return;
            // Position fixed relative to trigger button
            var r = btn.getBoundingClientRect();
            var w = 340;
            var spaceRight = window.innerWidth - r.left;
            var left = spaceRight >= w ? r.left : Math.max(8, r.right - w);
            dd.style.left  = left + 'px';
            dd.style.top   = (r.bottom + 6) + 'px';
            dd.style.width = Math.min(w, window.innerWidth - 16) + 'px';
            _open = true;
            dd.style.display = 'block';
            requestAnimationFrame(function () {
                dd.style.opacity = '1';
                dd.style.transform = 'translateY(0)';
            });
            if (chv) chv.style.transform = 'rotate(180deg)';
            var inp = document.getElementById('pvSearch');
            if (inp) { inp.value = ''; filterPvList(''); setTimeout(function(){ inp.focus(); }, 80); }
        }
        function closePvDropdown() {
            var dd  = document.getElementById('pvDropdown');
            var chv = document.getElementById('pvChevron');
            if (!dd || !_open) return;
            _open = false;
            dd.style.opacity = '0';
            dd.style.transform = 'translateY(-6px)';
            setTimeout(function () { if (!_open) dd.style.display = 'none'; }, 150);
            if (chv) chv.style.transform = '';
        }
        function togglePvDropdown() { _open ? closePvDropdown() : openPvDropdown(); }
        function filterPvList(q) {
            q = (q || '').trim().toLowerCase();
            var opts = document.querySelectorAll('#pvOptionsList .pv-option');
            var vis  = 0;
            opts.forEach(function (el) {
                var show = !q || (el.dataset.name || '').includes(q);
                el.style.display = show ? '' : 'none';
                if (show) vis++;
            });
            var nr = document.getElementById('pvNoResults');
            if (nr) nr.classList.toggle('hidden', vis > 0);
        }
        window.togglePvDropdown = togglePvDropdown;
        window.closePvDropdown  = closePvDropdown;
        window.filterPvList     = filterPvList;
        // Teleport dropdown to <body> so it escapes all overflow:hidden ancestors
        (function teleport() {
            var dd = document.getElementById('pvDropdown');
            if (dd) { document.body.appendChild(dd); return; }
            document.addEventListener('DOMContentLoaded', function () {
                var dd2 = document.getElementById('pvDropdown');
                if (dd2) document.body.appendChild(dd2);
            });
        }());
        document.addEventListener('click', function (e) {
            if (_open) {
                var wrap = document.getElementById('pvFilterWrap');
                if (wrap && !wrap.contains(e.target)) closePvDropdown();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && _open) closePvDropdown();
        });
    }());
    </script>
    <?php endif; ?>

    <!-- Visit rows -->
    <?php if (empty($mySchedule)): ?>
    <div class="px-6 py-8 flex flex-col items-center text-center gap-3">
        <?php if ($providerFilter !== ''): ?>
        <!-- No visits for this specific provider -->
        <div class="w-14 h-14 bg-amber-50 rounded-2xl grid place-items-center">
            <i class="bi bi-person-x text-amber-400 text-2xl"></i>
        </div>
        <div>
            <p class="font-bold text-slate-700 text-sm">No visits for <?= h($providerFilter) ?> today</p>
            <p class="text-xs text-slate-400 mt-1">No schedule found for this provider on <?= date('F j') ?>.</p>
        </div>
        <a href="?filter_provider=" class="mt-1 inline-flex items-center gap-1.5 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-xl transition">
            <i class="bi bi-arrow-left text-xs"></i> Back to My Visits
        </a>
        <?php else: ?>
        <!-- MA has no visits at all today -->
        <div class="w-14 h-14 bg-indigo-50 rounded-2xl grid place-items-center">
            <i class="bi bi-calendar2-x text-indigo-300 text-2xl"></i>
        </div>
        <div>
            <p class="font-bold text-slate-700 text-sm">No visits scheduled for today</p>
            <p class="text-xs text-slate-400 mt-1 max-w-xs">
                <?php if (isAdmin()): ?>
                No visits have been assigned yet for <?= date('l, F j') ?>.
                <?php else: ?>
                You don't have any visits assigned for <?= date('l, F j') ?>. Contact your supervisor if this seems incorrect.
                <?php endif; ?>
            </p>
        </div>
        <?php if (isAdmin()): ?>
        <a href="<?= BASE_URL ?>/admin/schedule_manage.php"
           class="mt-1 inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition shadow-sm">
            <i class="bi bi-plus-lg"></i> Assign Visits
        </a>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/schedule.php"
           class="mt-1 inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-xs font-bold rounded-xl transition">
            <i class="bi bi-calendar3"></i> View Full Schedule
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <?php
    $scStatusColors = [
        'pending'   => ['bg'=>'bg-slate-100',   'text'=>'text-slate-600',   'dot'=>'bg-slate-400'],
        'en_route'  => ['bg'=>'bg-blue-100',    'text'=>'text-blue-700',    'dot'=>'bg-blue-500'],
        'completed' => ['bg'=>'bg-emerald-100', 'text'=>'text-emerald-700', 'dot'=>'bg-emerald-500'],
        'missed'    => ['bg'=>'bg-red-100',     'text'=>'text-red-700',     'dot'=>'bg-red-400'],
    ];
    ?>
    <div class="divide-y divide-slate-50">
        <?php foreach ($mySchedule as $idx => $sv):
            $sc = $scStatusColors[$sv['status']] ?? $scStatusColors['pending'];
        ?>
        <div class="flex items-center gap-3 px-4 py-3.5 hover:bg-slate-50 transition-colors">
            <!-- Step number -->
            <div class="w-8 h-8 bg-indigo-50 text-indigo-600 font-bold text-xs rounded-xl grid place-items-center shrink-0 border border-indigo-100">
                <?= $idx + 1 ?>
            </div>
            <!-- Patient info -->
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $sv['patient_id'] ?>"
                       class="font-bold text-slate-800 hover:text-indigo-600 text-sm transition-colors">
                        <?= h($sv['patient_name']) ?>
                    </a>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= $sc['bg'] ?> <?= $sc['text'] ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?>"></span>
                        <?= ucfirst(str_replace('_',' ',$sv['status'])) ?>
                    </span>
                </div>
                <?php if ($sv['patient_address']): ?>
                <div class="text-xs text-slate-400 truncate mt-0.5"><?= h($sv['patient_address']) ?></div>
                <?php endif; ?>
                <?php if (!isAdmin() && !empty($sv['provider_name'])): ?>
                <div class="text-xs text-indigo-500 font-semibold truncate mt-0.5 uppercase tracking-wide" style="font-size:10px"><?= h($sv['provider_name']) ?></div>
                <?php endif; ?>
            </div>
            <!-- Action buttons -->
            <div class="flex flex-col items-end gap-1.5 shrink-0">
                <?php if ($sv['visit_time']): ?>
                <div class="text-xs font-medium text-slate-400"><?= date('g:i A', strtotime($sv['visit_time'])) ?></div>
                <?php endif; ?>
                <?php if ($sv['status'] === 'pending'): ?>
                <button onclick="dashStartVisit(<?= $sv['id'] ?>, <?= $sv['patient_id'] ?>, '<?= h($sv['visit_type'] ?? 'routine') ?>', '<?= h($sv['visit_subtype'] ?? '') ?>', this)"
                        class="flex items-center gap-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white rounded-xl text-xs font-bold shadow-sm transition-all whitespace-nowrap">
                    <i class="bi bi-play-fill"></i> Start
                </button>
                <?php elseif ($sv['status'] === 'en_route'): ?>
                <?php
                    $_dvt  = $sv['visit_type'] ?? 'routine';
                    $_dvst = $sv['visit_subtype'] ?? '';
                    $_dfp  = (str_contains(strtolower($_dvt),'new'))
                        ? '/forms/new_patient_pocket.php'
                        : '/forms/vital_cs.php';
                    $_npParam = (str_contains(strtolower($_dvt),'new')) ? '&np_type=' . ($_dvst === 'primary_care' ? 'primary_care' : 'wound_care') : '';
                ?>
                <a href="<?= BASE_URL . $_dfp ?>?patient_id=<?= $sv['patient_id'] ?>&visit_id=<?= $sv['id'] ?>&sched_visit_type=<?= urlencode($_dvt) ?><?= $_npParam ?>"
                   class="flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all whitespace-nowrap">
                    <i class="bi bi-file-earmark-plus-fill"></i> Open Forms
                </a>
                <button onclick="dashResetVisit(<?= $sv['id'] ?>, this)"
                        class="flex items-center gap-1 px-2.5 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-xl text-xs font-semibold hover:bg-amber-100 active:scale-95 transition-all whitespace-nowrap">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </button>
                <?php elseif ($sv['status'] === 'completed'): ?>
                <span class="flex items-center gap-1 px-2.5 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-semibold">
                    <i class="bi bi-check-circle-fill"></i> Done
                </span>
                <button onclick="dashUndoEndVisit(<?= $sv['id'] ?>, this)"
                        class="flex items-center gap-1 px-2.5 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-xl text-xs font-semibold hover:bg-amber-100 active:scale-95 transition-all whitespace-nowrap">
                    <i class="bi bi-arrow-counterclockwise"></i> Undo End
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($scheduleTotalToday > 6): ?>
    <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 text-center">
        <a href="<?= BASE_URL ?>/schedule.php" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">
            +<?= $scheduleTotalToday - 6 ?> more visit<?= ($scheduleTotalToday-6)!==1?'s':'' ?> — View full schedule
        </a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; // canAccessClinical schedule widget ?>

<!-- ── Today's Schedule Alert Banner ─────────────────────────────────── -->
<?php if (!isBilling()): ?>
<a href="<?= BASE_URL ?>/schedule.php" class="block mb-7 group">
<?php if ($scheduleTotalToday > 0): ?>
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 shadow-lg shadow-indigo-200 px-6 py-5 flex items-center gap-5">
        <span class="absolute -left-4 -top-4 w-24 h-24 bg-white/10 rounded-full"></span>
        <span class="absolute right-8 -bottom-6 w-32 h-32 bg-white/5 rounded-full"></span>
        <div class="relative z-10 w-14 h-14 bg-white/20 rounded-2xl grid place-items-center shrink-0">
            <i class="bi bi-calendar2-check-fill text-white text-2xl"></i>
        </div>
        <div class="relative z-10 flex-1 min-w-0">
            <p class="text-white font-extrabold text-lg leading-tight">
                You have <span class="underline decoration-white/60"><?= $scheduleTotalToday ?> visit<?= $scheduleTotalToday !== 1 ? 's' : '' ?></span> scheduled today
            </p>
            <p class="text-indigo-200 text-sm mt-0.5">Tap to view your full route for <?= date('l, F j') ?></p>
        </div>
        <div class="relative z-10 shrink-0">
            <span class="inline-flex items-center gap-1.5 bg-white/20 group-hover:bg-white/30 transition text-white text-sm font-bold px-4 py-2 rounded-xl">
                View Route <i class="bi bi-arrow-right"></i>
            </span>
        </div>
    </div>
<?php else: ?>
    <div class="rounded-2xl border-2 border-slate-200 group-hover:border-indigo-300 bg-white px-6 py-4 flex items-center gap-4 transition-colors shadow-sm">
        <div class="w-12 h-12 bg-indigo-50 group-hover:bg-indigo-100 rounded-xl grid place-items-center shrink-0 transition-colors">
            <i class="bi bi-calendar2-week-fill text-indigo-500 text-xl"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-bold text-slate-700 text-sm">View Today's Schedule</p>
            <p class="text-slate-400 text-xs mt-0.5"><?= date('l, F j') ?> &mdash; no visits assigned yet</p>
        </div>
        <span class="inline-flex items-center gap-1 text-indigo-500 group-hover:text-indigo-700 text-sm font-semibold transition-colors">
            Open <i class="bi bi-arrow-right"></i>
        </span>
    </div>
<?php endif; ?>
</a>
<?php endif; ?>

<div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-7">
    <?php if (canAccessClinical()):
    $stats = [
        ['val' => $totalPatients, 'label' => 'Total Patients',  'icon' => 'bi-people-fill',          'bg' => 'bg-blue-500',    'ring' => 'bg-blue-100',   'txt' => 'text-blue-600',  'alert' => 0,              'alertStyle' => 'rose', 'link' => BASE_URL.'/patients.php'],
        ['val' => $formsToday,    'label' => 'Forms Today',      'icon' => 'bi-file-earmark-check',   'bg' => 'bg-emerald-500', 'ring' => 'bg-emerald-100','txt' => 'text-emerald-600','alert' => 0,              'alertStyle' => 'rose', 'link' => BASE_URL.'/patients.php'],
        ['val' => $draftCount,    'label' => 'Needs Signature',  'icon' => 'bi-pen-fill',             'bg' => 'bg-rose-500',    'ring' => 'bg-rose-100',   'txt' => 'text-rose-600',  'alert' => $draftCount,    'alertStyle' => 'rose', 'link' => '#draft-forms'],
    ];
    else:
    $stats = [
        ['val' => $totalPatients,       'label' => 'Total Patients',        'icon' => 'bi-people-fill',         'bg' => 'bg-blue-500',    'ring' => 'bg-blue-100',   'txt' => 'text-blue-600',  'alert' => 0,                      'alertStyle' => 'rose', 'link' => BASE_URL.'/patients.php'],
        ['val' => $billingSignedForms,  'label' => 'Signed Forms',          'icon' => 'bi-file-earmark-check',  'bg' => 'bg-emerald-500', 'ring' => 'bg-emerald-100','txt' => 'text-emerald-600','alert' => 0,                     'alertStyle' => 'rose', 'link' => ''],
        ['val' => $billingSignedToday,  'label' => 'Signed Today',          'icon' => 'bi-calendar-check-fill', 'bg' => 'bg-indigo-500',  'ring' => 'bg-indigo-100', 'txt' => 'text-indigo-600','alert' => 0,                     'alertStyle' => 'rose', 'link' => ''],
    ];
    endif;
    foreach ($stats as $s):
        $hasAlert = ($s['alert'] ?? 0) > 0;
        $isAmber  = ($s['alertStyle'] ?? 'rose') === 'amber';
        $borderCls = $hasAlert ? ($isAmber ? 'border-amber-200 shadow-amber-50' : 'border-rose-200 shadow-rose-50') : 'border-slate-100';
        $numCls    = $hasAlert ? ($isAmber ? 'text-amber-600' : 'text-rose-600') : 'text-slate-800';
        $pulseCls  = $isAmber ? 'bg-amber-400' : 'bg-rose-500';
        $cardTag   = !empty($s['link']) ? 'a' : 'div';
        $cardAttr  = !empty($s['link']) ? 'href="'.h($s['link']).'"' : '';
    ?>
    <<?= $cardTag ?> <?= $cardAttr ?> class="bg-white rounded-2xl shadow-sm border <?= $borderCls ?> p-5 hover:shadow-md transition-all group<?= !empty($s['link']) ? ' cursor-pointer hover:-translate-y-0.5' : '' ?>">
        <div class="flex items-start justify-between mb-4">
            <div class="<?= $s['ring'] ?> p-3 rounded-xl">
                <i class="bi <?= $s['icon'] ?> <?= $s['txt'] ?> text-xl leading-none"></i>
            </div>
            <?php if ($hasAlert): ?>
            <span class="w-2.5 h-2.5 <?= $pulseCls ?> rounded-full mt-1 animate-pulse"></span>
            <?php endif; ?>
        </div>
        <div class="text-3xl font-extrabold <?= $numCls ?>"><?= number_format((int)$s['val']) ?></div>
        <div class="flex items-center justify-between mt-1">
            <div class="text-sm text-slate-500 font-medium"><?= $s['label'] ?></div>
            <?php if (!empty($s['link'])): ?>
            <i class="bi bi-arrow-right text-xs text-slate-300 group-hover:text-slate-500 group-hover:translate-x-0.5 transition-all"></i>
            <?php endif; ?>
        </div>
    </<?= $cardTag ?>>
    <?php endforeach; ?>
</div>

<!-- ── Offline Pending Forms Widget (populated by offline.js) ──────── -->
<div id="offlineDashWidget" class="hidden bg-amber-50 border-2 border-amber-300 rounded-2xl px-5 py-4 mb-7">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-amber-100 rounded-xl grid place-items-center flex-shrink-0">
                <i class="bi bi-cloud-slash-fill text-amber-600 text-lg"></i>
            </div>
            <div>
                <p class="font-bold text-amber-900 text-sm">
                    <span id="offlineDashCount">0</span> form<span id="offlineDashPlural">s</span> saved offline
                </p>
                <p class="text-xs text-amber-700 mt-0.5">These will sync automatically when you reconnect.</p>
            </div>
        </div>
        <button id="dashSyncBtn"
                class="hidden shrink-0 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold px-4 py-2 rounded-xl transition flex items-center gap-1.5">
            <i class="bi bi-cloud-upload-fill"></i> Sync Now
        </button>
    </div>
</div>

<!-- Quick Actions -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-7">
    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-4">
        <i class="bi bi-lightning-charge-fill text-amber-400 mr-1"></i> Quick Actions
    </h3>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        <?php if (canAccessClinical() && !isMa()): ?>
        <a href="<?= BASE_URL ?>/patient_add.php"
           class="flex flex-col items-center gap-2 p-4 rounded-2xl border-2 border-blue-100 hover:border-blue-400 hover:bg-blue-50 transition-all group">
            <div class="w-12 h-12 bg-blue-100 group-hover:bg-blue-200 rounded-xl grid place-items-center transition-colors">
                <i class="bi bi-person-plus-fill text-blue-600 text-xl"></i>
            </div>
            <span class="text-sm font-semibold text-slate-700">New Patient</span>
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/patients.php"
           class="flex flex-col items-center gap-2 p-4 rounded-2xl border-2 border-slate-100 hover:border-slate-300 hover:bg-slate-50 transition-all group">
            <div class="w-12 h-12 bg-slate-100 group-hover:bg-slate-200 rounded-xl grid place-items-center transition-colors">
                <i class="bi bi-search text-slate-600 text-xl"></i>
            </div>
            <span class="text-sm font-semibold text-slate-700">Find Patient</span>
        </a>
        <a href="<?= BASE_URL ?>/patients.php"
           class="flex flex-col items-center gap-2 p-4 rounded-2xl border-2 border-emerald-100 hover:border-emerald-400 hover:bg-emerald-50 transition-all group">
            <div class="w-12 h-12 bg-emerald-100 group-hover:bg-emerald-200 rounded-xl grid place-items-center transition-colors">
                <i class="bi bi-people-fill text-emerald-600 text-xl"></i>
            </div>
            <span class="text-sm font-semibold text-slate-700">All Patients</span>
        </a>
        <a href="<?= BASE_URL ?>/user_manual.html" target="_blank"
           class="flex flex-col items-center gap-2 p-4 rounded-2xl border-2 border-violet-100 hover:border-violet-400 hover:bg-violet-50 transition-all group">
            <div class="w-12 h-12 bg-violet-100 group-hover:bg-violet-200 rounded-xl grid place-items-center transition-colors">
                <i class="bi bi-book-half text-violet-600 text-xl"></i>
            </div>
            <span class="text-sm font-semibold text-slate-700">User Manual</span>
        </a>
    </div>
</div>

<!-- ─── Unsigned Forms Alert ────────────────────────────────────── -->
<?php if (canAccessClinical()): ?>
<div id="draft-forms">
<?php if ($draftCount === 0): ?>
<div class="flex items-center gap-4 bg-emerald-50 border border-emerald-200 rounded-2xl px-6 py-4 mb-7">
    <div class="w-10 h-10 bg-emerald-100 rounded-xl grid place-items-center flex-shrink-0">
        <i class="bi bi-check-circle-fill text-emerald-600 text-xl"></i>
    </div>
    <div>
        <p class="text-sm font-bold text-emerald-800">All Clear — No Unsigned Forms</p>
        <p class="text-xs text-emerald-600 mt-0.5">Every submitted form has a captured signature. Nothing blocked before billing.</p>
    </div>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border-2 border-rose-200 overflow-hidden mb-7">

    <!-- Header -->
    <div class="flex items-center justify-between gap-3 px-5 py-4 bg-gradient-to-r from-rose-50 to-orange-50 border-b border-rose-100">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-rose-100 rounded-xl grid place-items-center flex-shrink-0">
                <i class="bi bi-pen-fill text-rose-600 text-base"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="font-bold text-slate-800 text-sm">Unsigned Forms</h3>
                    <span class="inline-flex items-center justify-center w-6 h-6 bg-rose-500 text-white text-xs font-extrabold rounded-full animate-pulse"><?= $draftCount ?></span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Draft<?= $draftCount !== 1 ? 's' : '' ?> missing signature — must be signed before billing</p>
            </div>
        </div>
        <?php if ($draftCount > 5): ?>
        <button id="draftToggleAll"
                class="text-xs font-semibold text-rose-600 hover:text-rose-800 bg-rose-100 hover:bg-rose-200 px-3.5 py-2 rounded-xl transition-colors flex-shrink-0">
            Show all <?= $draftCount ?>
        </button>
        <?php endif; ?>
    </div>

    <!-- Drafts List -->
    <div class="divide-y divide-slate-50" id="draftList">
        <?php foreach ($draftForms as $idx => $dr):
            $fm  = $formMeta[$dr['form_type']] ?? ['label' => $dr['form_type'], 'icon' => 'bi-file', 'bg' => 'bg-slate-100', 'text' => 'text-slate-600'];
            [$ageLabel, $ageText, $ageBg] = draftAge($dr['created_at']);
            $hidden = $idx >= 5 ? 'draft-extra hidden' : '';
        ?>
        <div class="draft-row <?= $hidden ?> flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50/80 transition-colors">
            <!-- Form icon -->
            <span class="<?= $fm['bg'] ?> <?= $fm['text'] ?> p-2 rounded-xl flex-shrink-0">
                <i class="bi <?= $fm['icon'] ?> text-sm"></i>
            </span>

            <!-- Patient + form -->
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $dr['patient_id'] ?>"
                       class="font-bold text-slate-800 hover:text-rose-600 text-sm transition-colors">
                        <?= h($dr['patient_name']) ?>
                    </a>
                    <span class="text-xs text-slate-500"><?= $fm['label'] ?></span>
                </div>
                <div class="flex items-center gap-x-3 gap-y-0.5 flex-wrap mt-0.5">
                    <?php if ($dr['ma_name']): ?>
                    <span class="text-xs text-slate-400"><i class="bi bi-person mr-0.5"></i><?= h($dr['ma_name']) ?></span>
                    <?php endif; ?>
                    <span class="text-xs text-slate-400"><?= date('M j, g:ia', strtotime($dr['created_at'])) ?></span>
                </div>
            </div>

            <!-- Age badge -->
            <span class="<?= $ageBg ?> <?= $ageText ?> text-xs font-bold px-2.5 py-1 rounded-full flex-shrink-0">
                <?= $ageLabel ?>
            </span>

            <!-- Action -->
            <a href="<?= BASE_URL ?>/view_document.php?id=<?= $dr['id'] ?>"
               class="inline-flex items-center gap-1.5 text-xs font-bold text-rose-600 hover:text-rose-800
                      bg-rose-50 hover:bg-rose-100 px-3.5 py-2 rounded-xl transition-colors flex-shrink-0">
                <i class="bi bi-pencil-square"></i> Sign
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($draftCount > 5): ?>
    <!-- Collapsed overflow indicator -->
    <div id="draftSeeMore" class="px-5 py-3 bg-slate-50 border-t border-slate-100 text-center">
        <button onclick="document.querySelectorAll('.draft-extra').forEach(el=>el.classList.remove('hidden'));this.closest('#draftSeeMore').classList.add('hidden');document.getElementById('draftToggleAll').classList.add('hidden');"
                class="text-xs font-semibold text-rose-600 hover:text-rose-800">
            +<?= $draftCount - 5 ?> more unsigned form<?= ($draftCount - 5) !== 1 ? 's' : '' ?> — Show all
        </button>
    </div>
    <?php endif; ?>

</div>
<?php endif; ?>
<?php endif; // canAccessClinical unsigned forms ?>
</div><!-- #draft-forms -->

<?php if (isAdmin()): ?>
<!-- ═══════════════ ANALYTICS SECTION ══════════════════════════════════════ -->
<div class="mb-2">
    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider flex items-center gap-2">
        <i class="bi bi-graph-up-arrow text-indigo-500"></i> Analytics
        <span class="text-slate-400 font-normal normal-case tracking-normal">— <?= date('F Y') ?></span>
    </h3>
</div>

<!-- Row 1: Visits trend (wide) + Form types (narrow) -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">

    <!-- Visits per day — last 14 days -->
    <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h4 class="font-bold text-slate-700 flex items-center gap-2 text-sm">
                <i class="bi bi-activity text-emerald-500"></i> Visits Scheduled — Last 14 Days
            </h4>
            <span class="text-xs text-slate-400">Daily total</span>
        </div>
        <div class="px-6 py-5">
            <canvas id="chartVisitsLine" height="100"></canvas>
        </div>
    </div>

    <!-- Forms by type — last 30 days -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h4 class="font-bold text-slate-700 flex items-center gap-2 text-sm">
                <i class="bi bi-pie-chart-fill text-violet-500"></i> Forms by Type
            </h4>
            <p class="text-xs text-slate-400 mt-0.5">Last 30 days</p>
        </div>
        <div class="px-4 py-4 flex flex-col items-center gap-3">
            <canvas id="chartFormsDoughnut" style="max-height:180px;max-width:180px;"></canvas>
            <div id="doughnutLegend" class="w-full space-y-1.5 text-xs"></div>
        </div>
    </div>
</div>

<!-- Row 2: Visit status this month + MA productivity -->
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-7">

    <!-- Visit status breakdown -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h4 class="font-bold text-slate-700 flex items-center gap-2 text-sm">
                <i class="bi bi-bar-chart-steps text-blue-500"></i> Visit Status — This Month
            </h4>
        </div>
        <div class="px-6 py-5">
            <canvas id="chartVisitStatus" height="140"></canvas>
        </div>
    </div>

    <!-- MA productivity -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h4 class="font-bold text-slate-700 flex items-center gap-2 text-sm">
                <i class="bi bi-people-fill text-indigo-500"></i> MA Productivity — This Month
            </h4>
            <p class="text-xs text-slate-400 mt-0.5">Completed visits</p>
        </div>
        <div class="px-6 py-5">
            <canvas id="chartMaProductivity" height="140"></canvas>
        </div>
    </div>
</div>

<!-- Old weekly form activity bar -->
<?php if (!empty($weeklyStats)): ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-7">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h4 class="font-bold text-slate-700 flex items-center gap-2 text-sm">
            <i class="bi bi-bar-chart-fill text-indigo-500"></i> Forms Submitted — This Week vs Last Week
        </h4>
        <div class="flex items-center gap-4 text-xs text-slate-500">
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-indigo-500"></span> This week</span>
            <span class="flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-slate-200"></span> Last week</span>
        </div>
    </div>
    <div class="px-6 py-5">
        <canvas id="weeklyChart" height="110"></canvas>
    </div>
</div>
<?php endif; ?>

<?php endif; // isAdmin analytics ?>

<!-- Recent Forms -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-bold text-slate-700 flex items-center gap-2">
            <i class="bi bi-clock-history text-blue-500"></i> Recent Forms
        </h3>
        <a href="<?= BASE_URL ?>/patients.php" class="text-xs text-blue-600 hover:text-blue-700 font-semibold">View all →</a>
    </div>
    <?php if (empty($recent)): ?>
    <div class="flex flex-col items-center justify-center py-16 text-slate-400">
        <i class="bi bi-file-earmark-x text-5xl mb-3 opacity-30"></i>
        <p class="text-sm">No forms yet — start by adding a patient.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 text-left">
                    <th class="px-6 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Patient</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Form</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide hidden sm:table-cell">MA</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide hidden md:table-cell">Date</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($recent as $row):
                    $fm = $formMeta[$row['form_type']] ?? ['label' => $row['form_type'], 'icon' => 'bi-file', 'bg' => 'bg-slate-100', 'text' => 'text-slate-600'];
                    $statusCfg = ['draft' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-600', 'label' => 'Draft'],
                                  'signed' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'label' => 'Signed'],
                                  'uploaded' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'label' => 'Uploaded']];
                    $sc = $statusCfg[$row['status']] ?? $statusCfg['draft'];
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4">
                        <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $row['patient_id'] ?>"
                           class="font-semibold text-slate-800 hover:text-blue-600 transition-colors">
                            <?= h($row['patient_name']) ?>
                        </a>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex items-center gap-2">
                            <span class="<?= $fm['bg'] ?> <?= $fm['text'] ?> p-1.5 rounded-lg">
                                <i class="bi <?= $fm['icon'] ?> text-sm"></i>
                            </span>
                            <span class="text-slate-600 hidden lg:inline"><?= $fm['label'] ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-slate-500 hidden sm:table-cell"><?= h($row['ma_name'] ?? '—') ?></td>
                    <td class="px-4 py-4 text-slate-500 hidden md:table-cell">
                        <?= date('M j, g:ia', strtotime($row['created_at'])) ?>
                    </td>
                    <td class="px-4 py-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?= $sc['bg'] ?> <?= $sc['text'] ?>">
                            <?= $sc['label'] ?>
                        </span>
                    </td>
                    <td class="px-4 py-4">
                        <a href="<?= BASE_URL ?>/view_document.php?id=<?= $row['id'] ?>"
                           class="text-blue-600 hover:text-blue-800 font-medium text-xs bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors">
                            View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</div><!-- /main column -->

<!-- ═══════════════ RIGHT SIDEBAR ══════════════════════════════════════════ -->
<div class="hidden lg:block w-[300px] shrink-0 space-y-5">

    <?php
    // ── Action icons for audit entries ────────────────────────────────────
    $actIconMap = [
        'login'              => ['bi-box-arrow-in-right', 'bg-emerald-100', 'text-emerald-600'],
        'login_fail'         => ['bi-shield-exclamation', 'bg-red-100',     'text-red-600'],
        'logout'             => ['bi-box-arrow-right',    'bg-slate-100',   'text-slate-500'],
        'patient_view'       => ['bi-person-lines-fill',  'bg-blue-100',    'text-blue-600'],
        'patient_add'        => ['bi-person-plus-fill',   'bg-indigo-100',  'text-indigo-600'],
        'patient_edit'       => ['bi-pencil-square',      'bg-violet-100',  'text-violet-600'],
        'patient_status'     => ['bi-toggle2-on',         'bg-amber-100',   'text-amber-600'],
        'form_create'        => ['bi-file-earmark-plus',  'bg-sky-100',     'text-sky-600'],
        'form_sign'          => ['bi-pen-fill',           'bg-teal-100',    'text-teal-600'],
        'form_upload'        => ['bi-cloud-arrow-up-fill','bg-cyan-100',    'text-cyan-600'],
        'photo_upload'       => ['bi-camera-fill',        'bg-pink-100',    'text-pink-600'],
        'care_note_add'      => ['bi-chat-square-text',   'bg-teal-100',    'text-teal-600'],
        'care_note_delete'   => ['bi-chat-square-x',      'bg-red-100',     'text-red-500'],
        'admin_note_add'     => ['bi-sticky-fill',        'bg-yellow-100',  'text-yellow-600'],
        'admin_note_delete'  => ['bi-trash3',             'bg-slate-100',   'text-slate-500'],
        'soap_save'          => ['bi-file-medical',       'bg-emerald-100', 'text-emerald-600'],
    ];
    function actIcon(string $action, array $map): array {
        if (isset($map[$action])) return $map[$action];
        // fuzzy prefix match
        foreach ($map as $k => $v) {
            if (str_starts_with($action, $k)) return $v;
        }
        return ['bi-activity', 'bg-slate-100', 'text-slate-500'];
    }
    function actLabel(string $action): string {
        static $labels = [
            'login' => 'Signed in', 'login_fail' => 'Failed login',
            'logout' => 'Signed out', 'patient_view' => 'Viewed patient',
            'patient_add' => 'Added patient', 'patient_edit' => 'Edited patient',
            'patient_status' => 'Status change', 'form_create' => 'Form created',
            'form_sign' => 'Form signed', 'form_upload' => 'Form uploaded',
            'photo_upload' => 'Photo uploaded', 'care_note_add' => 'Care note posted',
            'care_note_delete' => 'Care note deleted', 'admin_note_add' => 'Admin note posted',
            'admin_note_delete' => 'Admin note deleted', 'soap_save' => 'SOAP note saved',
        ];
        return $labels[$action] ?? ucwords(str_replace('_', ' ', $action));
    }
    function shortAgo(string $ts): string {
        $d = time() - strtotime($ts);
        if ($d < 60)    return 'just now';
        if ($d < 3600)  return (int)($d/60).'m ago';
        if ($d < 86400) return (int)($d/3600).'h ago';
        return (int)($d/86400).'d ago';
    }
    ?>

    <!-- ── Activity Feed ─────────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-4 py-3.5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-700 text-sm flex items-center gap-2">
                <span class="w-6 h-6 bg-blue-100 rounded-lg grid place-items-center flex-shrink-0">
                    <i class="bi bi-activity text-blue-600 text-xs"></i>
                </span>
                Activity Feed
            </h3>
            <?php if (isAdmin()): ?>
            <span class="text-xs text-slate-400">All staff</span>
            <?php endif; ?>
        </div>
        <?php if (empty($activityFeed)): ?>
        <div class="px-4 py-6 text-center text-slate-400 text-xs">No recent activity</div>
        <?php else: ?>
        <ul class="divide-y divide-slate-50 max-h-[420px] overflow-y-auto">
            <?php foreach ($activityFeed as $ev):
                [$ico, $ibg, $itxt] = actIcon($ev['action'], $actIconMap);
            ?>
            <li class="flex items-start gap-2.5 px-4 py-3 hover:bg-slate-50/60 transition-colors">
                <span class="<?= $ibg ?> <?= $itxt ?> w-7 h-7 rounded-lg grid place-items-center flex-shrink-0 mt-0.5">
                    <i class="bi <?= $ico ?> text-xs"></i>
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-slate-700 leading-tight">
                        <?= actLabel($ev['action']) ?>
                        <?php if ($ev['target_label']): ?>
                        <span class="font-normal text-slate-500">— <?= h(mb_strimwidth($ev['target_label'], 0, 28, '…')) ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="text-[10px] text-slate-400 mt-0.5">
                        <?= h($ev['username'] ?? 'system') ?>
                        <span class="mx-1">·</span>
                        <?= shortAgo($ev['created_at']) ?>
                    </p>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <!-- ── Staff Online ─────────────────────────────────────────────── -->
    <?php if (isAdmin()): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-4 py-3.5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-700 text-sm flex items-center gap-2">
                <span class="w-6 h-6 bg-emerald-100 rounded-lg grid place-items-center flex-shrink-0">
                    <i class="bi bi-circle-fill text-emerald-500 text-[8px]"></i>
                </span>
                Staff Online
            </h3>
            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                last 15 min
            </span>
        </div>
        <?php
        $roleColors = [
            'admin'   => ['bg-indigo-100', 'text-indigo-700'],
            'ma'      => ['bg-blue-100',   'text-blue-700'],
            'billing' => ['bg-amber-100',  'text-amber-700'],
        ];
        ?>
        <?php if (empty($staffOnline)): ?>
        <div class="px-4 py-5 text-center text-slate-400 text-xs">No recent activity</div>
        <?php else: ?>
        <ul class="divide-y divide-slate-50">
            <?php foreach ($staffOnline as $su):
                [$rbg, $rtxt] = $roleColors[$su['user_role']] ?? ['bg-slate-100', 'text-slate-600'];
                $initials = strtoupper(substr($su['username'] ?? '?', 0, 2));
            ?>
            <li class="flex items-center gap-3 px-4 py-3">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-blue-500
                            flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                    <?= h($initials) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-slate-700 truncate"><?= h($su['username']) ?></p>
                    <p class="text-[10px] text-slate-400">last seen <?= shortAgo($su['last_seen']) ?></p>
                </div>
                <span class="<?= $rbg ?> <?= $rtxt ?> text-[10px] font-bold px-2 py-0.5 rounded-full capitalize flex-shrink-0">
                    <?= h($su['user_role']) ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── Pinned Admin Notes ────────────────────────────────────────── -->
    <?php if (isAdmin()): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden" id="adminNotesWidget">
        <div class="px-4 py-3.5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-700 text-sm flex items-center gap-2">
                <span class="w-6 h-6 bg-yellow-100 rounded-lg grid place-items-center flex-shrink-0">
                    <i class="bi bi-sticky-fill text-yellow-500 text-xs"></i>
                </span>
                Team Notes
            </h3>
            <button onclick="document.getElementById('noteCompose').classList.toggle('hidden')"
                    class="w-6 h-6 rounded-lg bg-slate-100 hover:bg-slate-200 grid place-items-center transition-colors">
                <i class="bi bi-plus-lg text-slate-600 text-xs"></i>
            </button>
        </div>

        <!-- Compose -->
        <div id="noteCompose" class="hidden px-4 py-3 border-b border-slate-100 bg-yellow-50/60">
            <textarea id="noteBody" rows="3" maxlength="1000" placeholder="Write a note for the team…"
                      class="w-full text-xs border border-yellow-200 rounded-xl px-3 py-2.5 bg-white
                             focus:outline-none focus:ring-2 focus:ring-yellow-300 resize-none placeholder-slate-400"></textarea>
            <div class="flex items-center justify-between mt-2">
                <span class="text-[10px] text-slate-400"><span id="noteCharCount">0</span>/1000</span>
                <div class="flex gap-2">
                    <button onclick="document.getElementById('noteCompose').classList.add('hidden')"
                            class="text-xs text-slate-500 hover:text-slate-700 px-3 py-1.5 rounded-lg hover:bg-slate-100 transition-colors">
                        Cancel
                    </button>
                    <button id="notePostBtn" onclick="postAdminNote()"
                            class="text-xs font-bold text-white bg-yellow-500 hover:bg-yellow-600 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
                        <i class="bi bi-sticky-fill"></i> Post
                    </button>
                </div>
            </div>
        </div>

        <!-- Notes list -->
        <ul id="notesList" class="divide-y divide-slate-50 max-h-[360px] overflow-y-auto">
            <?php if (empty($adminNotes)): ?>
            <li class="px-4 py-6 text-center text-slate-400 text-xs" id="notesEmpty">
                No notes yet — post the first one above.
            </li>
            <?php else: ?>
            <?php foreach ($adminNotes as $note): ?>
            <li class="note-item group flex items-start gap-2.5 px-4 py-3 hover:bg-yellow-50/40 transition-colors"
                data-id="<?= (int)$note['id'] ?>">
                <i class="bi bi-sticky-fill text-yellow-400 text-xs mt-0.5 flex-shrink-0"></i>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-slate-700 whitespace-pre-wrap break-words"><?= h($note['body']) ?></p>
                    <p class="text-[10px] text-slate-400 mt-1">
                        <?= h($note['author_name'] ?? '—') ?> · <?= shortAgo($note['created_at']) ?>
                    </p>
                </div>
                <button onclick="deleteAdminNote(<?= (int)$note['id'] ?>, this)"
                        class="opacity-0 group-hover:opacity-100 shrink-0 text-slate-300 hover:text-red-500 transition-all mt-0.5">
                    <i class="bi bi-x-lg text-[10px]"></i>
                </button>
            </li>
            <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; // isAdmin admin notes ?>

</div><!-- /sidebar -->
</div><!-- /flex grid -->

<?php
$extraJs = <<<'JS'
<script>
/* ── Dashboard offline widget ─────────────────────────────── */
(function () {
    function updateWidget(count) {
        var widget    = document.getElementById('offlineDashWidget');
        var countEl   = document.getElementById('offlineDashCount');
        var pluralEl  = document.getElementById('offlineDashPlural');
        var syncBtn   = document.getElementById('dashSyncBtn');
        if (!widget) return;
        if (count > 0) {
            widget.classList.remove('hidden');
            if (countEl)  countEl.textContent  = count;
            if (pluralEl) pluralEl.textContent  = count === 1 ? '' : 's';
            if (syncBtn && navigator.onLine) syncBtn.classList.remove('hidden');
        } else {
            widget.classList.add('hidden');
        }
    }

    function getPendingCount() {
        return new Promise(function (resolve) {
            try {
                var req = indexedDB.open('pd-offline', 1);
                req.onsuccess = function (e) {
                    var db = e.target.result;
                    if (!db.objectStoreNames.contains('form_queue')) { resolve(0); return; }
                    var r = db.transaction('form_queue', 'readonly')
                               .objectStore('form_queue').index('status')
                               .count(IDBKeyRange.only('pending'));
                    r.onsuccess = function () { resolve(r.result); };
                    r.onerror   = function () { resolve(0); };
                };
                req.onerror = function () { resolve(0); };
            } catch (_) { resolve(0); }
        });
    }

    /* Wire the dashboard sync button to offlineSyncBtn in the banner */
    var dashBtn = document.getElementById('dashSyncBtn');
    if (dashBtn) {
        dashBtn.addEventListener('click', function () {
            var bannerBtn = document.getElementById('offlineSyncBtn');
            if (bannerBtn) bannerBtn.click();
            else if (navigator.onLine) location.reload();
        });
    }

    /* Update widget on load and whenever online/offline changes */
    getPendingCount().then(updateWidget);
    window.addEventListener('online',  function () { getPendingCount().then(updateWidget); });
    window.addEventListener('offline', function () { getPendingCount().then(updateWidget); });
    /* Re-check after a sync completes (offline.js dispatches this) */
    window.addEventListener('pd:synced', function () { getPendingCount().then(updateWidget); });
})();
</script>
JS;

// Weekly stats chart JS (admin only)
if (isAdmin()):
$extraJs .= '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>';
if (!empty($weeklyStats)):
$weeklyJson = $weeklyStatsJson;
$extraJs .= <<<WEEKJS
<script>
(function () {
    var data = {$weeklyJson};
    var labels   = data.map(function(d){ return d.label; });
    var thisWeek = data.map(function(d){ return d.thisWeek; });
    var lastWeek = data.map(function(d){ return d.lastWeek; });

    var ctx = document.getElementById('weeklyChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'This week',
                    data: thisWeek,
                    backgroundColor: 'rgba(99,102,241,0.85)',
                    borderRadius: 6,
                    borderSkipped: false,
                },
                {
                    label: 'Last week',
                    data: lastWeek,
                    backgroundColor: 'rgba(203,213,225,0.7)',
                    borderRadius: 6,
                    borderSkipped: false,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ' ' + ctx.dataset.label + ': ' + ctx.raw + ' form' + (ctx.raw !== 1 ? 's' : '');
                        }
                    }
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 }, color: '#94a3b8' },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        font: { size: 11 },
                        color: '#94a3b8',
                    },
                    grid: { color: 'rgba(148,163,184,0.12)' },
                },
            },
        },
    });
})();
</script>
WEEKJS;
endif; // end !empty($weeklyStats)
endif; // end isAdmin weekly chart

// Analytics charts JS (admin only)
if (isAdmin()):
$chartJson  = $chartDataJson;
$extraJs .= <<<CHARTJS
<script>
(function () {
    var cd = {$chartJson};
    if (!cd || !window.Chart) return;

    var PALETTE = {
        indigo:  'rgba(99,102,241,',
        emerald: 'rgba(16,185,129,',
        blue:    'rgba(59,130,246,',
        violet:  'rgba(139,92,246,',
        amber:   'rgba(245,158,11,',
        rose:    'rgba(244,63,94,',
        cyan:    'rgba(6,182,212,',
        slate:   'rgba(148,163,184,',
        teal:    'rgba(20,184,166,',
        pink:    'rgba(236,72,153,',
    };
    var PIE_COLORS = Object.values(PALETTE).map(function(c){ return c + '0.85)'; });
    var PIE_HOVER  = Object.values(PALETTE).map(function(c){ return c + '1)'; });

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";

    /* ── 1. Visits per day — line chart ─────────────────────────── */
    (function(){
        var el = document.getElementById('chartVisitsLine');
        if (!el || !cd.visitsPerDay) return;
        var labels = cd.visitsPerDay.map(function(d){ return d.label; });
        var counts = cd.visitsPerDay.map(function(d){ return d.count; });
        new Chart(el, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Visits',
                    data: counts,
                    borderColor: 'rgba(16,185,129,1)',
                    backgroundColor: 'rgba(16,185,129,0.10)',
                    borderWidth: 2.5,
                    pointBackgroundColor: 'rgba(16,185,129,1)',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.38,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(c){ return ' ' + c.raw + ' visit' + (c.raw !== 1 ? 's' : ''); }
                        }
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#94a3b8', maxRotation: 0 } },
                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.12)' } },
                },
            },
        });
    })();

    /* ── 2. Forms by type — doughnut ────────────────────────────── */
    (function(){
        var el = document.getElementById('chartFormsDoughnut');
        if (!el || !cd.formsByType || !cd.formsByType.length) return;

        var typeLabels = {
            'vital_cs':'Visit Consent','new_patient':'New Patient','abn':'ABN',
            'pf_signup':'PF Portal','ccm_consent':'CCM','cognitive_wellness':'Cognitive',
            'medicare_awv':'AWV','il_disclosure':'IL Disclosure'
        };
        var labels = cd.formsByType.map(function(r){ return typeLabels[r.form_type] || r.form_type; });
        var counts = cd.formsByType.map(function(r){ return parseInt(r.cnt); });
        var total  = counts.reduce(function(a,b){ return a+b; }, 0);

        new Chart(el, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: counts,
                    backgroundColor: PIE_COLORS.slice(0, labels.length),
                    hoverBackgroundColor: PIE_HOVER.slice(0, labels.length),
                    borderWidth: 2,
                    borderColor: '#fff',
                }],
            },
            options: {
                responsive: true,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(c){
                                var pct = total > 0 ? Math.round(c.raw / total * 100) : 0;
                                return ' ' + c.raw + ' (' + pct + '%)';
                            }
                        }
                    },
                },
            },
        });

        /* Custom legend */
        var legendEl = document.getElementById('doughnutLegend');
        if (legendEl) {
            labels.forEach(function(lbl, i){
                var pct = total > 0 ? Math.round(counts[i] / total * 100) : 0;
                var row = document.createElement('div');
                row.className = 'flex items-center justify-between gap-2';
                row.innerHTML =
                    '<span class="flex items-center gap-1.5 truncate">' +
                      '<span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:' + PIE_COLORS[i] + '"></span>' +
                      '<span class="text-slate-600 truncate">' + lbl + '</span>' +
                    '</span>' +
                    '<span class="font-bold text-slate-700 flex-shrink-0">' + counts[i] + ' <span class="font-normal text-slate-400">(' + pct + '%)</span></span>';
                legendEl.appendChild(row);
            });
        }
    })();

    /* ── 3. Visit status — horizontal bar ───────────────────────── */
    (function(){
        var el = document.getElementById('chartVisitStatus');
        if (!el || !cd.visitStatus) return;
        var statusCfg = {
            'pending':   { label: 'Pending',   color: 'rgba(148,163,184,0.85)' },
            'en_route':  { label: 'En Route',  color: 'rgba(59,130,246,0.85)'  },
            'completed': { label: 'Completed', color: 'rgba(16,185,129,0.85)'  },
            'missed':    { label: 'Missed',    color: 'rgba(244,63,94,0.85)'   },
        };
        var order  = ['completed','en_route','pending','missed'];
        var labels = [], counts = [], colors = [];
        order.forEach(function(k){
            var row = cd.visitStatus.find(function(r){ return r.status === k; });
            var cfg = statusCfg[k];
            labels.push(cfg.label);
            counts.push(row ? parseInt(row.cnt) : 0);
            colors.push(cfg.color);
        });
        new Chart(el, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Visits',
                    data: counts,
                    backgroundColor: colors,
                    borderRadius: 7,
                    borderSkipped: false,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(c){ return ' ' + c.raw + ' visit' + (c.raw !== 1 ? 's' : ''); }
                        }
                    },
                },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.12)' } },
                    y: { grid: { display: false }, ticks: { font: { size: 11, weight: '600' }, color: '#475569' } },
                },
            },
        });
    })();

    /* ── 4. MA productivity — bar chart ─────────────────────────── */
    (function(){
        var el = document.getElementById('chartMaProductivity');
        if (!el || !cd.maProductivity || !cd.maProductivity.length) return;
        var labels = cd.maProductivity.map(function(r){ return r.ma_name.split(' ')[0]; });
        var counts = cd.maProductivity.map(function(r){ return parseInt(r.completed); });
        var maxVal = Math.max.apply(null, counts);
        var bgColors = counts.map(function(v){
            return v === maxVal && maxVal > 0 ? 'rgba(99,102,241,0.85)' : 'rgba(99,102,241,0.40)';
        });
        new Chart(el, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Completed',
                    data: counts,
                    backgroundColor: bgColors,
                    borderRadius: 7,
                    borderSkipped: false,
                }],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(c){ return ' ' + c.raw + ' visit' + (c.raw !== 1 ? 's' : '') + ' completed'; }
                        }
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#94a3b8', maxRotation: 0 } },
                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.12)' } },
                },
            },
        });
    })();

})();
</script>
CHARTJS;
endif;

// Admin Notes JS (only for admins — note: PHP is evaluated before heredoc content, so we append inline)
if (isAdmin()):
$noteCsrfJs = json_encode($noteCsrf);
$baseUrlJs  = json_encode(BASE_URL);
$extraJs .= <<<NOTEJS
<script>
(function () {
    var CSRF    = {$noteCsrfJs};
    var BASE    = {$baseUrlJs};

    /* Char counter */
    var bodyEl = document.getElementById('noteBody');
    if (bodyEl) {
        bodyEl.addEventListener('input', function () {
            var el = document.getElementById('noteCharCount');
            if (el) el.textContent = bodyEl.value.length;
        });
    }

    window.postAdminNote = async function () {
        var body = (bodyEl ? bodyEl.value.trim() : '');
        if (!body) return;
        var btn = document.getElementById('notePostBtn');
        if (btn) { btn.disabled = true; btn.innerHTML = '…'; }
        try {
            var r    = await fetch(BASE + '/api/save_admin_note.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({csrf: CSRF, action: 'create', body: body}),
            });
            var data = await r.json();
            if (!data.ok) { alert(data.error || 'Error'); return; }

            /* Prepend new note to list */
            var list  = document.getElementById('notesList');
            var empty = document.getElementById('notesEmpty');
            if (empty) empty.remove();
            var li = document.createElement('li');
            li.className = 'note-item group flex items-start gap-2.5 px-4 py-3 hover:bg-yellow-50/40 transition-colors';
            li.dataset.id = data.id;
            li.innerHTML =
                '<i class="bi bi-sticky-fill text-yellow-400 text-xs mt-0.5 flex-shrink-0"></i>' +
                '<div class="flex-1 min-w-0">' +
                  '<p class="text-xs text-slate-700 whitespace-pre-wrap break-words">' + escHtml(body) + '</p>' +
                  '<p class="text-[10px] text-slate-400 mt-1">you · just now</p>' +
                '</div>' +
                '<button onclick="deleteAdminNote(' + data.id + ', this)" ' +
                  'class="opacity-0 group-hover:opacity-100 shrink-0 text-slate-300 hover:text-red-500 transition-all mt-0.5">' +
                  '<i class="bi bi-x-lg text-[10px]"></i></button>';
            list.insertBefore(li, list.firstChild);

            /* Reset compose */
            if (bodyEl) bodyEl.value = '';
            var cc = document.getElementById('noteCharCount');
            if (cc) cc.textContent = '0';
            document.getElementById('noteCompose').classList.add('hidden');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-sticky-fill"></i> Post'; }
        }
    };

    window.deleteAdminNote = async function (id, btn) {
        if (!confirm('Delete this note?')) return;
        btn.disabled = true;
        try {
            var r    = await fetch(BASE + '/api/save_admin_note.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({csrf: CSRF, action: 'delete', note_id: id}),
            });
            var data = await r.json();
            if (!data.ok) { alert(data.error || 'Error'); return; }
            var li = document.querySelector('.note-item[data-id="' + id + '"]');
            if (li) li.remove();
            /* If list is now empty, show placeholder */
            var list = document.getElementById('notesList');
            if (list && list.querySelectorAll('.note-item').length === 0) {
                var empty = document.createElement('li');
                empty.id = 'notesEmpty';
                empty.className = 'px-4 py-6 text-center text-slate-400 text-xs';
                empty.textContent = 'No notes yet — post the first one above.';
                list.appendChild(empty);
            }
        } finally { btn.disabled = false; }
    };

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }
})();
</script>
NOTEJS;
endif;
?>
<script>
// ── Dashboard: one-tap Start Visit ───────────────────────────────────────────
function dashStartVisit(visitId, patientId, visitType, visitSubtype, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i>';

    const isNew   = visitType.toLowerCase().includes('new');
    const npType  = (visitSubtype === 'primary_care') ? 'primary_care' : 'wound_care';
    const formPath = isNew ? '/forms/new_patient_pocket.php' : '/forms/vital_cs.php';
    const npParam  = isNew ? '&np_type=' + npType : '';

    fetch(window._pdBase + '/api/schedule_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: window._pdCsrf, id: visitId, action: 'status', status: 'en_route' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            window.location.href = window._pdBase + formPath
                + '?patient_id=' + patientId
                + '&visit_id=' + visitId
                + '&sched_visit_type=' + encodeURIComponent(visitType)
                + npParam;
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-fill"></i> Start';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-fill"></i> Start';
    });
}

function dashResetVisit(visitId, btn) {
    if (!confirm('Reset this visit to Pending and clear the start time?')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i>';
    fetch(window._pdBase + '/api/schedule_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: window._pdCsrf, id: visitId, action: 'reset_visit' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            location.reload();
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Reset';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Reset';
    });
}

function dashUndoEndVisit(visitId, btn) {
    if (!confirm('Undo the End Visit and set this visit back to In Progress?')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i>';
    fetch(window._pdBase + '/api/schedule_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: window._pdCsrf, id: visitId, action: 'undo_end' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            location.reload();
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Undo End';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Undo End';
    });
}
</script>
<?php include __DIR__ . '/includes/wound_photo_panel.php'; ?>
<?php include __DIR__ . '/includes/rx_pad_panel.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
