<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/visit_types.php';
requireNotBilling();

$pageTitle = 'My Schedule';
$activeNav = 'schedule';

// Date navigation
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$isToday  = $date === date('Y-m-d');

// View mode: day | week
$view = in_array($_GET['view'] ?? '', ['day','week']) ? $_GET['view'] : 'day';

// Admins can view any MA's schedule via ?ma_id=X
$viewMaId = isAdmin() && isset($_GET['ma_id']) ? (int)$_GET['ma_id'] : (int)$_SESSION['user_id'];

// Fetch MA info
$maStmt = $pdo->prepare("SELECT id, full_name FROM staff WHERE id = ?");
$maStmt->execute([$viewMaId]);
$ma = $maStmt->fetch();
if (!$ma) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }

// Week bounds (Monday–Sunday of the week containing $date)
$dow       = (int)date('N', strtotime($date));   // 1=Mon … 7=Sun
$weekStart = date('Y-m-d', strtotime($date . ' -' . ($dow - 1) . ' days'));
$weekEnd   = date('Y-m-d', strtotime($weekStart . ' +6 days'));
$prevWeek  = date('Y-m-d', strtotime($weekStart . ' -7 days'));
$nextWeek  = date('Y-m-d', strtotime($weekStart . ' +7 days'));

// Week query (only executed when needed)
$visitsByDate = [];
$weekVisits   = [];
$weekCounts   = ['pending'=>0,'en_route'=>0,'completed'=>0,'missed'=>0];
if ($view === 'week') {
    $wkStmt = $pdo->prepare("
        SELECT sc.*,
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               p.id AS patient_id
        FROM `schedule` sc
        JOIN patients p ON p.id = sc.patient_id
        WHERE sc.ma_id = ? AND sc.visit_date BETWEEN ? AND ?
        ORDER BY sc.visit_date ASC, sc.visit_order ASC, sc.visit_time ASC
    ");
    $wkStmt->execute([$viewMaId, $weekStart, $weekEnd]);
    $weekVisits = $wkStmt->fetchAll();
    foreach ($weekVisits as $wv) {
        $visitsByDate[$wv['visit_date']][] = $wv;
        $weekCounts[$wv['status']]++;
    }
}

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
            <?= h($ma['full_name']) ?> &mdash;
            <?php if ($view === 'week'): ?>
                <?= date('M j', strtotime($weekStart)) ?> – <?= date('M j, Y', strtotime($weekEnd)) ?>
            <?php else: ?>
                <?= date('l, F j, Y', strtotime($date)) ?>
                <?php if ($isToday): ?><span class="ml-2 px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-bold rounded-full">TODAY</span><?php endif; ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <!-- Admin MA switcher -->
        <?php if (isAdmin() && $allMas): ?>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="date" value="<?= h($date) ?>">
            <input type="hidden" name="view" value="<?= h($view) ?>">
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

        <!-- Day / Week toggle -->
        <div class="flex items-center bg-slate-100 rounded-xl p-1 gap-0.5">
            <a href="?date=<?= $date ?>&ma_id=<?= $viewMaId ?>&view=day"
               class="px-3.5 py-1.5 rounded-lg text-sm font-semibold transition-all <?= $view === 'day' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">
                <i class="bi bi-calendar3 mr-1"></i>Day
            </a>
            <a href="?date=<?= $weekStart ?>&ma_id=<?= $viewMaId ?>&view=week"
               class="px-3.5 py-1.5 rounded-lg text-sm font-semibold transition-all <?= $view === 'week' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">
                <i class="bi bi-calendar-week mr-1"></i>Week
            </a>
        </div>

        <!-- Date navigation -->
        <?php if ($view === 'week'): ?>
        <a href="?date=<?= $prevWeek ?>&ma_id=<?= $viewMaId ?>&view=week"
           class="p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors text-slate-600">
            <i class="bi bi-chevron-left text-sm"></i>
        </a>
        <a href="?date=<?= date('Y-m-d') ?>&ma_id=<?= $viewMaId ?>&view=week"
           class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors <?= (date('Y-m-d') >= $weekStart && date('Y-m-d') <= $weekEnd) ? 'border-indigo-300 text-indigo-600' : '' ?>">
            Today
        </a>
        <a href="?date=<?= $nextWeek ?>&ma_id=<?= $viewMaId ?>&view=week"
           class="p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors text-slate-600">
            <i class="bi bi-chevron-right text-sm"></i>
        </a>
        <?php else: ?>
        <a href="?date=<?= $prevDate ?>&ma_id=<?= $viewMaId ?>&view=day"
           class="p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors text-slate-600">
            <i class="bi bi-chevron-left text-sm"></i>
        </a>
        <a href="?date=<?= date('Y-m-d') ?>&ma_id=<?= $viewMaId ?>&view=day"
           class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors <?= $isToday ? 'border-indigo-300 text-indigo-600' : '' ?>">
            Today
        </a>
        <a href="?date=<?= $nextDate ?>&ma_id=<?= $viewMaId ?>&view=day"
           class="p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors text-slate-600">
            <i class="bi bi-chevron-right text-sm"></i>
        </a>
        <?php endif; ?>

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
    $displayCounts = ($view === 'week') ? $weekCounts : $counts;
    foreach ($statusDefs as $key => $def): ?>
    <div class="bg-white border border-slate-100 rounded-2xl p-4 flex items-center gap-3 shadow-sm">
        <div class="<?= $def['bg'] ?> p-2.5 rounded-xl">
            <i class="bi <?= $def['icon'] ?> <?= $def['text'] ?> text-lg leading-none"></i>
        </div>
        <div>
            <div class="text-2xl font-extrabold text-slate-800"><?= $displayCounts[$key] ?></div>
            <div class="text-xs text-slate-500 font-medium"><?= $def['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($view === 'week'): ?>
<!-- ═══════════════════════ WEEKLY VIEW ═══════════════════════ -->
<div class="overflow-x-auto -mx-1 pb-2">
    <div class="inline-grid gap-3 min-w-full" style="grid-template-columns: repeat(7, minmax(160px, 1fr));">
        <?php
        for ($d = 0; $d < 7; $d++):
            $colDate  = date('Y-m-d', strtotime($weekStart . ' +' . $d . ' days'));
            $isColToday = $colDate === date('Y-m-d');
            $colVisits  = $visitsByDate[$colDate] ?? [];
            $colCounts  = ['pending'=>0,'en_route'=>0,'completed'=>0,'missed'=>0];
            foreach ($colVisits as $cv) $colCounts[$cv['status']]++;
        ?>
        <div class="flex flex-col rounded-2xl overflow-hidden border <?= $isColToday ? 'border-indigo-300 shadow-md' : 'border-slate-100 shadow-sm' ?> bg-white">
            <!-- Day header -->
            <div class="px-3 py-2.5 <?= $isColToday ? 'bg-indigo-600 text-white' : 'bg-slate-50 text-slate-600' ?> border-b <?= $isColToday ? 'border-indigo-500' : 'border-slate-100' ?>">
                <div class="text-xs font-bold uppercase tracking-wide <?= $isColToday ? 'text-indigo-100' : 'text-slate-400' ?>">
                    <?= date('D', strtotime($colDate)) ?>
                </div>
                <div class="text-sm font-extrabold leading-tight <?= $isColToday ? 'text-white' : 'text-slate-700' ?>">
                    <?= date('M j', strtotime($colDate)) ?>
                </div>
                <?php if (!empty($colVisits)): ?>
                <div class="flex gap-1 mt-1.5 flex-wrap">
                    <?php foreach (['pending'=>'bg-slate-300','en_route'=>'bg-blue-400','completed'=>'bg-emerald-400','missed'=>'bg-red-400'] as $sk=>$sc): if ($colCounts[$sk]): ?>
                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold
                                 <?= $isColToday ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= $sc ?>"></span><?= $colCounts[$sk] ?>
                    </span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <!-- Visit list -->
            <div class="flex flex-col gap-1.5 p-2 flex-1 <?= empty($colVisits) ? 'items-center justify-center py-6' : '' ?>">
                <?php if (empty($colVisits)): ?>
                <span class="text-xs text-slate-300 font-medium">No visits</span>
                <?php else: ?>
                <?php foreach ($colVisits as $cv):
                    $cvSd = $statusDefs[$cv['status']];
                ?>
                <div class="rounded-xl border <?= $cv['status']==='completed' ? 'border-emerald-100 bg-emerald-50' : ($cv['status']==='en_route' ? 'border-blue-100 bg-blue-50' : ($cv['status']==='missed' ? 'border-red-100 bg-red-50' : 'border-slate-100 bg-slate-50')) ?> px-2.5 py-2">
                    <div class="flex items-start gap-1.5">
                        <span class="mt-0.5 w-2 h-2 rounded-full <?= $cvSd['dot'] ?> shrink-0"></span>
                        <div class="min-w-0 flex-1">
                            <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $cv['patient_id'] ?>"
                               class="text-xs font-semibold text-slate-700 hover:text-indigo-600 leading-snug block truncate">
                                <?= h($cv['patient_name']) ?>
                            </a>
                            <?php if ($cv['visit_time']): ?>
                            <span class="text-[10px] text-slate-400"><?= date('g:i A', strtotime($cv['visit_time'])) ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $cv['patient_id'] ?>"
                           class="shrink-0 text-[10px] font-semibold text-slate-400 hover:text-indigo-600 mt-0.5">
                            <i class="bi bi-person-lines-fill"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <!-- Day footer: link to day view -->
            <a href="?date=<?= $colDate ?>&ma_id=<?= $viewMaId ?>&view=day"
               class="block text-center text-[10px] font-semibold py-2 border-t border-slate-100
                      <?= $isColToday ? 'text-indigo-600 bg-indigo-50 hover:bg-indigo-100' : 'text-slate-400 bg-slate-50 hover:bg-slate-100' ?> transition-colors">
                View day <i class="bi bi-arrow-right-short"></i>
            </a>
        </div>
        <?php endfor; ?>
    </div>
</div>

<?php else: ?>
<!-- ═══════════════════════ DAILY VIEW ═══════════════════════ -->
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
        $addr    = $v['patient_address'] ? rawurlencode($v['patient_address']) : '';
        $mapsUrl = $addr ? 'https://www.google.com/maps/dir/?api=1&destination=' . $addr : '#';
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
                    <?php
                    $vt = $v['visit_type'] ?? 'routine';
                    $vtLabels = ['routine'=>'Routine','new_patient'=>'New Pt','wound_care'=>'Wound Care','awv'=>'AWV','ccm'=>'CCM','il'=>'IL Disc.'];
                    ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">
                        <?= h($vtLabels[$vt] ?? 'Routine') ?>
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
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $addr ?>" target="_blank" rel="noopener"
                       class="hover:text-blue-600 underline decoration-dotted"><?= h($v['patient_address']) ?></a>
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
                <?php if ($v['status'] === 'pending'): ?>
                <button onclick="startVisit(<?= $v['id'] ?>, <?= $v['patient_id'] ?>, this)"
                        class="flex items-center gap-1.5 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95
                               text-white rounded-xl text-xs font-bold shadow-sm transition-all">
                    <i class="bi bi-play-fill text-sm"></i> Start Visit
                </button>
                <?php elseif ($v['status'] === 'en_route'): ?>
                <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $v['patient_id'] ?>&tab=forms&visit=<?= $v['id'] ?>"
                   class="flex items-center gap-1.5 px-4 py-2.5 bg-blue-600 hover:bg-blue-700
                          text-white rounded-xl text-xs font-bold shadow-sm transition-all">
                    <i class="bi bi-file-earmark-plus-fill text-sm"></i> Open Forms
                </a>
                <?php endif; ?>
                <?php if ($v['patient_address']): ?>
                <a href="<?= h($mapsUrl) ?>" target="_blank" rel="noopener"
                   class="flex items-center gap-1.5 px-3 py-2 bg-blue-50 text-blue-700 border border-blue-200 rounded-xl text-xs font-semibold hover:bg-blue-100 transition-colors">
                    <i class="bi bi-navigation-fill"></i> Navigate
                </a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $v['patient_id'] ?>"
                   class="flex items-center gap-1.5 px-3 py-2 bg-slate-50 text-slate-600 border border-slate-200 rounded-xl text-xs font-semibold hover:bg-slate-100 transition-colors">
                    <i class="bi bi-person-lines-fill"></i> Chart
                </a>
            </div>
        </div>

        <!-- Status update bar (MA action) -->
        <div class="border-t border-slate-100 px-4 py-3 flex flex-wrap gap-2 bg-slate-50/60">
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

        <!-- Quick Visit Notes -->
        <div class="border-t border-slate-100 rounded-b-2xl overflow-hidden">
            <!-- Toggle row -->
            <button type="button"
                    onclick="toggleNotes(this, <?= $v['id'] ?>)"
                    class="w-full flex items-center gap-2 px-4 py-2.5 text-xs font-semibold
                           text-left transition-colors
                           <?= !empty($v['visit_notes']) ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'bg-slate-50/80 text-slate-500 hover:bg-slate-100' ?>">
                <i class="bi bi-pencil-square text-sm"></i>
                <?php if (!empty($v['visit_notes'])): ?>
                    <span class="truncate flex-1"><?= h(mb_strimwidth($v['visit_notes'], 0, 80, '…')) ?></span>
                    <span class="shrink-0 px-2 py-0.5 bg-amber-200 text-amber-800 rounded-full text-[10px] font-bold">Note saved</span>
                <?php else: ?>
                    <span class="flex-1">Add quick note…</span>
                <?php endif; ?>
                <i class="bi bi-chevron-down text-xs shrink-0 note-chevron transition-transform"></i>
            </button>
            <!-- Expandable note area -->
            <div class="note-panel hidden px-4 pb-4 pt-3 bg-amber-50/60">
                <textarea
                    id="note-<?= $v['id'] ?>"
                    class="w-full px-3 py-2.5 border border-amber-200 rounded-xl text-sm bg-white
                           focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent
                           resize-none transition"
                    rows="3"
                    placeholder="Quick clinical observation — e.g. wound looks improved, patient reports pain 3/10…"
                    ><?= h($v['visit_notes'] ?? '') ?></textarea>
                <div class="flex items-center gap-2 mt-2">
                    <button type="button"
                            onclick="saveNote(<?= $v['id'] ?>, this)"
                            class="px-4 py-2 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white
                                   text-xs font-bold rounded-xl transition-all shadow-sm">
                        <i class="bi bi-floppy-fill mr-1"></i> Save Note
                    </button>
                    <span class="note-saved-msg hidden text-xs text-emerald-600 font-semibold">
                        <i class="bi bi-check-circle-fill mr-0.5"></i> Saved!
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; // visit list ?>
<?php endif; // end daily view ?>

<script>
const CSRF   = '<?= csrfToken() ?>';
const BASE   = '<?= BASE_URL ?>';

// ── One-tap Start Visit ───────────────────────────────────────────────────────
function startVisit(visitId, patientId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split text-sm animate-spin"></i> Starting…';

    fetch(BASE + '/api/schedule_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: CSRF, id: visitId, action: 'status', status: 'en_route' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            // Navigate straight to the patient's forms page
            window.location.href = BASE + '/patient_view.php?id=' + patientId + '&tab=forms&visit=' + visitId;
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-fill text-sm"></i> Start Visit';
            alert('Error: ' + (data.error || 'Could not start visit.'));
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-fill text-sm"></i> Start Visit';
        alert('Network error. Please try again.');
    });
}

// ── Quick Visit Notes ────────────────────────────────────────────────────────
function toggleNotes(btn, visitId) {
    const card  = btn.closest('[id^="visit-"]');
    const panel = card.querySelector('.note-panel');
    const chev  = btn.querySelector('.note-chevron');
    const open  = panel.classList.toggle('hidden');
    chev.style.transform = open ? '' : 'rotate(180deg)';
    if (!open) {
        // focus textarea
        const ta = document.getElementById('note-' + visitId);
        if (ta) { ta.focus(); ta.selectionStart = ta.value.length; }
    }
}

async function saveNote(visitId, btn) {
    const ta  = document.getElementById('note-' + visitId);
    const msg = btn.closest('.flex').querySelector('.note-saved-msg');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i> Saving…';
    try {
        const r = await fetch(BASE + '/api/schedule_update.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ csrf: CSRF, id: visitId, action: 'save_note', visit_notes: ta.value })
        });
        const d = await r.json();
        if (d.ok) {
            // Update the toggle button preview text
            const card    = ta.closest('[id^="visit-"]');
            const togBtn  = card.querySelector('button[onclick^="toggleNotes"]');
            const preview = togBtn.querySelector('span.flex-1');
            const badge   = togBtn.querySelector('span.shrink-0');
            if (ta.value.trim()) {
                preview.textContent = ta.value.length > 80 ? ta.value.slice(0,80) + '…' : ta.value;
                togBtn.classList.remove('bg-slate-50/80','text-slate-500','hover:bg-slate-100');
                togBtn.classList.add('bg-amber-50','text-amber-700','hover:bg-amber-100');
                if (!badge) {
                    const b = document.createElement('span');
                    b.className = 'shrink-0 px-2 py-0.5 bg-amber-200 text-amber-800 rounded-full text-[10px] font-bold';
                    b.textContent = 'Note saved';
                    togBtn.querySelector('.note-chevron').before(b);
                } else { badge.textContent = 'Note saved'; }
            } else {
                preview.textContent = 'Add quick note…';
                togBtn.classList.add('bg-slate-50/80','text-slate-500','hover:bg-slate-100');
                togBtn.classList.remove('bg-amber-50','text-amber-700','hover:bg-amber-100');
                if (badge) badge.remove();
            }
            msg.classList.remove('hidden');
            setTimeout(() => msg.classList.add('hidden'), 2500);
        } else { alert(d.error || 'Could not save note.'); }
    } catch { alert('Network error.'); }
    btn.disabled = false;
    btn.innerHTML = orig;
}

// ── Status update (status bar buttons) ───────────────────────────────────────
function updateStatus(visitId, status) {
    fetch(BASE + '/api/schedule_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: CSRF, id: visitId, action: 'status', status: status })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Could not update status.'));
        }
    })
    .catch(() => alert('Network error. Please try again.'));
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
