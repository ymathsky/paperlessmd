<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireAdminOrScheduler();

$pageTitle = 'Manage Schedule';
$activeNav = 'schedule';

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));

// Handle add
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { http_response_code(403); die('Invalid request.'); }
    $maId      = (int)($_POST['ma_id']      ?? 0);
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $visitDate = $_POST['visit_date'] ?? $date;
    $visitTime = $_POST['visit_time'] ?: null;
    $visitType = $_POST['visit_type'] ?? 'routine';
    $allowedTypes = ['routine','new_patient','wound_care','awv','ccm','il'];
    if (!in_array($visitType, $allowedTypes, true)) $visitType = 'routine';
    $notes        = trim($_POST['notes']         ?? '');
    $providerName = trim($_POST['provider_name'] ?? '');
    $allowedCompanies = ['Beyond Wound Care Inc.', 'Visiting Medical Physician Inc.'];
    $company = in_array($_POST['company'] ?? '', $allowedCompanies, true) ? $_POST['company'] : 'Beyond Wound Care Inc.';
    $allowedSubtypes = ['wound_care', 'primary_care'];
    $visitSubtype = ($visitType === 'new_patient' && in_array($_POST['visit_subtype'] ?? '', $allowedSubtypes, true))
        ? $_POST['visit_subtype'] : null;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitDate)) $errors[] = 'Invalid date.';
    if (!$maId)      $errors[] = 'Please select an MA.';
    if (!$patientId) $errors[] = 'Please select a patient.';

    // Check for duplicate (same MA + patient + date)
    if (!$errors) {
        $dupChk = $pdo->prepare("SELECT id FROM `schedule` WHERE ma_id=? AND patient_id=? AND visit_date=?");
        $dupChk->execute([$maId, $patientId, $visitDate]);
        if ($dupChk->fetch()) $errors[] = 'This patient is already assigned to this MA on that date.';
    }

    if (!$errors) {
        // Auto-set visit_order to end of list for this MA+date
        $orderStmt = $pdo->prepare("SELECT COALESCE(MAX(visit_order),0)+1 FROM `schedule` WHERE ma_id=? AND visit_date=?");
        $orderStmt->execute([$maId, $visitDate]);
        $nextOrder = (int)$orderStmt->fetchColumn();

        $ins = $pdo->prepare("INSERT INTO `schedule` (visit_date,ma_id,patient_id,visit_time,visit_order,visit_type,visit_subtype,notes,provider_name,company,created_by)
                               VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $ins->execute([$visitDate, $maId, $patientId, $visitTime, $nextOrder, $visitType, $visitSubtype, $notes ?: null, $providerName ?: null, $company, $_SESSION['user_id']]);
        $date = $visitDate;

        require_once __DIR__ . '/../includes/mailer.php';
        require_once __DIR__ . '/../includes/notifications.php';
        notifyScheduleAssigned($pdo, $maId, $patientId, $visitDate, $visitType, $providerName, (int)$_SESSION['user_id']);

        header('Location: ' . BASE_URL . '/admin/schedule_manage.php?date=' . $date . '&saved=1');
        exit;
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { http_response_code(403); die('Invalid request.'); }
    $delId = (int)($_POST['entry_id'] ?? 0);
    if ($delId) {
        $pdo->prepare("DELETE FROM `schedule` WHERE id=?")->execute([$delId]);
    }
    header('Location: ' . BASE_URL . '/admin/schedule_manage.php?date=' . $date . '&deleted=1');
    exit;
}

// Handle reorder (save order via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reorder') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { http_response_code(403); die('Invalid request.'); }
    $ids = array_map('intval', explode(',', $_POST['order'] ?? ''));
    foreach ($ids as $pos => $id) {
        if ($id) $pdo->prepare("UPDATE `schedule` SET visit_order=? WHERE id=?")->execute([$pos+1, $id]);
    }
    header('Location: ' . BASE_URL . '/admin/schedule_manage.php?date=' . $date . '&saved=1');
    exit;
}

// Ensure recurring_rule_id column exists (idempotent)
try { $pdo->exec("ALTER TABLE `schedule` ADD COLUMN `recurring_rule_id` INT NULL"); } catch (PDOException $e) {}

// Fetch all MAs
$allMas = $pdo->query("SELECT id, full_name FROM staff WHERE active=1 ORDER BY full_name")->fetchAll();

// Fetch all patients (for add form)
$allPatients = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) AS name, address, phone FROM patients ORDER BY last_name,first_name")->fetchAll();

// Provider staff accounts for the dropdown (provider role + admin accounts act as providers)
$providerStaff = $pdo->query("SELECT full_name FROM staff WHERE active=1 AND role IN ('provider','admin') AND username != 'admin' ORDER BY full_name")->fetchAll(PDO::FETCH_COLUMN);

