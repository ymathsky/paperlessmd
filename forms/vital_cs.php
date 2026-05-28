<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireNotBilling();

$patient_id = (int)($_GET['patient_id'] ?? 0);
if (!$patient_id) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

$_schedVtMap = [
    'new_patient'  => 'New',
    'routine'      => 'Follow Up',
    'awv'          => 'Follow Up',
    'wound_care'   => 'Follow Up',
    'ccm'          => 'Follow Up',
    'il'           => 'Follow Up',
    'sick'         => 'Sick',
    'post_hospital'=> 'Post Hospital F/U',
];
$_schedVtSlug = preg_replace('/[^a-z0-9_]/', '', strtolower($_GET['sched_visit_type'] ?? ''));
$_preselVt    = $_schedVtMap[$_schedVtSlug] ?? '';
$pStmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$pStmt->execute([$patient_id]);
$patient = $pStmt->fetch();
if (!$patient) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

// ── Provider list for autocomplete ──────────────────────────────────────
$_providerNames = [];
try {
    $_pnStmt = $pdo->query("SELECT full_name FROM staff WHERE active=1 AND role='provider' ORDER BY full_name");
    $_providerNames = $_pnStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

// Auto-fill provider name from today's schedule
$_schedProvider = '';
try {
    $__sp = $pdo->prepare("SELECT provider_name FROM `schedule` WHERE patient_id = ? AND visit_date = CURDATE() AND COALESCE(provider_name,'') != '' ORDER BY id DESC LIMIT 1");
    $__sp->execute([$patient_id]);
    $_schedProvider = (string)($__sp->fetchColumn() ?: '');
} catch (PDOException $e) {}

// Edit mode: allow re-opening a signed CS form without the one-signature redirect
$_csEditMode = !empty($_GET['edit']);

// One-signature rule: redirect to existing signed form if already signed today
// (bypassed when ?edit=1 is in the URL)
if (!$_csEditMode) {
    $_dupQ = $pdo->prepare("SELECT id FROM form_submissions WHERE patient_id = ? AND form_type = 'vital_cs' AND status IN ('signed','uploaded') AND DATE(created_at) = CURDATE() LIMIT 1");
    $_dupQ->execute([$patient_id]);
    if ($_dupId = $_dupQ->fetchColumn()) { header('Location: ' . BASE_URL . '/view_document.php?id=' . (int)$_dupId . '&already_signed=1'); exit; }
}

// â”€â”€ Pre-fill from most recent Visit Consent submission â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$prevStmt = $pdo->prepare("
    SELECT form_data, created_at
    FROM form_submissions
    WHERE patient_id = ? AND form_type = 'vital_cs'
    ORDER BY created_at DESC
    LIMIT 1
");
$prevStmt->execute([$patient_id]);
$prevRow  = $prevStmt->fetch();
$prev     = $prevRow ? (json_decode($prevRow['form_data'], true) ?? []) : [];
$prevDate = $prevRow ? $prevRow['created_at'] : null;

// Helper: get previous value, html-escaped
function pv(array $prev, string $key): string {
    return isset($prev[$key]) ? htmlspecialchars((string)$prev[$key], ENT_QUOTES, 'UTF-8') : '';
}
// Merge patient record fields as fallback for pre-fill
foreach (['race','insurance_id'] as $_pk) {
    if (empty($prev[$_pk]) && !empty($patient[$_pk])) {
        $prev[$_pk] = $patient[$_pk];
    }
}
// Race was saved as array or comma-list — normalise to array
$prevRace = [];
if (isset($prev['race'])) {
    $prevRace = is_array($prev['race']) ? $prev['race'] : explode(',', $prev['race']);
}

// â”€â”€ Load current active medication list (master list = source of truth) â”€â”€â”€â”€â”€â”€â”€â”€
$activeMeds = [];
try {
    $medsStmt = $pdo->prepare("
        SELECT id, med_name, med_frequency
        FROM patient_medications
        WHERE patient_id = ? AND status = 'active'
        ORDER BY sort_order ASC, added_at ASC
    ");
    $medsStmt->execute([$patient_id]);
    $activeMeds = $medsStmt->fetchAll();
} catch (PDOException $e) { /* table not yet migrated &mdash; fall back to empty */ }

// All active meds pre-filled, then at least 2 empty rows for new entries
$medRows = [];
foreach ($activeMeds as $m) {
    $medRows[] = [
        'med_id'   => $m['id'],
        'med_name' => $m['med_name'],
        'med_freq' => $m['med_frequency'],
        'med_type' => 'Refill',   // existing active meds default to Refill
    ];
}
$emptyTarget = count($activeMeds) + 2;
while (count($medRows) < $emptyTarget) {
    $medRows[] = ['med_id' => 0, 'med_name' => '', 'med_freq' => '', 'med_type' => ''];
}

$pageTitle = 'Visit Consent Form';
$activeNav = 'patients';
$_fcs_company = $patient['company'] ?? 'Beyond Wound Care Inc.';
include __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-slate-400 mb-4 flex-wrap">
    <a href="<?= BASE_URL ?>/patients.php" class="hover:text-blue-500 transition-colors">Patients</a>
    <i class="bi bi-chevron-right text-xs text-slate-300"></i>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patient_id ?>" class="hover:text-blue-500 transition-colors">
        <?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>
    </a>
    <i class="bi bi-chevron-right text-xs text-slate-300"></i>
    <span class="text-slate-600 font-semibold">Visit Consent Form</span>
</nav>

<!-- Patient context bar -->
<div class="flex items-center gap-3 bg-white border border-slate-100 rounded-2xl px-4 py-3 mb-5 shadow-sm">
    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-red-500 to-red-700 flex items-center justify-center text-white font-bold text-sm shadow-sm">
        <?= mb_strtoupper(mb_substr($patient['first_name'], 0, 1) . mb_substr($patient['last_name'], 0, 1)) ?>
    </div>
    <div class="min-w-0">
        <p class="font-bold text-slate-800 text-sm leading-tight truncate"><?= h($patient['first_name'] . ' ' . $patient['last_name']) ?></p>
        <p class="text-xs text-slate-400 truncate"><?= h($patient['company'] ?? '') ?></p>
    </div>
    <div class="ml-auto flex items-center gap-2 text-xs text-slate-400 shrink-0">
        <i class="bi bi-calendar3 text-red-400"></i>
        <span><?= date('M j, Y') ?></span>
    </div>
</div>

<div class="max-w-3xl mx-auto">

<?php if ($_csEditMode): ?>
<div class="flex items-center gap-3 bg-amber-50 border border-amber-300 rounded-2xl px-5 py-3.5 mb-4 text-sm text-amber-800">
    <i class="bi bi-pencil-square text-amber-500 text-lg shrink-0"></i>
    <div class="flex-1">
        <span class="font-semibold">Edit / Re-open mode</span>
        <span class="text-amber-700"> &mdash; You are creating a new Visit Consent entry. All fields are pre-filled from the most recent submission. Update as needed and re-sign.</span>
    </div>
</div>
<?php endif; ?>

<?php if ($prevDate): ?>
<div id="prefillBanner"
     class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-2xl px-5 py-3.5 mb-4 text-sm text-amber-800">
    <i class="bi bi-arrow-counterclockwise text-amber-500 text-lg shrink-0"></i>
    <div class="flex-1">
        <span class="font-semibold">Pre-filled from last visit</span>
        <span class="text-amber-600"> &mdash; <?= date('M j, Y', strtotime($prevDate)) ?></span>
        <span class="text-amber-600 text-xs ml-1">(pharmacy, allergies, medications &amp; vitals carried over &mdash; update as needed)</span>
    </div>
    <button onclick="document.getElementById('prefillBanner').remove()"
            class="text-amber-400 hover:text-amber-700 transition-colors p-1 rounded-lg hover:bg-amber-100">
        <i class="bi bi-x-lg text-sm"></i>
    </button>
</div>
<?php endif; ?>

<!-- Resume draft banner (shown by JS if localStorage draft found) -->
<div id="wiz-resume-banner" class="hidden flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-2xl px-5 py-3.5 mb-4 text-sm text-blue-800">
    <i class="bi bi-floppy-fill text-blue-500 text-lg shrink-0"></i>
    <div class="flex-1"><span class="font-semibold">Unsaved draft found.</span> Resume where you left off?</div>
    <button id="wiz-resume-yes" class="px-4 py-1.5 bg-blue-600 text-white text-xs font-bold rounded-lg hover:bg-blue-700 transition-colors">Resume</button>
    <button id="wiz-resume-no"  class="px-4 py-1.5 bg-white border border-blue-200 text-blue-600 text-xs font-bold rounded-lg hover:bg-blue-50 transition-colors ml-1">Start fresh</button>
</div>

<!-- Card -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <!-- Card Header -->
    <div class="bg-gradient-to-r from-red-700 via-red-600 to-rose-600 px-6 py-5">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <div class="bg-white/20 backdrop-blur-sm p-2.5 rounded-xl shrink-0">
                    <i class="bi bi-file-medical-fill text-white text-xl leading-none"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="text-white font-extrabold text-base leading-tight truncate">
                        <span class="co-name-display"><?= h($_fcs_company) ?></span>
                    </h2>
                    <p class="text-red-200 text-sm mt-0.5">Visit Consent Form</p>
                </div>
            </div>
            <span class="shrink-0 inline-flex items-center gap-1.5 bg-white/20 text-white text-xs font-bold px-3 py-1.5 rounded-full">
                <i class="bi bi-calendar-check text-sm"></i>
                <?= date('M j, Y') ?>
            </span>
        </div>
    </div>

    <!-- Wizard progress header (built by form-wizard.js) -->
    <div id="wiz-header"></div>

    <form id="mainForm" method="POST" action="<?= BASE_URL ?>/api/save_form.php" novalidate>
        <input type="hidden" name="csrf_token"  value="<?= csrfToken() ?>">
        <input type="hidden" name="patient_id"  value="<?= $patient_id ?>">
        <input type="hidden" name="form_type"   value="vital_cs">
        <input type="hidden" name="med_count"   value="<?= count($medRows) ?>">
        <?php if ($_csEditMode): ?>
        <input type="hidden" name="edit_override" value="1">
        <?php endif; ?>
        <input type="hidden" id="wiz-form-key"  value="vital_cs_<?= $patient_id ?>">

        <div class="px-6 pb-2">
        <?php include __DIR__ . '/../includes/form_company_selector.php'; ?>

        <!-- Step 1 - Visit Info -->
        <div class="wiz-step space-y-4 py-4"
             data-step="0"
             data-title="Visit Info"
             data-icon="bi-calendar-check">

            <!-- Section: Provider & Date -->
            <div class="wiz-section">
                <div class="wiz-section-hd">
                    <i class="bi bi-person-badge"></i> Provider, Date &amp; Time
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5 uppercase tracking-wide">Provider</label>
                        <input type="text" name="provider_name"
                               required data-label="Provider Name"
                               list="providerNameList"
                               value="<?= h($_schedProvider ?: pv($prev, 'provider_name')) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-white
                                      focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition"
                               placeholder="Attending provider name">
                        <datalist id="providerNameList">
                            <?php foreach ($_providerNames as $_pn): ?>
                            <option value="<?= h($_pn) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5 uppercase tracking-wide">Date of Visit</label>
                            <input type="date" name="form_date" value="<?= date('Y-m-d') ?>"
                                   required data-label="Date of Visit"
                                   class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-white
                                          focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5 uppercase tracking-wide">
                                <i class="bi bi-clock text-red-400 mr-1"></i>Time In
                            </label>
                            <input type="time" name="time_in"
                                   required data-label="Time In"
                                   class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-white
                                          focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Visit Type -->
            <div class="wiz-section">
                <div class="wiz-section-hd">
                    <i class="bi bi-clipboard2-pulse"></i> Visit Type
                </div>
                <?php
                $_vtIcons = ['New'=>'bi-star','Follow Up'=>'bi-arrow-repeat','Sick'=>'bi-thermometer-half','Post Hospital F/U'=>'bi-hospital'];
                ?>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                    <?php foreach (['New','Follow Up','Sick','Post Hospital F/U'] as $vt): ?>
                    <label class="radio-card flex flex-col items-center gap-1.5 py-3 px-2 border-2 border-slate-200 rounded-xl cursor-pointer text-center
                                  has-[:checked]:border-red-500 has-[:checked]:bg-red-50 has-[:checked]:shadow-sm">
                        <input type="radio" name="visit_type" value="<?= $vt ?>"
                               required data-label="Visit Type"
                               <?= $_preselVt === $vt ? 'checked' : '' ?>
                               class="sr-only">
                        <i class="bi <?= $_vtIcons[$vt] ?> vt-icon text-xl"></i>
                        <span class="text-xs font-semibold text-slate-600 leading-tight"><?= $vt ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Section: Homebound Status -->
            <div class="wiz-section">
                <div class="wiz-section-hd">
                    <i class="bi bi-house-heart"></i> Homebound Status
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="radio-card radio-hb-yes flex flex-col items-center gap-2.5 py-5 px-3 border-2 border-slate-200 rounded-2xl cursor-pointer text-center
                                  has-[:checked]:border-green-500 has-[:checked]:bg-green-50 has-[:checked]:shadow-sm">
                        <input type="radio" name="homebound" value="homebound"
                               required data-label="Homebound Status"
                               class="sr-only">
                        <span class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                            <i class="bi bi-house-check-fill text-green-500 text-2xl leading-none"></i>
                        </span>
                        <span class="font-bold text-slate-700 text-sm leading-snug">Patient IS<br>Homebound</span>
                    </label>
                    <label class="radio-card radio-hb-no flex flex-col items-center gap-2.5 py-5 px-3 border-2 border-slate-200 rounded-2xl cursor-pointer text-center
                                  has-[:checked]:border-slate-500 has-[:checked]:bg-slate-100 has-[:checked]:shadow-sm">
                        <input type="radio" name="homebound" value="not_homebound"
                               class="sr-only">
                        <span class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center">
                            <i class="bi bi-house-x-fill text-slate-400 text-2xl leading-none"></i>
                        </span>
                        <span class="font-bold text-slate-700 text-sm leading-snug">Patient IS NOT<br>Homebound</span>
                    </label>
                </div>
            </div>

            <!-- Section: Missed Visit -->
            <div class="wiz-section">
                <div class="wiz-section-hd">
                    <i class="bi bi-exclamation-circle"></i> Missed Visit
                    <span class="ml-auto text-xs font-normal normal-case tracking-normal text-slate-400">optional</span>
                </div>
                <input type="text" name="missed_visit_reason"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-white
                              focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition"
                       placeholder="Leave blank if not a missed visit">
            </div>

        </div><!-- /step 1 -->


        <!-- â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•— -->
        <!-- â•‘  STEP 2 &mdash; Vitals & Clinical      â•‘ -->
        <!-- â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="1"
             data-title="Vitals"
             data-icon="bi-heart-pulse">

            <p class="form-section-title"><i class="bi bi-heart-pulse text-red-500"></i> Vital Signs</p>

            <div class="vitals-quick-grid grid grid-cols-2 sm:grid-cols-4 gap-3">
                <?php
                $vitals = [
                    ['name'=>'bp',      'label'=>'BP',       'placeholder'=>'120/80',      'req'=>true],
                    ['name'=>'pulse',   'label'=>'Pulse',    'placeholder'=>'72 bpm',      'req'=>true],
                    ['name'=>'temp',    'label'=>'Temp',     'placeholder'=>'98.6°F',      'req'=>false],
                    ['name'=>'o2sat',   'label'=>'O2Sat',    'placeholder'=>'98%',         'req'=>true],
                    ['name'=>'glucose', 'label'=>'Glucose',  'placeholder'=>'mg/dL',       'req'=>false],
                    ['name'=>'height',  'label'=>'Height',   'placeholder'=>'in / cm',     'req'=>false],
                    ['name'=>'weight',  'label'=>'Weight',   'placeholder'=>'lbs / kg',    'req'=>false],
                    ['name'=>'resp',    'label'=>'Resp',     'placeholder'=>'breaths/min', 'req'=>false],
                ];
                foreach ($vitals as $v):
                    $prefilled = pv($prev, $v['name']);
                ?>
                <div class="bg-slate-50 border <?= $prefilled ? 'border-amber-300' : 'border-slate-200' ?> rounded-xl p-3">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">
                        <?= $v['label'] ?><?= $v['req'] ? ' <span class="text-red-400">*</span>' : '' ?>
                        <?php if ($prefilled): ?><span class="ml-1 text-amber-400" title="Pre-filled"><i class="bi bi-arrow-counterclockwise"></i></span><?php endif; ?>
                    </label>
                    <input type="text" name="<?= $v['name'] ?>" value="<?= $prefilled ?>"
                           <?= $v['req'] ? 'required data-label="' . $v['label'] . '"' : '' ?>
                           data-voice-numbers-only="1"
                           class="w-full bg-transparent text-sm font-semibold text-slate-800 border-0 border-b border-slate-300 pb-1
                                  focus:outline-none focus:border-red-400 transition"
                           placeholder="<?= $v['placeholder'] ?>">
                    <p class="text-xs text-slate-400 mt-2">Checked or Per patient</p>
                </div>
                <?php endforeach; ?>
            </div>

        </div><!-- /step 2 -->


        <!-- â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•— -->
        <!-- â•‘  STEP 3 &mdash; Medications            â•‘ -->
        <!-- â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="2"
             data-title="Medications"
             data-icon="bi-capsule">

            <p class="form-section-title"><i class="bi bi-bag-heart text-red-500"></i> Allergies</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Assistive Device</label>
                    <input type="text" name="assistive_device" value="<?= pv($prev,'assistive_device') ?>"
                           class="w-full px-4 py-3 border <?= pv($prev,'assistive_device') ? 'border-amber-300' : 'border-slate-200' ?> rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white"
                           placeholder="Cane, walker, wheelchair...">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Allergies</label>
                    <input type="text" name="allergies" id="allergies_vcs" value="<?= pv($prev,'allergies') ?>"
                           class="w-full px-4 py-3 border <?= pv($prev,'allergies') ? 'border-amber-300' : 'border-slate-200' ?> rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white"
                           placeholder="NKDA or list..."
                           oninput="toggleAllergySeverity('allergies_vcs','allergy_severity_vcs')">
                    <select name="allergy_severity" id="allergy_severity_vcs"
                            class="mt-2 w-full px-4 py-2.5 border <?= pv($prev,'allergy_severity') ? 'border-amber-300' : 'border-slate-200' ?> rounded-xl text-sm bg-slate-50
                                   focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition"
                            style="display:<?= (pv($prev,'allergies') && !preg_match('/^(nkda|nka|no known (drug )?allergies?|none|no allergies?)$/i',trim(pv($prev,'allergies')))) ? 'block' : 'none' ?>">
                        <option value="">— Severity —</option>
                        <?php foreach (['Mild','Moderate','Severe','GI','SOD','Hives'] as $sev): ?>
                        <option value="<?= $sev ?>" <?= pv($prev,'allergy_severity') === $sev ? 'selected' : '' ?>><?= $sev ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Race -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Race</label>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (['African American','Caucasian','Latin','Asian','Pacific Islander','Other'] as $race):
                        $raceChecked = in_array($race, $prevRace);
                    ?>
                    <label class="flex items-center gap-2 px-3.5 py-2 border <?= $raceChecked ? 'border-amber-300 bg-amber-50' : 'border-slate-200' ?> rounded-xl cursor-pointer text-sm
                                  hover:border-red-300 hover:bg-red-50/50 transition-colors has-[:checked]:border-red-400 has-[:checked]:bg-red-50">
                        <input type="checkbox" name="race[]" value="<?= $race ?>" <?= $raceChecked ? 'checked' : '' ?>
                               class="w-3.5 h-3.5 text-red-600 border-slate-300 rounded focus:ring-red-400">
                        <span class="text-slate-700"><?= $race ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <p class="form-section-title"><i class="bi bi-capsule text-red-500"></i> Medication List &amp; Reconciliation</p>

            <?php if (!empty($activeMeds)): ?>
            <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patient_id ?>&tab=meds" target="_blank"
               class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700 bg-emerald-50
                      border border-emerald-200 px-3 py-1.5 rounded-full hover:bg-emerald-100 transition-colors mb-2">
                <i class="bi bi-arrow-counterclockwise"></i><?= count($activeMeds) ?> meds from master list &mdash; Manage
            </a>
            <?php endif; ?>

            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 med-rows-thead">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide w-28">New / Refill</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Medication &amp; Dose</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide w-36">Frequency</th>
                            <th class="w-8 no-print"></th>
                        </tr>
                    </thead>
                    <tbody class="med-rows-tbody med-collapsible divide-y divide-slate-100">
                        <?php foreach ($medRows as $mi => $row):
                            $i           = $mi + 1;
                            $isPrefilled = $row['med_id'] > 0;
                        ?>
                        <input type="hidden" name="med_id_<?= $i ?>" value="<?= $row['med_id'] ?>">
                        <tr class="<?= $isPrefilled ? 'bg-emerald-50/30 med-prefilled' : '' ?>">
                            <!-- Collapsed summary (mobile only) -->
                            <td class="med-sum-cell" onclick="this.closest('tr').classList.add('med-row-open')">
                                <div class="flex items-center gap-2">
                                    <span class="med-sum-badge"><?= $row['med_type'] ?: '&mdash;' ?></span>
                                    <span class="med-sum-name flex-1 truncate">
                                        <?= $row['med_name'] ? h($row['med_name']) : '<em class="text-slate-400">Tap to edit&hellip;</em>' ?>
                                    </span>
                                    <i class="bi bi-chevron-down text-slate-400 text-xs shrink-0"></i>
                                </div>
                            </td>
                            <!-- Detail cells (expanded) -->
                            <td class="px-3 py-2 med-detail-td med-td-type" data-label="Type">
                                <select name="med_type_<?= $i ?>"
                                        class="w-full px-2 py-2 border <?= $isPrefilled ? 'border-emerald-200' : 'border-slate-200' ?> rounded-lg text-xs bg-white
                                               focus:outline-none focus:ring-2 focus:ring-red-400"
                                        onchange="(function(sel){var tr=sel.closest('tr');tr&&tr.querySelector('.med-sum-badge')&&(tr.querySelector('.med-sum-badge').textContent=sel.value||'\u2014');})(this)">
                                    <option value="">&mdash;</option>
                                    <?php foreach (['New','Refill','D/C'] as $opt): ?>
                                    <option <?= $row['med_type'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="px-3 py-2 med-detail-td med-td-med" data-label="Medication &amp; Dose">
                                <?php if ($isPrefilled): ?><div class="flex items-center gap-1.5"><?php endif; ?>
                                <?php if ($isPrefilled): ?><i class="bi bi-capsule text-emerald-500 text-xs shrink-0"></i><?php endif; ?>
                                <input type="text" name="med_name_<?= $i ?>" value="<?= h($row['med_name']) ?>"
                                       class="w-full px-3 py-2 border <?= $isPrefilled ? 'border-emerald-200' : 'border-slate-200' ?> rounded-lg text-sm bg-white
                                              focus:outline-none focus:ring-2 focus:ring-red-400"
                                       placeholder="Medication name and dose"
                                       oninput="(function(inp){var tr=inp.closest('tr');var el=tr&&tr.querySelector('.med-sum-name');if(el)el.textContent=inp.value||'';})(this)">
                                <?php if ($isPrefilled): ?></div><?php endif; ?>
                            </td>
                            <td class="px-3 py-2 med-detail-td med-td-freq" data-label="Frequency">
                                <input type="text" name="med_freq_<?= $i ?>" value="<?= h($row['med_freq']) ?>"
                                       class="w-full px-3 py-2 border <?= $isPrefilled ? 'border-emerald-200' : 'border-slate-200' ?> rounded-lg text-sm bg-white
                                              focus:outline-none focus:ring-2 focus:ring-red-400"
                                       placeholder="e.g. BID">
                            </td>
                            <td class="px-2 py-2 no-print med-detail-td med-td-actions">
                                <button type="button" class="med-collapse-btn"
                                        onclick="this.closest('tr').classList.remove('med-row-open')"
                                        title="Minimize">
                                    <i class="bi bi-chevron-up"></i>
                                </button>
                                <?php if (!$isPrefilled): ?>
                                <button type="button" class="med-remove-btn text-slate-300 hover:text-red-500 transition-colors"
                                        title="Remove row"
                                        onclick="(function(btn){var tr=btn.closest('tr');tr&&tr.remove();})(this)">
                                    <i class="bi bi-x-circle text-base"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center gap-4 mt-2">
                <button type="button" id="medAddRow"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-50 hover:bg-red-100
                               border border-red-200 text-red-700 font-semibold text-sm rounded-xl transition-all no-print">
                    <i class="bi bi-plus-circle"></i> Add Row
                </button>
                <p class="text-xs text-slate-400">
                    <i class="bi bi-info-circle mr-0.5 text-emerald-500"></i>
                    Set type to <strong class="text-red-600">D/C</strong> to discontinue &mdash;
                    <strong class="text-emerald-600">New</strong> rows are added to the master list on save.
                </p>
            </div>

            <!-- Handwriting pad (tablet stylus) -->
            <?php
            $hwFieldName   = 'med_handwriting';
            $hwFieldId     = 'medHandwritingData';
            $hwLabel       = 'Handwrite Medications (stylus / draw)';
            $hwPlaceholder = 'Write medication names, doses &amp; frequencies with your stylus or finger&hellip;';
            $hwExisting    = '';
            include __DIR__ . '/../includes/handwriting_pad.php';
            ?>

            <!-- Extra handwriting pads added dynamically -->
            <div id="hwExtraContainer"></div>

            <!-- Add another drawing + PDF annotator row -->
            <div class="flex flex-wrap items-center gap-3 mt-3 no-print">
                <button type="button" id="addMoreHw"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 hover:bg-indigo-100
                               border border-indigo-200 text-indigo-700 font-semibold text-sm rounded-xl transition-all">
                    <i class="bi bi-plus-square"></i> Add Another Drawing
                </button>
                <label class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100
                              border border-red-200 text-red-700 font-semibold text-sm rounded-xl
                              transition-all cursor-pointer no-print">
                    <i class="bi bi-file-earmark-pdf text-red-500"></i> Upload PDF &amp; Annotate
                    <input type="file" id="pdfAnnotFile" accept="application/pdf" class="sr-only">
                </label>
            </div>

            <!-- PDF Annotator Panel -->
            <div id="pdfAnnotPanel" class="hidden mt-3 border-2 border-red-200 rounded-2xl overflow-hidden no-print">
                <!-- Toolbar -->
                <div class="flex flex-wrap items-center gap-3 px-4 py-3 bg-red-50 border-b border-red-200">
                    <span class="text-sm font-semibold text-red-700">
                        <i class="bi bi-file-earmark-pdf mr-1"></i>
                        Page <span id="pdfCurPage">1</span> / <span id="pdfTotPages">?</span>
                    </span>
                    <div class="flex items-center gap-2 ml-auto flex-wrap">
                        <button type="button" class="pdf-pen-btn w-5 h-5 rounded-full bg-slate-800 border-2 border-red-500" data-min="0.8" data-max="1.5" title="Fine pen"></button>
                        <button type="button" class="pdf-pen-btn w-6 h-6 rounded-full bg-slate-800 border-2 border-transparent hover:border-red-400" data-min="1.5" data-max="3" title="Medium pen"></button>
                        <button type="button" class="pdf-pen-btn w-7 h-7 rounded-full bg-slate-800 border-2 border-transparent hover:border-red-400" data-min="3" data-max="6" title="Thick pen"></button>
                        <button type="button" id="pdfAnnotUndo"
                                class="px-2.5 py-1 text-xs bg-white border border-slate-200 text-slate-600 rounded-lg hover:bg-slate-50 transition-colors">
                            <i class="bi bi-arrow-counterclockwise"></i> Undo
                        </button>
                        <button type="button" id="pdfAnnotClear"
                                class="px-2.5 py-1 text-xs bg-white border border-red-200 text-red-500 rounded-lg hover:bg-red-50 transition-colors">
                            <i class="bi bi-eraser"></i> Clear
                        </button>
                    </div>
                </div>
                <!-- Canvas area -->
                <div id="pdfCanvasWrap" class="relative bg-slate-100 overflow-auto" style="max-height:60vh;">
                    <div id="pdfCanvasContainer" class="relative inline-block" style="touch-action:none;cursor:crosshair;">
                        <canvas id="pdfBgCanvas"></canvas>
                        <canvas id="pdfDrawCanvas" style="position:absolute;top:0;left:0;touch-action:none;"></canvas>
                    </div>
                </div>
                <!-- Nav + Save -->
                <div class="flex flex-wrap items-center gap-3 px-4 py-3 bg-slate-50 border-t border-slate-200">
                    <button type="button" id="pdfPrevBtn"
                            class="px-3.5 py-2 bg-white border border-slate-200 text-sm font-semibold text-slate-600 rounded-xl hover:bg-slate-50 transition-colors">
                        <i class="bi bi-chevron-left"></i> Prev
                    </button>
                    <button type="button" id="pdfNextBtn"
                            class="px-3.5 py-2 bg-white border border-slate-200 text-sm font-semibold text-slate-600 rounded-xl hover:bg-slate-50 transition-colors">
                        Next <i class="bi bi-chevron-right"></i>
                    </button>
                    <span id="pdfPageLimitMsg" class="hidden text-xs text-amber-600"><i class="bi bi-info-circle"></i> Only first 4 pages will be saved</span>
                    <button type="button" id="pdfAnnotSave"
                            class="ml-auto px-5 py-2.5 bg-red-600 hover:bg-red-700 active:scale-95 text-white font-bold text-sm rounded-xl shadow-sm transition-all">
                        <i class="bi bi-check2-circle"></i> Save Annotations
                    </button>
                    <button type="button" id="pdfAnnotCancel"
                            class="px-4 py-2 text-slate-400 hover:text-slate-600 text-sm transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
            <!-- Saved page thumbnails -->
            <div id="pdfAnnotThumbs" class="hidden flex-wrap gap-2 mt-2 no-print"></div>
            <!-- Hidden PNG fields (med_handwriting_2 .. _5) written by JS -->
            <div id="pdfAnnotHiddens"></div>
        </div><!-- /step 3 -->


        <!-- â•‘  STEP 4 &mdash; Sign & Submit          â•‘ -->
        <!-- â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="3"
             data-title="Sign"
             data-icon="bi-pen">

            <p class="form-section-title"><i class="bi bi-person-badge text-red-500"></i> Medical Assistant</p>

            <div class="grid grid-cols-2 gap-4 max-w-xs">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Medical Assistant</label>
                    <input type="text" name="ma_name" value="<?= h($_SESSION['full_name'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Time Out</label>
                    <input type="time" name="time_out"
                           required data-label="Time Out"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white">
                </div>
            </div>

            <!-- AI SOAP Note -->
            <div class="flex items-center gap-3 p-3 bg-violet-50 border border-violet-200 rounded-xl no-print">
                <i class="bi bi-stars text-violet-600 text-lg shrink-0"></i>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-violet-800">AI SOAP Note</p>
                    <p class="text-xs text-violet-600">Draft a SOAP note from today's visit data.</p>
                </div>
                <button type="button" id="aiSoapBtn" class="ai-btn shrink-0">
                    <i class="bi bi-file-medical"></i> Draft
                </button>
            </div>

            <!-- F/U in (weeks/days) -->
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">F/U In</label>
                <div class="flex gap-2">
                    <input type="number" name="fu_weeks" min="1"
                           value="<?= pv($prev,'fu_weeks') ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white"
                           placeholder="e.g. 2">
                    <select name="fu_unit"
                            class="px-3 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                   focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white">
                        <option value="weeks" <?= pv($prev,'fu_unit') === 'days' ? '' : 'selected' ?>>Weeks</option>
                        <option value="days"  <?= pv($prev,'fu_unit') === 'days' ? 'selected' : '' ?>>Days</option>
                    </select>
                </div>
            </div>

            <?php include __DIR__ . '/../includes/sig_block.php'; ?>

        </div><!-- /step 4 -->

        <?php
        $accentClass = 'bg-red-700 hover:bg-red-800';
        $cancelUrl   = BASE_URL . '/patient_view.php?id=' . $patient_id;
        $endVisitId  = (int)($_GET['visit_id'] ?? 0);
        include __DIR__ . '/../includes/wiz_nav.php';
        ?>

        </div><!-- /px-6 -->
    </form>
</div><!-- /card -->
<?php
// Pass pre-filled ICD codes to JS
$prevIcdCodes = [];
if (isset($prev['icd10_codes']) && is_array($prev['icd10_codes'])) {
    $prevIcdCodes = array_values($prev['icd10_codes']);
}
$icdPrefillJson = json_encode($prevIcdCodes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$icdApiBase     = BASE_URL;
$icdApiBaseJson = json_encode(BASE_URL);

$extraJs = <<<JSBLOCK
<script>
(function () {
    var BASE     = $icdApiBaseJson;
    var MAX      = 6;
    var selected = []; // array of {code, desc, cat}
    var debTimer = null;

    var searchEl   = document.getElementById('icdSearch');
    var dropdown   = document.getElementById('icdDropdown');
    var chipsEl    = document.getElementById('icdChips');
    var hiddenEl   = document.getElementById('icdHiddenInputs');
    var maxMsg     = document.getElementById('icdMaxMsg');

    if (!searchEl) return; // ICD section removed -- nothing to wire up
    /* â”€â”€ Render chips â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function renderChips() {
        chipsEl.innerHTML    = '';
        hiddenEl.innerHTML   = '';
        maxMsg.classList.toggle('hidden', selected.length < MAX);

        selected.forEach(function (item, idx) {
            // Chip
            var chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1.5 '
                + 'bg-red-50 border border-red-200 text-red-800 text-xs font-semibold rounded-xl';
            chip.innerHTML =
                '<span class="font-mono text-red-600">' + escHtml(item.code) + '</span>'
                + '<span class="text-red-700 max-w-[220px] truncate">' + escHtml(item.desc) + '</span>'
                + '<button type="button" class="ml-0.5 text-red-400 hover:text-red-700 transition" '
                + 'aria-label="Remove" data-idx="' + idx + '">'
                + '<i class="bi bi-x-circle-fill text-sm"></i></button>';
            chipsEl.appendChild(chip);

            // Hidden input &mdash; stored as "CODE &mdash; desc" string in array
            var inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = 'icd10_codes[]';
            inp.value = item.code + ' &mdash; ' + item.desc;
            hiddenEl.appendChild(inp);
        });

        // Remove button listeners
        chipsEl.querySelectorAll('[data-idx]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selected.splice(parseInt(btn.dataset.idx), 1);
                renderChips();
            });
        });
    }

    /* â”€â”€ Add code (guard duplicates + max) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function addCode(item) {
        if (selected.length >= MAX) return;
        if (selected.some(function (s) { return s.code === item.code; })) return;
        selected.push(item);
        renderChips();
        searchEl.value = '';
        closeDropdown();
        searchEl.focus();
    }

    /* â”€â”€ Dropdown â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    function closeDropdown() { dropdown.classList.add('hidden'); }

    /* Position the fixed dropdown below the search input (escapes overflow:hidden) */
    function positionDropdown() {
        var rect = searchEl.getBoundingClientRect();
        var maxH = Math.min(280, window.innerHeight - rect.bottom - 16);
        dropdown.style.top       = (rect.bottom + 4) + 'px';
        dropdown.style.left      = rect.left + 'px';
        dropdown.style.width     = rect.width + 'px';
        dropdown.style.maxHeight = maxH + 'px';
    }

    function showResults(items) {
        dropdown.innerHTML = '';
        positionDropdown();
        if (!items.length) {
            var empty = document.createElement('div');
            empty.className = 'px-4 py-3 text-slate-400 text-xs italic';
            empty.textContent = 'No codes found &mdash; try different keywords';
            dropdown.appendChild(empty);
            dropdown.classList.remove('hidden');
            return;
        }

        var lastCat = null;
        items.forEach(function (item) {
            if (item.cat !== lastCat) {
                var hdr = document.createElement('div');
                hdr.className = 'px-3 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-widest bg-slate-50 sticky top-0';
                hdr.textContent = item.cat;
                dropdown.appendChild(hdr);
                lastCat = item.cat;
            }
            var row = document.createElement('div');
            var alreadySelected = selected.some(function (s) { return s.code === item.code; });
            row.className = 'px-4 py-2.5 cursor-pointer flex items-start gap-3 transition-colors '
                + (alreadySelected ? 'bg-red-50 opacity-60' : 'hover:bg-slate-50');
            row.innerHTML =
                '<span class="font-mono text-xs font-bold text-red-600 shrink-0 mt-0.5 w-20">' + escHtml(item.code) + '</span>'
                + '<span class="text-slate-700 text-xs leading-relaxed">' + escHtml(item.desc)
                + (alreadySelected ? ' <span class="text-emerald-600 font-semibold">(added)</span>' : '') + '</span>';
            if (!alreadySelected) {
                row.addEventListener('click', function () { addCode(item); });
            }
            dropdown.appendChild(row);
        });
        dropdown.classList.remove('hidden');
    }

    /* â”€â”€ Search (debounced) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    searchEl.addEventListener('input', function () {
        clearTimeout(debTimer);
        var q = searchEl.value.trim();
        if (q.length < 2) { closeDropdown(); return; }
        debTimer = setTimeout(function () {
            fetch(BASE + '/api/icd10_search.php?q=' + encodeURIComponent(q), {
                credentials: 'same-origin',
            })
            .then(function (r) { return r.json(); })
            .then(showResults)
            .catch(function () { closeDropdown(); });
        }, 200);
    });

    searchEl.addEventListener('focus', function () {
        if (searchEl.value.trim().length >= 2) {
            positionDropdown();
            dropdown.classList.remove('hidden');
        }
    });

    /* Reposition on scroll / resize so the dropdown tracks the input */
    window.addEventListener('scroll', function () {
        if (!dropdown.classList.contains('hidden')) positionDropdown();
    }, true);
    window.addEventListener('resize', function () {
        if (!dropdown.classList.contains('hidden')) positionDropdown();
    });

    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target) && e.target !== searchEl) closeDropdown();
    });

    /* â”€â”€ Keyboard: close on Escape â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    searchEl.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeDropdown(); searchEl.blur(); }
    });

    /* â”€â”€ Pre-fill from last visit â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    var prefill = $icdPrefillJson;
    if (Array.isArray(prefill)) {
        prefill.forEach(function (raw) {
            // raw is "CODE &mdash; desc" string
            var m = raw.match(/^([A-Z0-9.]+)\s+\u2014\s+(.+)$/);
            if (m) addCode({ code: m[1], desc: m[2], cat: '' });
        });
    }

    /* ── Simple HTML escape ──────────────────────────────────────────── */
    function escHtml(s) {
        return String(s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ── Expose addCode globally for AI assistant ──────────────────────── */
    window.icdAddChip = function (code, desc, cat) {
        addCode({ code: String(code), desc: String(desc || code), cat: String(cat || '') });
    };
})();

/* ── PDF Annotator (PC-05) ─────────────────────────────────────────── */
(function () {
    'use strict';
    var PDFJS_URL  = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
    var WORKER_URL = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    var MAX_PAGES  = 4; // slots med_handwriting_2 .. med_handwriting_5

    var fileEl    = document.getElementById('pdfAnnotFile');
    if (!fileEl) return;

    var panel      = document.getElementById('pdfAnnotPanel');
    var bgCanvas   = document.getElementById('pdfBgCanvas');
    var drawCanvas = document.getElementById('pdfDrawCanvas');
    var curPageEl  = document.getElementById('pdfCurPage');
    var totPagesEl = document.getElementById('pdfTotPages');
    var prevBtn    = document.getElementById('pdfPrevBtn');
    var nextBtn    = document.getElementById('pdfNextBtn');
    var saveBtn    = document.getElementById('pdfAnnotSave');
    var cancelBtn  = document.getElementById('pdfAnnotCancel');
    var undoBtn    = document.getElementById('pdfAnnotUndo');
    var clearBtn   = document.getElementById('pdfAnnotClear');
    var thumbsEl   = document.getElementById('pdfAnnotThumbs');
    var hiddensEl  = document.getElementById('pdfAnnotHiddens');
    var limitMsg   = document.getElementById('pdfPageLimitMsg');
    var penBtns    = document.querySelectorAll('.pdf-pen-btn');

    var pdfDoc       = null;
    var curPage      = 1;
    var pad          = null;
    var pageDrawings = {}; // pageNum → PNG data URL of drawing layer, or null
    var minW = 0.8, maxW = 1.5;

    function loadScript(src, cb) {
        var s = document.createElement('script');
        s.src = src;
        s.onload = cb;
        s.onerror = function () { alert('Failed to load PDF renderer. Check internet connection.'); };
        document.head.appendChild(s);
    }

    fileEl.addEventListener('change', function () {
        if (!this.files || !this.files[0]) return;
        if (this.files[0].type !== 'application/pdf') { alert('Please select a PDF file.'); return; }
        var reader = new FileReader();
        reader.onload = function (e) {
            var buf = e.target.result;
            if (window.pdfjsLib) {
                openPdf(buf);
            } else {
                loadScript(PDFJS_URL, function () {
                    window.pdfjsLib.GlobalWorkerOptions.workerSrc = WORKER_URL;
                    openPdf(buf);
                });
            }
        };
        reader.readAsArrayBuffer(this.files[0]);
    });

    function openPdf(buffer) {
        pdfjsLib.getDocument({ data: buffer }).promise.then(function (doc) {
            pdfDoc       = doc;
            curPage      = 1;
            pageDrawings = {};
            totPagesEl.textContent = doc.numPages;
            if (limitMsg) limitMsg.classList.toggle('hidden', doc.numPages <= MAX_PAGES);
            panel.classList.remove('hidden');
            renderPage(1);
        }).catch(function (err) {
            alert('Could not open PDF: ' + (err.message || err));
        });
    }

    function renderPage(num) {
        pdfDoc.getPage(num).then(function (page) {
            var wrapW    = document.getElementById('pdfCanvasWrap').clientWidth || 620;
            var vp0      = page.getViewport({ scale: 1 });
            var fitScale = (wrapW - 4) / vp0.width;
            var vp       = page.getViewport({ scale: fitScale });
            var dpr      = window.devicePixelRatio || 1;
            var cssW     = Math.floor(vp.width);
            var cssH     = Math.floor(vp.height);

            bgCanvas.width        = cssW * dpr;
            bgCanvas.height       = cssH * dpr;
            bgCanvas.style.width  = cssW + 'px';
            bgCanvas.style.height = cssH + 'px';

            var bgCtx = bgCanvas.getContext('2d');
            bgCtx.setTransform(dpr, 0, 0, dpr, 0, 0);

            page.render({ canvasContext: bgCtx, viewport: vp }).promise.then(function () {
                drawCanvas.width        = cssW * dpr;
                drawCanvas.height       = cssH * dpr;
                drawCanvas.style.width  = cssW + 'px';
                drawCanvas.style.height = cssH + 'px';

                var drawCtx = drawCanvas.getContext('2d');
                drawCtx.clearRect(0, 0, drawCanvas.width, drawCanvas.height);

                if (pad) { pad.off(); pad = null; }
                pad = new SignaturePad(drawCanvas, {
                    penColor: 'rgb(15,23,42)',
                    minWidth: minW,
                    maxWidth: maxW,
                    backgroundColor: 'rgba(0,0,0,0)'
                });

                if (pageDrawings[num]) {
                    var img = new Image();
                    img.onload = function () { drawCtx.drawImage(img, 0, 0, drawCanvas.width, drawCanvas.height); };
                    img.src = pageDrawings[num];
                }

                curPageEl.textContent = num;
                prevBtn.disabled = num <= 1;
                nextBtn.disabled = num >= pdfDoc.numPages;
            });
        });
    }

    function captureDrawing() {
        pageDrawings[curPage] = (pad && !pad.isEmpty()) ? drawCanvas.toDataURL('image/png') : null;
    }

    prevBtn.addEventListener('click', function () {
        if (curPage <= 1) return;
        captureDrawing(); curPage--; renderPage(curPage);
    });
    nextBtn.addEventListener('click', function () {
        if (!pdfDoc || curPage >= pdfDoc.numPages) return;
        captureDrawing(); curPage++; renderPage(curPage);
    });

    undoBtn.addEventListener('click', function () {
        if (!pad) return;
        var d = pad.toData();
        if (d.length) { d.pop(); pad.fromData(d); }
    });
    clearBtn.addEventListener('click', function () { if (pad) pad.clear(); });

    penBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            penBtns.forEach(function (b) { b.style.borderColor = 'transparent'; });
            btn.style.borderColor = '#dc2626';
            minW = parseFloat(btn.dataset.min);
            maxW = parseFloat(btn.dataset.max);
            if (pad) { pad.minWidth = minW; pad.maxWidth = maxW; }
        });
    });

    cancelBtn.addEventListener('click', function () {
        panel.classList.add('hidden');
        fileEl.value = '';
        pdfDoc = null;
        pageDrawings = {};
        if (pad) { pad.off(); pad = null; }
    });

    saveBtn.addEventListener('click', function () {
        if (!pdfDoc) return;
        captureDrawing();
        var total   = Math.min(pdfDoc.numPages, MAX_PAGES);
        var results = new Array(total).fill(null);
        var done    = 0;
        var wrapW   = document.getElementById('pdfCanvasWrap').clientWidth || 620;
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving…';

        function finish(idx, dataUrl) {
            results[idx] = dataUrl;
            done++;
            if (done === total) commitResults(results);
        }

        for (var n = 1; n <= total; n++) {
            (function (pn) {
                pdfDoc.getPage(pn).then(function (page) {
                    var vp0      = page.getViewport({ scale: 1 });
                    var fitScale = (wrapW - 4) / vp0.width;
                    var vp       = page.getViewport({ scale: fitScale });
                    var dpr      = window.devicePixelRatio || 1;
                    var cssW     = Math.floor(vp.width);
                    var cssH     = Math.floor(vp.height);

                    var off      = document.createElement('canvas');
                    off.width    = cssW * dpr;
                    off.height   = cssH * dpr;
                    var ctx      = off.getContext('2d');
                    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                    page.render({ canvasContext: ctx, viewport: vp }).promise.then(function () {
                        var drawing = pageDrawings[pn];
                        if (drawing) {
                            var img = new Image();
                            img.onload = function () {
                                ctx.drawImage(img, 0, 0, cssW, cssH);
                                finish(pn - 1, off.toDataURL('image/png'));
                            };
                            img.src = drawing;
                        } else {
                            finish(pn - 1, off.toDataURL('image/png'));
                        }
                    });
                });
            })(n);
        }
    });

    function commitResults(pngs) {
        hiddensEl.innerHTML = '';
        thumbsEl.innerHTML  = '';

        pngs.forEach(function (dataUrl, idx) {
            if (!dataUrl) return;
            var slot = idx + 2; // page 1 → med_handwriting_2, page 2 → _3, …

            var inp   = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = 'med_handwriting_' + slot;
            inp.value = dataUrl;
            hiddensEl.appendChild(inp);

            var wrap  = document.createElement('div');
            wrap.className = 'relative inline-block';
            var img   = document.createElement('img');
            img.src   = dataUrl;
            img.alt   = 'Page ' + (idx + 1);
            img.className = 'h-24 border-2 border-red-300 rounded-xl shadow-sm object-contain bg-white';
            var badge = document.createElement('span');
            badge.className = 'absolute -top-1.5 -right-1.5 bg-red-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full shadow';
            badge.textContent = 'p.' + (idx + 1);

            (function (w, s) {
                var rm = document.createElement('button');
                rm.type = 'button';
                rm.title = 'Remove';
                rm.className = 'absolute -bottom-1.5 -right-1.5 w-4 h-4 flex items-center justify-center bg-red-500 hover:bg-red-700 text-white text-[10px] font-bold rounded-full transition-colors';
                rm.innerHTML = '&times;';
                rm.addEventListener('click', function () {
                    var f = hiddensEl.querySelector('[name="med_handwriting_' + s + '"]');
                    if (f) f.remove();
                    w.remove();
                });
                w.appendChild(rm);
            })(wrap, slot);

            wrap.appendChild(img);
            wrap.appendChild(badge);
            thumbsEl.appendChild(wrap);
        });

        var reopen = document.createElement('button');
        reopen.type = 'button';
        reopen.className = 'text-xs text-slate-400 hover:text-red-500 transition-colors self-center';
        reopen.innerHTML = '<i class="bi bi-pencil-square"></i> Re-annotate PDF';
        reopen.addEventListener('click', function () { reopen.remove(); fileEl.click(); });
        thumbsEl.appendChild(reopen);

        thumbsEl.classList.remove('hidden');
        thumbsEl.style.display = 'flex';
        panel.classList.add('hidden');
        fileEl.value = '';
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="bi bi-check2-circle"></i> Save Annotations';
    }
})();
</script>
JSBLOCK;
?>
<script>
function toggleAllergySeverity(inputId, selectId) {
    var val = document.getElementById(inputId).value.trim();
    var noAllergy = /^(nkda|nka|no\s*known\s*(drug\s*)?allergies?|none|no\s*allergies?)$/i.test(val);
    var sel = document.getElementById(selectId);
    if (!val || noAllergy) {
        sel.style.display = 'none';
        sel.value = '';
    } else {
        sel.style.display = 'block';
    }
}
</script>
<?php include __DIR__ . '/../includes/wound_photo_panel.php'; ?>
<?php include __DIR__ . '/../includes/drug_autocomplete.php'; ?>
<?php include __DIR__ . '/../includes/rx_pad_panel.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
