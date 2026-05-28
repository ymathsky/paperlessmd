<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireNotBilling();

$patient_id = (int)($_GET['patient_id'] ?? 0);
if (!$patient_id) { header('Location: ' . BASE_URL . '/patients.php'); exit; }
$visit_id   = (int)($_GET['visit_id']   ?? 0);   // optional — used for End Visit button

// Map schedule visit_type slug → form radio value
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
$_schedVtSlug   = preg_replace('/[^a-z0-9_]/', '', strtolower($_GET['sched_visit_type'] ?? ''));
$_preselVt      = $_schedVtMap[$_schedVtSlug] ?? 'New';
$pStmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$pStmt->execute([$patient_id]);
$patient = $pStmt->fetch();
if (!$patient) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

// Determine if this is the Primary Care variant (no wound care consent step)
$_npType = $_GET['np_type'] ?? 'wound_care'; // 'wound_care' | 'primary_care'
$_isPrimarycare = ($_npType === 'primary_care');

$_coName = ($patient['company'] ?? '') === 'Visiting Medical Physician Inc.' ? 'Visiting Medical Physician Inc.' : PRACTICE_NAME;
$_coUC   = strtoupper($_coName);
$_coAbb  = ($_coName === 'Visiting Medical Physician Inc.') ? 'VMP' : 'BWC';