// Fetch schedule grouped by Provider
$schedStmt = $pdo->prepare("
    SELECT sc.*,
           CONCAT(p.first_name,' ',p.last_name) AS patient_name,
           p.address AS patient_address,
           s.full_name AS ma_name
    FROM `schedule` sc
    JOIN patients p ON p.id = sc.patient_id
    JOIN staff s ON s.id = sc.ma_id
    WHERE sc.visit_date = ?
    ORDER BY sc.provider_name ASC, sc.visit_order ASC, sc.visit_time ASC
");
$schedStmt->execute([$date]);
$allEntries = $schedStmt->fetchAll();

// Group by provider_name (fallback to '— Unassigned —' when null)
$byProvider = [];
foreach ($allEntries as $e) {
    $pKey = $e['provider_name'] ?: '— Unassigned —';
    $byProvider[$pKey] = $byProvider[$pKey] ?? ['name' => $pKey, 'visits' => []];
    $byProvider[$pKey]['visits'][] = $e;
}
ksort($byProvider); // alphabetical, Unassigned goes last because '—' sorts after letters in most locales
// Move Unassigned to end
if (isset($byProvider['— Unassigned —'])) {
    $tmp = $byProvider['— Unassigned —'];
    unset($byProvider['— Unassigned —']);
    $byProvider['— Unassigned —'] = $tmp;
}

$statusDefs = [
    'pending'   => ['label'=>'Pending',   'bg'=>'bg-slate-100',   'text'=>'text-slate-600',   'icon'=>'bi-clock'],
    'en_route'  => ['label'=>'En Route',  'bg'=>'bg-blue-100',    'text'=>'text-blue-700',    'icon'=>'bi-car-front-fill'],
    'completed' => ['label'=>'Completed', 'bg'=>'bg-emerald-100', 'text'=>'text-emerald-700', 'icon'=>'bi-check-circle-fill'],
    'missed'    => ['label'=>'Missed',    'bg'=>'bg-red-100',     'text'=>'text-red-700',     'icon'=>'bi-x-circle-fill'],
];

$saved   = isset($_GET['saved']);
$deleted = isset($_GET['deleted']);

// Summary stats across all entries for today
$totalStats = ['pending'=>0,'en_route'=>0,'completed'=>0,'missed'=>0];
foreach ($allEntries as $e) $totalStats[$e['status']]++;

// Visit type — left-border color and badge color
$vtMeta = [
    'routine'     => ['border'=>'border-l-indigo-400',  'badge'=>'bg-indigo-100 text-indigo-700',  'label'=>'Routine'],
    'new_patient' => ['border'=>'border-l-emerald-400', 'badge'=>'bg-emerald-100 text-emerald-700','label'=>'New Patient'],
    'wound_care'  => ['border'=>'border-l-rose-400',    'badge'=>'bg-rose-100 text-rose-700',      'label'=>'Wound Care'],
    'awv'         => ['border'=>'border-l-violet-400',  'badge'=>'bg-violet-100 text-violet-700',  'label'=>'AWV'],
    'ccm'         => ['border'=>'border-l-blue-400',    'badge'=>'bg-blue-100 text-blue-700',      'label'=>'CCM'],
    'il'          => ['border'=>'border-l-amber-500',   'badge'=>'bg-amber-100 text-amber-700',    'label'=>'IL Disc.'],
];

include __DIR__ . '/../includes/header.php';
?>

<?php if ($saved): ?>
<div id="toast" class="fixed top-20 right-4 z-50 flex items-center gap-3 bg-emerald-600 text-white px-5 py-3.5 rounded-2xl shadow-2xl text-sm font-semibold">
    <i class="bi bi-check-circle-fill text-lg"></i> Schedule saved!
</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.remove();},3000);</script>
<?php endif; ?>
<?php if ($deleted): ?>
<div id="toast" class="fixed top-20 right-4 z-50 flex items-center gap-3 bg-rose-600 text-white px-5 py-3.5 rounded-2xl shadow-2xl text-sm font-semibold">
    <i class="bi bi-trash3-fill text-lg"></i> Visit removed.
</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.remove();},3000);</script>
<?php endif; ?>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-800">
            <i class="bi bi-calendar-week-fill text-indigo-500 mr-1"></i> Manage Schedule
        </h2>
        <p class="text-slate-500 text-sm mt-0.5"><?= date('l, F j, Y', strtotime($date)) ?></p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/admin/schedule_manage.php?date=<?= $prevDate ?>"
           class="p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-600 transition-colors">
            <i class="bi bi-chevron-left text-sm"></i>
        </a>
        <a href="<?= BASE_URL ?>/admin/schedule_manage.php?date=<?= date('Y-m-d') ?>"
           class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors <?= $date === date('Y-m-d') ? 'border-indigo-300 text-indigo-600' : '' ?>">
            Today
        </a>
        <a href="<?= BASE_URL ?>/admin/schedule_manage.php?date=<?= $nextDate ?>"
           class="p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-600 transition-colors">
            <i class="bi bi-chevron-right text-sm"></i>
        </a>
        <input type="date" id="datePicker" value="<?= $date ?>"
               onchange="window.location='<?= BASE_URL ?>/admin/schedule_manage.php?date=' + this.value"
               class="px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 cursor-pointer hover:bg-slate-50 transition-colors">
        <a href="<?= BASE_URL ?>/schedule.php?date=<?= $date ?>"
           class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-700 rounded-xl text-sm font-semibold hover:bg-slate-50 transition-colors">
            <i class="bi bi-eye-fill text-indigo-400"></i> View
        </a>
        <a href="<?= BASE_URL ?>/admin/recurring_schedule.php"
           class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-700 rounded-xl text-sm font-semibold hover:bg-slate-50 transition-colors">
            <i class="bi bi-arrow-repeat text-indigo-400"></i> Recurring
        </a>
        <button onclick="window.print()"
                class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-700 rounded-xl text-sm font-semibold hover:bg-slate-50 transition-colors">
            <i class="bi bi-printer-fill text-slate-400"></i> Print
        </button>
    </div>
</div>

<!-- Errors -->
<?php if ($errors): ?>
<div class="bg-red-50 border border-red-200 rounded-2xl px-5 py-4 mb-5 flex items-start gap-3 text-sm text-red-700">
    <i class="bi bi-exclamation-triangle-fill text-red-500 mt-0.5 text-lg flex-shrink-0"></i>
    <div>
        <p class="font-bold mb-1">Please fix the following:</p>
        <ul class="space-y-0.5 list-disc list-inside"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════ ASSIGN NEW VISIT ═══════════════════════ -->
