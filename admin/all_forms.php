<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();
if (!isAdmin() && !isMa() && !isPcc()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}
$_isMaView = isMa() || isPcc();

/* ── Form type labels ─────────────────────────────────────── */
$formLabels = [
    'vital_cs'               => 'Vital CS Consent',
    'new_patient'            => 'New Patient Consent',
    'new_patient_pocket'     => 'New Patient Pocket',
    'new_patient_pocket_pc'  => 'New Patient Pocket (PC)',
    'abn'                    => 'ABN',
    'pf_signup'              => 'PF Portal Signup',
    'ccm_consent'            => 'CCM Consent',
    'wound_care_consent'     => 'Wound Care Consent',
    'informed_consent_wound' => 'Informed Consent – Wound Care',
    'rpm_consent'            => 'RPM Consent',
    'medicare_awv'           => 'Medicare AWV',
    'cognitive_wellness'     => 'Cognitive Wellness',
    'il_disclosure'          => 'IL Disclosure',
];

$statusLabels = [
    'draft'    => ['label' => 'Draft',       'bg' => 'bg-slate-100',   'text' => 'text-slate-600'],
    'signed'   => ['label' => 'Signed',      'bg' => 'bg-blue-100',    'text' => 'text-blue-700'],
    'uploaded' => ['label' => 'Uploaded',    'bg' => 'bg-emerald-100', 'text' => 'text-emerald-700'],
];

/* ── Filters ─────────────────────────────────────────────── */
$fType   = $_GET['type']   ?? '';
$fStatus = $_GET['status'] ?? '';
$fFrom   = $_GET['from']   ?? '';
$fTo     = $_GET['to']     ?? '';
$fMa     = $_GET['ma']     ?? '';
$fQ      = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

/* ── Build WHERE ─────────────────────────────────────────── */
$where  = [];
$params = [];

