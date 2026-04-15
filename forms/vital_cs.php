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

// ── Pre-fill from most recent Visit Consent submission ────────────────
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
// Race was saved as array or comma-list — normalise to array
$prevRace = [];
if (isset($prev['race'])) {
    $prevRace = is_array($prev['race']) ? $prev['race'] : explode(',', $prev['race']);
}

// ── Load current active medication list (master list = source of truth) ────────
$activeMeds = [];
try {
    $medsStmt = $pdo->prepare("
        SELECT id, med_name, med_frequency
        FROM patient_medications
        WHERE patient_id = ? AND status = 'active'
        ORDER BY sort_order ASC, added_at ASC
        LIMIT 6
    ");
    $medsStmt->execute([$patient_id]);
    $activeMeds = $medsStmt->fetchAll();
} catch (PDOException $e) { /* table not yet migrated — fall back to empty */ }

// Build exactly 6 rows: pre-filled from master list first, then empty
$medRows = [];
foreach ($activeMeds as $m) {
    $medRows[] = [
        'med_id'   => $m['id'],
        'med_name' => $m['med_name'],
        'med_freq' => $m['med_frequency'],
        'med_type' => 'Refill',   // existing active meds default to Refill
    ];
}
while (count($medRows) < 6) {
    $medRows[] = ['med_id' => 0, 'med_name' => '', 'med_freq' => '', 'med_type' => ''];
}

$pageTitle = 'Visit Consent Form';
$activeNav = 'patients';
include __DIR__ . '/../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6 flex-wrap">
    <a href="<?= BASE_URL ?>/patients.php" class="hover:text-blue-600 font-medium">Patients</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patient_id ?>" class="hover:text-blue-600 font-medium">
        <?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>
    </a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold">Visit Consent Form</span>
</nav>

<div class="max-w-3xl">

<?php if ($prevDate): ?>
<div id="prefillBanner"
     class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-2xl px-5 py-3.5 mb-4 text-sm text-amber-800">
    <i class="bi bi-arrow-counterclockwise text-amber-500 text-lg shrink-0"></i>
    <div class="flex-1">
        <span class="font-semibold">Pre-filled from last visit</span>
        <span class="text-amber-600"> — <?= date('M j, Y', strtotime($prevDate)) ?></span>
        <span class="text-amber-600 text-xs ml-1">(pharmacy, allergies, medications &amp; vitals carried over — update as needed)</span>
    </div>
    <button onclick="document.getElementById('prefillBanner').remove()"
            class="text-amber-400 hover:text-amber-700 transition-colors p-1 rounded-lg hover:bg-amber-100">
        <i class="bi bi-x-lg text-sm"></i>
    </button>
</div>
<?php endif; ?>
<!-- Practice Header -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-5">
    <div class="bg-gradient-to-r from-red-700 to-red-600 px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-xl">
            <i class="bi bi-file-medical-fill text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-white font-bold text-lg"><?= h(PRACTICE_NAME) ?> — Consent Form</h2>
            <p class="text-red-100 text-sm"><?= h($patient['first_name'] . ' ' . $patient['last_name']) ?></p>
        </div>
    </div>

    <form id="mainForm" method="POST" action="<?= BASE_URL ?>/api/save_form.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="form_type"  value="vital_cs">

        <div class="p-6 space-y-6">

            <!-- Provider / Date -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Provider</label>
                    <input type="text" name="provider_name"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white"
                           placeholder="Attending provider name">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date of Visit</label>
                    <input type="date" name="form_date" value="<?= date('Y-m-d') ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white">
                </div>
            </div>

            <!-- Visit Type + F/U + Times -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-3">Visit Type</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                    <?php foreach (['New','Follow Up','Sick','Post Hospital F/U'] as $vt): ?>
                    <label class="flex items-center gap-2.5 p-3 border border-slate-200 rounded-xl cursor-pointer
                                  hover:border-red-300 hover:bg-red-50/50 transition-colors has-[:checked]:border-red-400 has-[:checked]:bg-red-50">
                        <input type="radio" name="visit_type" value="<?= $vt ?>"
                               class="w-4 h-4 text-red-600 border-slate-300 focus:ring-red-400 flex-shrink-0">
                        <span class="text-sm font-medium text-slate-700"><?= $vt ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">F/U in (weeks)</label>
                        <input type="number" name="fu_weeks" min="1" max="52"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white"
                               placeholder="e.g. 2">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Time In</label>
                        <input type="time" name="time_in"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Time Out</label>
                        <input type="time" name="time_out"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white">
                    </div>
                </div>
            </div>

            <!-- Homebound Status -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-3">Homebound Status</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer
                                  hover:border-red-300 hover:bg-red-50/50 transition-colors has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
                        <input type="radio" name="homebound" value="homebound"
                               class="w-4 h-4 text-red-600 border-slate-300 focus:ring-red-400">
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

            <!-- Missed Visit -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Missed Visit Reason <span class="text-slate-400 font-normal">(if applicable)</span>
                </label>
                <input type="text" name="missed_visit_reason"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white"
                       placeholder="Leave blank if not a missed visit">
            </div>

            <!-- Vitals Grid -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-3">Vital Signs</label>
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
                            <?php if ($prefilled): ?><span class="ml-1 text-amber-400" title="Pre-filled from last visit"><i class="bi bi-arrow-counterclockwise"></i></span><?php endif; ?>
                        </label>
                        <input type="text" name="<?= $v['name'] ?>" value="<?= $prefilled ?>"
                               class="w-full bg-transparent text-sm font-semibold text-slate-800 border-0 border-b border-slate-300 pb-1
                                      focus:outline-none focus:border-red-400 transition"
                               placeholder="<?= $v['placeholder'] ?>">
                        <p class="text-xs text-slate-400 mt-2">Checked or Per patient</p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Chief Complaint / Notes -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Chief Complaint / Notes</label>
                <textarea name="chief_complaint" rows="4"
                          class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                 focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white resize-none"
                          placeholder="Chief complaint and clinical notes..."></textarea>
            </div>

            <!-- ICD-10 Diagnosis Codes -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">
                    Diagnosis / ICD-10 Codes
                    <span class="ml-1.5 text-xs font-normal text-slate-400">(up to 6 — required for billing)</span>
                </label>

                <!-- Selected chips container -->
                <div id="icdChips" class="flex flex-wrap gap-2 mb-2 min-h-[2rem]"></div>
                <!-- Hidden inputs are injected here by JS -->
                <div id="icdHiddenInputs"></div>

                <!-- Search input -->
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i class="bi bi-search text-sm"></i>
                    </span>
                    <input type="text" id="icdSearch" autocomplete="off"
                           placeholder="Search by code or keyword (e.g. &quot;sacral stage 2&quot;, &quot;heel&quot;, &quot;cellulitis&quot;)…"
                           class="w-full pl-8 pr-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white">
                    <div id="icdDropdown"
                         class="hidden absolute z-40 left-0 right-0 top-full mt-1
                                bg-white border border-slate-200 rounded-xl shadow-2xl overflow-y-auto text-sm"
                         style="max-height:280px"></div>
                </div>
                <p id="icdMaxMsg" class="hidden text-xs text-amber-600 mt-1.5 font-semibold">
                    Maximum of 6 codes reached. Remove a code to add another.
                </p>
                <p class="text-xs text-slate-400 mt-1.5">
                    <i class="bi bi-info-circle text-slate-300 mr-0.5"></i>
                    Wound-care ICD-10 library — codes pre-filled from last visit when available.
                </p>
            </div>

            <!-- Pharmacy / Assistive Device / Race / Allergies -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Pharmacy</label>
                    <input type="text" name="pharmacy_name" value="<?= pv($prev,'pharmacy_name') ?>"
                           class="w-full px-4 py-3 border <?= pv($prev,'pharmacy_name') ? 'border-amber-300' : 'border-slate-200' ?> rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white"
                           placeholder="Pharmacy name">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Pharmacy Phone</label>
                    <input type="tel" name="pharmacy_phone" value="<?= pv($prev,'pharmacy_phone') ?>"
                           class="w-full px-4 py-3 border <?= pv($prev,'pharmacy_phone') ? 'border-amber-300' : 'border-slate-200' ?> rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white"
                           placeholder="Phone number">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Assistive Device</label>
                    <input type="text" name="assistive_device" value="<?= pv($prev,'assistive_device') ?>"
                           class="w-full px-4 py-3 border <?= pv($prev,'assistive_device') ? 'border-amber-300' : 'border-slate-200' ?> rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white"
                           placeholder="Cane, walker, wheelchair...">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Allergies</label>
                    <input type="text" name="allergies" value="<?= pv($prev,'allergies') ?>"
                           class="w-full px-4 py-3 border <?= pv($prev,'allergies') ? 'border-amber-300' : 'border-slate-200' ?> rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white"
                           placeholder="NKDA or list...">
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

            <!-- Medication List -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-3 flex flex-wrap items-center gap-2">
                    Medication List &amp; Reconciliation
                    <?php if (!empty($activeMeds)): ?>
                    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patient_id ?>&tab=meds"
                       class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 bg-emerald-50
                              border border-emerald-200 px-2 py-0.5 rounded-full hover:bg-emerald-100 transition-colors"
                       target="_blank">
                        <i class="bi bi-arrow-counterclockwise"></i><?= count($activeMeds) ?> from master list — Manage
                    </a>
                    <?php endif; ?>
                </label>
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
                                $i           = $mi + 1;
                                $isPrefilled = $row['med_id'] > 0;
                            ?>
                            <input type="hidden" name="med_id_<?= $i ?>" value="<?= $row['med_id'] ?>">
                            <tr class="<?= $isPrefilled ? 'bg-emerald-50/30' : '' ?>">
                                <td class="px-3 py-2">
                                    <select name="med_type_<?= $i ?>"
                                            class="w-full px-2 py-2 border <?= $isPrefilled ? 'border-emerald-200' : 'border-slate-200' ?> rounded-lg text-xs bg-white
                                                   focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent">
                                        <option value="">—</option>
                                        <?php foreach (['New','Refill','D/C'] as $opt): ?>
                                        <option <?= $row['med_type'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <?php if ($isPrefilled): ?><div class="flex items-center gap-1.5"><?php endif; ?>
                                    <?php if ($isPrefilled): ?><i class="bi bi-capsule text-emerald-500 text-xs shrink-0" title="From master list"></i><?php endif; ?>
                                    <input type="text" name="med_name_<?= $i ?>" value="<?= h($row['med_name']) ?>"
                                           class="w-full px-3 py-2 border <?= $isPrefilled ? 'border-emerald-200' : 'border-slate-200' ?> rounded-lg text-sm bg-white
                                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent"
                                           placeholder="Medication name and dose">
                                    <?php if ($isPrefilled): ?></div><?php endif; ?>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" name="med_freq_<?= $i ?>" value="<?= h($row['med_freq']) ?>"
                                           class="w-full px-3 py-2 border <?= $isPrefilled ? 'border-emerald-200' : 'border-slate-200' ?> rounded-lg text-sm bg-white
                                                  focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent"
                                           placeholder="e.g. BID">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-slate-400 mt-2">
                    <i class="bi bi-info-circle mr-0.5 text-emerald-500"></i>
                    Pre-filled from master med list. Set type to <strong class="text-red-600">D/C</strong> to discontinue &mdash;
                    <strong class="text-emerald-600">New</strong> rows are automatically added to the master list on save.
                </p>
            </div>
            <div class="max-w-xs">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Medical Assistant</label>
                <input type="text" name="ma_name" value="<?= h($_SESSION['full_name'] ?? '') ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition focus:bg-white">
            </div>

        </div><!-- /p-6 -->
    </form>
</div><!-- /card -->

<?php include __DIR__ . '/../includes/sig_block.php'; ?>

<div class="mt-5 flex flex-col sm:flex-row gap-3">
    <button id="submitBtn" type="button"
            class="flex-1 sm:flex-none flex items-center justify-center gap-2
                   bg-red-700 hover:bg-red-800 active:scale-95 text-white font-bold
                   px-10 py-3.5 rounded-xl transition-all shadow-md hover:shadow-lg text-base">
        <i class="bi bi-check2-circle text-xl"></i> Submit &amp; Save
    </button>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patient_id ?>"
       class="flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl text-sm font-semibold
              text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors">
        Cancel
    </a>
</div>
</div>

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

    /* ── Render chips ────────────────────────────────────────── */
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

            // Hidden input — stored as "CODE — desc" string in array
            var inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = 'icd10_codes[]';
            inp.value = item.code + ' — ' + item.desc;
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

    /* ── Add code (guard duplicates + max) ──────────────────── */
    function addCode(item) {
        if (selected.length >= MAX) return;
        if (selected.some(function (s) { return s.code === item.code; })) return;
        selected.push(item);
        renderChips();
        searchEl.value = '';
        closeDropdown();
        searchEl.focus();
    }

    /* ── Dropdown ────────────────────────────────────────────── */
    function closeDropdown() { dropdown.classList.add('hidden'); }

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

    /* ── Search (debounced) ─────────────────────────────────── */
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
            dropdown.classList.remove('hidden');
        }
    });

    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target) && e.target !== searchEl) closeDropdown();
    });

    /* ── Keyboard: close on Escape ──────────────────────────── */
    searchEl.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeDropdown(); searchEl.blur(); }
    });

    /* ── Pre-fill from last visit ────────────────────────────── */
    var prefill = $icdPrefillJson;
    if (Array.isArray(prefill)) {
        prefill.forEach(function (raw) {
            // raw is "CODE — desc" string
            var m = raw.match(/^([A-Z0-9.]+)\s+\u2014\s+(.+)$/);
            if (m) addCode({ code: m[1], desc: m[2], cat: '' });
        });
    }

    /* ── Simple HTML escape ─────────────────────────────────── */
    function escHtml(s) {
        return String(s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>
JSBLOCK;
?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
