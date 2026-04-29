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

        $ins = $pdo->prepare("INSERT INTO `schedule` (visit_date,ma_id,patient_id,visit_time,visit_order,visit_type,notes,provider_name,created_by)
                               VALUES (?,?,?,?,?,?,?,?,?)");
        $ins->execute([$visitDate, $maId, $patientId, $visitTime, $nextOrder, $visitType, $notes ?: null, $providerName ?: null, $_SESSION['user_id']]);
        $date = $visitDate;
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
$allPatients = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM patients ORDER BY last_name,first_name")->fetchAll();

// Distinct provider names already in schedule (for datalist autocomplete)
$providerList = [];
try {
    $providerList = $pdo->query("SELECT DISTINCT provider_name FROM `schedule` WHERE provider_name IS NOT NULL AND provider_name <> '' ORDER BY provider_name")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { /* column not yet on this server */ }

// Fetch schedule grouped by MA
$schedStmt = $pdo->prepare("
    SELECT sc.*,
           CONCAT(p.first_name,' ',p.last_name) AS patient_name,
           p.address AS patient_address,
           s.full_name AS ma_name
    FROM `schedule` sc
    JOIN patients p ON p.id = sc.patient_id
    JOIN staff s ON s.id = sc.ma_id
    WHERE sc.visit_date = ?
    ORDER BY s.full_name, sc.visit_order ASC, sc.visit_time ASC
");
$schedStmt->execute([$date]);
$allEntries = $schedStmt->fetchAll();

// Group by MA
$byMa = [];
foreach ($allEntries as $e) {
    $byMa[$e['ma_id']] = $byMa[$e['ma_id']] ?? ['name' => $e['ma_name'], 'visits' => []];
    $byMa[$e['ma_id']]['visits'][] = $e;
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
    <i class="bi bi-exclamation-triangle-fill text-red-500 mt-0.5 flex-shrink-0"></i>
    <ul class="space-y-1"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<!-- ADD VISIT FORM -->
<div class="bg-white border border-slate-100 rounded-2xl shadow-sm mb-6">
    <button onclick="document.getElementById('addForm').classList.toggle('hidden')"
            class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-slate-50 rounded-2xl transition-colors">
        <span class="flex items-center gap-2 font-bold text-slate-700">
            <i class="bi bi-plus-circle-fill text-indigo-500 text-lg"></i> Assign New Visit
        </span>
        <i class="bi bi-chevron-down text-slate-400 text-sm"></i>
    </button>
    <div id="addForm" class="<?= $errors ? '' : 'hidden' ?> border-t border-slate-100">
        <form method="POST" class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="add">

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">MA / Staff</label>
                <select name="ma_id" required
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                               focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                    <option value="">Select MA...</option>
                    <?php foreach ($allMas as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= (($_POST['ma_id']??0)==$m['id'])?'selected':'' ?>>
                        <?= h($m['full_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Patient</label>
                <select name="patient_id" required
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                               focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                    <option value="">Select patient...</option>
                    <?php foreach ($allPatients as $pt): ?>
                    <option value="<?= $pt['id'] ?>" <?= (($_POST['patient_id']??0)==$pt['id'])?'selected':'' ?>>
                        <?= h($pt['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Visit Date</label>
                <input type="date" name="visit_date" value="<?= h($_POST['visit_date'] ?? $date) ?>" required
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Visit Time <span class="font-normal text-slate-400">(optional)</span></label>
                <input type="time" name="visit_time" value="<?= h($_POST['visit_time'] ?? '') ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Visit Type</label>
                <select name="visit_type"
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                               focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                    <option value="routine"     <?= ($_POST['visit_type']??'routine')==='routine'     ?'selected':'' ?>>Routine Visit</option>
                    <option value="new_patient" <?= ($_POST['visit_type']??'')==='new_patient' ?'selected':'' ?>>New Patient</option>
                    <option value="wound_care"  <?= ($_POST['visit_type']??'')==='wound_care'  ?'selected':'' ?>>Wound Care</option>
                    <option value="awv"         <?= ($_POST['visit_type']??'')==='awv'         ?'selected':'' ?>>Annual Wellness (AWV)</option>
                    <option value="ccm"         <?= ($_POST['visit_type']??'')==='ccm'         ?'selected':'' ?>>CCM Visit</option>
                    <option value="il"          <?= ($_POST['visit_type']??'')==='il'          ?'selected':'' ?>>IL Disclosure</option>
                </select>
            </div>

            <div class="sm:col-span-2 lg:col-span-1">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Provider <span class="font-normal text-slate-400">(optional)</span></label>
                <input type="text" name="provider_name" value="<?= h($_POST['provider_name'] ?? '') ?>" placeholder="Attending provider name"
                       list="providerDatalist"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
                <datalist id="providerDatalist">
                    <?php foreach ($providerList as $pn): ?>
                    <option value="<?= h($pn) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="sm:col-span-2 lg:col-span-1">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Notes <span class="font-normal text-slate-400">(optional)</span></label>
                <input type="text" name="notes" value="<?= h($_POST['notes'] ?? '') ?>" placeholder="e.g. Use back entrance, patient has a dog..."
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition focus:bg-white">
            </div>

            <div class="sm:col-span-2 lg:col-span-3 flex justify-end">
                <button type="submit"
                        class="flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-sm transition-colors shadow-sm">
                    <i class="bi bi-plus-lg"></i> Add Visit
                </button>
            </div>
        </form>
    </div>
</div>

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
<?php if (empty($byMa)): ?>
<div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-12 text-center">
    <div class="w-16 h-16 bg-indigo-50 rounded-2xl grid place-items-center mx-auto mb-4">
        <i class="bi bi-calendar-x text-indigo-400 text-3xl"></i>
    </div>
    <p class="text-slate-600 font-semibold text-lg mb-1">No visits assigned for this date</p>
    <p class="text-slate-400 text-sm">Use the form above to start building the route list.</p>
</div>
<?php else: ?>

<?php foreach ($byMa as $maId => $maGroup):
    $maCounts = ['pending'=>0,'en_route'=>0,'completed'=>0,'missed'=>0];
    foreach ($maGroup['visits'] as $mv) $maCounts[$mv['status']]++;
    $maDone  = $maCounts['completed'];
    $maTotal = count($maGroup['visits']);
    $maPct   = $maTotal > 0 ? round($maDone / $maTotal * 100) : 0;
?>
<div class="bg-white border border-slate-100 rounded-2xl shadow-sm mb-5">
    <div class="px-6 py-4 border-b border-slate-100">
        <div class="flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-600 text-white rounded-xl grid place-items-center font-bold text-sm shrink-0">
                    <?= strtoupper(mb_substr($maGroup['name'], 0, 2)) ?>
                </div>
                <div>
                    <div class="font-bold text-slate-800 text-base"><?= h($maGroup['name']) ?></div>
                    <div class="text-xs text-slate-500"><?= $maTotal ?> visit<?= $maTotal !== 1 ? 's' : '' ?> &mdash; <?= $maDone ?> completed</div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap justify-end">
                <?php foreach ([
                    'pending'   => ['bg-slate-100',   'text-slate-600',   'bi-clock'],
                    'en_route'  => ['bg-blue-100',    'text-blue-700',    'bi-car-front-fill'],
                    'completed' => ['bg-emerald-100', 'text-emerald-700', 'bi-check-circle-fill'],
                    'missed'    => ['bg-red-100',     'text-red-600',     'bi-x-circle-fill'],
                ] as $sk => [$sbg, $stxt, $sico]):
                    if (!$maCounts[$sk]) continue;
                ?>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold <?= $sbg ?> <?= $stxt ?>">
                    <i class="bi <?= $sico ?> text-xs"></i> <?= $maCounts[$sk] ?>
                </span>
                <?php endforeach; ?>
                <a href="<?= BASE_URL ?>/schedule.php?date=<?= $date ?>&ma_id=<?= $maId ?>"
                   class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 px-2.5 py-1 bg-indigo-50 rounded-full hover:bg-indigo-100 transition-colors">
                    <i class="bi bi-eye"></i> MA View
                </a>
                <?php if (count($maGroup['visits']) > 1): ?>
                <button type="button"
                        onclick="optimizeRoute(<?= $maId ?>, this)"
                        class="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100
                               text-emerald-700 text-xs font-bold rounded-full transition-colors border border-emerald-200">
                    <i class="bi bi-magic"></i> Optimize Route
                </button>
                <?php endif; ?>
            </div>
        </div>
        <!-- Progress bar -->
        <div class="flex items-center gap-3">
            <div class="flex-1 bg-slate-100 rounded-full h-1.5 overflow-hidden">
                <div class="bg-emerald-500 h-1.5 rounded-full transition-all" style="width:<?= $maPct ?>%"></div>
            </div>
            <span class="text-xs font-bold text-slate-400 shrink-0"><?= $maPct ?>%</span>
        </div>
    </div>

    <div class="divide-y divide-slate-100" id="sortable-<?= $maId ?>">
        <?php foreach ($maGroup['visits'] as $idx => $v):
            $sd   = $statusDefs[$v['status']];
            $vt   = $v['visit_type'] ?? 'routine';
            $vtm  = $vtMeta[$vt] ?? $vtMeta['routine'];
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
                    <?php if (!empty($v['provider_name'])): ?>
                    <span class="inline-flex items-center gap-1 text-xs text-slate-500">
                        <i class="bi bi-person-badge text-slate-400"></i><?= h($v['provider_name']) ?>
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
        <form method="POST" id="orderForm-<?= $maId ?>">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="reorder">
            <input type="hidden" name="date" value="<?= $date ?>">
            <input type="hidden" name="order" id="orderInput-<?= $maId ?>"
                   value="<?= implode(',', array_column($maGroup['visits'], 'id')) ?>">
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
    const maId = el.id.replace('sortable-','');
    Sortable.create(el, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function() {
            const ids = Array.from(el.querySelectorAll('[data-id]')).map(r => r.dataset.id);
            document.getElementById('orderInput-' + maId).value = ids.join(',');
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
