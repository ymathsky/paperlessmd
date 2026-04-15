<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireAdmin();

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
    $notes     = trim($_POST['notes'] ?? '');

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

        $ins = $pdo->prepare("INSERT INTO `schedule` (visit_date,ma_id,patient_id,visit_time,visit_order,visit_type,notes,created_by)
                               VALUES (?,?,?,?,?,?,?,?)");
        $ins->execute([$visitDate, $maId, $patientId, $visitTime, $nextOrder, $visitType, $notes ?: null, $_SESSION['user_id']]);
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

// Fetch all MAs
$allMas = $pdo->query("SELECT id, full_name FROM staff WHERE active=1 ORDER BY full_name")->fetchAll();

// Fetch all patients (for add form)
$allPatients = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM patients ORDER BY last_name,first_name")->fetchAll();

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
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/admin/schedule_manage.php?date=<?= $prevDate ?>"
           class="p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-600 transition-colors">
            <i class="bi bi-chevron-left text-sm"></i>
        </a>
        <a href="<?= BASE_URL ?>/admin/schedule_manage.php?date=<?= date('Y-m-d') ?>"
           class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
            Today
        </a>
        <a href="<?= BASE_URL ?>/admin/schedule_manage.php?date=<?= $nextDate ?>"
           class="p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-600 transition-colors">
            <i class="bi bi-chevron-right text-sm"></i>
        </a>
        <a href="<?= BASE_URL ?>/schedule.php?date=<?= $date ?>"
           class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-700 rounded-xl text-sm font-semibold hover:bg-slate-50 transition-colors">
            <i class="bi bi-eye-fill text-indigo-400"></i> View
        </a>
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
<?php if (empty($byMa)): ?>
<div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-12 text-center">
    <div class="w-16 h-16 bg-indigo-50 rounded-2xl grid place-items-center mx-auto mb-4">
        <i class="bi bi-calendar-x text-indigo-400 text-3xl"></i>
    </div>
    <p class="text-slate-600 font-semibold text-lg mb-1">No visits assigned for this date</p>
    <p class="text-slate-400 text-sm">Use the form above to start building the route list.</p>
</div>
<?php else: ?>

<?php foreach ($byMa as $maId => $maGroup): ?>
<div class="bg-white border border-slate-100 rounded-2xl shadow-sm mb-5">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-indigo-100 text-indigo-700 rounded-xl grid place-items-center font-bold text-sm">
                <?= strtoupper(mb_substr($maGroup['name'], 0, 2)) ?>
            </div>
            <div>
                <div class="font-bold text-slate-800"><?= h($maGroup['name']) ?></div>
                <div class="text-xs text-slate-500"><?= count($maGroup['visits']) ?> visit<?= count($maGroup['visits'])!==1?'s':'' ?></div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/schedule.php?date=<?= $date ?>&ma_id=<?= $maId ?>"
           class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
            <i class="bi bi-eye"></i> MA View
        </a>
    </div>

    <div class="divide-y divide-slate-100" id="sortable-<?= $maId ?>">
        <?php foreach ($maGroup['visits'] as $idx => $v):
            $sd = $statusDefs[$v['status']];
        ?>
        <div class="flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50 transition-colors" data-id="<?= $v['id'] ?>">
            <!-- Drag handle -->
            <div class="cursor-grab text-slate-300 hover:text-slate-500 drag-handle">
                <i class="bi bi-grip-vertical text-lg"></i>
            </div>

            <div class="w-7 h-7 bg-slate-100 text-slate-500 rounded-lg grid place-items-center text-xs font-bold shrink-0">
                <?= $idx + 1 ?>
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-semibold text-slate-800 text-sm"><?= h($v['patient_name']) ?></span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= $sd['bg'] ?> <?= $sd['text'] ?>">
                        <i class="bi <?= $sd['icon'] ?> text-xs"></i> <?= $sd['label'] ?>
                    </span>
                    <?php
                    $vtLabels = ['routine'=>'Routine','new_patient'=>'New Pt','wound_care'=>'Wound Care','awv'=>'AWV','ccm'=>'CCM','il'=>'IL Disc.'];
                    $vtLabel  = $vtLabels[$v['visit_type'] ?? 'routine'] ?? 'Routine';
                    ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700"><?= h($vtLabel) ?></span>
                </div>
                <?php if ($v['patient_address']): ?>
                <div class="text-xs text-slate-400 mt-0.5 truncate"><?= h($v['patient_address']) ?></div>
                <?php endif; ?>
                <?php if ($v['visit_time']): ?>
                <div class="text-xs text-slate-400"><?= date('g:i A', strtotime($v['visit_time'])) ?></div>
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
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