<div class="bg-white border border-slate-200 rounded-2xl shadow-sm mb-6 overflow-hidden" id="assignCard">

    <!-- Header toggle -->
    <button type="button" onclick="toggleAssignForm()"
            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-slate-50 transition-colors group">
        <span class="flex items-center gap-3">
            <span class="w-9 h-9 bg-indigo-100 group-hover:bg-indigo-200 rounded-xl grid place-items-center transition-colors">
                <i class="bi bi-calendar-plus-fill text-indigo-600 text-base leading-none"></i>
            </span>
            <span>
                <span class="block font-bold text-slate-800 text-base">Assign New Visit</span>
                <span class="block text-xs text-slate-400 font-normal mt-0.5">Schedule a patient visit for a staff member</span>
            </span>
        </span>
        <i class="bi bi-chevron-down text-slate-400 text-sm transition-transform duration-200" id="assignChevron"></i>
    </button>

    <!-- Form body -->
    <div id="addForm" class="<?= $errors ? '' : 'hidden' ?>">
        <div class="h-px bg-gradient-to-r from-indigo-200 via-violet-200 to-indigo-200"></div>
        <form method="POST" id="assignForm" class="p-6" onsubmit="return validateAssignForm()">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="add">

            <!-- Row 0a: Practice / Company -->
            <div class="mb-5 p-4 bg-slate-50 border border-slate-200 rounded-2xl">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <span class="w-5 h-5 bg-slate-200 rounded-md grid place-items-center text-slate-600 text-[10px]">1</span>
                    <i class="bi bi-building-fill text-slate-400"></i> Practice
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <label class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition-all
                                  has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50
                                  [&:not(:has(:checked))]:border-slate-200 [&:not(:has(:checked))]:bg-white">
                        <input type="radio" name="company" value="Beyond Wound Care Inc."
                               class="w-4 h-4 text-blue-600 border-slate-300 flex-shrink-0"
                               <?= ($_POST['company'] ?? 'Beyond Wound Care Inc.') === 'Beyond Wound Care Inc.' ? 'checked' : '' ?>>
                        <div class="leading-tight min-w-0">
                            <div class="font-semibold text-sm text-slate-800">Beyond Wound Care Inc.</div>
                            <div class="text-xs text-slate-500">BWC</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition-all
                                  has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50
                                  [&:not(:has(:checked))]:border-slate-200 [&:not(:has(:checked))]:bg-white">
                        <input type="radio" name="company" value="Visiting Medical Physician Inc."
                               class="w-4 h-4 text-teal-600 border-slate-300 flex-shrink-0"
                               <?= ($_POST['company'] ?? '') === 'Visiting Medical Physician Inc.' ? 'checked' : '' ?>>
                        <div class="leading-tight min-w-0">
                            <div class="font-semibold text-sm text-slate-800">Visiting Medical Physician Inc.</div>
                            <div class="text-xs text-slate-500">VMP</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Row 0b: Provider (prioritized) -->
            <div class="mb-5 p-4 bg-teal-50 border border-teal-200 rounded-2xl">
                <p class="text-xs font-bold text-teal-700 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <span class="w-5 h-5 bg-teal-200 rounded-md grid place-items-center text-teal-700 text-[10px]">2</span>
                    Attending Provider
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-bold text-teal-700 uppercase tracking-wide mb-1.5">
                            <i class="bi bi-person-badge-fill text-teal-500 mr-1"></i>Select Provider <span class="text-red-400">*</span>
                        </label>
                        <select name="provider_name" id="providerSelect"
                                onchange="updateAssignSummary()"
                                class="w-full px-3 py-3 border-2 border-teal-300 rounded-xl text-sm bg-white font-semibold
                                       focus:outline-none focus:ring-2 focus:ring-teal-400 focus:border-teal-400 transition">
                            <option value="">— Select a provider first —</option>
                            <?php foreach ($providerStaff as $pn): ?>
                            <option value="<?= h($pn) ?>" <?= ($_POST['provider_name'] ?? '') === $pn ? 'selected' : '' ?>>
                                <?= h($pn) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="providerSelectedBadge" class="<?= ($_POST['provider_name'] ?? '') ? '' : 'hidden' ?> flex items-center gap-2 px-4 py-3 bg-teal-100 border border-teal-200 rounded-xl">
                        <div class="w-9 h-9 bg-teal-500 rounded-xl grid place-items-center shrink-0">
                            <i class="bi bi-person-badge-fill text-white"></i>
                        </div>
                        <div>
                            <p class="text-xs text-teal-600 font-medium">Assigned Provider</p>
                            <p class="text-sm font-bold text-teal-800" id="providerBadgeName"><?= h($_POST['provider_name'] ?? '') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 1: Who & When -->
            <div class="mb-5">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <span class="w-5 h-5 bg-indigo-100 rounded-md grid place-items-center text-indigo-500 text-[10px]">3</span>
                    Who &amp; When
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                    <!-- MA / Staff -->
                    <div class="lg:col-span-1">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">
                            <i class="bi bi-person-fill text-indigo-400 mr-1"></i>MA / Staff <span class="text-red-400">*</span>
                        </label>
                        <select name="ma_id" id="maSelect" required
                                onchange="updateMaPreview(this)"
                                class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white
                                       focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition">
                            <option value="">Select staff member…</option>
                            <?php foreach ($allMas as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= (($_POST['ma_id']??0)==$m['id'])?'selected':'' ?>>
                                <?= h($m['full_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Patient -->
                    <div class="lg:col-span-1">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">
                            <i class="bi bi-person-heart text-rose-400 mr-1"></i>Patient <span class="text-red-400">*</span>
                        </label>
                        <select name="patient_id" id="patientSelect" required
                                onchange="updatePatientPreview(this)"
                                class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white
                                       focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition">
                            <option value="">Search patient…</option>
                            <?php foreach ($allPatients as $pt): ?>
                            <option value="<?= $pt['id'] ?>"
                                    data-addr="<?= h($pt['address'] ?? '') ?>"
                                    data-phone="<?= h($pt['phone'] ?? '') ?>"
                                    <?= (($_POST['patient_id']??0)==$pt['id'])?'selected':'' ?>>
                                <?= h($pt['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Patient quick-info preview -->
                        <div id="patientPreview" class="hidden mt-2 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600 space-y-0.5">
                            <div id="ptAddr" class="flex items-start gap-1.5"><i class="bi bi-geo-alt text-slate-400 mt-0.5"></i><span></span></div>
                            <div id="ptPhone" class="flex items-center gap-1.5"><i class="bi bi-telephone text-slate-400"></i><span></span></div>
                        </div>
                    </div>

                    <!-- Visit Date -->
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">
                            <i class="bi bi-calendar3 text-violet-400 mr-1"></i>Visit Date <span class="text-red-400">*</span>
                        </label>
                        <input type="date" name="visit_date" id="visitDate"
                               value="<?= h($_POST['visit_date'] ?? $date) ?>" required
                               class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white
                                      focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition">
                    </div>

                    <!-- Visit Time -->
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">
                            <i class="bi bi-clock text-amber-400 mr-1"></i>Visit Time
                            <span class="font-normal text-slate-400 normal-case tracking-normal">(optional)</span>
                        </label>
                        <input type="time" name="visit_time"
                               value="<?= h($_POST['visit_time'] ?? '') ?>"
                               class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white
                                      focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition">
                    </div>
                </div>
            </div>

            <!-- Row 2: Visit Details -->
            <div class="mb-5">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <span class="w-5 h-5 bg-indigo-100 rounded-md grid place-items-center text-indigo-500 text-[10px]">4</span>
                    Visit Details
                </p>

                <!-- Visit Type — pill selector -->
                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Visit Type</label>
                    <input type="hidden" name="visit_type" id="visitTypeInput" value="<?= h($_POST['visit_type'] ?? 'routine') ?>">
                    <input type="hidden" name="visit_subtype" id="visitSubtypeInput" value="<?= h($_POST['visit_subtype'] ?? 'wound_care') ?>">
                    <div class="flex flex-wrap gap-2" id="visitTypePills">
                        <?php
                        $vtPills = [
                            ['val'=>'routine',     'label'=>'Routine',      'icon'=>'bi-activity',          'color'=>'indigo'],
                            ['val'=>'new_patient', 'label'=>'New Patient',  'icon'=>'bi-person-plus-fill',  'color'=>'emerald'],
                        ];
                        $selVt = $_POST['visit_type'] ?? 'routine';
                        foreach ($vtPills as $vp):
                            $active = $selVt === $vp['val'];
                        ?>
                        <button type="button"
                                onclick="selectVisitType('<?= $vp['val'] ?>', this)"
                                data-val="<?= $vp['val'] ?>"
                                class="vt-pill inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all
                                       <?= $active
                                           ? "bg-{$vp['color']}-600 text-white border-{$vp['color']}-600 shadow-sm"
                                           : "bg-white text-slate-600 border-slate-200 hover:border-{$vp['color']}-300 hover:text-{$vp['color']}-600" ?>">
                            <i class="bi <?= $vp['icon'] ?>"></i> <?= $vp['label'] ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- New Patient sub-type selector (shown only when New Patient is selected) -->
                <div id="newPatientSubtypeRow" class="<?= ($_POST['visit_type'] ?? 'routine') === 'new_patient' ? '' : 'hidden' ?> mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">New Patient Type</label>
                    <div class="flex gap-3">
                        <?php
                        $selVs = $_POST['visit_subtype'] ?? 'wound_care';
                        $subtypePills = [
                            ['val'=>'wound_care',   'label'=>'Wound Care',   'icon'=>'bi-bandaid-fill'],
                            ['val'=>'primary_care', 'label'=>'Primary Care', 'icon'=>'bi-heart-pulse-fill'],
                        ];
                        foreach ($subtypePills as $sp):
                            $activeS = $selVs === $sp['val'];
                        ?>
                        <button type="button"
                                onclick="selectVisitSubtype('<?= $sp['val'] ?>', this)"
                                data-val="<?= $sp['val'] ?>"
                                class="np-subtype-pill inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold border transition-all
                                       <?= $activeS ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:border-emerald-300 hover:text-emerald-600' ?>">
                            <i class="bi <?= $sp['icon'] ?>"></i> <?= $sp['label'] ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Notes (provider moved to top) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">
                            <i class="bi bi-sticky-fill text-amber-400 mr-1"></i>Dispatch Notes
                            <span class="font-normal text-slate-400 normal-case tracking-normal">(optional)</span>
                        </label>
                        <input type="text" name="notes" value="<?= h($_POST['notes'] ?? '') ?>"
                               placeholder="e.g. Use back entrance, patient has a dog…"
                               class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white
                                      focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition">
                    </div>
                </div>
            </div>

            <!-- Footer: summary + submit -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pt-4 border-t border-slate-100">
                <!-- Live preview summary -->
                <div id="assignSummary" class="flex items-center gap-2 text-sm text-slate-500">
                    <i class="bi bi-info-circle text-slate-400"></i>
                    <span id="summaryText">Fill in the required fields above to assign a visit.</span>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <button type="button" onclick="resetAssignForm()"
                            class="px-4 py-2.5 text-sm font-semibold text-slate-500 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                        <i class="bi bi-arrow-counterclockwise mr-1"></i> Reset
                    </button>
                    <button type="submit" id="assignSubmitBtn"
                            class="flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-bold rounded-xl text-sm transition-all shadow-sm">
                        <i class="bi bi-calendar-check-fill"></i> Assign Visit
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
// ── Assign form helpers ───────────────────────────────────────────────────────
function toggleAssignForm() {
    const form = document.getElementById('addForm');
    const chev = document.getElementById('assignChevron');
    form.classList.toggle('hidden');
    chev.style.transform = form.classList.contains('hidden') ? '' : 'rotate(180deg)';
}

function selectVisitSubtype(val, btn) {
    document.getElementById('visitSubtypeInput').value = val;
    document.querySelectorAll('.np-subtype-pill').forEach(p => {
        if (p.dataset.val === val) {
            p.className = p.className.replace(/bg-white text-slate-600 border-slate-200[^"']*/g, '').trim();
            p.classList.add('bg-emerald-600', 'text-white', 'border-emerald-600', 'shadow-sm');
        } else {
            p.className = p.className.replace(/bg-emerald-600 text-white border-emerald-600 shadow-sm/g, '').trim();
            p.classList.add('bg-white', 'text-slate-600', 'border-slate-200');
        }
    });
}

function selectVisitType(val, btn) {
    document.getElementById('visitTypeInput').value = val;
    const subRow = document.getElementById('newPatientSubtypeRow');
    if (subRow) subRow.classList.toggle('hidden', val !== 'new_patient');
    document.querySelectorAll('.vt-pill').forEach(p => {
        const pVal  = p.dataset.val;
        const colors = {routine:'indigo',new_patient:'emerald',wound_care:'rose',awv:'violet',ccm:'blue',il:'amber'};
        const c = colors[pVal] || 'indigo';
        if (pVal === val) {
            p.className = p.className.replace(/bg-\S+ text-\S+ border-\S+( shadow-sm)?/g,'').trim();
            p.classList.add('bg-'+c+'-600','text-white','border-'+c+'-600','shadow-sm');
        } else {
            p.className = p.className.replace(/bg-\S+-600 text-white border-\S+-600 shadow-sm/g,'').trim();
            p.classList.add('bg-white','text-slate-600','border-slate-200');
        }
    });
    updateSummary();
}

function updatePatientPreview(sel) {
    const opt     = sel.options[sel.selectedIndex];
    const preview = document.getElementById('patientPreview');
    const addr    = opt.dataset.addr || '';
    const phone   = opt.dataset.phone || '';
    if (sel.value && (addr || phone)) {
        document.querySelector('#ptAddr span').textContent  = addr  || '—';
        document.querySelector('#ptPhone span').textContent = phone || '—';
        preview.classList.remove('hidden');
    } else {
        preview.classList.add('hidden');
    }
    updateSummary();
}

function updateMaPreview(sel) { updateSummary(); }

function updateAssignSummary() {
    // Provider badge
    const pSel  = document.getElementById('providerSelect');
    const badge = document.getElementById('providerSelectedBadge');
    const bName = document.getElementById('providerBadgeName');
    if (pSel && pSel.value) {
        bName.textContent = pSel.options[pSel.selectedIndex].text;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
    updateSummary();
}

function updateSummary() {
    const ma       = document.getElementById('maSelect');
    const pt       = document.getElementById('patientSelect');
    const dt       = document.getElementById('visitDate');
    const prov     = document.getElementById('providerSelect');
    const vtLabels = {routine:'Routine',new_patient:'New Patient',wound_care:'Wound Care',awv:'AWV',ccm:'CCM',il:'IL Disclosure'};
    const vt       = vtLabels[document.getElementById('visitTypeInput').value] || '';
    const txt      = document.getElementById('summaryText');

    if (ma.value && pt.value && dt.value) {
        const maName   = ma.options[ma.selectedIndex].text;
        const ptName   = pt.options[pt.selectedIndex].text;
        const provName = prov && prov.value ? prov.options[prov.selectedIndex].text : '';
        const dtFmt    = new Date(dt.value + 'T00:00:00').toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'});
        txt.innerHTML = '<strong class="text-slate-700">' + ptName + '</strong> &rarr; '
                      + '<strong class="text-indigo-600">' + maName + '</strong>'
                      + (provName ? ' &bull; <span class="text-teal-600 font-semibold">' + provName + '</span>' : '')
                      + ' &bull; ' + dtFmt + ' &bull; ' + vt;
        document.getElementById('assignSubmitBtn').classList.remove('opacity-60');
    } else {
        txt.textContent = 'Fill in the required fields above to assign a visit.';
    }
}

function resetAssignForm() {
    document.getElementById('assignForm').reset();
    document.getElementById('patientPreview').classList.add('hidden');
    document.getElementById('providerSelectedBadge').classList.add('hidden');
    document.getElementById('visitTypeInput').value = 'routine';
    document.getElementById('visitSubtypeInput').value = 'wound_care';
    const subRow = document.getElementById('newPatientSubtypeRow');
    if (subRow) subRow.classList.add('hidden');
    document.querySelectorAll('.vt-pill').forEach(p => {
        selectVisitType('routine', null);
    });
    document.getElementById('summaryText').textContent = 'Fill in the required fields above to assign a visit.';
}

function validateAssignForm() {
    const btn = document.getElementById('assignSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin mr-1"></i> Saving…';
    return true;
}

// Wire up live summary on date change
document.getElementById('visitDate')?.addEventListener('change', updateSummary);

// Auto-open if there were errors
<?php if ($errors): ?>toggleAssignForm();<?php endif; ?>
</script>

<!-- Schedule grid per MA -->
<?php
$totalVisits = count($allEntries);
$completedVisits = $totalStats['completed'];
$pctComplete = $totalVisits > 0 ? round($completedVisits / $totalVisits * 100) : 0;
?>
<?php if ($totalVisits > 0): ?>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    <?php
    $summaryCards = [
        'pending'   => ['label'=>'Pending',   'icon'=>'bi-clock-fill',       'bg'=>'bg-slate-50',    'iconBg'=>'bg-slate-200',   'iconText'=>'text-slate-600', 'text'=>'text-slate-700'],
        'en_route'  => ['label'=>'En Route',  'icon'=>'bi-car-front-fill',   'bg'=>'bg-blue-50',     'iconBg'=>'bg-blue-200',    'iconText'=>'text-blue-700',  'text'=>'text-blue-800'],
        'completed' => ['label'=>'Completed', 'icon'=>'bi-check-circle-fill','bg'=>'bg-emerald-50',  'iconBg'=>'bg-emerald-200', 'iconText'=>'text-emerald-700','text'=>'text-emerald-800'],
        'missed'    => ['label'=>'Missed',    'icon'=>'bi-x-circle-fill',    'bg'=>'bg-red-50',      'iconBg'=>'bg-red-200',     'iconText'=>'text-red-600',   'text'=>'text-red-700'],
    ];
    foreach ($summaryCards as $key => $sc):
    ?>
    <div class="<?= $sc['bg'] ?> rounded-2xl border border-slate-100 p-4 flex items-center gap-3">
        <div class="<?= $sc['iconBg'] ?> w-10 h-10 rounded-xl grid place-items-center shrink-0">
            <i class="bi <?= $sc['icon'] ?> <?= $sc['iconText'] ?> text-lg leading-none"></i>
        </div>
        <div>
            <div class="text-2xl font-extrabold <?= $sc['text'] ?>"><?= $totalStats[$key] ?></div>
            <div class="text-xs font-medium text-slate-500"><?= $sc['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<div class="bg-white border border-slate-100 rounded-2xl px-5 py-3.5 mb-6 flex items-center gap-4 shadow-sm">
    <div class="text-sm font-semibold text-slate-700 shrink-0"><?= $totalVisits ?> visits total</div>
    <div class="flex-1 bg-slate-100 rounded-full h-2.5 overflow-hidden">
        <div class="bg-emerald-500 h-2.5 rounded-full transition-all" style="width:<?= $pctComplete ?>%"></div>
    </div>
    <div class="text-sm font-bold text-emerald-600 shrink-0"><?= $pctComplete ?>% complete</div>
</div>
<?php endif; ?>
<?php if (empty($byProvider)): ?>
<div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-12 text-center">
    <div class="w-16 h-16 bg-indigo-50 rounded-2xl grid place-items-center mx-auto mb-4">
        <i class="bi bi-calendar-x text-indigo-400 text-3xl"></i>
    </div>
    <p class="text-slate-600 font-semibold text-lg mb-1">No visits assigned for this date</p>
    <p class="text-slate-400 text-sm">Use the form above to start building the route list.</p>
</div>
<?php else: ?>

<?php foreach ($byProvider as $providerKey => $providerGroup):
    $isUnassigned = $providerKey === '— Unassigned —';
    $provCounts = ['pending'=>0,'en_route'=>0,'completed'=>0,'missed'=>0];
    foreach ($providerGroup['visits'] as $mv) $provCounts[$mv['status']]++;
    $provDone  = $provCounts['completed'];
    $provTotal = count($providerGroup['visits']);
    $provPct   = $provTotal > 0 ? round($provDone / $provTotal * 100) : 0;
    // Collect unique MAs under this provider
    $maUnder = [];
    foreach ($providerGroup['visits'] as $pv) {
        if (!isset($maUnder[$pv['ma_id']])) $maUnder[$pv['ma_id']] = $pv['ma_name'];
    }
?>
<div class="bg-white border border-slate-100 rounded-2xl shadow-sm mb-5">
    <div class="px-6 py-4 border-b border-slate-100">
        <div class="flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 <?= $isUnassigned ? 'bg-slate-300 text-slate-600' : 'bg-teal-600 text-white' ?> rounded-xl grid place-items-center font-bold text-sm shrink-0">
                    <?php if ($isUnassigned): ?><i class="bi bi-question-lg"></i><?php else: ?><?= strtoupper(mb_substr($providerKey, 0, 2)) ?><?php endif; ?>
                </div>
                <div>
                    <div class="font-bold text-slate-800 text-base flex items-center gap-2">
                        <?php if (!$isUnassigned): ?><i class="bi bi-person-badge text-teal-500 text-sm"></i><?php endif; ?>
                        <?= h($providerKey) ?>
                    </div>
                    <div class="text-xs text-slate-500">
                        <?= $provTotal ?> visit<?= $provTotal !== 1 ? 's' : '' ?> &mdash; <?= $provDone ?> completed
                        <?php if (!empty($maUnder)): ?>
                        &bull; <span class="text-slate-400">MAs: <?= h(implode(', ', $maUnder)) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap justify-end">
                <?php foreach ([
                    'pending'   => ['bg-slate-100',   'text-slate-600',   'bi-clock'],
                    'en_route'  => ['bg-blue-100',    'text-blue-700',    'bi-car-front-fill'],
                    'completed' => ['bg-emerald-100', 'text-emerald-700', 'bi-check-circle-fill'],
                    'missed'    => ['bg-red-100',     'text-red-600',     'bi-x-circle-fill'],
                ] as $sk => [$sbg, $stxt, $sico]):
                    if (!$provCounts[$sk]) continue;
                ?>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold <?= $sbg ?> <?= $stxt ?>">
                    <i class="bi <?= $sico ?> text-xs"></i> <?= $provCounts[$sk] ?>
                </span>
                <?php endforeach; ?>
                <?php foreach ($maUnder as $maIdUnder => $maNameUnder): ?>
                <a href="<?= BASE_URL ?>/schedule.php?date=<?= $date ?>&ma_id=<?= $maIdUnder ?>"
                   class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 px-2.5 py-1 bg-indigo-50 rounded-full hover:bg-indigo-100 transition-colors">
                    <i class="bi bi-eye"></i> <?= h(mb_substr($maNameUnder,0,10)) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <!-- Progress bar -->
        <div class="flex items-center gap-3">
            <div class="flex-1 bg-slate-100 rounded-full h-1.5 overflow-hidden">
                <div class="bg-emerald-500 h-1.5 rounded-full transition-all" style="width:<?= $provPct ?>%"></div>
            </div>
            <span class="text-xs font-bold text-slate-400 shrink-0"><?= $provPct ?>%</span>
        </div>
    </div>

    <div class="divide-y divide-slate-100" id="sortable-<?= md5($providerKey) ?>">
        <?php foreach ($providerGroup['visits'] as $idx => $v):
            $sd   = $statusDefs[$v['status']];
            $vt   = $v['visit_type'] ?? 'routine';
            $vtm  = $vtMeta[$vt] ?? $vtMeta['routine'];
            $visitTypeText = $vtm['label'];
            if ($vt === 'new_patient' && !empty($v['visit_subtype'])) {
                $visitTypeText .= ' • ' . ($v['visit_subtype'] === 'primary_care' ? 'Primary Care' : ucwords(str_replace('_', ' ', $v['visit_subtype'])));
            }
            $addr = $v['patient_address'] ?? '';
            $mapsUrl = $addr ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($addr) : '';
        ?>
        <div class="flex items-center gap-3 px-4 py-3.5 hover:bg-slate-50/70 transition-colors border-l-4 <?= $vtm['border'] ?>"
             data-id="<?= $v['id'] ?>" data-address="<?= h($addr) ?>">
            <!-- Drag handle -->
            <div class="cursor-grab text-slate-300 hover:text-slate-500 drag-handle shrink-0">
                <i class="bi bi-grip-vertical text-lg"></i>
            </div>

            <!-- Visit number -->
            <div class="w-7 h-7 bg-slate-100 text-slate-500 rounded-lg grid place-items-center text-xs font-bold shrink-0">
                <?= $idx + 1 ?>
            </div>

            <div class="flex-1 min-w-0">
                <!-- Patient name + badges -->
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $v['patient_id'] ?>"
                       class="font-bold text-slate-800 hover:text-indigo-600 transition-colors text-sm">
                        <?= h($v['patient_name']) ?>
                    </a>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= $sd['bg'] ?> <?= $sd['text'] ?>">
                        <i class="bi <?= $sd['icon'] ?> text-xs"></i> <?= $sd['label'] ?>
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?= $vtm['badge'] ?>">
                        <?= h($vtm['label']) ?>
                    </span>
                    <?php if (!empty($v['recurring_rule_id'])): ?>
                    <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-600">
                        <i class="bi bi-arrow-repeat text-xs"></i> Recurring
                    </span>
                    <?php endif; ?>
                </div>
                <!-- Details row -->
                <div class="flex items-center gap-3 flex-wrap">
                    <?php if ($v['visit_time']): ?>
                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-lg">
                        <i class="bi bi-clock text-slate-400"></i>
                        <?= date('g:i A', strtotime($v['visit_time'])) ?>
                    </span>
                    <?php endif; ?>
                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-lg">
                        <i class="bi bi-clipboard2-pulse text-slate-400"></i>
                        Visit Type: <?= h($visitTypeText) ?>
                    </span>
                    <?php if (!empty($v['provider_name'])): ?>
                    <span class="inline-flex items-center gap-1 text-xs text-indigo-600 font-semibold">
                        <i class="bi bi-person-fill text-indigo-400"></i><?= h($v['ma_name']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($addr): ?>
                    <a href="<?= h($mapsUrl) ?>" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 hover:underline">
                        <i class="bi bi-geo-alt-fill text-blue-400"></i>
                        <span class="truncate max-w-[200px]"><?= h($addr) ?></span>
                        <i class="bi bi-box-arrow-up-right text-[10px]"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($v['notes'])): ?>
                <div class="mt-1.5 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 flex items-start gap-1.5">
                    <i class="bi bi-sticky-fill mt-0.5 shrink-0"></i>
                    <span><?= h($v['notes']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Delete -->
            <form method="POST" onsubmit="return confirm('Remove this visit from the schedule?')">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="entry_id" value="<?= $v['id'] ?>">
                <input type="hidden" name="date" value="<?= $date ?>">
                <button type="submit"
                        class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-colors">
                    <i class="bi bi-trash3"></i>
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Save order button -->
    <div class="px-5 py-3 bg-slate-50/60 border-t border-slate-100 rounded-b-2xl flex justify-end">
        <form method="POST" id="orderForm-<?= md5($providerKey) ?>">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="reorder">
            <input type="hidden" name="date" value="<?= $date ?>">
            <input type="hidden" name="order" id="orderInput-<?= md5($providerKey) ?>"
                   value="<?= implode(',', array_column($providerGroup['visits'], 'id')) ?>">
            <button type="submit"
                    class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-colors">
                <i class="bi bi-arrow-down-up"></i> Save Order
            </button>
        </form>
    </div>
</div>
<?php endforeach; ?>

<!-- Drag-to-reorder with SortableJS CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
document.querySelectorAll('[id^="sortable-"]').forEach(function(el) {
    const key = el.id.replace('sortable-','');
    Sortable.create(el, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function() {
            const ids = Array.from(el.querySelectorAll('[data-id]')).map(r => r.dataset.id);
            document.getElementById('orderInput-' + key).value = ids.join(',');
        }
    });
});

// ── Route Optimizer ────────────────────────────────────────────────────────
function haversine(lat1, lon1, lat2, lon2) {
    const R = 3958.8;
    const toRad = x => x * Math.PI / 180;
    const dLat = toRad(lat2 - lat1), dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat/2)**2 +
              Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

async function geocode(address) {
    try {
        const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&addressdetails=0&q='
                    + encodeURIComponent(address);
        const res = await fetch(url, { headers: { 'Accept-Language': 'en' } });
        const data = await res.json();
        if (data[0]) return { lat: parseFloat(data[0].lat), lng: parseFloat(data[0].lon) };
    } catch (_) {}
    return null;
}

async function optimizeRoute(maId, btn) {
    const container = document.getElementById('sortable-' + maId);
    const rows = Array.from(container.querySelectorAll('[data-id]'));
    if (rows.length < 2) { alert('Need at least 2 visits to optimize.'); return; }

    const origHtml = btn.innerHTML;
    btn.disabled = true;

    // Geocode each address sequentially (Nominatim: 1 req/sec)
    const points = [];
    for (let i = 0; i < rows.length; i++) {
        const addr = (rows[i].dataset.address || '').trim();
        btn.innerHTML = `<i class="bi bi-hourglass-split"></i> Geocoding ${i + 1}/${rows.length}…`;
        const coords = addr ? await geocode(addr) : null;
        points.push({ row: rows[i], lat: coords?.lat ?? null, lng: coords?.lng ?? null });
        if (i < rows.length - 1) await new Promise(r => setTimeout(r, 1150));
    }

    const withCoords    = points.filter(p => p.lat !== null);
    const withoutCoords = points.filter(p => p.lat === null);

    let ordered;
    if (withCoords.length > 1) {
        // Nearest-neighbour TSP from first geocoded point
        const result    = [withCoords[0]];
        const remaining = withCoords.slice(1);
        while (remaining.length) {
            const last = result[result.length - 1];
            let minDist = Infinity, minIdx = 0;
            remaining.forEach((p, idx) => {
                const d = haversine(last.lat, last.lng, p.lat, p.lng);
                if (d < minDist) { minDist = d; minIdx = idx; }
            });
            result.push(remaining.splice(minIdx, 1)[0]);
        }
        ordered = [...result, ...withoutCoords];
    } else {
        ordered = points; // nothing to reorder
    }

    // Re-append rows in optimised order, animate
    ordered.forEach(p => {
        p.row.style.transition = 'background 0.4s';
        p.row.style.background = '#ecfdf5';
        container.appendChild(p.row);
        setTimeout(() => { p.row.style.background = ''; }, 800);
    });

    // Persist new order
    const ids = ordered.map(p => p.row.dataset.id);
    document.getElementById('orderInput-' + maId).value = ids.join(',');
    document.getElementById('orderForm-' + maId).requestSubmit();

    btn.innerHTML = '<i class="bi bi-check-lg"></i> Optimized!';
    setTimeout(() => { btn.innerHTML = origHtml; btn.disabled = false; }, 2500);
}
</script>
<?php endif; ?>

<!-- Tom Select — searchable dropdowns -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.min.css">
<style>
.ts-wrapper.single .ts-control {
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0;
    padding: 0.625rem 0.75rem;
    font-size: 0.875rem;
    background: #fff;
    box-shadow: none;
    cursor: pointer;
}
.ts-wrapper.single.focus .ts-control {
    border-color: #818cf8;
    box-shadow: 0 0 0 2px rgba(99,102,241,.25);
}
/* Provider select — teal accent */
#ts-providerSelect .ts-wrapper.single .ts-control,
.ts-wrapper[id^="ts-providerSelect"] .ts-control {
    border: 2px solid #5eead4;
    font-weight: 600;
    padding: 0.75rem;
}
#ts-providerSelect.ts-wrapper.single.focus .ts-control,
.ts-wrapper.ts-providerSelect.single.focus .ts-control {
    border-color: #14b8a6;
    box-shadow: 0 0 0 2px rgba(20,184,166,.25);
}
.ts-dropdown { border-radius: 0.75rem; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,.1); margin-top: 4px; overflow: hidden; }
.ts-dropdown .ts-dropdown-content { max-height: 240px; }
.ts-dropdown .option { padding: 0.5rem 0.75rem; font-size: 0.875rem; }
.ts-dropdown .option.selected { background: #eef2ff; color: #4338ca; font-weight: 600; }
.ts-dropdown .option:hover { background: #f1f5f9; }
.ts-dropdown-header { padding: 0.5rem 0.75rem; }
.ts-control input { font-size: 0.875rem !important; }
</style>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
(function() {
    // Provider select — teal themed
    const provSel = document.getElementById('providerSelect');
    if (provSel) {
        const tsP = new TomSelect(provSel, {
            placeholder: '— Select a provider first —',
            allowEmptyOption: true,
            maxOptions: 50,
            onItemAdd() { updateAssignSummary(); },
            onItemRemove() { updateAssignSummary(); },
        });
        // Override control class for teal border
        tsP.control.style.border = '2px solid #5eead4';
        tsP.control.style.borderRadius = '0.75rem';
        tsP.control.style.padding = '0.75rem';
        tsP.control.style.fontWeight = '600';
        tsP.control.style.fontSize = '0.875rem';
        tsP.on('focus', () => { tsP.control.style.borderColor = '#14b8a6'; tsP.control.style.boxShadow = '0 0 0 2px rgba(20,184,166,.25)'; });
        tsP.on('blur',  () => { tsP.control.style.borderColor = '#5eead4'; tsP.control.style.boxShadow = 'none'; });
    }

    // MA / Staff select
    const maSel = document.getElementById('maSelect');
    if (maSel) {
        new TomSelect(maSel, {
            placeholder: 'Select staff member…',
            allowEmptyOption: true,
            maxOptions: 50,
            onItemAdd() { updateMaPreview(maSel); },
        });
    }

    // Patient select
    const ptSel = document.getElementById('patientSelect');
    if (ptSel) {
        new TomSelect(ptSel, {
            placeholder: 'Search patient…',
            allowEmptyOption: true,
            maxOptions: 200,
            searchField: ['text'],
            onItemAdd(val) {
                // Mirror to native select so updatePatientPreview works
                ptSel.value = val;
                updatePatientPreview(ptSel);
            },
        });
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
