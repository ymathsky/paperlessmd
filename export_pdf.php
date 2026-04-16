<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();

/* ── Parameters ──────────────────────────────────────────── */
$patientId = (int)($_GET['patient_id'] ?? 0);
$date      = $_GET['date'] ?? '';          // YYYY-MM-DD  — filter to a single visit day
$idsRaw    = $_GET['ids']  ?? '';          // comma-list of form_submission IDs

if (!$patientId) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

/* ── Patient ─────────────────────────────────────────────── */
$pStmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$pStmt->execute([$patientId]);
$patient = $pStmt->fetch();
if (!$patient) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

auditLog($pdo, 'form_export', 'patient', $patientId,
    $patient['first_name'] . ' ' . $patient['last_name'],
    'date=' . $date . ' ids=' . $idsRaw);

/* ── Forms ───────────────────────────────────────────────── */
if ($idsRaw !== '') {
    // Specific IDs — split, sanitise, query
    $ids = array_filter(array_map('intval', explode(',', $idsRaw)));
    if (empty($ids)) { header('Location: ' . BASE_URL . '/patient_view.php?id=' . $patientId); exit; }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $fStmt = $pdo->prepare("
        SELECT fs.*, s.full_name AS ma_name
        FROM form_submissions fs
        LEFT JOIN staff s ON s.id = fs.ma_id
        WHERE fs.id IN ($placeholders) AND fs.patient_id = ?
        ORDER BY fs.created_at ASC
    ");
    $fStmt->execute([...$ids, $patientId]);
} elseif ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $fStmt = $pdo->prepare("
        SELECT fs.*, s.full_name AS ma_name
        FROM form_submissions fs
        LEFT JOIN staff s ON s.id = fs.ma_id
        WHERE fs.patient_id = ? AND DATE(fs.created_at) = ?
        ORDER BY fs.created_at ASC
    ");
    $fStmt->execute([$patientId, $date]);
} else {
    $fStmt = $pdo->prepare("
        SELECT fs.*, s.full_name AS ma_name
        FROM form_submissions fs
        LEFT JOIN staff s ON s.id = fs.ma_id
        WHERE fs.patient_id = ?
        ORDER BY fs.created_at ASC
    ");
    $fStmt->execute([$patientId]);
}

$forms = $fStmt->fetchAll();

if (empty($forms)) {
    header('Location: ' . BASE_URL . '/patient_view.php?id=' . $patientId . '&msg=no_forms');
    exit;
}

/* ── Metadata ────────────────────────────────────────────── */
$patientName = $patient['first_name'] . ' ' . $patient['last_name'];
$exportDate  = $date ?: date('Y-m-d', strtotime($forms[0]['created_at']));
$exportLabel = $date
    ? date('F j, Y', strtotime($date)) . ' Visit'
    : 'All Forms';
$formCount   = count($forms);
$backUrl     = $date
    ? BASE_URL . '/patient_timeline.php?id=' . $patientId
    : BASE_URL . '/patient_view.php?id=' . $patientId;
$pdfFilename = preg_replace('/[^A-Za-z0-9_-]/', '_', $patientName)
             . '_' . $exportDate . '.pdf';

/* ── Form definitions ────────────────────────────────────── */
$formDefs = [
    'vital_cs'           => ['label' => 'Visit Consent',           'color' => '#dc2626'],
    'new_patient'        => ['label' => 'New Patient Consent',     'color' => '#2563eb'],
    'abn'                => ['label' => 'ABN (CMS-R-131)',          'color' => '#d97706'],
    'pf_signup'          => ['label' => 'PF Portal Consent',        'color' => '#0891b2'],
    'ccm_consent'        => ['label' => 'CCM Consent',              'color' => '#059669'],
    'cognitive_wellness' => ['label' => 'Cognitive Wellness Exam',  'color' => '#7c3aed'],
    'medicare_awv'       => ['label' => 'Medicare AWV',             'color' => '#0284c7'],
    'il_disclosure'      => ['label' => 'IL Disclosure Auth.',       'color' => '#475569'],
];
$statusLabel = ['draft' => 'Draft', 'signed' => 'Signed', 'uploaded' => 'Uploaded to PF'];

/* ── Print template helpers (shared across all templates) ── */
if (!function_exists('vd')) {
    function vd(array $d, string $k): string {
        return isset($d[$k]) ? htmlspecialchars((string)$d[$k], ENT_QUOTES, 'UTF-8') : '';
    }
}
if (!function_exists('vdArr')) {
    function vdArr(array $d, string $k): array {
        if (!isset($d[$k])) return [];
        return is_array($d[$k]) ? $d[$k] : array_filter(array_map('trim', explode(',', (string)$d[$k])));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($patientName) ?> — <?= h($exportLabel) ?> — <?= APP_NAME ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','system-ui','sans-serif'] } } } }</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  /* ── Screen/print visibility ──────────────────────────── */
  @media print {
    .no-print   { display: none !important; }
    .page-break { page-break-after: always; break-after: page; }
    body        { background: white !important; }
    .paper-card { box-shadow: none !important; border: none !important; max-width: 100% !important; overflow: visible !important; border-radius: 0 !important; }
  }
  @media screen {
    .page-break { border-bottom: 3px dashed #e2e8f0; margin-bottom: 2.5rem; padding-bottom: 2.5rem; }
    .page-break:last-child { border-bottom: none; }
  }

  /* ── Page setup ────────────────────────────────────────── */
  @page { size: letter; margin: 0.5in; }

  /* ── BWC Paper Form Classes (.bwc-*) ───────────────────── */
  .bwc-form            { max-width: 6.5in; margin: 0 auto; font-family: Arial, sans-serif; font-size: 10pt; color: #000; }
  .bwc-header          { display: flex; align-items: center; gap: 14pt; margin-bottom: 10pt; border-bottom: 1.5pt solid #000; padding-bottom: 8pt; }
  .bwc-header-logo     { width: 60pt; height: 60pt; object-fit: contain; flex-shrink: 0; }
  .bwc-header-text     { flex: 1; }
  .bwc-header p        { margin: 1pt 0; }
  .bwc-practice-name   { font-size: 14pt; font-weight: bold; margin: 0 !important; }
  .bwc-form-title      { font-size: 12pt; font-weight: bold; text-decoration: underline; margin: 8pt 0 4pt !important; }
  .bwc-patient-line,
  .bwc-provider-line,
  .bwc-visit-row,
  .bwc-homebound-row,
  .bwc-row             { margin: 4pt 0; line-height: 1.8; font-size: 10pt; }
  .bwc-fill            { display: inline-block; min-width: 120pt; border-bottom: 1px solid #000; vertical-align: bottom; }
  .bwc-fill-sm         { display: inline-block; min-width: 40pt;  border-bottom: 1px solid #000; vertical-align: bottom; }
  .bwc-vitals-table    { width: 100%; border-collapse: collapse; margin: 6pt 0; }
  .bwc-vitals-table td { border: 1px solid #000; padding: 4pt 6pt; min-height: 28pt; vertical-align: top; width: 25%; font-size: 9.5pt; }
  .bwc-med-table       { width: 100%; border-collapse: collapse; margin: 6pt 0; }
  .bwc-med-table td,
  .bwc-med-table th    { border: 1px solid #000; padding: 3pt 5pt; font-size: 9.5pt; }
  .bwc-med-header th   { background: #f0f0f0; font-weight: bold; text-align: left; }
  .bwc-race-chip       { display: inline; margin-right: 6pt; }
  .bwc-checked         { font-weight: bold; text-decoration: underline; }
  .bwc-sigs            { margin-top: 20pt; }
  .bwc-sig-row         { display: flex; align-items: flex-end; gap: 20pt; margin-bottom: 2pt; }
  .bwc-sig-line        { flex: 1; border-bottom: 1px solid #000; min-height: 32pt; position: relative; }
  .bwc-sig-date        { white-space: nowrap; width: 140pt; border-bottom: 1px solid #000; min-height: 32pt; }
  .bwc-sig-label       { font-size: 9pt; color: #333; margin-bottom: 12pt; }
  .bwc-sig-img         { max-height: 30pt; max-width: 200pt; object-fit: contain; position: absolute; bottom: 2pt; }
  .bwc-section-hdr     { background: #333; color: #fff; font-weight: bold; padding: 3pt 6pt; font-size: 10pt; margin: 6pt 0 4pt; }
  .bwc-cog-table       { width: 100%; border-collapse: collapse; margin-bottom: 6pt; }
  .bwc-cog-table td    { border: 1px solid #ccc; padding: 4pt 6pt; font-size: 9.5pt; vertical-align: top; }
</style>
</head>
<body class="bg-slate-100 font-sans min-h-screen">

<!-- ── Action Bar (no-print) ─────────────────────────────── -->
<div class="no-print sticky top-0 z-50 bg-white border-b border-slate-200 shadow-sm">
    <div class="max-w-4xl mx-auto px-4 py-3 flex flex-wrap items-center gap-3">
        <!-- Back -->
        <a href="<?= $backUrl ?>"
           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-700
                  bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
            <i class="bi bi-arrow-left"></i> Back
        </a>

        <!-- Info -->
        <div class="flex-1 min-w-0">
            <p class="text-sm font-bold text-slate-800 truncate"><?= h($patientName) ?></p>
            <p class="text-xs text-slate-500"><?= h($exportLabel) ?> &mdash; <?= $formCount ?> form<?= $formCount !== 1 ? 's' : '' ?></p>
        </div>

        <!-- Print -->
        <button onclick="window.print()"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-700
                       bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
            <i class="bi bi-printer-fill"></i> Print / Fax
        </button>

        <!-- Download PDF -->
        <button id="downloadBtn"
                class="inline-flex items-center gap-2 px-5 py-2 text-sm font-bold text-white
                       bg-blue-600 hover:bg-blue-700 rounded-xl transition-colors shadow-sm">
            <i class="bi bi-file-earmark-pdf-fill"></i>
            <span id="downloadLabel">Download PDF</span>
        </button>
    </div>
</div>

<!-- ── Document Content ───────────────────────────────────── -->
<div class="max-w-4xl mx-auto px-4 py-8">

    <!-- Cover banner -->
    <div id="exportContent">

    <div class="no-print bg-gradient-to-r from-blue-900 to-blue-700 rounded-2xl p-6 mb-8 text-white">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <p class="text-blue-300 text-xs font-semibold uppercase tracking-widest mb-1"><?= h(APP_NAME) ?></p>
                <h1 class="text-2xl font-extrabold"><?= h($patientName) ?></h1>
                <p class="text-blue-200 mt-1 text-sm"><?= h($exportLabel) ?></p>
            </div>
            <div class="text-right text-sm text-blue-200 space-y-1">
                <p class="font-bold text-white"><?= h(PRACTICE_NAME) ?></p>
                <p><?= h(PRACTICE_ADDRESS) ?></p>
                <p>Ph: <?= h(PRACTICE_PHONE) ?> &nbsp;|&nbsp; Fax: <?= h(PRACTICE_FAX) ?></p>
                <p class="text-blue-300 text-xs mt-2">Generated <?= date('F j, Y \a\t g:i a') ?></p>
            </div>
        </div>

        <!-- Patient meta strip -->
        <div class="mt-5 pt-5 border-t border-blue-600/50 flex flex-wrap gap-x-6 gap-y-2 text-sm">
            <?php if ($patient['dob']): ?>
            <span><span class="text-blue-300">DOB </span><?= date('M j, Y', strtotime($patient['dob'])) ?></span>
            <?php endif; ?>
            <?php if ($patient['phone']): ?>
            <span><span class="text-blue-300">Phone </span><?= h($patient['phone']) ?></span>
            <?php endif; ?>
            <?php if ($patient['insurance']): ?>
            <span><span class="text-blue-300">Insurance </span><?= h($patient['insurance']) ?></span>
            <?php endif; ?>
            <?php if ($patient['address']): ?>
            <span><span class="text-blue-300">Address </span><?= h($patient['address']) ?></span>
            <?php endif; ?>
            <span class="ml-auto">
                <span class="text-blue-300"><?= $formCount ?> form<?= $formCount !== 1 ? 's' : '' ?></span>
            </span>
        </div>
    </div>

    <!-- ── Forms loop ── -->
    <?php foreach ($forms as $idx => $f):
        $fd    = $formDefs[$f['form_type']] ?? ['label' => $f['form_type'], 'color' => '#475569'];
        $data  = json_decode($f['form_data'] ?? '{}', true) ?: [];
        $stLbl = $statusLabel[$f['status']] ?? 'Draft';
        $stBg  = ['draft' => 'bg-slate-100 text-slate-600', 'signed' => 'bg-blue-100 text-blue-700', 'uploaded' => 'bg-emerald-100 text-emerald-700'][$f['status']] ?? 'bg-slate-100 text-slate-600';
        $isLast = $idx === count($forms) - 1;
        $tplFile = __DIR__ . '/includes/print_templates/' . preg_replace('/[^a-z0-9_]/', '', $f['form_type']) . '.php';
    ?>

    <!-- Screen-only status / meta strip -->
    <div class="no-print flex flex-wrap items-center justify-between gap-3 mb-4 px-5 py-3
                bg-white rounded-2xl shadow-sm border border-slate-100 max-w-3xl">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                 style="background:<?= $fd['color'] ?>22;">
                <span class="text-xs font-bold" style="color:<?= $fd['color'] ?>;"><?= $idx + 1 ?></span>
            </div>
            <div>
                <p class="font-bold text-slate-800 text-sm"><?= h($fd['label']) ?></p>
                <p class="text-xs text-slate-400">
                    <?= date('M j, Y g:i a', strtotime($f['created_at'])) ?>
                    <?= $f['ma_name'] ? ' &mdash; MA: ' . h($f['ma_name']) : '' ?>
                    &mdash; Form <?= $idx + 1 ?> of <?= $formCount ?>
                </p>
            </div>
        </div>
        <span class="<?= $stBg ?> text-xs font-bold px-3 py-1.5 rounded-full"><?= h($stLbl) ?></span>
    </div>

    <!-- Paper card — matches view_document #printDoc -->
    <div class="paper-card bg-white rounded-2xl shadow-md border border-slate-200 overflow-hidden mb-8 max-w-3xl <?= $isLast ? '' : 'page-break' ?>">
        <div class="px-10 py-8">
            <?php if (file_exists($tplFile)): include $tplFile;
            else: ?>
            <div class="bwc-form" style="font-family:Arial,sans-serif;font-size:11px;">
                <div style="text-align:center;margin-bottom:16px;">
                    <p style="font-size:18px;font-weight:bold;margin:0;">Beyond Wound Care Inc.</p>
                    <p style="margin:2px 0;">1340 Remington RD, Ste P &nbsp; Schaumburg, IL 60173</p>
                    <p style="margin:2px 0;">Phone: 847.873.8693 &nbsp;&nbsp; Fax: 847.873.8486</p>
                </div>
                <?php foreach ($data as $key => $value):
                    if (in_array($key, ['csrf_token','patient_id','form_type'], true)) continue;
                    if ($value === '' || $value === null) continue;
                    if (is_array($value) && empty($value)) continue;
                    $lbl = ucwords(str_replace('_', ' ', $key));
                ?>
                <?php if (is_array($value)): ?>
                <div class="border-b border-slate-100 pb-3 mb-3">
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1"><?= h($lbl) ?></div>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($value as $v): ?>
                        <span class="inline-flex items-center px-2.5 py-1 bg-blue-50 text-blue-700 text-xs font-medium rounded-lg"><?= h((string)$v) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php elseif ($value === '1' || $value === 1): ?>
                <div class="flex items-center gap-2 text-sm bg-emerald-50 px-4 py-2.5 rounded-xl mb-2">
                    <i class="bi bi-check-circle-fill text-emerald-500"></i>
                    <span class="font-medium text-slate-700"><?= h($lbl) ?></span>
                </div>
                <?php else: ?>
                <div class="border-b border-slate-100 pb-3 mb-3">
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1"><?= h($lbl) ?></div>
                    <div class="text-sm text-slate-800 font-medium"><?= nl2br(h((string)$value)) ?></div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Screen-only: signed electronic notice -->
        <?php if ($f['patient_signature']): ?>
        <div class="no-print px-10 py-3 bg-emerald-50 border-t border-emerald-100 flex items-center gap-2">
            <i class="bi bi-shield-check text-emerald-600"></i>
            <span class="text-xs text-emerald-700 font-semibold">
                Signed electronically on <?= date('F j, Y \a\t g:i a', strtotime($f['created_at'])) ?>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <?php endforeach; ?>

    <!-- Footer stamp -->
    <div class="text-center text-xs text-slate-400 pb-6 no-print">
        <?= h(APP_NAME) ?> &mdash; <?= h(PRACTICE_NAME) ?> &mdash; <?= h(PRACTICE_ADDRESS) ?>
        &mdash; Generated <?= date('F j, Y g:i a') ?>
    </div>

    </div><!-- /#exportContent -->
</div>

<!-- html2pdf.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
(function () {
    var btn   = document.getElementById('downloadBtn');
    var label = document.getElementById('downloadLabel');

    btn.addEventListener('click', function () {
        btn.disabled = true;
        label.textContent = 'Generating…';

        var opt = {
            margin:       [10, 10, 10, 10],
            filename:     '<?= addslashes($pdfFilename) ?>',
            image:        { type: 'jpeg', quality: 0.96 },
            html2canvas:  { scale: 2, useCORS: true, logging: false },
            jsPDF:        { unit: 'mm', format: 'letter', orientation: 'portrait' },
            pagebreak:    { mode: ['avoid-all', 'css'], before: '.page-break' },
        };

        html2pdf()
            .set(opt)
            .from(document.getElementById('exportContent'))
            .save()
            .then(function () {
                btn.disabled  = false;
                label.textContent = 'Download PDF';
            });
    });
})();
</script>
</body>
</html>