// ── Provider list for autocomplete ──────────────────────────────────────
$_providerNames = [];
try {
    $_pnStmt = $pdo->query("SELECT full_name FROM staff WHERE active=1 AND role='provider' ORDER BY full_name");
    $_providerNames = $_pnStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

// Auto-fill provider name, date and time from today's schedule
$_schedProvider = '';
$_schedDate     = date('Y-m-d');
$_schedTime     = '';
try {
    $__sp = $pdo->prepare("SELECT provider_name, visit_date, visit_time FROM `schedule` WHERE patient_id = ? AND visit_date = CURDATE() AND COALESCE(provider_name,'') != '' ORDER BY id DESC LIMIT 1");
    $__sp->execute([$patient_id]);
    $__sr = $__sp->fetch(PDO::FETCH_ASSOC);
    if ($__sr) {
        $_schedProvider = (string)($__sr['provider_name'] ?: '');
        $_schedDate     = (string)($__sr['visit_date'] ?: date('Y-m-d'));
        $_schedTime     = (string)($__sr['visit_time'] ?: '');
    }
} catch (PDOException $e) {}

// Fetch provider's saved signature for auto-fill.
// Priority: 1) Scheduled provider's saved sig (MA workflow), 2) Logged-in provider's own sig
$_provSavedSig = '';
if ($_schedProvider) {
    try {
        $__ps = $pdo->prepare("SELECT saved_signature FROM staff WHERE full_name = ? AND COALESCE(saved_signature,'') != '' LIMIT 1");
        $__ps->execute([$_schedProvider]);
        $_provSavedSig = (string)($__ps->fetchColumn() ?: '');
    } catch (PDOException $e) {}
}
if (!$_provSavedSig && !empty($_SESSION['user_id']) && isProvider()) {
    $__ps = $pdo->prepare("SELECT saved_signature FROM staff WHERE id = ? LIMIT 1");
    $__ps->execute([(int)$_SESSION['user_id']]);
    $_provSavedSig = (string)($__ps->fetchColumn() ?: '');
}

// One-signature rule
$_ftForDupCheck = $_isPrimarycare ? 'new_patient_pocket_pc' : 'new_patient_pocket';
$_dupQ = $pdo->prepare("SELECT id FROM form_submissions WHERE patient_id = ? AND form_type = ? AND status IN ('signed','uploaded') AND DATE(created_at) = CURDATE() LIMIT 1");
$_dupQ->execute([$patient_id, $_ftForDupCheck]);
if ($_dupId = $_dupQ->fetchColumn()) { header('Location: ' . BASE_URL . '/view_document.php?id=' . (int)$_dupId . '&already_signed=1'); exit; }

// ── Pre-fill from most recent vital_cs submission ─────────────────────────
$prevStmt = $pdo->prepare("
    SELECT form_data, created_at
    FROM form_submissions
    WHERE patient_id = ? AND form_type IN ('vital_cs','new_patient_pocket','new_patient_pocket_pc')
    ORDER BY created_at DESC LIMIT 1
");
$prevStmt->execute([$patient_id]);
$prevRow  = $prevStmt->fetch();
$prev     = $prevRow ? (json_decode($prevRow['form_data'], true) ?? []) : [];
$prevDate = $prevRow ? $prevRow['created_at'] : null;

function pv(array $prev, string $key): string {
    return isset($prev[$key]) ? htmlspecialchars((string)$prev[$key], ENT_QUOTES, 'UTF-8') : '';
}
// Merge patient record fields as fallback for pre-fill
foreach (['pharmacy_name','pharmacy_phone','pharmacy_address','race','insurance_id'] as $_pk) {
    if (empty($prev[$_pk]) && !empty($patient[$_pk])) {
        $prev[$_pk] = $patient[$_pk];
    }
}
$prevRace = [];
if (isset($prev['race'])) {
    $prevRace = is_array($prev['race']) ? $prev['race'] : explode(',', $prev['race']);
}

// ── Load active medications ───────────────────────────────────────────────
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
} catch (PDOException $e) {}

$medRows = [];
foreach ($activeMeds as $m) {
    $medRows[] = ['med_id' => $m['id'], 'med_name' => $m['med_name'], 'med_freq' => $m['med_frequency'], 'med_type' => 'Refill'];
}
$emptyTarget = count($activeMeds) + 2;
while (count($medRows) < $emptyTarget) {
    $medRows[] = ['med_id' => 0, 'med_name' => '', 'med_freq' => '', 'med_type' => ''];
}

$dob          = $patient['dob'] ?? '';
$formattedDob = $dob ? date('m/d/Y', strtotime($dob)) : '';
$patientFullName = h($patient['first_name'] . ' ' . $patient['last_name']);

$pageTitle = 'New Patient Pocket';
$activeNav = 'patients';
include __DIR__ . '/../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6 flex-wrap">
    <a href="<?= BASE_URL ?>/patients.php" class="hover:text-blue-600 font-medium">Patients</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patient_id ?>" class="hover:text-blue-600 font-medium">
        <?= $patientFullName ?>
    </a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold">New Patient Pocket</span>
</nav>

<div class="max-w-3xl mx-auto">

<?php if ($prevDate): ?>
<div id="prefillBanner"
     class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-2xl px-5 py-3.5 mb-4 text-sm text-amber-800">
    <i class="bi bi-arrow-counterclockwise text-amber-500 text-lg shrink-0"></i>
    <div class="flex-1">
        <span class="font-semibold">Pre-filled from last visit</span>
        <span class="text-amber-600"> &mdash; <?= date('M j, Y', strtotime($prevDate)) ?></span>
        <span class="text-amber-600 text-xs ml-1">(pharmacy, allergies, medications carried over &mdash; update as needed)</span>
    </div>
    <button onclick="document.getElementById('prefillBanner').remove()"
            class="text-amber-400 hover:text-amber-700 transition-colors p-1 rounded-lg hover:bg-amber-100">
        <i class="bi bi-x-lg text-sm"></i>
    </button>
</div>
<?php endif; ?>

<div id="wiz-resume-banner" class="hidden flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-2xl px-5 py-3.5 mb-4 text-sm text-blue-800">
    <i class="bi bi-floppy-fill text-blue-500 text-lg shrink-0"></i>
    <div class="flex-1"><span class="font-semibold">Unsaved draft found.</span> Resume where you left off?</div>
    <button id="wiz-resume-yes" class="px-4 py-1.5 bg-blue-600 text-white text-xs font-bold rounded-lg hover:bg-blue-700 transition-colors">Resume</button>
    <button id="wiz-resume-no"  class="px-4 py-1.5 bg-white border border-blue-200 text-blue-600 text-xs font-bold rounded-lg hover:bg-blue-50 transition-colors ml-1">Start fresh</button>
</div>

<!-- Card -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <!-- Header -->
    <div class="bg-gradient-to-r from-indigo-700 to-indigo-600 px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-xl">
            <i class="bi bi-folder2-open text-white text-xl"></i>
        </div>
        <div class="flex-1">
            <div class="flex items-center gap-2 flex-wrap">
                <h2 class="text-white font-bold text-lg"><?= h($_coName ?: PRACTICE_NAME) ?> &mdash; New Patient Pocket</h2>
                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full
                             <?= $_isPrimarycare ? 'bg-teal-400/30 text-teal-100' : 'bg-rose-400/30 text-rose-100' ?>">
                    <i class="bi <?= $_isPrimarycare ? 'bi-person-heart' : 'bi-bandaid-fill' ?>"></i>
                    <?= $_isPrimarycare ? 'Primary Care' : 'Wound Care' ?>
                </span>
            </div>
            <p class="text-indigo-100 text-sm"><?= $patientFullName ?> &mdash; CS &bull; CCM &bull; ABN <?php if (!$_isPrimarycare): ?>&bull; Wound Care Consent <?php endif; ?>&bull; PHI &bull; Patient Fusion</p>
        </div>
    </div>

    <div id="wiz-header" class="px-6 pt-5 pb-2"></div>

    <form id="mainForm" method="POST" action="<?= BASE_URL ?>/api/save_form.php" novalidate>
        <input type="hidden" name="csrf_token"  value="<?= csrfToken() ?>">
        <input type="hidden" name="patient_id"  value="<?= $patient_id ?>">
        <input type="hidden" name="form_type"   value="<?= $_isPrimarycare ? 'new_patient_pocket_pc' : 'new_patient_pocket' ?>">
        <input type="hidden" name="visit_id"     value="<?= $visit_id ?>">
        <input type="hidden" name="med_count"   value="<?= count($medRows) ?>">
        <input type="hidden" id="wiz-form-key"  value="new_patient_pocket_<?= $patient_id ?>">

        <div class="px-6 pb-2">
        <?php include __DIR__ . '/../includes/form_company_selector.php'; ?>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- STEP 0 — Visit Info (CS)                               -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="wiz-step space-y-4 py-4"
             data-step="0" data-title="Visit Info" data-icon="bi-calendar-check">

            <!-- Section: Provider, Date & Time -->
            <div class="wiz-section">
                <div class="wiz-section-hd">
                    <i class="bi bi-person-badge"></i> Provider, Date &amp; Time
                </div>
                <?php
                    $_dispProvider = h($_schedProvider ?: pv($prev, 'provider_name') ?: '—');
                    $_dispDate     = $_schedDate ? date('M j, Y', strtotime($_schedDate)) : date('M j, Y');
                    $_dispTime     = $_schedTime ? date('g:i A', strtotime($_schedTime)) : '—';
                ?>
                <input type="hidden" name="provider_name" value="<?= h($_schedProvider ?: pv($prev, 'provider_name')) ?>">
                <input type="hidden" name="form_date"     value="<?= h($_schedDate) ?>">
                <input type="hidden" name="time_in"       value="<?= h($_schedTime) ?>">
                <div class="divide-y divide-slate-100 rounded-xl border border-slate-100 overflow-hidden">
                    <div class="flex items-center gap-3 px-4 py-3 bg-slate-50">
                        <i class="bi bi-person-badge-fill text-indigo-400 text-base shrink-0"></i>
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Provider</p>
                            <p class="text-sm font-bold text-slate-800 truncate"><?= $_dispProvider ?></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 divide-x divide-slate-100">
                        <div class="flex items-center gap-3 px-4 py-3 bg-slate-50">
                            <i class="bi bi-calendar3 text-indigo-400 text-base shrink-0"></i>
                            <div>
                                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Date of Visit</p>
                                <p class="text-sm font-bold text-slate-800"><?= $_dispDate ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 px-4 py-3 bg-slate-50">
                            <i class="bi bi-clock-fill text-indigo-400 text-base shrink-0"></i>
                            <div>
                                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide leading-none mb-0.5">Time In</p>
                                <p class="text-sm font-bold text-slate-800"><?= $_dispTime ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5 uppercase tracking-wide">F/U In</label>
                        <div class="flex gap-2">
                            <input type="number" name="fu_weeks" min="1"
                                   class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-white
                                          focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition"
                                   placeholder="e.g. 2">
                            <select name="fu_unit"
                                    class="px-3 py-3 border border-slate-200 rounded-xl text-sm bg-white
                                           focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition">
                                <option value="weeks">Weeks</option>
                                <option value="days">Days</option>
                            </select>
                        </div>
                </div>
            </div>

            <!-- Section: Visit Type -->
            <div class="wiz-section">
                <div class="wiz-section-hd">
                    <i class="bi bi-clipboard2-pulse"></i> Visit Type
                </div>
                <?php
                $_vtIcons = [
                    'New'               => 'bi-person-plus-fill',
                    'Follow Up'         => 'bi-arrow-repeat',
                    'Sick'              => 'bi-thermometer-half',
                    'Post Hospital F/U' => 'bi-hospital-fill',
                ];
                ?>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <?php foreach ($_vtIcons as $vt => $icon): ?>
                    <label class="radio-card flex flex-col items-center gap-1.5 py-3 px-2 border-2 border-slate-200 rounded-xl cursor-pointer text-center
                                  has-[:checked]:border-red-500 has-[:checked]:bg-red-50 has-[:checked]:shadow-sm">
                        <input type="radio" name="visit_type" value="<?= $vt ?>"
                               <?= $vt === $_preselVt ? 'checked' : '' ?>
                               class="sr-only">
                        <i class="bi <?= $icon ?> vt-icon text-xl"></i>
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
                    <label class="radio-card flex flex-col items-center gap-2.5 py-5 px-3 border-2 border-slate-200 rounded-2xl cursor-pointer text-center
                                  has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50 has-[:checked]:shadow-sm">
                        <input type="radio" name="homebound" value="homebound" checked class="sr-only">
                        <span class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center">
                            <i class="bi bi-house-check-fill text-emerald-500 text-2xl leading-none"></i>
                        </span>
                        <span class="font-bold text-slate-700 text-sm leading-snug">Patient IS<br>Homebound</span>
                    </label>
                    <label class="radio-card flex flex-col items-center gap-2.5 py-5 px-3 border-2 border-slate-200 rounded-2xl cursor-pointer text-center
                                  has-[:checked]:border-slate-500 has-[:checked]:bg-slate-100 has-[:checked]:shadow-sm">
                        <input type="radio" name="homebound" value="not_homebound" class="sr-only">
                        <span class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center">
                            <i class="bi bi-house-x-fill text-slate-400 text-2xl leading-none"></i>
                        </span>
                        <span class="font-bold text-slate-700 text-sm leading-snug">Patient IS NOT<br>Homebound</span>
                    </label>
                </div>
            </div>

            <!-- Section: Missed Visit -->
            <div class="wiz-section" id="missedVisitSection">
                <div class="wiz-section-hd">
                    <i class="bi bi-exclamation-circle"></i> Missed Visit
                </div>
                <div id="mvRegularState">
                    <button type="button" id="mvToggleBtn"
                            class="w-full flex items-center gap-3 px-4 py-3.5 border-2 border-dashed border-slate-300
                                   rounded-xl text-sm font-medium text-slate-500
                                   hover:border-amber-400 hover:text-amber-700 hover:bg-amber-50 transition-all">
                        <i class="bi bi-calendar-x text-lg flex-shrink-0"></i>
                        <span>Mark as Missed Visit</span>
                        <span class="ml-auto text-xs font-normal text-slate-400">tap to log a missed visit</span>
                    </button>
                </div>
                <div id="mvActiveState" class="hidden space-y-3">
                    <div class="flex items-center gap-2.5 px-4 py-3 bg-amber-50 border border-amber-300
                                text-amber-800 rounded-xl text-sm font-semibold">
                        <i class="bi bi-calendar-x-fill text-amber-500 text-base flex-shrink-0"></i>
                        <span>Missed Visit &mdash; vitals &amp; signatures will be skipped</span>
                        <button type="button" id="mvCancelBtn"
                                class="ml-auto text-amber-600 hover:text-amber-800 text-xs font-normal underline flex-shrink-0">
                            Cancel
                        </button>
                    </div>
                    <label class="block text-sm font-semibold text-slate-700">
                        Reason for missed visit <span class="text-red-400">*</span>
                    </label>
                    <textarea name="missed_visit_reason" id="mvReasonText" rows="2"
                              class="w-full px-4 py-3 border border-amber-300 rounded-xl text-sm bg-white
                                     focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent
                                     transition resize-none"
                              placeholder="e.g. Patient not home, Patient cancelled appointment&hellip;"><?= h(pv($prev, 'missed_visit_reason')) ?></textarea>
                </div>
            </div>
        </div><!-- /step 0 -->


        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- STEP 1 — Vitals & Clinical (CS)                        -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="1" data-title="Vitals" data-icon="bi-heart-pulse">

            <p class="form-section-title">
                <i class="bi bi-heart-pulse text-indigo-500"></i>
                Section 2 of 9 &mdash; Vital Signs &amp; Clinical <span class="text-slate-400 font-normal text-xs">(Visit Consent / CS)</span>
            </p>

            <div class="vitals-quick-grid grid grid-cols-2 sm:grid-cols-4 gap-3">
                <?php
                $vitals = [
                    ['name'=>'bp',      'label'=>'BP',       'placeholder'=>'120/80'],
                    ['name'=>'pulse',   'label'=>'Pulse',    'placeholder'=>'72 bpm'],
                    ['name'=>'temp',    'label'=>'Temp',     'placeholder'=>'98.6°F'],
                    ['name'=>'o2sat',   'label'=>'O2Sat',    'placeholder'=>'98%'],
                    ['name'=>'glucose', 'label'=>'Glucose',  'placeholder'=>'mg/dL'],
                    ['name'=>'height',  'label'=>'Height',   'placeholder'=>'in / cm'],
                    ['name'=>'weight',  'label'=>'Weight',   'placeholder'=>'lbs / kg'],
                    ['name'=>'resp',    'label'=>'Resp',     'placeholder'=>'breaths/min'],
                ];
                foreach ($vitals as $v):
                    $prefilled = pv($prev, $v['name']);
                ?>
                <div class="bg-slate-50 border <?= $prefilled ? 'border-amber-300' : 'border-slate-200' ?> rounded-xl p-3">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">
                        <?= $v['label'] ?>
                        <?php if ($prefilled): ?><span class="ml-1 text-amber-400" title="Pre-filled"><i class="bi bi-arrow-counterclockwise"></i></span><?php endif; ?>
                    </label>
                    <input type="text" name="<?= $v['name'] ?>" value="<?= $prefilled ?>"
                           class="w-full bg-transparent text-sm font-semibold text-slate-800 border-0 border-b border-slate-300 pb-1
                                  focus:outline-none focus:border-indigo-400 transition"
                           placeholder="<?= $v['placeholder'] ?>">
                    <p class="text-xs text-slate-400 mt-2">Checked or Per patient</p>
                </div>
                <?php endforeach; ?>
            </div>

        </div><!-- /step 1 -->


        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- STEP 2 — Medications (CS)                              -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="2" data-title="Medications" data-icon="bi-capsule">

            <p class="form-section-title">
                <i class="bi bi-bag-heart text-indigo-500"></i>
                Section 3 of 9 &mdash; Pharmacy, Allergies &amp; Medications <span class="text-slate-400 font-normal text-xs">(Visit Consent / CS)</span>
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Pharmacy</label>
                    <input type="text" name="pharmacy_name" value="<?= pv($prev,'pharmacy_name') ?>"
                           class="w-full px-4 py-3 border <?= pv($prev,'pharmacy_name') ? 'border-amber-300' : 'border-slate-200' ?> rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white"
                           placeholder="Pharmacy name">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Pharmacy Phone</label>
                    <input type="tel" name="pharmacy_phone" value="<?= pv($prev,'pharmacy_phone') ?>"
                           class="w-full px-4 py-3 border <?= pv($prev,'pharmacy_phone') ? 'border-amber-300' : 'border-slate-200' ?> rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white"
                           placeholder="Phone number">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Assistive Device</label>
                    <input type="text" name="assistive_device" value="<?= pv($prev,'assistive_device') ?>"
                           class="w-full px-4 py-3 border <?= pv($prev,'assistive_device') ? 'border-amber-300' : 'border-slate-200' ?> rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white"
                           placeholder="Cane, walker, wheelchair...">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Allergies</label>
                    <input type="text" name="allergies" id="allergies_npp" value="<?= pv($prev,'allergies') ?>"
                           class="w-full px-4 py-3 border <?= pv($prev,'allergies') ? 'border-amber-300' : 'border-slate-200' ?> rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white"
                           placeholder="NKDA or list..."
                           oninput="toggleAllergySeverity('allergies_npp','allergy_severity_npp')">
                    <select name="allergy_severity" id="allergy_severity_npp"
                            class="mt-2 w-full px-4 py-2.5 border <?= pv($prev,'allergy_severity') ? 'border-amber-300' : 'border-slate-200' ?> rounded-xl text-sm bg-slate-50
                                   focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition"
                            style="display:<?= (pv($prev,'allergies') && !preg_match('/^(nkda|nka|no known (drug )?allergies?|none|no allergies?)$/i',trim(pv($prev,'allergies')))) ? 'block' : 'none' ?>">
                        <option value="">— Severity —</option>
                        <?php foreach (['Mild','Moderate','Severe','GI','SOD','Hives'] as $sev): ?>
                        <option value="<?= $sev ?>" <?= pv($prev,'allergy_severity') === $sev ? 'selected' : '' ?>><?= $sev ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Race</label>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (['African American','Caucasian','Latin','Asian','Pacific Islander','Other'] as $race):
                        $raceChecked = in_array($race, $prevRace);
                    ?>
                    <label class="flex items-center gap-2 px-3.5 py-2 border <?= $raceChecked ? 'border-amber-300 bg-amber-50' : 'border-slate-200' ?> rounded-xl cursor-pointer text-sm
                                  hover:border-indigo-300 hover:bg-indigo-50/50 transition-colors has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                        <input type="checkbox" name="race[]" value="<?= $race ?>" <?= $raceChecked ? 'checked' : '' ?>
                               class="w-3.5 h-3.5 text-indigo-600 border-slate-300 rounded focus:ring-indigo-400">
                        <span class="text-slate-700"><?= $race ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <p class="form-section-title"><i class="bi bi-capsule text-indigo-500"></i> Medication List &amp; Reconciliation</p>

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
                    <tbody class="med-rows-tbody divide-y divide-slate-100">
                        <?php foreach ($medRows as $mi => $row):
                            $i           = $mi + 1;
                            $isPrefilled = $row['med_id'] > 0;
                        ?>
                        <input type="hidden" name="med_id_<?= $i ?>" value="<?= $row['med_id'] ?>">
                        <tr class="<?= $isPrefilled ? 'bg-emerald-50/30 med-prefilled' : '' ?>">
                            <td class="px-3 py-2" data-label="Type">
                                <select name="med_type_<?= $i ?>"
                                        class="w-full px-2 py-2 border <?= $isPrefilled ? 'border-emerald-200' : 'border-slate-200' ?> rounded-lg text-xs bg-white
                                               focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                    <option value="">&mdash;</option>
                                    <?php foreach (['New','Refill','D/C'] as $opt): ?>
                                    <option <?= $row['med_type'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="px-3 py-2" data-label="Medication &amp; Dose">
                                <?php if ($isPrefilled): ?><div class="flex items-center gap-1.5"><?php endif; ?>
                                <?php if ($isPrefilled): ?><i class="bi bi-capsule text-emerald-500 text-xs shrink-0"></i><?php endif; ?>
                                <input type="text" name="med_name_<?= $i ?>" value="<?= h($row['med_name']) ?>"
                                       class="w-full px-3 py-2 border <?= $isPrefilled ? 'border-emerald-200' : 'border-slate-200' ?> rounded-lg text-sm bg-white
                                              focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                       placeholder="Medication name and dose">
                                <?php if ($isPrefilled): ?></div><?php endif; ?>
                            </td>
                            <td class="px-3 py-2" data-label="Frequency">
                                <input type="text" name="med_freq_<?= $i ?>" value="<?= h($row['med_freq']) ?>"
                                       class="w-full px-3 py-2 border <?= $isPrefilled ? 'border-emerald-200' : 'border-slate-200' ?> rounded-lg text-sm bg-white
                                              focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                       placeholder="e.g. BID">
                            </td>
                            <td class="px-2 py-2 no-print">
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
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-50 hover:bg-indigo-100
                               border border-indigo-200 text-indigo-700 font-semibold text-sm rounded-xl transition-all no-print">
                    <i class="bi bi-plus-circle"></i> Add Row
                </button>
                <label class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100
                              border border-red-200 text-red-700 font-semibold text-sm rounded-xl
                              transition-all cursor-pointer no-print">
                    <i class="bi bi-file-earmark-pdf text-red-500"></i> Upload PDF &amp; Annotate
                    <input type="file" id="pdfAnnotFile" accept="application/pdf" class="sr-only">
                </label>
                <p class="text-xs text-slate-400">
                    <i class="bi bi-info-circle mr-0.5 text-emerald-500"></i>
                    Set type to <strong class="text-indigo-600">D/C</strong> to discontinue &mdash;
                    <strong class="text-emerald-600">New</strong> rows are added to the master list on save.
                </p>
            </div>

            <!-- PDF Annotator Panel -->
            <div id="pdfAnnotPanel" class="hidden mt-3 border-2 border-red-200 rounded-2xl overflow-hidden no-print">
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
                <div id="pdfCanvasWrap" class="relative bg-slate-100 overflow-auto" style="max-height:60vh;">
                    <div id="pdfCanvasContainer" class="relative inline-block" style="touch-action:none;cursor:crosshair;">
                        <canvas id="pdfBgCanvas"></canvas>
                        <canvas id="pdfDrawCanvas" style="position:absolute;top:0;left:0;touch-action:none;"></canvas>
                    </div>
                </div>
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

        </div><!-- /step 2 -->


        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- STEP 3 — CCM Consent                                   -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="3" data-title="CCM Consent" data-icon="bi-calendar2-heart">

            <p class="form-section-title">
                <i class="bi bi-calendar2-heart text-indigo-500"></i>
                Section 4 of 9 &mdash; Chronic Care Management Consent
            </p>

            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-sm text-slate-700 space-y-3 leading-relaxed max-h-[380px] overflow-y-auto">
                <p>By signing this Agreement, you consent to <strong><?= h($_coName) ?></strong> (referred to as "Provider"),
                providing chronic care management services (referred to as "CCM Services") to you as more fully described below.</p>
                <p>CCM Services are available to you because you have been diagnosed with two (2) or more chronic conditions
                which are expected to last at least twelve (12) months and which place you at significant risk of further decline.</p>
                <p>CCM Services include 24-hours-a-day, 7-days-a-week access to a health care provider in Provider's practice to
                address acute chronic care needs; systematic assessment of your health care needs; processes to assure that you
                timely receive preventative care services; medication reviews and oversight; a plan of care covering your health
                issues; and management of care transitions among health care providers and settings.</p>

                <h4 class="font-bold text-slate-800 mt-2">Provider's Obligations</h4>
                <ul class="space-y-1.5">
                    <li class="flex items-start gap-2"><i class="bi bi-check-circle text-emerald-500 mt-0.5 shrink-0"></i>Explain to you (and your caregiver, if applicable), and offer to you, all the CCM Services that are applicable to your conditions.</li>
                    <li class="flex items-start gap-2"><i class="bi bi-check-circle text-emerald-500 mt-0.5 shrink-0"></i>Provide to you a written or electronic copy of your care plan.</li>
                    <li class="flex items-start gap-2"><i class="bi bi-check-circle text-emerald-500 mt-0.5 shrink-0"></i>If you revoke this Agreement, provide you with a written confirmation of the revocation, stating the effective date of the revocation.</li>
                </ul>

                <h4 class="font-bold text-slate-800 mt-2">Beneficiary Acknowledgment and Authorization</h4>
                <p class="italic text-slate-500 text-xs">By signing, you agree to all of the following:</p>
                <ul class="space-y-1.5">
                    <li class="flex items-start gap-2"><i class="bi bi-check-circle text-emerald-500 mt-0.5 shrink-0"></i>I consent to the Provider providing CCM Services to me.</li>
                    <li class="flex items-start gap-2"><i class="bi bi-check-circle text-emerald-500 mt-0.5 shrink-0"></i>I authorize electronic communication of my medical information with other treating providers as part of coordination of my care.</li>
                    <li class="flex items-start gap-2"><i class="bi bi-check-circle text-emerald-500 mt-0.5 shrink-0"></i>I acknowledge that only one practitioner can furnish CCM Services to me during a calendar month.</li>
                    <li class="flex items-start gap-2"><i class="bi bi-check-circle text-emerald-500 mt-0.5 shrink-0"></i>I understand that cost-sharing will apply to CCM Services and I may be billed for a portion even without a face-to-face meeting.</li>
                </ul>

                <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 mt-2">
                    <p class="font-bold text-emerald-800 text-xs mb-1">Beneficiary Rights</p>
                    <ul class="text-emerald-700 space-y-1 text-xs">
                        <li class="flex items-start gap-1.5"><i class="bi bi-info-circle shrink-0 mt-0.5"></i>The Provider will provide you with a written or electronic copy of your care plan.</li>
                        <li class="flex items-start gap-1.5"><i class="bi bi-info-circle shrink-0 mt-0.5"></i>You have the right to stop CCM Services at any time by revoking this Agreement effective at the end of the then-current month. You may revoke verbally or in writing to <strong><?= h($_coName) ?></strong>.</li>
                    </ul>
                </div>
            </div>

            <!-- CCM acknowledgment checkboxes (stored in form_data) -->
            <div class="space-y-2">
                <?php
                $auths = [
                    'ccm_ack_consent'    => 'I consent to the Provider providing CCM Services to me.',
                    'ccm_ack_electronic' => 'I authorize electronic communication of my medical information with other treating providers.',
                    'ccm_ack_one_only'   => 'I acknowledge that only one practitioner can furnish CCM Services during a calendar month.',
                    'ccm_ack_copay'      => 'I understand that cost-sharing will apply to CCM Services.',
                ];
                foreach ($auths as $name => $text): ?>
                <label class="flex items-start gap-3 p-3 border border-slate-200 rounded-xl cursor-pointer
                              hover:border-emerald-300 hover:bg-emerald-50/50 transition-colors has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50">
                    <input type="checkbox" name="<?= $name ?>" value="1" checked
                           class="mt-0.5 w-4 h-4 text-emerald-600 border-slate-300 rounded focus:ring-emerald-400 flex-shrink-0">
                    <span class="text-sm text-slate-700"><?= h($text) ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Witness Name (Print)</label>
                    <input type="text" name="ccm_witness_name"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">CCM Date</label>
                    <input type="date" name="ccm_date" value="<?= date('Y-m-d') ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                </div>
            </div>
        </div><!-- /step 3 -->


        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- STEP 4 — ABN                                           -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="4" data-title="ABN" data-icon="bi-file-earmark-ruled">

            <p class="form-section-title">
                <i class="bi bi-file-earmark-ruled text-indigo-500"></i>
                Section 5 of 9 &mdash; Advance Beneficiary Notice (ABN) <span class="text-slate-400 font-normal text-xs">CMS-R-131</span>
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">A. Notifier</label>
                    <input type="text" name="notifier" value="<?= h(PRACTICE_NAME) ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">C. ID Number</label>
                    <input type="text" name="id_number"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white"
                           placeholder="Optional">
                </div>
            </div>

            <div class="bg-amber-50 border-2 border-amber-300 rounded-xl p-4">
                <p class="text-sm font-bold text-amber-900 mb-1">
                    NOTE: If Medicare doesn't pay for the Home visit below, you may have to pay.
                </p>
                <p class="text-sm text-amber-800 leading-relaxed">
                    Medicare does not pay for everything, even some care that you or your health care provider
                    have good reason to think you need. We expect Medicare may not pay for the <strong>20%</strong> below.
                </p>
            </div>

            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <div class="grid grid-cols-1 sm:grid-cols-3 border-b border-slate-200">
                    <div class="bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-500 uppercase border-b sm:border-b-0 sm:border-r border-slate-200">D. Service / Item</div>
                    <div class="bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-500 uppercase border-b sm:border-b-0 sm:border-r border-slate-200">E. Reason Medicare May Not Pay</div>
                    <div class="bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-500 uppercase">F. Estimated Cost</div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3">
                    <div class="p-4 sm:border-r border-slate-200">
                        <textarea name="service_description" rows="4"
                                  class="w-full text-sm bg-transparent border-0 focus:outline-none resize-none text-slate-700"
                                  >Medicare covers 80% of home visit for wound care.
If secondary insurance is available will bill 20% to secondary insurance.</textarea>
                    </div>
                    <div class="p-4 sm:border-r border-slate-200">
                        <textarea name="reason_not_paid" rows="4"
                                  class="w-full text-sm bg-transparent border-0 focus:outline-none resize-none text-slate-700"
                                  >Medicare covers 80% leaving 20% to be billed. This will be billed to a secondary insurance if available. If patient does not have a secondary insurance will be billed FOR 20%</textarea>
                    </div>
                    <div class="p-4">
                        <input type="text" name="estimated_cost" value="20%"
                               class="w-full text-sm bg-transparent border-b border-slate-300 focus:outline-none focus:border-indigo-400 text-slate-700 font-semibold py-1">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-3">G. OPTIONS &mdash; Check only one box.</label>
                <div class="space-y-3">
                    <?php
                    $abnOptions = [
                        1 => 'OPTION 1. I want the Office visit listed above. You may ask to be paid now, but I also want Medicare billed for an official decision on payment, which is sent to me on a Medicare Summary Notice (MSN). I understand that if Medicare doesn\'t pay, I am responsible for payment, but I can appeal to Medicare by following the directions on the MSN. If Medicare does pay, you will refund any payments I made to you, less co-pays or deductibles.',
                        2 => 'OPTION 2. I want the Office visit listed above, but do not bill Medicare. You may ask to be paid now as I am responsible for payment. I cannot appeal if Medicare is not billed.',
                        3 => 'OPTION 3. I don\'t want the Office visit listed above. I understand with this choice I am not responsible for payment, and I cannot appeal to see if Medicare would pay.',
                    ];
                    foreach ($abnOptions as $opt => $text): ?>
                    <label class="flex items-start gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer
                                  hover:border-amber-300 hover:bg-amber-50/50 transition-colors has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50">
                        <input type="radio" name="patient_option" value="<?= $opt ?>"
                               class="mt-0.5 w-4 h-4 text-amber-500 border-slate-300 focus:ring-amber-400 flex-shrink-0">
                        <div class="text-sm text-slate-700">
                            <span class="font-bold">OPTION <?= $opt ?>.</span> <?= h(substr($text, strpos($text, ' ')+1)) ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">H. Additional Information</label>
                <textarea name="additional_info" rows="2"
                          class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white resize-none"
                          placeholder="Any additional information..."></textarea>
            </div>
        </div><!-- /step 4 -->


        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- STEP 5 — Informed Consent for Wound Care               -->
        <?php if (!$_isPrimarycare): ?>
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="5" data-title="Wound Care Consent" data-icon="bi-file-earmark-medical">

            <p class="form-section-title">
                <i class="bi bi-file-earmark-medical text-indigo-500"></i>
                Section 6 of 9 &mdash; Informed Consent for Wound Care Treatment
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Patient Name</label>
                    <input type="text" name="wc_patient_name"
                           value="<?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date of Birth</label>
                    <input type="text" name="wc_dob" value="<?= h($formattedDob) ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                </div>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-sm text-slate-700 space-y-4 leading-relaxed max-h-[400px] overflow-y-auto">
                <p>Patient/caregiver hereby voluntarily consents to wound care treatment by the provider (MD/NP) of <strong><?= h($_coUC) ?></strong> Patient/Caregiver understands that this consent form will be valid and remain in effect as long as the patient remains active and receives services and treatments at <?= h($_coUC) ?>. A new consent form will be obtained when a patient is discharged and returns for services and treatments. <strong>Patient has the right to give or refuse consent to any proposed service or treatment.</strong></p>
                <ol class="space-y-3 pl-4 list-decimal">
                    <li><strong>General Description of Wound Care Treatment:</strong> Patient acknowledges that physician/NP has explained their treatment for wound care, which can include, but not be limited to: debridement, dressing changes, skin grafts, off-loading devices, physical examinations and treatment, diagnostic procedures, laboratory work (such as wound care cultures), request x-rays, other imaging studies and administration of medications prescribed by a physician and or NP.</li>
                    <li><strong>Benefits of Wound Care Treatment:</strong> Patient acknowledges that physician/NP has explained the benefits of wound care treatment, which include enhanced wound healing and reduced risks of amputation and infection.</li>
                    <li><strong>Risks and Side Effects of Wound Care Treatment:</strong> Patient acknowledges that physician/NP has explained that wound care treatment may cause side effects and risks including, but not limited to: infection, pain and inflammation, bleeding, allergic reaction to topical and injected local anesthetics or skin prep solutions, removal of healthy tissue, delayed healing or failure to heal, possible scarring and possible damage to: blood vessels, surrounding tissues, and nerves.</li>
                    <li><strong>Likelihood of achieving goals:</strong> Patient acknowledges that physician/NP has explained the proposed treatment plan that they are more than likely to have optimized treatment outcomes; however, any service or treatment carry the risk of unsuccessful results, complications and injuries, from both known and unforeseen causes.</li>
                    <li><strong>General Description of Wound Debridement:</strong> Patient acknowledges that physician/NP has explained that wound debridement means the removal of unhealthy tissue from a wound to promote healing. During the course of treatment, multiple wound debridement's may be necessary.</li>
                    <li><strong>Risks/Side Effects of Wound Debridement:</strong> Patient acknowledges the physician/NP has explained the risks and/or complications of wound debridement include, but are not limited to: potential scarring, possible allergic reactions, excessive bleeding, removal of healthy tissue, infection, ongoing pain and inflammation, and failure to heal.</li>
                    <li><strong>Patient Identification and Wound Images:</strong> Patient/caregiver understands and consents that images may be taken by <?= h($_coAbb) ?> of patient's wounds. The purpose of these images is to monitor the progress of wound treatment and ensure continuity of care. Images are considered protected health information and will be handled in accordance with federal laws.</li>
                    <li><strong>Use and Disclosure of PHI:</strong> Patient consents to <?= h($_coAbb) ?> use of PHI for purposes of education and quality assessment in compliance with HIPAA. Patient/caregiver specifically authorizes use and disclosure of PHI for purposes related to treatment, payment and health care operations.</li>
                    <li><strong>Financial Responsibility:</strong> Patient/caregiver understands that regardless of insurance benefits, patient is responsible for any amount not covered by insurance. Patient authorizes medical information to be released to any payor to determine benefits payable for related services.</li>
                </ol>
                <p>The patient/caregiver or POA hereby acknowledges that he or she has read and agrees to the contents of sections 1 through 9 of these documents.</p>
            </div>
        </div><!-- /step 5 -->
        <?php endif; // !$_isPrimarycare ?>


        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- STEP 6 — IL DHS PHI Authorization                      -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="6" data-title="PHI Authorization" data-icon="bi-key-fill">

            <p class="form-section-title">
                <i class="bi bi-key-fill text-indigo-500"></i>
                Section 7 of 9 &mdash; Authorization to Disclose / Obtain PHI <span class="text-slate-400 font-normal text-xs">(IL DHS)</span>
            </p>

            <!-- Auth type -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">I authorize <strong><?= h(PRACTICE_NAME) ?></strong> to:</label>
                <div class="flex flex-wrap gap-3">
                    <?php foreach (['disclose' => 'Disclose information', 'obtain' => 'Obtain information', 'both' => 'Both disclose and obtain'] as $val => $lbl): ?>
                    <label class="flex items-center gap-2 px-4 py-3 border border-slate-200 rounded-xl cursor-pointer text-sm font-medium
                                  hover:border-indigo-300 hover:bg-indigo-50 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 transition-colors">
                        <input type="radio" name="auth_type" value="<?= $val ?>"
                               class="w-4 h-4 text-indigo-700 border-slate-300 focus:ring-indigo-500">
                        <?= h($lbl) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Record types -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Type of Records</label>
                <p class="text-xs text-slate-500 italic mb-3">Note: Checking "Mental Health," "Alcohol &amp; Substance Use," "HIV/AIDS," or other sensitive records does not authorize the release of records for discrimination purposes.</p>
                <?php
                $recordTypes = [
                    'all'=>'Complete / All Records','discharge_summary'=>'Discharge Summary',
                    'inpatient'=>'Inpatient Records','outpatient'=>'Outpatient Records',
                    'psychiatric'=>'Psychiatric Records','psych_eval'=>'Psychiatric Evaluation',
                    'mental_health'=>'Mental Health Records','alcohol_substance'=>'Alcohol &amp; Substance Use (42 CFR Part 2)',
                    'hiv_aids'=>'HIV / AIDS Records','genetic'=>'Genetic Information',
                    'lab'=>'Lab / Pathology Reports','xray'=>'Radiology / X-Ray','other'=>'Other',
                ];
                ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach ($recordTypes as $key => $label): ?>
                    <label class="flex items-center gap-2.5 px-4 py-3 border border-slate-200 rounded-xl cursor-pointer text-sm
                                  hover:border-indigo-300 hover:bg-indigo-50 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 transition-colors">
                        <input type="checkbox" name="record_types[]" value="<?= h($key) ?>"
                               class="w-4 h-4 text-indigo-700 border-slate-300 rounded focus:ring-indigo-500">
                        <?= $label ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Records from</label>
                        <input type="date" name="records_from"
                               class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Records to</label>
                        <input type="date" name="records_to" value="<?= date('Y-m-d') ?>"
                               class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                    </div>
                </div>
            </div>

            <!-- Patient ID -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Patient Identification</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Patient Full Name</label>
                        <input type="text" name="patient_name"
                               value="<?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Date of Birth</label>
                        <input type="text" name="patient_dob" value="<?= h($formattedDob) ?>" placeholder="MM/DD/YYYY"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">SSN (last 4 digits)</label>
                        <input type="text" name="patient_ssn" maxlength="4" placeholder="XXXX"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                    </div>
                </div>
            </div>

            <!-- Purpose -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Purpose of Disclosure</label>
                <?php
                $purposes = [
                    'personal_use'=>'Personal Use','continuity_care'=>'Continuity of Care',
                    'placement_transfer'=>'Placement / Transfer','legal'=>'Legal / Judicial',
                    'insurance'=>'Insurance / Benefits','research'=>'Research','other'=>'Other',
                ];
                ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach ($purposes as $key => $label): ?>
                    <label class="flex items-center gap-2.5 px-4 py-3 border border-slate-200 rounded-xl cursor-pointer text-sm
                                  hover:border-indigo-300 hover:bg-indigo-50 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 transition-colors">
                        <input type="checkbox" name="purposes[]" value="<?= h($key) ?>"
                               class="w-4 h-4 text-indigo-700 border-slate-300 rounded focus:ring-indigo-500">
                        <?= h($label) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recipients -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Disclose To / Obtain From</label>
                <?php for ($i = 1; $i <= 2; $i++): ?>
                <div class="border border-slate-200 rounded-xl p-4 mb-3">
                    <p class="text-xs font-bold text-slate-500 uppercase mb-3">Entry <?= $i ?></p>
                    <div class="grid grid-cols-1 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Name of Facility / Provider</label>
                            <input type="text" name="recipient_name_<?= $i ?>"
                                   <?= $i===1 ? 'value="' . h(PRACTICE_NAME) . '"' : '' ?>
                                   class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50
                                          focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Address</label>
                            <input type="text" name="recipient_address_<?= $i ?>"
                                   <?= $i===1 ? 'value="' . h(PRACTICE_ADDRESS) . '"' : '' ?>
                                   class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50
                                          focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Authorization Valid Until <span class="text-slate-400 font-normal text-xs">(leave blank for one year from today)</span></label>
                <input type="date" name="expiration_date"
                       class="px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs text-slate-600 leading-relaxed space-y-2">
                <p class="font-bold text-slate-700 uppercase tracking-wide">Important Legal Information</p>
                <p><strong>Right to Revoke:</strong> You have the right to revoke this authorization in writing at any time by submitting a written request to <?= h(PRACTICE_NAME) ?>.</p>
                <p><strong>Consequences of Refusal:</strong> Your treatment, payment, enrollment, and eligibility for benefits generally may NOT be conditioned on signing this authorization.</p>
                <p><strong>Re-disclosure:</strong> Information disclosed under this authorization may be subject to re-disclosure by the recipient and may no longer be protected by HIPAA unless otherwise prohibited by law.</p>
            </div>
        </div><!-- /step 6 -->


        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- STEP 7 — Patient Fusion Portal                         -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="7" data-title="Patient Fusion" data-icon="bi-envelope-at">

            <p class="form-section-title">
                <i class="bi bi-envelope-at text-indigo-500"></i>
                Section 8 of 9 &mdash; Patient Fusion Portal Consent
            </p>

            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-sm text-slate-700 space-y-3 leading-relaxed">
                <p>I acknowledge that I have read and fully understand this consent form. I have been given the risks and benefits of Patient Fusion and understand the risks associated with online communications between our office and patients.</p>
                <p>By signing below and providing an e-mail address, I hereby give my informed consent to participate in Patient Fusion and I hereby agree to and accept the provisions contained above.</p>
                <p>I acknowledge that the e-mail address provided belongs to me or my authorized representative and that I will receive Patient Fusion enrollment instructions, including applicable terms of service, to the address if I agree to participate.</p>
                <p class="font-bold underline decoration-2">By declining and not providing an email, my signature indicates that I am informed about Patient Fusion being offered to me, but I do not wish to participate.</p>
                <p>I understand I may choose to participate at any time in the future by requesting to update my response to this agreement with the practice.</p>
                <p>A copy of this agreement will be provided to you and one will also be included in your medical record with our practice.</p>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-3">Please check one:</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer
                                  hover:border-cyan-400 hover:bg-cyan-50/50 transition-colors has-[:checked]:border-cyan-500 has-[:checked]:bg-cyan-50">
                        <input type="radio" name="pf_decision" value="participate" id="pf_participate" checked
                               class="w-4 h-4 text-cyan-600 border-slate-300 focus:ring-cyan-400">
                        <span class="font-semibold text-slate-700">Participate in Patient Fusion</span>
                    </label>
                    <label class="flex items-center gap-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer
                                  hover:border-red-300 hover:bg-red-50/50 transition-colors has-[:checked]:border-red-400 has-[:checked]:bg-red-50">
                        <input type="radio" name="pf_decision" value="decline"
                               class="w-4 h-4 text-red-500 border-slate-300 focus:ring-red-400">
                        <span class="font-semibold text-slate-700">Decline</span>
                    </label>
                </div>
            </div>

            <div id="pf_email_section">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email, if participating:</label>
                <input type="email" name="patient_email" value="<?= h($patient['email'] ?? '') ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white"
                       placeholder="patient@email.com">
            </div>
        </div><!-- /step 7 -->


        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- STEP 8 — Sign & Submit                                 -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="8" data-title="Sign" data-icon="bi-pen">

            <p class="form-section-title">
                <i class="bi bi-pen text-indigo-500"></i>
                Section 9 of 9 &mdash; Signatures <span class="text-slate-400 font-normal text-xs">(Patient &bull; MA &bull; Provider)</span>
            </p>

            <p class="form-section-title"><i class="bi bi-person-badge text-indigo-500"></i> Staff Information</p>

            <div class="max-w-xs">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Medical Assistant</label>
                <input type="text" name="ma_name" value="<?= h($_SESSION['full_name'] ?? '') ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
            </div>
            <input type="hidden" name="time_out">

            <!-- Patient + MA signatures via standard sig_block -->
            <?php include __DIR__ . '/../includes/sig_block.php'; ?>

            <!-- Provider Signature Block -->
            <div class="bg-white border-2 border-violet-100 rounded-2xl overflow-hidden mt-4">
                <div class="bg-gradient-to-r from-violet-600 to-violet-500 px-5 py-3 flex items-center gap-2">
                    <i class="bi bi-person-check-fill text-white"></i>
                    <span class="text-white font-semibold text-sm">Provider / Physician Signature</span>
                    <?php if ($_provSavedSig): ?>
                    <span class="ml-auto inline-flex items-center gap-1 text-xs bg-white/20 text-white rounded-full px-2.5 py-0.5">
                        <i class="bi bi-lightning-charge-fill"></i> Auto-fill on
                    </span>
                    <?php endif; ?>
                </div>
                <div class="p-5">
                    <div id="providerSigAlert" class="hidden flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm mb-4">
                        <i class="bi bi-exclamation-circle text-lg flex-shrink-0"></i>
                        Provider signature is required before submitting.
                    </div>
                    <?php if ($_provSavedSig): ?>
                    <div id="provSavedBanner" class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2.5 rounded-xl text-sm mb-3">
                        <i class="bi bi-check-circle-fill shrink-0"></i>
                        <span class="flex-1">Using your <strong>saved signature</strong>. <a href="<?= BASE_URL ?>/profile.php#savedSigSection" class="underline hover:text-emerald-900 font-semibold" target="_blank">Update in Profile</a></span>
                        <button type="button" id="useManualProvSig" class="text-xs font-semibold bg-emerald-100 hover:bg-emerald-200 px-3 py-1 rounded-lg transition-colors">Sign manually</button>
                    </div>
                    <?php endif; ?>
                    <div class="mb-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Provider Name (Print)</label>
                            <input type="text" name="provider_print_name"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm
                                          focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent bg-slate-50">
                        </div>
                    </div>
                    <div id="provSigPadArea" <?= $_provSavedSig ? 'class="hidden"' : '' ?>>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Provider sign below
                            <span class="text-slate-400 font-normal text-xs ml-1">(attending physician / nurse practitioner)</span>
                        </label>
                        <div class="sig-wrapper border-2 border-dashed border-slate-300 rounded-2xl focus-within:border-violet-400 transition-colors" id="providerSigWrapper">
                            <canvas id="providerSigPad"></canvas>
                            <div class="sig-placeholder">Provider sign here</div>
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <button type="button" id="clearProviderSig"
                                    class="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-xl transition-colors">
                                <i class="bi bi-eraser"></i> Clear
                            </button>
                            <span class="text-xs text-slate-400">Provider signature confirms medical supervision and authorization</span>
                        </div>
                    </div>
                    <input type="hidden" name="provider_signature" id="providerSigData" form="mainForm">
                    <?php if ($_provSavedSig): ?>
                    <script>window._pdProvSavedSig = <?= json_encode($_provSavedSig) ?>;</script>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /step 8 -->

        <?php
        $accentClass  = 'bg-indigo-700 hover:bg-indigo-800';
        $cancelUrl    = BASE_URL . '/patient_view.php?id=' . $patient_id;
        $endVisitId   = $visit_id;   // passed to wiz_nav to render End Visit button
        include __DIR__ . '/../includes/wiz_nav.php';
        ?>

        </div><!-- /px-6 -->
    </form>
</div><!-- /card -->
</div><!-- /max-w-3xl -->

<?php
$prevIcdCodes   = [];
if (isset($prev['icd10_codes']) && is_array($prev['icd10_codes'])) {
    $prevIcdCodes = array_values($prev['icd10_codes']);
}
$icdPrefillJson = json_encode($prevIcdCodes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$icdApiBaseJson = json_encode(BASE_URL);

$extraJs = <<<JSBLOCK
<script>
/* ── Missed Visit Mode ──────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    var mvToggleBtn = document.getElementById('mvToggleBtn');
    var mvCancelBtn = document.getElementById('mvCancelBtn');
    var mvRegular   = document.getElementById('mvRegularState');
    var mvActive    = document.getElementById('mvActiveState');
    var mvReason    = document.getElementById('mvReasonText');

    function enterMissedMode() {
        window._pdMissedVisit = true;
        if (mvRegular) mvRegular.classList.add('hidden');
        if (mvActive)  mvActive.classList.remove('hidden');
        if (mvReason)  mvReason.setAttribute('required', '');
    }

    function exitMissedMode() {
        window._pdMissedVisit = false;
        if (mvActive)  mvActive.classList.add('hidden');
        if (mvRegular) mvRegular.classList.remove('hidden');
        if (mvReason)  { mvReason.value = ''; mvReason.removeAttribute('required'); }
    }

    if (mvToggleBtn) mvToggleBtn.addEventListener('click', enterMissedMode);
    if (mvCancelBtn) mvCancelBtn.addEventListener('click', exitMissedMode);

    // Auto-enter if draft had a reason
    if (mvReason && mvReason.value.trim()) enterMissedMode();
});

/* ── ICD-10 Search ───────────────────────────────────────────── */
(function () {
    var BASE     = {$icdApiBaseJson};
    var MAX      = 6;
    var selected = [];
    var debTimer = null;

    var searchEl = document.getElementById('icdSearch');
    var dropdown = document.getElementById('icdDropdown');
    var chipsEl  = document.getElementById('icdChips');
    var hiddenEl = document.getElementById('icdHiddenInputs');
    var maxMsg   = document.getElementById('icdMaxMsg');

    if (!searchEl) return; // guard for step not visible

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function renderChips() {
        chipsEl.innerHTML  = '';
        hiddenEl.innerHTML = '';
        maxMsg.classList.toggle('hidden', selected.length < MAX);
        selected.forEach(function (item, idx) {
            var chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1.5 '
                + 'bg-indigo-50 border border-indigo-200 text-indigo-800 text-xs font-semibold rounded-xl';
            chip.innerHTML =
                '<span class="font-mono text-indigo-600">' + escHtml(item.code) + '</span>'
                + '<span class="text-indigo-700 max-w-[220px] truncate">' + escHtml(item.desc) + '</span>'
                + '<button type="button" class="ml-0.5 text-indigo-400 hover:text-indigo-700 transition" '
                + 'aria-label="Remove" data-idx="' + idx + '">'
                + '<i class="bi bi-x-circle-fill text-sm"></i></button>';
            chipsEl.appendChild(chip);
            var inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = 'icd10_codes[]';
            inp.value = item.code + ' — ' + item.desc;
            hiddenEl.appendChild(inp);
        });
        chipsEl.querySelectorAll('[data-idx]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selected.splice(parseInt(btn.dataset.idx), 1);
                renderChips();
            });
        });
    }

    function addCode(item) {
        if (selected.length >= MAX) return;
        if (selected.some(function (s) { return s.code === item.code; })) return;
        selected.push(item);
        renderChips();
        searchEl.value = '';
        dropdown.classList.add('hidden');
        searchEl.focus();
    }

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
            empty.textContent = 'No codes found — try different keywords';
            dropdown.appendChild(empty);
            dropdown.classList.remove('hidden');
            return;
        }
        items.forEach(function (item) {
            var row = document.createElement('div');
            row.className = 'px-4 py-3 cursor-pointer hover:bg-indigo-50 flex items-baseline gap-2 border-b border-slate-100 last:border-0';
            row.innerHTML = '<span class="font-mono text-xs font-bold text-indigo-600 shrink-0">'
                + escHtml(item.code) + '</span>'
                + '<span class="text-slate-700 text-xs truncate">' + escHtml(item.desc || item.description || '') + '</span>';
            row.addEventListener('click', function () {
                addCode({code: item.code, desc: item.desc || item.description || '', cat: item.cat || ''});
            });
            dropdown.appendChild(row);
        });
        dropdown.classList.remove('hidden');
    }

    searchEl.addEventListener('input', function () {
        clearTimeout(debTimer);
        var q = searchEl.value.trim();
        if (q.length < 2) { dropdown.classList.add('hidden'); return; }
        debTimer = setTimeout(function () {
            fetch(BASE + '/api/icd10_search.php?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(showResults)
                .catch(function () { dropdown.classList.add('hidden'); });
        }, 220);
    });

    /* Reposition on scroll / resize so the dropdown tracks the input */
    window.addEventListener('scroll', function () {
        if (!dropdown.classList.contains('hidden')) positionDropdown();
    }, true);
    window.addEventListener('resize', function () {
        if (!dropdown.classList.contains('hidden')) positionDropdown();
    });

    document.addEventListener('click', function (e) {
        if (!searchEl.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    // Pre-fill ICD codes from last visit
    var prefill = {$icdPrefillJson};
    if (Array.isArray(prefill)) {
        prefill.forEach(function (raw) {
            var m = String(raw).match(/^([A-Z0-9.]+)\s*[—\-]\s*(.+)$/);
            if (m) addCode({code: m[1].trim(), desc: m[2].trim(), cat: ''});
        });
    }
})();

/* ── Provider Signature Pad ─────────────────────────────────── */
(function () {
    var canvas  = document.getElementById('providerSigPad');
    var hidden  = document.getElementById('providerSigData');
    var clearBtn = document.getElementById('clearProviderSig');
    var wrapper = document.getElementById('providerSigWrapper');
    if (!hidden) return;

    // Pre-fill from saved sig if available (pad may be hidden)
    if (window._pdProvSavedSig && !hidden.value) {
        hidden.value = window._pdProvSavedSig;
    }

    if (!canvas || !window.SignaturePad) {
        // "Sign manually" still needs to work even if canvas doesn't exist yet
    } else {
        function initProviderPad() {
            if (canvas._sp) return;
            canvas.width  = wrapper.offsetWidth  || 600;
            canvas.height = wrapper.offsetHeight || 140;
            var sp = new SignaturePad(canvas, {
                minWidth: 1, maxWidth: 3,
                penColor: 'rgb(30, 27, 75)'
            });
            canvas._sp = sp;
            sp.addEventListener('endStroke', function () {
                hidden.value = sp.toDataURL('image/png');
                var ph = wrapper.querySelector('.sig-placeholder');
                if (ph) ph.style.display = 'none';
            });
            if (hidden.value) {
                try { sp.fromDataURL(hidden.value); } catch(e) {}
            }
        }
        setTimeout(initProviderPad, 120);
        window.addEventListener('resize', initProviderPad);

        clearBtn && clearBtn.addEventListener('click', function () {
            if (!canvas._sp || canvas._sp.isEmpty()) return;
            if (!confirm('Clear the provider signature? This cannot be undone.')) return;
            canvas._sp.clear();
            hidden.value = '';
            var ph = wrapper.querySelector('.sig-placeholder');
            if (ph) ph.style.display = '';
        });
    }

    // "Sign manually" button — show pad area, discard saved sig
    var manualBtn = document.getElementById('useManualProvSig');
    if (manualBtn) {
        manualBtn.addEventListener('click', function () {
            var banner  = document.getElementById('provSavedBanner');
            var padArea = document.getElementById('provSigPadArea');
            if (banner)  banner.style.display = 'none';
            if (padArea) padArea.classList.remove('hidden');
            hidden.value = '';
            window._pdProvSavedSig = null;
            // Re-init pad now that it is visible
            if (canvas && window.SignaturePad && !canvas._sp && wrapper) {
                setTimeout(function () {
                    canvas.width  = wrapper.offsetWidth  || 600;
                    canvas.height = wrapper.offsetHeight || 140;
                    var sp = new SignaturePad(canvas, { minWidth: 1, maxWidth: 3, penColor: 'rgb(30, 27, 75)' });
                    canvas._sp = sp;
                    sp.addEventListener('endStroke', function () {
                        hidden.value = sp.toDataURL('image/png');
                        var ph = wrapper.querySelector('.sig-placeholder');
                        if (ph) ph.style.display = 'none';
                    });
                }, 80);
            }
        });
    }

    // Validate provider sig via app.js hook (mainForm.submit() doesn't fire submit events).
    // Chain with wiz_nav's End Visit gate if it was set (runs after provider sig passes).
    var _wizNavGate = typeof window._pdEndVisitGate === 'function' ? window._pdEndVisitGate : null;
    window._pdValidateExtra = function () {
        if (!hidden.value) {
            var alertEl = document.getElementById('providerSigAlert');
            if (alertEl) { alertEl.classList.remove('hidden'); alertEl.scrollIntoView({behavior:'smooth', block:'center'}); }
            return false;
        }
        return _wizNavGate ? _wizNavGate() : true;
    };
})();

/* ── Auto-fill provider_print_name from provider_name (step 0) ── */
(function () {
    var srcField   = document.querySelector('[name="provider_name"]');
    var destField  = document.querySelector('[name="provider_print_name"]');
    var signStep   = document.querySelector('.wiz-step[data-title="Sign"]');
    if (!srcField || !destField || !signStep) return;

    function maybeFill() {
        if (!signStep.classList.contains('hidden') && destField.value.trim() === '') {
            var val = srcField.value.trim();
            if (val) {
                destField.value = val;
                // Brief highlight so the MA notices it was auto-filled
                destField.style.transition = 'border-color .3s, background .3s';
                destField.style.borderColor = '#a78bfa';
                destField.style.background  = '#f5f3ff';
                setTimeout(function () {
                    destField.style.borderColor = '';
                    destField.style.background  = '';
                }, 2000);
            }
        }
    }

    // Watch for the sign step becoming visible (wizard removes 'hidden' class)
    var observer = new MutationObserver(maybeFill);
    observer.observe(signStep, { attributes: true, attributeFilter: ['class'] });
})();

/* ── PDF Annotator (medication upload) ─────────────────────────────── */
(function () {
    'use strict';
    var PDFJS_URL  = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
    var WORKER_URL = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    var MAX_PAGES  = 4;

    var fileEl = document.getElementById('pdfAnnotFile');
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

    var pdfDoc = null, curPage = 1, pad = null, pageDrawings = {};
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
            if (window.pdfjsLib) { openPdf(buf); }
            else { loadScript(PDFJS_URL, function () { window.pdfjsLib.GlobalWorkerOptions.workerSrc = WORKER_URL; openPdf(buf); }); }
        };
        reader.readAsArrayBuffer(this.files[0]);
    });

    function openPdf(buffer) {
        pdfjsLib.getDocument({ data: buffer }).promise.then(function (doc) {
            pdfDoc = doc; curPage = 1; pageDrawings = {};
            totPagesEl.textContent = doc.numPages;
            if (limitMsg) limitMsg.classList.toggle('hidden', doc.numPages <= MAX_PAGES);
            panel.classList.remove('hidden');
            renderPage(1);
        }).catch(function (err) { alert('Could not open PDF: ' + (err.message || err)); });
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

            bgCanvas.width = cssW * dpr; bgCanvas.height = cssH * dpr;
            bgCanvas.style.width = cssW + 'px'; bgCanvas.style.height = cssH + 'px';
            var bgCtx = bgCanvas.getContext('2d');
            bgCtx.setTransform(dpr, 0, 0, dpr, 0, 0);

            page.render({ canvasContext: bgCtx, viewport: vp }).promise.then(function () {
                drawCanvas.width = cssW * dpr; drawCanvas.height = cssH * dpr;
                drawCanvas.style.width = cssW + 'px'; drawCanvas.style.height = cssH + 'px';
                var drawCtx = drawCanvas.getContext('2d');
                drawCtx.clearRect(0, 0, drawCanvas.width, drawCanvas.height);

                if (pad) { pad.off(); pad = null; }
                pad = new SignaturePad(drawCanvas, { penColor: 'rgb(15,23,42)', minWidth: minW, maxWidth: maxW, backgroundColor: 'rgba(0,0,0,0)' });

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

    function captureDrawing() { pageDrawings[curPage] = (pad && !pad.isEmpty()) ? drawCanvas.toDataURL('image/png') : null; }

    prevBtn.addEventListener('click', function () { if (curPage > 1) { captureDrawing(); curPage--; renderPage(curPage); } });
    nextBtn.addEventListener('click', function () { if (pdfDoc && curPage < pdfDoc.numPages) { captureDrawing(); curPage++; renderPage(curPage); } });
    undoBtn.addEventListener('click', function () { if (!pad) return; var d = pad.toData(); if (d.length) { d.pop(); pad.fromData(d); } });
    clearBtn.addEventListener('click', function () { if (pad) pad.clear(); });
    penBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            penBtns.forEach(function (b) { b.style.borderColor = 'transparent'; });
            btn.style.borderColor = '#dc2626';
            minW = parseFloat(btn.dataset.min); maxW = parseFloat(btn.dataset.max);
            if (pad) { pad.minWidth = minW; pad.maxWidth = maxW; }
        });
    });
    cancelBtn.addEventListener('click', function () {
        panel.classList.add('hidden'); fileEl.value = ''; pdfDoc = null; pageDrawings = {};
        if (pad) { pad.off(); pad = null; }
    });

    saveBtn.addEventListener('click', function () {
        if (!pdfDoc) return;
        captureDrawing();
        var total = Math.min(pdfDoc.numPages, MAX_PAGES);
        var results = new Array(total).fill(null);
        var done = 0;
        var wrapW = document.getElementById('pdfCanvasWrap').clientWidth || 620;
        saveBtn.disabled = true; saveBtn.textContent = 'Saving\u2026';

        function finish(idx, dataUrl) { results[idx] = dataUrl; done++; if (done === total) commitResults(results); }

        for (var n = 1; n <= total; n++) {
            (function (pn) {
                pdfDoc.getPage(pn).then(function (page) {
                    var vp0 = page.getViewport({ scale: 1 });
                    var fitScale = (wrapW - 4) / vp0.width;
                    var vp  = page.getViewport({ scale: fitScale });
                    var dpr = window.devicePixelRatio || 1;
                    var cssW = Math.floor(vp.width), cssH = Math.floor(vp.height);
                    var off = document.createElement('canvas');
                    off.width = cssW * dpr; off.height = cssH * dpr;
                    var ctx = off.getContext('2d');
                    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                    page.render({ canvasContext: ctx, viewport: vp }).promise.then(function () {
                        var drawing = pageDrawings[pn];
                        if (drawing) {
                            var img = new Image();
                            img.onload = function () { ctx.drawImage(img, 0, 0, cssW, cssH); finish(pn - 1, off.toDataURL('image/png')); };
                            img.src = drawing;
                        } else { finish(pn - 1, off.toDataURL('image/png')); }
                    });
                });
            })(n);
        }
    });

    function commitResults(pngs) {
        hiddensEl.innerHTML = ''; thumbsEl.innerHTML = '';
        pngs.forEach(function (dataUrl, idx) {
            if (!dataUrl) return;
            var slot = idx + 2;
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'med_handwriting_' + slot; inp.value = dataUrl;
            hiddensEl.appendChild(inp);

            var wrap = document.createElement('div'); wrap.className = 'relative inline-block';
            var img  = document.createElement('img');
            img.src  = dataUrl; img.alt = 'Page ' + (idx + 1);
            img.className = 'h-24 border-2 border-red-300 rounded-xl shadow-sm object-contain bg-white';
            var badge = document.createElement('span');
            badge.className = 'absolute -top-1.5 -right-1.5 bg-red-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full shadow';
            badge.textContent = 'p.' + (idx + 1);
            (function (w, s) {
                var rm = document.createElement('button');
                rm.type = 'button'; rm.title = 'Remove';
                rm.className = 'absolute -bottom-1.5 -right-1.5 w-4 h-4 flex items-center justify-center bg-red-500 hover:bg-red-700 text-white text-[10px] font-bold rounded-full transition-colors';
                rm.innerHTML = '&times;';
                rm.addEventListener('click', function () { var f = hiddensEl.querySelector('[name="med_handwriting_' + s + '"]'); if (f) f.remove(); w.remove(); });
                w.appendChild(rm);
            })(wrap, slot);
            wrap.appendChild(img); wrap.appendChild(badge); thumbsEl.appendChild(wrap);
        });

        var reopen = document.createElement('button');
        reopen.type = 'button';
        reopen.className = 'text-xs text-slate-400 hover:text-red-500 transition-colors self-center';
        reopen.innerHTML = '<i class="bi bi-pencil-square"></i> Re-annotate PDF';
        reopen.addEventListener('click', function () { reopen.remove(); fileEl.click(); });
        thumbsEl.appendChild(reopen);

        thumbsEl.classList.remove('hidden'); thumbsEl.style.display = 'flex';
        panel.classList.add('hidden'); fileEl.value = '';
        saveBtn.disabled = false; saveBtn.innerHTML = '<i class="bi bi-check2-circle"></i> Save Annotations';
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

<?php
// ── Floating Wound Photo Button & Panel ──────────────────────────
include __DIR__ . '/../includes/wound_photo_panel.php';
include __DIR__ . '/../includes/drug_autocomplete.php';
include __DIR__ . '/../includes/rx_pad_panel.php';

include __DIR__ . '/../includes/footer.php';
