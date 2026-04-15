<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireNotBilling();

$pageTitle = 'My Schedule';
$activeNav = 'schedule';

// Date navigation
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$isToday  = $date === date('Y-m-d');

// Admins can view any MA's schedule via ?ma_id=X
$viewMaId = isAdmin() && isset($_GET['ma_id']) ? (int)$_GET['ma_id'] : (int)$_SESSION['user_id'];

// Fetch MA info
$maStmt = $pdo->prepare("SELECT id, full_name FROM staff WHERE id = ?");
$maStmt->execute([$viewMaId]);
$ma = $maStmt->fetch();
if (!$ma) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }

// Fetch schedule for this MA + date, ordered by visit_order
$schedStmt = $pdo->prepare("
    SELECT sc.*, 
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
           p.address AS patient_address,
           p.phone   AS patient_phone,
           p.id      AS patient_id
    FROM `schedule` sc
    JOIN patients p ON p.id = sc.patient_id
    WHERE sc.ma_id = ? AND sc.visit_date = ?
    ORDER BY sc.visit_order ASC, sc.visit_time ASC
");
$schedStmt->execute([$viewMaId, $date]);
$visits = $schedStmt->fetchAll();

// Stats
$counts = ['pending'=>0,'en_route'=>0,'completed'=>0,'missed'=>0];
foreach ($visits as $v) $counts[$v['status']]++;

// All MAs for admin switcher
$allMas = [];
if (isAdmin()) {
    $allMas = $pdo->query("SELECT id, full_name FROM staff WHERE active=1 ORDER BY full_name")->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<!-- Date nav + Title -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-800">
            <i class="bi bi-calendar3 text-indigo-500 mr-1"></i> Daily Schedule
        </h2>
        <p class="text-slate-500 text-sm mt-0.5">
            <?= h($ma['full_name']) ?> &mdash; <?= date('l, F j, Y', strtotime($date)) ?>
            <?php if ($isToday): ?><span class="ml-2 px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-bold rounded-full">TODAY</span><?php endif; ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <!-- Admin MA switcher -->
        <?php if (isAdmin() && $allMas): ?>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="date" value="<?= h($date) ?>">
            <select name="ma_id" onchange="this.form.submit()"
                    class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <?php foreach ($allMas as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $m['id'] == $viewMaId ? 'selected' : '' ?>>
                    <?= h($m['full_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>

        <!-- Date navigation -->
        <a href="?date=<?= $prevDate ?>&ma_id=<?= $viewMaId ?>"
           class="p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors text-slate-600">
            <i class="bi bi-chevron-left text-sm"></i>
        </a>
        <a href="?date=<?= date('Y-m-d') ?>&ma_id=<?= $viewMaId ?>"
           class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors <?= $isToday ? 'border-indigo-300 text-indigo-600' : '' ?>">
            Today
        </a>
        <a href="?date=<?= $nextDate ?>&ma_id=<?= $viewMaId ?>"
           class="p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors text-slate-600">
            <i class="bi bi-chevron-right text-sm"></i>
        </a>

        <?php if (isAdmin()): ?>
        <a href="<?= BASE_URL ?>/admin/schedule_manage.php?date=<?= $date ?>"
           class="flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold transition-colors shadow-sm">
            <i class="bi bi-pencil-fill text-xs"></i> Manage
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Status summary bar -->
<div class="grid grid-cols-4 gap-3 mb-6">
    <?php
    $statusDefs = [
        'pending'   => ['label'=>'Pending',   'bg'=>'bg-slate-100',   'text'=>'text-slate-600',   'dot'=>'bg-slate-400',   'icon'=>'bi-clock'],
        'en_route'  => ['label'=>'En Route',  'bg'=>'bg-blue-100',    'text'=>'text-blue-700',    'dot'=>'bg-blue-500',    'icon'=>'bi-car-front-fill'],
        'completed' => ['label'=>'Completed', 'bg'=>'bg-emerald-100', 'text'=>'text-emerald-700', 'dot'=>'bg-emerald-500', 'icon'=>'bi-check-circle-fill'],
        'missed'    => ['label'=>'Missed',    'bg'=>'bg-red-100',     'text'=>'text-red-700',     'dot'=>'bg-red-400',     'icon'=>'bi-x-circle-fill'],
    ];
    foreach ($statusDefs as $key => $def): ?>
    <div class="bg-white border border-slate-100 rounded-2xl p-4 flex items-center gap-3 shadow-sm">
        <div class="<?= $def['bg'] ?> p-2.5 rounded-xl">
            <i class="bi <?= $def['icon'] ?> <?= $def['text'] ?> text-lg leading-none"></i>
        </div>
        <div>
            <div class="text-2xl font-extrabold text-slate-800"><?= $counts[$key] ?></div>
            <div class="text-xs text-slate-500 font-medium"><?= $def['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Visit list -->
<?php if (empty($visits)): ?>
<div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-12 text-center">
    <div class="w-16 h-16 bg-indigo-50 rounded-2xl grid place-items-center mx-auto mb-4">
        <i class="bi bi-calendar-x text-indigo-400 text-3xl"></i>
    </div>
    <p class="text-slate-600 font-semibold text-lg mb-1">No visits scheduled</p>
    <p class="text-slate-400 text-sm mb-5">
        <?= isAdmin() ? 'Use "Manage" to assign patients to this MA.' : 'Check with your supervisor to get visits assigned.' ?>
    </p>
    <?php if (isAdmin()): ?>
    <a href="<?= BASE_URL ?>/admin/schedule_manage.php?date=<?= $date ?>"
       class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 transition-colors">
        <i class="bi bi-plus-lg"></i> Add Visits
    </a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="space-y-3" id="visitList">
    <?php foreach ($visits as $idx => $v):
        $sd   = $statusDefs[$v['status']];
        $addr = $v['patient_address'] ? htmlspecialchars(urlencode($v['patient_address'])) : '';
        $mapsUrl = $addr ? "https://www.google.com/maps/search/?api=1&query={$addr}" : '#';
    ?>
    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm hover:shadow-md transition-shadow"
         id="visit-<?= $v['id'] ?>">
        <div class="flex items-start gap-4 p-4">

            <!-- Order badge -->
            <div class="w-10 h-10 bg-indigo-100 text-indigo-700 font-extrabold text-sm rounded-xl grid place-items-center shrink-0">
                <?= $idx + 1 ?>
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $v['patient_id'] ?>"
                       class="font-bold text-slate-800 hover:text-indigo-600 transition-colors text-base">
                        <?= h($v['patient_name']) ?>
                    </a>
                    <!-- Status badge -->
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $sd['bg'] ?> <?= $sd['text'] ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= $sd['dot'] ?>"></span>
                        <?= $sd['label'] ?>
                    </span>
                </div>

                <?php if ($v['visit_time']): ?>
                <div class="flex items-center gap-1.5 text-sm text-slate-500 mb-1">
                    <i class="bi bi-clock text-slate-400"></i>
                    <?= date('g:i A', strtotime($v['visit_time'])) ?>
                </div>
                <?php endif; ?>

                <?php if ($v['patient_address']): ?>
                <div class="flex items-start gap-1.5 text-sm text-slate-500 mb-1">
                    <i class="bi bi-geo-alt text-slate-400 mt-0.5 shrink-0"></i>
                    <span><?= h($v['patient_address']) ?></span>
                </div>
                <?php endif; ?>

                <?php if ($v['patient_phone']): ?>
                <div class="flex items-center gap-1.5 text-sm text-slate-500">
                    <i class="bi bi-telephone text-slate-400"></i>
                    <a href="tel:<?= h(preg_replace('/\D/','',$v['patient_phone'])) ?>"
                       class="hover:text-indigo-600"><?= h($v['patient_phone']) ?></a>
                </div>
                <?php endif; ?>

                <?php if ($v['notes']): ?>
                <div class="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-1.5">
                    <i class="bi bi-sticky-fill mr-1"></i><?= h($v['notes']) ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="flex flex-col gap-2 shrink-0">
                <?php if ($v['patient_address']): ?>
                <a href="<?= $mapsUrl ?>" target="_blank" rel="noopener"
                   class="flex items-center gap-1.5 px-3 py-2 bg-blue-50 text-blue-700 border border-blue-200 rounded-xl text-xs font-semibold hover:bg-blue-100 transition-colors">
                    <i class="bi bi-map-fill"></i> Map
                </a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $v['patient_id'] ?>"
                   class="flex items-center gap-1.5 px-3 py-2 bg-slate-50 text-slate-600 border border-slate-200 rounded-xl text-xs font-semibold hover:bg-slate-100 transition-colors">
                    <i class="bi bi-person-lines-fill"></i> Chart
                </a>
            </div>
        </div>

        <!-- Status update bar (MA action) -->
        <div class="border-t border-slate-100 px-4 py-3 flex flex-wrap gap-2 bg-slate-50/60 rounded-b-2xl">
            <span class="text-xs text-slate-500 font-medium self-center mr-1">Update:</span>
            <?php foreach ($statusDefs as $sKey => $sDef): ?>
            <button onclick="updateStatus(<?= $v['id'] ?>, '<?= $sKey ?>')"
                    class="status-btn px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors
                           <?= $v['status'] === $sKey
                               ? $sDef['bg'] . ' ' . $sDef['text'] . ' border-transparent ring-2 ring-offset-1 ring-' . explode('-',$sDef['dot'])[1] . '-400'
                               : 'bg-white border-slate-200 text-slate-500 hover:border-slate-300 hover:bg-slate-100' ?>"
                    data-visit="<?= $v['id'] ?>" data-status="<?= $sKey ?>">
                <i class="bi <?= $sDef['icon'] ?> mr-0.5"></i> <?= $sDef['label'] ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function updateStatus(visitId, status) {
    fetch('<?= BASE_URL ?>/api/schedule_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            csrf: '<?= csrfToken() ?>',
            id: visitId,
            action: 'status',
            status: status
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            // Reload to show updated state
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Could not update status.'));
        }
    })
    .catch(() => alert('Network error. Please try again.'));
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