if ($fType !== '') {
    $where[]  = 'fs.form_type = ?';
    $params[] = $fType;
}
if ($fStatus !== '') {
    $where[]  = 'fs.status = ?';
    $params[] = $fStatus;
}
if ($fFrom !== '') {
    $where[]  = 'DATE(fs.created_at) >= ?';
    $params[] = $fFrom;
}
if ($fTo !== '') {
    $where[]  = 'DATE(fs.created_at) <= ?';
    $params[] = $fTo;
}
if ($fMa !== '') {
    $where[]  = 'fs.ma_id = ?';
    $params[] = (int)$fMa;
}
if ($fQ !== '') {
    $like     = '%' . $fQ . '%';
    $where[]  = '(p.first_name LIKE ? OR p.last_name LIKE ? OR CONCAT(p.first_name," ",p.last_name) LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// MA users: restrict to forms for their assigned patients only
if ($_isMaView) {
    $where[]  = 'p.assigned_ma = ?';
    $params[] = (int)$_SESSION['user_id'];
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ── Count ───────────────────────────────────────────────── */
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM form_submissions fs
    JOIN patients p ON p.id = fs.patient_id
    LEFT JOIN staff s ON s.id = fs.ma_id
    $whereSQL
");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

/* ── CSV Export (before HTML) ────────────────────────────── */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expStmt = $pdo->prepare("
        SELECT fs.id, fs.created_at, fs.form_type, fs.status,
               p.first_name, p.last_name, p.dob,
               s.full_name AS ma_name,
               fs.provider_name,
               CASE WHEN fs.patient_signature IS NOT NULL AND fs.patient_signature != '' THEN 'Yes' ELSE 'No' END AS patient_signed,
               CASE WHEN fs.provider_signature IS NOT NULL AND fs.provider_signature != '' THEN 'Yes' ELSE 'No' END AS provider_signed
        FROM form_submissions fs
        JOIN patients p ON p.id = fs.patient_id
        LEFT JOIN staff s ON s.id = fs.ma_id
        $whereSQL
        ORDER BY fs.created_at DESC, fs.id DESC
    ");
    $expStmt->execute($params);
    $expRows = $expStmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="all_forms_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['ID', 'Date', 'Patient', 'DOB', 'Form Type', 'Status', 'Collected By', 'Provider', 'Patient Signed', 'Provider Signed']);
    foreach ($expRows as $r) {
        fputcsv($fh, [
            $r['id'],
            $r['created_at'],
            $r['first_name'] . ' ' . $r['last_name'],
            $r['dob'],
            $formLabels[$r['form_type']] ?? $r['form_type'],
            $r['status'],
            $r['ma_name'] ?? '',
            $r['provider_name'] ?? '',
            $r['patient_signed'],
            $r['provider_signed'],
        ]);
    }
    fclose($fh);
    exit;
}

/* ── Rows ────────────────────────────────────────────────── */
$rowStmt = $pdo->prepare("
    SELECT fs.id, fs.created_at, fs.form_type, fs.status,
           p.id AS patient_id, p.first_name, p.last_name, p.dob,
           s.full_name AS ma_name,
           fs.provider_name,
           CASE WHEN fs.patient_signature IS NOT NULL AND fs.patient_signature != '' THEN 1 ELSE 0 END AS has_patient_sig,
           CASE WHEN fs.provider_signature IS NOT NULL AND fs.provider_signature != '' THEN 1 ELSE 0 END AS has_provider_sig
    FROM form_submissions fs
    JOIN patients p ON p.id = fs.patient_id
    LEFT JOIN staff s ON s.id = fs.ma_id
    $whereSQL
    ORDER BY fs.created_at DESC, fs.id DESC
    LIMIT $perPage OFFSET $offset
");
$rowStmt->execute($params);
$rows = $rowStmt->fetchAll();

/* ── MA list for filter dropdown ─────────────────────────── */
$maList = $pdo->query("
    SELECT DISTINCT s.id, s.full_name
    FROM staff s
    INNER JOIN form_submissions fs ON fs.ma_id = s.id
    ORDER BY s.full_name
")->fetchAll();

/* ── Form types present in DB ────────────────────────────── */
$typesInDb = $pdo->query("SELECT DISTINCT form_type FROM form_submissions ORDER BY form_type")->fetchAll(PDO::FETCH_COLUMN);

/* ── Pagination helper ───────────────────────────────────── */
function pgUrl(array $params, int $pg): string {
    $p = array_merge($params, ['page' => $pg]);
    unset($p['export']);
    return '?' . http_build_query(array_filter($p, fn($v) => $v !== ''));
}

/* ── Color maps ───────────────────────────────────────────── */
$formTypeColors = [
    'vital_cs'               => ['bg'=>'#eff6ff','color'=>'#2563eb','border'=>'#bfdbfe'],
    'new_patient'            => ['bg'=>'#f0fdf4','color'=>'#16a34a','border'=>'#bbf7d0'],
    'new_patient_pocket'     => ['bg'=>'#f0fdf4','color'=>'#16a34a','border'=>'#bbf7d0'],
    'new_patient_pocket_pc'  => ['bg'=>'#f0fdf4','color'=>'#15803d','border'=>'#bbf7d0'],
    'abn'                    => ['bg'=>'#fef3c7','color'=>'#d97706','border'=>'#fde68a'],
    'pf_signup'              => ['bg'=>'#f5f3ff','color'=>'#7c3aed','border'=>'#ddd6fe'],
    'ccm_consent'            => ['bg'=>'#fdf4ff','color'=>'#a21caf','border'=>'#f0abfc'],
    'wound_care_consent'     => ['bg'=>'#fff7ed','color'=>'#c2410c','border'=>'#fed7aa'],
    'informed_consent_wound' => ['bg'=>'#fff7ed','color'=>'#c2410c','border'=>'#fed7aa'],
    'rpm_consent'            => ['bg'=>'#ecfdf5','color'=>'#047857','border'=>'#a7f3d0'],
    'medicare_awv'           => ['bg'=>'#f0f9ff','color'=>'#0369a1','border'=>'#bae6fd'],
    'cognitive_wellness'     => ['bg'=>'#fff1f2','color'=>'#be123c','border'=>'#fecdd3'],
    'il_disclosure'          => ['bg'=>'#f8fafc','color'=>'#475569','border'=>'#cbd5e1'],
];
$statusIcons = [
    'draft'    => ['icon'=>'bi-pencil-fill',     'bg'=>'#f1f5f9','color'=>'#64748b','border'=>'#e2e8f0'],
    'signed'   => ['icon'=>'bi-pen-fill',         'bg'=>'#eff6ff','color'=>'#2563eb','border'=>'#bfdbfe'],
    'uploaded' => ['icon'=>'bi-cloud-check-fill', 'bg'=>'#f0fdf4','color'=>'#16a34a','border'=>'#bbf7d0'],
];

/* ── Export URL (shared) ──────────────────────────────────── */
$_exportUrl = '?' . http_build_query(array_filter(
    ['type'=>$fType,'status'=>$fStatus,'from'=>$fFrom,'to'=>$fTo,'ma'=>$fMa,'q'=>$fQ],
    fn($v) => $v !== ''
)) . '&export=csv';

/* ── Render: count HTML ───────────────────────────────────── */
ob_start();
if ($totalRows === 0): ?>
No forms found
<?php else: ?>
Showing <?= number_format(($page-1)*$perPage + 1) ?>–<?= number_format(min($page*$perPage, $totalRows)) ?>
of <strong style="color:#334155;font-weight:700;"><?= number_format($totalRows) ?></strong> form<?= $totalRows !== 1 ? 's' : '' ?>
<?php endif;
$_countHtml = ob_get_clean();

/* ── Render: cards HTML ───────────────────────────────────── */
ob_start();
if (empty($rows)): ?>
<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:80px 20px;
            color:#94a3b8;background:#fff;border-radius:16px;border:1px solid #e2e8f0;" class="dark-form-card">
    <i class="bi bi-folder2-open" style="font-size:3rem;margin-bottom:12px;opacity:0.3;"></i>
    <p style="font-weight:600;margin:0;">No forms match your filters</p>
</div>
<?php else:
foreach ($rows as $r):
    $sc      = $statusIcons[$r['status']] ?? $statusIcons['draft'];
    $lbl     = $formLabels[$r['form_type']] ?? $r['form_type'];
    $tc      = $formTypeColors[$r['form_type']] ?? ['bg'=>'#f8fafc','color'=>'#475569','border'=>'#e2e8f0'];
    $dt      = new DateTime($r['created_at']);
    $isToday = $dt->format('Y-m-d') === date('Y-m-d');
    $timeStr = $dt->format('g:i a');
?>
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px 18px;
            display:flex;align-items:center;gap:14px;" class="dark-form-card">

    <!-- Date block -->
    <div style="min-width:54px;text-align:center;flex-shrink:0;">
        <div style="font-size:20px;font-weight:800;line-height:1;color:#1e293b;"><?= $dt->format('j') ?></div>
        <div style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin-top:2px;"><?= $dt->format('M') ?></div>
        <div style="font-size:10px;color:#94a3b8;margin-top:2px;"><?= $timeStr ?></div>
        <?php if ($isToday): ?>
        <div style="font-size:9px;font-weight:800;color:#16a34a;margin-top:3px;text-transform:uppercase;letter-spacing:0.04em;">Today</div>
        <?php endif; ?>
    </div>

    <!-- Divider -->
    <div style="width:1px;align-self:stretch;background:#e2e8f0;flex-shrink:0;"></div>

    <!-- Main content -->
    <div style="flex:1;min-width:0;">
        <!-- Patient + status -->
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;flex-wrap:wrap;">
            <div style="min-width:0;">
                <a href="<?= BASE_URL ?>/patient_view.php?id=<?= (int)$r['patient_id'] ?>"
                   style="font-size:15px;font-weight:700;color:#2563eb;text-decoration:none;
                          white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;max-width:220px;">
                    <?= h($r['first_name'] . ' ' . $r['last_name']) ?>
                </a>
                <?php if ($r['dob']): ?>
                <div style="font-size:11px;color:#94a3b8;margin-top:1px;">DOB: <?= h(date('m/d/Y', strtotime($r['dob']))) ?></div>
                <?php endif; ?>
            </div>
            <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;
                         font-size:11px;font-weight:700;flex-shrink:0;
                         background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;border:1px solid <?= $sc['border'] ?>;">
                <i class="bi <?= $sc['icon'] ?>" style="font-size:10px;"></i><?= ucfirst($r['status']) ?>
            </span>
        </div>

        <!-- Form type pill -->
        <div style="margin-top:8px;">
            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;
                         font-size:11px;font-weight:700;
                         background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>;border:1px solid <?= $tc['border'] ?>;">
                <i class="bi bi-file-earmark-text" style="font-size:10px;"></i><?= h($lbl) ?>
            </span>
        </div>

        <!-- Meta row -->
        <div style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin-top:9px;">
            <div style="display:flex;align-items:center;gap:5px;font-size:12px;color:#64748b;">
                <i class="bi bi-person-fill" style="font-size:11px;color:#94a3b8;"></i>
                <span><?= h($r['ma_name'] ?? 'Unassigned') ?></span>
            </div>
            <?php if ($r['provider_name']): ?>
            <div style="display:flex;align-items:center;gap:5px;font-size:12px;color:#64748b;">
                <i class="bi bi-person-badge-fill" style="font-size:11px;color:#94a3b8;"></i>
                <span><?= h($r['provider_name']) ?></span>
            </div>
            <?php endif; ?>
            <div style="display:flex;align-items:center;gap:5px;">
                <span title="Patient signature"
                      style="display:inline-flex;align-items:center;gap:3px;font-size:11px;font-weight:600;
                             padding:2px 7px;border-radius:12px;
                             <?= $r['has_patient_sig'] ? 'background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;' : 'background:#f8fafc;color:#94a3b8;border:1px solid #e2e8f0;' ?>">
                    <i class="bi bi-pen" style="font-size:9px;"></i> Pt
                </span>
                <span title="Provider signature"
                      style="display:inline-flex;align-items:center;gap:3px;font-size:11px;font-weight:600;
                             padding:2px 7px;border-radius:12px;
                             <?= $r['has_provider_sig'] ? 'background:#f5f3ff;color:#7c3aed;border:1px solid #ddd6fe;' : 'background:#f8fafc;color:#94a3b8;border:1px solid #e2e8f0;' ?>">
                    <i class="bi bi-pen" style="font-size:9px;"></i> MD
                </span>
            </div>
        </div>
    </div>

    <!-- View button -->
    <a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>"
       style="flex-shrink:0;display:inline-flex;align-items:center;gap:6px;padding:9px 16px;
              background:#6366f1;color:#fff;border-radius:12px;font-size:13px;font-weight:700;
              text-decoration:none;white-space:nowrap;">
        <i class="bi bi-eye-fill" style="font-size:12px;"></i> View
    </a>
</div>
<?php endforeach; endif;
$_cardsHtml = ob_get_clean();

/* ── Render: pagination HTML ──────────────────────────────── */
$_pgParams = ['type'=>$fType,'status'=>$fStatus,'from'=>$fFrom,'to'=>$fTo,'ma'=>$fMa,'q'=>$fQ];
ob_start();
if ($totalPages > 1): ?>
<div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:24px;">
    <?php if ($page > 1): ?>
    <a href="<?= pgUrl($_pgParams, $page-1) ?>" data-pg="<?= $page-1 ?>"
       style="padding:8px 18px;background:#fff;border:1px solid #e2e8f0;color:#475569;font-size:13px;
              font-weight:600;border-radius:12px;text-decoration:none;transition:background 0.15s;">
        ← Prev
    </a>
    <?php endif; ?>
    <span style="font-size:13px;color:#94a3b8;padding:0 8px;">
        Page <?= $page ?> of <?= $totalPages ?>
    </span>
    <?php if ($page < $totalPages): ?>
    <a href="<?= pgUrl($_pgParams, $page+1) ?>" data-pg="<?= $page+1 ?>"
       style="padding:8px 18px;background:#fff;border:1px solid #e2e8f0;color:#475569;font-size:13px;
              font-weight:600;border-radius:12px;text-decoration:none;transition:background 0.15s;">
        Next →
    </a>
    <?php endif; ?>
</div>
<?php endif;
$_paginHtml = ob_get_clean();

/* ── Ajax JSON response ───────────────────────────────────── */
if (($_GET['ajax'] ?? '') === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'count'      => $_countHtml,
        'cards'      => $_cardsHtml,
        'pagination' => $_paginHtml,
        'exportUrl'  => $_exportUrl,
        'total'      => $totalRows,
    ]);
    exit;
}

/* ── Full page ────────────────────────────────────────────── */
$pageTitle = 'All Forms';
$activeNav = 'all_forms';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-3">
            <i class="bi bi-folder2-open text-indigo-600"></i> All Forms
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            <?= $_isMaView ? 'Forms for your assigned patients' : 'All submitted forms across all patients' ?>
        </p>
    </div>
    <a id="af-export-btn" href="<?= h($_exportUrl) ?>"
       class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white
              text-sm font-semibold rounded-xl transition-all shadow-sm">
        <i class="bi bi-download"></i> Export CSV
    </a>
</div>

<!-- Filters -->
<form id="afForm" method="get"
      class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700
             shadow-sm px-5 py-4 mb-6 flex flex-wrap gap-3 items-end">
    <!-- Patient search -->
    <div class="flex-1 min-w-[160px]">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Patient</label>
        <input type="text" name="q" value="<?= htmlspecialchars($fQ) ?>"
               placeholder="Search patient name…" autocomplete="off"
               class="w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm
                      bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                      focus:outline-none focus:ring-2 focus:ring-indigo-400">
    </div>
    <!-- Form type -->
    <div class="min-w-[170px]">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Form Type</label>
        <select name="type"
                class="w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm
                       bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                       focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">All Types</option>
            <?php foreach ($typesInDb as $t): ?>
            <option value="<?= h($t) ?>" <?= $fType === $t ? 'selected' : '' ?>>
                <?= h($formLabels[$t] ?? $t) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <!-- Status -->
    <div class="min-w-[130px]">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Status</label>
        <select name="status"
                class="w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm
                       bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                       focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">All Statuses</option>
            <option value="draft"    <?= $fStatus==='draft'    ? 'selected':'' ?>>Draft</option>
            <option value="signed"   <?= $fStatus==='signed'   ? 'selected':'' ?>>Signed</option>
            <option value="uploaded" <?= $fStatus==='uploaded' ? 'selected':'' ?>>Uploaded to PF</option>
        </select>
    </div>
    <!-- Collected by — admin only -->
    <?php if (!$_isMaView): ?>
    <div class="min-w-[160px]">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Collected By</label>
        <select name="ma"
                class="w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm
                       bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                       focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">All Staff</option>
            <?php foreach ($maList as $m): ?>
            <option value="<?= (int)$m['id'] ?>" <?= (int)$fMa === (int)$m['id'] ? 'selected' : '' ?>>
                <?= h($m['full_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <!-- Date from -->
    <div class="min-w-[140px]">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">From</label>
        <input type="date" name="from" value="<?= h($fFrom) ?>"
               class="w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm
                      bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                      focus:outline-none focus:ring-2 focus:ring-indigo-400">
    </div>
    <!-- Date to -->
    <div class="min-w-[140px]">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">To</label>
        <input type="date" name="to" value="<?= h($fTo) ?>"
               class="w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm
                      bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                      focus:outline-none focus:ring-2 focus:ring-indigo-400">
    </div>
    <!-- Buttons -->
    <div class="flex gap-2">
        <button type="submit"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-all">
            <i class="bi bi-funnel-fill mr-1"></i> Filter
        </button>
        <button type="button" id="af-clear-btn"
                class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600
                       text-slate-700 dark:text-slate-200 text-sm font-semibold rounded-xl transition-all">
            Clear
        </button>
    </div>
</form>

<!-- Results count + loading indicator -->
<div class="flex items-center justify-between mb-3 text-sm text-slate-500 dark:text-slate-400">
    <span id="af-count"><?= $_countHtml ?></span>
    <span id="af-spinner" style="display:none;" class="text-indigo-500 text-xs font-semibold flex items-center gap-1.5">
        <svg style="width:14px;height:14px;animation:spin 0.7s linear infinite;" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="40" stroke-dashoffset="10"/>
        </svg>
        Loading…
    </span>
</div>

<!-- Cards -->
<div id="af-results" style="display:flex;flex-direction:column;gap:12px;transition:opacity 0.15s;">
    <?= $_cardsHtml ?>
</div>

<!-- Pagination -->
<div id="af-pagination">
    <?= $_paginHtml ?>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
.dark-form-card { background: #fff; }
.dark .dark-form-card { background: #1e293b !important; border-color: rgba(255,255,255,0.08) !important; }
.dark .dark-form-card a[href*="patient_view"] { color: #60a5fa !important; }
.dark .dark-form-card div[style*="color:#1e293b"] { color: #f1f5f9 !important; }
.dark .dark-form-card div[style*="color:#64748b"] { color: #94a3b8 !important; }
.dark .dark-form-card div[style*="width:1px"] { background: rgba(255,255,255,0.08) !important; }
#af-results.af-loading { opacity: 0.45; pointer-events: none; }
</style>

<script>
(function () {
    const form    = document.getElementById('afForm');
    const results = document.getElementById('af-results');
    const countEl = document.getElementById('af-count');
    const paginEl = document.getElementById('af-pagination');
    const expBtn  = document.getElementById('af-export-btn');
    const spinner = document.getElementById('af-spinner');
    let timer = null;
    let ctrl  = null; // AbortController

    function getQS(pg) {
        const p = new URLSearchParams();
        for (const [k, v] of new FormData(form).entries()) {
            if (v && v.trim()) p.set(k, v.trim());
        }
        if (pg > 1) p.set('page', pg);
        return p;
    }

    async function load(pg = 1) {
        // Cancel any in-flight request
        if (ctrl) ctrl.abort();
        ctrl = new AbortController();

        const qs = getQS(pg);
        results.classList.add('af-loading');
        spinner.style.display = 'flex';

        try {
            const fetchQS = new URLSearchParams(qs);
            fetchQS.set('ajax', '1');
            const res = await fetch('?' + fetchQS.toString(), { signal: ctrl.signal });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const d = await res.json();

            countEl.innerHTML  = d.count;
            results.innerHTML  = d.cards;
            paginEl.innerHTML  = d.pagination;
            if (expBtn) expBtn.href = d.exportUrl;

            // Update browser URL (clean, no ajax param)
            history.pushState({ pg }, '', qs.toString() ? '?' + qs.toString() : location.pathname);

            wirePagination();
        } catch (e) {
            if (e.name !== 'AbortError') {
                countEl.innerHTML = 'Failed to load — <a href="?" style="color:#6366f1;">reload</a>';
            }
        } finally {
            results.classList.remove('af-loading');
            spinner.style.display = 'none';
        }
    }

    function wirePagination() {
        paginEl.querySelectorAll('a[data-pg]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                load(parseInt(a.dataset.pg, 10));
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    }

    // Selects → instant
    form.querySelectorAll('select').forEach(s =>
        s.addEventListener('change', () => { clearTimeout(timer); load(); })
    );

    // Text / date inputs → debounced 450ms
    form.querySelectorAll('input').forEach(i =>
        i.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => load(), 450); })
    );

    // Prevent full-page form submit
    form.addEventListener('submit', e => { e.preventDefault(); clearTimeout(timer); load(); });

    // Clear button
    document.getElementById('af-clear-btn').addEventListener('click', () => {
        form.querySelectorAll('input').forEach(i => i.value = '');
        form.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
        clearTimeout(timer);
        load();
    });

    // Back/forward navigation
    window.addEventListener('popstate', () => {
        const p = new URLSearchParams(location.search);
        form.querySelectorAll('[name]').forEach(el => { el.value = p.get(el.name) || ''; });
        load(+(p.get('page') || 1));
    });

    // Wire pagination on first load
    wirePagination();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
