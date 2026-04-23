<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireNotBilling();

$patient_id = (int)($_GET['patient_id'] ?? 0);
if (!$patient_id) { header('Location: ' . BASE_URL . '/patients.php'); exit; }
$pStmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$pStmt->execute([$patient_id]);
$patient = $pStmt->fetch();
if (!$patient) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

// One-signature rule
$_dupQ = $pdo->prepare("SELECT id FROM form_submissions WHERE patient_id = ? AND form_type = 'new_patient_pocket' AND status IN ('signed','uploaded') AND DATE(created_at) = CURDATE() LIMIT 1");
$_dupQ->execute([$patient_id]);
if ($_dupId = $_dupQ->fetchColumn()) { header('Location: ' . BASE_URL . '/view_document.php?id=' . (int)$_dupId . '&already_signed=1'); exit; }

// ── Pre-fill from most recent vital_cs submission ─────────────────────────
$prevStmt = $pdo->prepare("
    SELECT form_data, created_at
    FROM form_submissions
    WHERE patient_id = ? AND form_type IN ('vital_cs','new_patient_pocket')
    ORDER BY created_at DESC LIMIT 1
");
$prevStmt->execute([$patient_id]);
$prevRow  = $prevStmt->fetch();
$prev     = $prevRow ? (json_decode($prevRow['form_data'], true) ?? []) : [];
$prevDate = $prevRow ? $prevRow['created_at'] : null;

function pv(array $prev, string $key): string {
    return isset($prev[$key]) ? htmlspecialchars((string)$prev[$key], ENT_QUOTES, 'UTF-8') : '';
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
        ORDER BY sort_order ASC, added_at ASC LIMIT 6
    ");
    $medsStmt->execute([$patient_id]);
    $activeMeds = $medsStmt->fetchAll();
} catch (PDOException $e) {}

$medRows = [];
foreach ($activeMeds as $m) {
    $medRows[] = ['med_id' => $m['id'], 'med_name' => $m['med_name'], 'med_freq' => $m['med_frequency'], 'med_type' => 'Refill'];
}
while (count($medRows) < 6) {
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
        <div>
            <h2 class="text-white font-bold text-lg"><?= h(PRACTICE_NAME) ?> &mdash; New Patient Pocket</h2>
            <p class="text-indigo-100 text-sm"><?= $patientFullName ?> &mdash; CS &bull; CCM &bull; ABN &bull; Wound Care Consent &bull; PHI &bull; Patient Fusion</p>
        </div>
    </div>

    <div id="wiz-header" class="px-6 pt-5 pb-2"></div>

    <form id="mainForm" method="POST" action="<?= BASE_URL ?>/api/save_form.php" novalidate>
        <input type="hidden" name="csrf_token"  value="<?= csrfToken() ?>">
        <input type="hidden" name="patient_id"  value="<?= $patient_id ?>">
        <input type="hidden" name="form_type"   value="new_patient_pocket">
        <input type="hidden" id="wiz-form-key"  value="new_patient_pocket_<?= $patient_id ?>">

        <div class="px-6 pb-2">

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- STEP 0 — Visit Info (CS)                               -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="wiz-step space-y-6 py-4"
             data-step="0" data-title="Visit Info" data-icon="bi-calendar-check">

            <p class="form-section-title">
                <i class="bi bi-calendar-check text-indigo-500"></i>
                Section 1 of 8 &mdash; Visit Information <span class="text-slate-400 font-normal text-xs">(Visit Consent / CS)</span>
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Provider</label>
                    <input type="text" name="provider_name"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white"
                           placeholder="Attending provider name">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date of Visit</label>
                    <input type="date" name="form_date" value="<?= date('Y-m-d') ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-3">Visit Type</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                    <?php foreach (['New','Follow Up','Sick','Post Hospital F/U'] as $vt): ?>
                    <label class="flex items-center gap-2.5 p-3 border border-slate-200 rounded-xl cursor-pointer
                                  hover:border-indigo-300 hover:bg-indigo-50/50 transition-colors has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                        <input type="radio" name="visit_type" value="<?= $vt ?>"
                               class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-400 flex-shrink-0"
                               <?= $vt === 'New' ? 'checked' : '' ?>>
                        <span class="text-sm font-medium text-slate-700"><?= $vt ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">F/U in (weeks)</label>
                        <input type="number" name="fu_weeks" min="1" max="52"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white"
                               placeholder="e.g. 2">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Time In</label>
                        <input type="time" name="time_in"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Time Out</label>
                        <input type="time" name="time_out"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-3">Homebound Status</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer
                                  hover:border-indigo-300 hover:bg-indigo-50/50 transition-colors has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                        <input type="radio" name="homebound" value="homebound"
                               class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-400">
                        <span class="font-semibold text-slate-700 underline decoration-2">Patient IS Homebound</span>
                    </label>
                    <label class="flex items-center gap-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer
                                  hover:border-slate-400 hover:bg-slate-50 transition-colors has-[:checked]:border-slate-500 has-[:checked]:bg-slate-50">
                        <input type="radio" name="homebound" value="not_homebound"
                               class="w-4 h-4 text-slate-600 border-slate-300 focus:ring-slate-400">
                        <span class="font-semibold text-slate-700 underline decoration-2">Patient IS NOT Homebound</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Missed Visit Reason <span class="text-slate-400 font-normal">(if applicable)</span>
                </label>
                <input type="text" name="missed_visit_reason"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white"
                       placeholder="Leave blank if not a missed visit">
            </div>
        </div><!-- /step 0 -->


        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- STEP 1 — Vitals & Clinical (CS)                        -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="1" data-title="Vitals" data-icon="bi-heart-pulse">

            <p class="form-section-title">
                <i class="bi bi-heart-pulse text-indigo-500"></i>
                Section 2 of 8 &mdash; Vital Signs &amp; Clinical <span class="text-slate-400 font-normal text-xs">(Visit Consent / CS)</span>
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
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

            <p class="form-section-title mt-2"><i class="bi bi-chat-left-text text-indigo-500"></i> Chief Complaint &amp; ICD-10</p>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Chief Complaint / Notes</label>
                <textarea name="chief_complaint" rows="4"
                          class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white resize-none"
                          placeholder="Chief complaint and clinical notes..."></textarea>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">
                    Diagnosis / ICD-10 Codes
                    <span class="ml-1.5 text-xs font-normal text-slate-400">(up to 6)</span>
                </label>
                <div id="icdChips" class="flex flex-wrap gap-2 mb-2 min-h-[2rem]"></div>
                <div id="icdHiddenInputs"></div>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i class="bi bi-search text-sm"></i>
                    </span>
                    <input type="text" id="icdSearch" autocomplete="off"
                           placeholder="Search by code or keyword (e.g. &quot;sacral stage 2&quot;)&hellip;"
                           class="w-full pl-8 pr-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                    <div id="icdDropdown"
                         class="hidden absolute z-40 left-0 right-0 top-full mt-1
                                bg-white border border-slate-200 rounded-xl shadow-2xl overflow-y-auto text-sm"
                         style="max-height:280px"></div>
                </div>
                <p id="icdMaxMsg" class="hidden text-xs text-amber-600 mt-1.5 font-semibold">Maximum of 6 codes reached.</p>
                <p class="text-xs text-slate-400 mt-1.5">
                    <i class="bi bi-info-circle text-slate-300 mr-0.5"></i>
                    Wound-care ICD-10 library
                </p>
            </div>
        </div><!-- /step 1 -->


        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- STEP 2 — Medications (CS)                              -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="2" data-title="Medications" data-icon="bi-capsule">

            <p class="form-section-title">
                <i class="bi bi-bag-heart text-indigo-500"></i>
                Section 3 of 8 &mdash; Pharmacy, Allergies &amp; Medications <span class="text-slate-400 font-normal text-xs">(Visit Consent / CS)</span>
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
                    <input type="text" name="allergies" value="<?= pv($prev,'allergies') ?>"
                           class="w-full px-4 py-3 border <?= pv($prev,'allergies') ? 'border-amber-300' : 'border-slate-200' ?> rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white"
                           placeholder="NKDA or list...">
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
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide w-28">New / Refill</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">Medication &amp; Dose</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wide w-36">Frequency</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($medRows as $mi => $row):
                            $i = $mi + 1; $isPrefilled = $row['med_id'] > 0; ?>
                        <input type="hidden" name="med_id_<?= $i ?>" value="<?= $row['med_id'] ?>">
                        <tr class="<?= $isPrefilled ? 'bg-emerald-50/30' : '' ?>">
                            <td class="px-3 py-2">
                                <select name="med_type_<?= $i ?>"
                                        class="w-full px-2 py-2 border <?= $isPrefilled ? 'border-emerald-200' : 'border-slate-200' ?> rounded-lg text-xs bg-white
                                               focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                    <option value="">&mdash;</option>
                                    <?php foreach (['New','Refill','D/C'] as $opt): ?>
                                    <option <?= $row['med_type'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <?php if ($isPrefilled): ?><div class="flex items-center gap-1.5"><i class="bi bi-capsule text-emerald-500 text-xs shrink-0"></i><?php endif; ?>
                                <input type="text" name="med_name_<?= $i ?>" value="<?= h($row['med_name']) ?>"
                                       class="w-full px-3 py-2 border <?= $isPrefilled ? 'border-emerald-200' : 'border-slate-200' ?> rounded-lg text-sm bg-white
                                              focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                       placeholder="Medication name and dose">
                                <?php if ($isPrefilled): ?></div><?php endif; ?>
                            </td>
                            <td class="px-3 py-2">
                                <input type="text" name="med_freq_<?= $i ?>" value="<?= h($row['med_freq']) ?>"
                                       class="w-full px-3 py-2 border <?= $isPrefilled ? 'border-emerald-200' : 'border-slate-200' ?> rounded-lg text-sm bg-white
                                              focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                       placeholder="e.g. BID">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                <i class="bi bi-info-circle mr-0.5 text-emerald-500"></i>
                Set type to <strong class="text-red-600">D/C</strong> to discontinue &mdash;
                <strong class="text-emerald-600">New</strong> rows are added to the master list on save.
            </p>
        </div><!-- /step 2 -->


        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- STEP 3 — CCM Consent                                   -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="3" data-title="CCM Consent" data-icon="bi-calendar2-heart">

            <p class="form-section-title">
                <i class="bi bi-calendar2-heart text-indigo-500"></i>
                Section 4 of 8 &mdash; Chronic Care Management Consent
            </p>

            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-sm text-slate-700 space-y-3 leading-relaxed max-h-[380px] overflow-y-auto">
                <p>By signing this Agreement, you consent to <strong><?= h(PRACTICE_NAME) ?></strong> (referred to as "Provider"),
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
                        <li class="flex items-start gap-1.5"><i class="bi bi-info-circle shrink-0 mt-0.5"></i>You have the right to stop CCM Services at any time by revoking this Agreement effective at the end of the then-current month. You may revoke verbally or in writing to <strong><?= h(PRACTICE_NAME) ?></strong>.</li>
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
                    <input type="checkbox" name="<?= $name ?>" value="1"
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
                Section 5 of 8 &mdash; Advance Beneficiary Notice (ABN) <span class="text-slate-400 font-normal text-xs">CMS-R-131</span>
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
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="5" data-title="Wound Care Consent" data-icon="bi-file-earmark-medical">

            <p class="form-section-title">
                <i class="bi bi-file-earmark-medical text-indigo-500"></i>
                Section 6 of 8 &mdash; Informed Consent for Wound Care Treatment
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
                <p>Patient/caregiver hereby voluntarily consents to wound care treatment by the provider (MD/NP) of <strong>BEYOND WOUND CARE INC.</strong> Patient/Caregiver understands that this consent form will be valid and remain in effect as long as the patient remains active and receives services and treatments at BEYOND WOUND CARE INC. A new consent form will be obtained when a patient is discharged and returns for services and treatments. <strong>Patient has the right to give or refuse consent to any proposed service or treatment.</strong></p>
                <ol class="space-y-3 pl-4 list-decimal">
                    <li><strong>General Description of Wound Care Treatment:</strong> Patient acknowledges that physician/NP has explained their treatment for wound care, which can include, but not be limited to: debridement, dressing changes, skin grafts, off-loading devices, physical examinations and treatment, diagnostic procedures, laboratory work (such as wound care cultures), request x-rays, other imaging studies and administration of medications prescribed by a physician and or NP.</li>
                    <li><strong>Benefits of Wound Care Treatment:</strong> Patient acknowledges that physician/NP has explained the benefits of wound care treatment, which include enhanced wound healing and reduced risks of amputation and infection.</li>
                    <li><strong>Risks and Side Effects of Wound Care Treatment:</strong> Patient acknowledges that physician/NP has explained that wound care treatment may cause side effects and risks including, but not limited to: infection, pain and inflammation, bleeding, allergic reaction to topical and injected local anesthetics or skin prep solutions, removal of healthy tissue, delayed healing or failure to heal, possible scarring and possible damage to: blood vessels, surrounding tissues, and nerves.</li>
                    <li><strong>Likelihood of achieving goals:</strong> Patient acknowledges that physician/NP has explained the proposed treatment plan that they are more than likely to have optimized treatment outcomes; however, any service or treatment carry the risk of unsuccessful results, complications and injuries, from both known and unforeseen causes.</li>
                    <li><strong>General Description of Wound Debridement:</strong> Patient acknowledges that physician/NP has explained that wound debridement means the removal of unhealthy tissue from a wound to promote healing. During the course of treatment, multiple wound debridement's may be necessary.</li>
                    <li><strong>Risks/Side Effects of Wound Debridement:</strong> Patient acknowledges the physician/NP has explained the risks and/or complications of wound debridement include, but are not limited to: potential scarring, possible allergic reactions, excessive bleeding, removal of healthy tissue, infection, ongoing pain and inflammation, and failure to heal.</li>
                    <li><strong>Patient Identification and Wound Images:</strong> Patient/caregiver understands and consents that images may be taken by BWC of patient's wounds. The purpose of these images is to monitor the progress of wound treatment and ensure continuity of care. Images are considered protected health information and will be handled in accordance with federal laws.</li>
                    <li><strong>Use and Disclosure of PHI:</strong> Patient consents to BWC use of PHI for purposes of education and quality assessment in compliance with HIPAA. Patient/caregiver specifically authorizes use and disclosure of PHI for purposes related to treatment, payment and health care operations.</li>
                    <li><strong>Financial Responsibility:</strong> Patient/caregiver understands that regardless of insurance benefits, patient is responsible for any amount not covered by insurance. Patient authorizes medical information to be released to any payor to determine benefits payable for related services.</li>
                </ol>
                <p>The patient/caregiver or POA hereby acknowledges that he or she has read and agrees to the contents of sections 1 through 9 of these documents.</p>
            </div>
        </div><!-- /step 5 -->


        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- STEP 6 — IL DHS PHI Authorization                      -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="wiz-step hidden space-y-6 py-4"
             data-step="6" data-title="PHI Authorization" data-icon="bi-key-fill">

            <p class="form-section-title">
                <i class="bi bi-key-fill text-indigo-500"></i>
                Section 7 of 8 &mdash; Authorization to Disclose / Obtain PHI <span class="text-slate-400 font-normal text-xs">(IL DHS)</span>
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
                Section 8 of 8 &mdash; Patient Fusion Portal Consent
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
                        <input type="radio" name="pf_decision" value="participate" id="pf_participate"
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

            <p class="form-section-title"><i class="bi bi-person-badge text-indigo-500"></i> Staff Information</p>

            <div class="max-w-xs">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Medical Assistant</label>
                <input type="text" name="ma_name" value="<?= h($_SESSION['full_name'] ?? '') ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
            </div>

            <!-- Patient + MA signatures via standard sig_block -->
            <?php include __DIR__ . '/../includes/sig_block.php'; ?>

            <!-- Provider Signature Block -->
            <div class="bg-white border-2 border-violet-100 rounded-2xl overflow-hidden mt-4">
                <div class="bg-gradient-to-r from-violet-600 to-violet-500 px-5 py-3 flex items-center gap-2">
                    <i class="bi bi-person-check-fill text-white"></i>
                    <span class="text-white font-semibold text-sm">Provider / Physician Signature</span>
                </div>
                <div class="p-5">
                    <div id="providerSigAlert" class="hidden flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm mb-4">
                        <i class="bi bi-exclamation-circle text-lg flex-shrink-0"></i>
                        Provider signature is required before submitting.
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Provider Name (Print)</label>
                            <input type="text" name="provider_print_name"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm
                                          focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent bg-slate-50">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Provider NPI</label>
                            <input type="text" name="provider_npi"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm
                                          focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent bg-slate-50"
                                   placeholder="10-digit NPI">
                        </div>
                    </div>
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
                    <input type="hidden" name="provider_signature" id="providerSigData" form="mainForm">
                </div>
            </div>

        </div><!-- /step 8 -->

        <?php
        $accentClass = 'bg-indigo-700 hover:bg-indigo-800';
        $cancelUrl   = BASE_URL . '/patient_view.php?id=' . $patient_id;
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

    function showResults(items) {
        dropdown.innerHTML = '';
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
                + '<span class="text-slate-700 text-xs truncate">' + escHtml(item.description) + '</span>';
            row.addEventListener('click', function () {
                addCode({code: item.code, desc: item.description, cat: item.category || ''});
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
            fetch(BASE + '/api/icd_search.php?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(showResults)
                .catch(function () { dropdown.classList.add('hidden'); });
        }, 220);
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
    if (!canvas || !window.SignaturePad) return;

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
        if (canvas._sp) canvas._sp.clear();
        hidden.value = '';
        var ph = wrapper.querySelector('.sig-placeholder');
        if (ph) ph.style.display = '';
    });

    // Validate provider sig before form submit
    document.getElementById('mainForm').addEventListener('submit', function (e) {
        if (!hidden.value) {
            e.preventDefault();
            var alert = document.getElementById('providerSigAlert');
            if (alert) alert.classList.remove('hidden');
            canvas.scrollIntoView({behavior:'smooth', block:'center'});
        }
    }, true);
})();
</script>
JSBLOCK;

include __DIR__ . '/../includes/footer.php';
?>
