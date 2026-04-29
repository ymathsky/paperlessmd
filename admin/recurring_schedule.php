<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireAdminOrScheduler();

$pageTitle = 'Recurring Schedule';
$activeNav = 'recurring_schedule';

// ─── Inline schema migration (idempotent) ─────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `schedule_recurring` (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        ma_id        INT          NOT NULL,
        patient_id   INT          NOT NULL,
        visit_type   VARCHAR(30)  NOT NULL DEFAULT 'routine',
        visit_time   TIME         NULL,
        notes        TEXT         NULL,
        frequency    ENUM('weekly','biweekly','monthly') NOT NULL DEFAULT 'weekly',
        days_of_week VARCHAR(20)  NULL,
        start_date   DATE         NOT NULL,
        end_date     DATE         NULL,
        occurrences  SMALLINT     NULL,
        active       TINYINT(1)   NOT NULL DEFAULT 1,
        created_by   INT          NULL,
        created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ma_id)      REFERENCES staff(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `schedule` ADD COLUMN `recurring_rule_id` INT NULL"); } catch (PDOException $e) {}

// ─── Helper: insert one visit (skips duplicates) ──────────────────────────────
function _rr_insert(PDO $pdo, array $rule, string $visitDate): int {
    $dup = $pdo->prepare("SELECT id FROM `schedule` WHERE ma_id=? AND patient_id=? AND visit_date=? LIMIT 1");
    $dup->execute([$rule['ma_id'], $rule['patient_id'], $visitDate]);
    if ($dup->fetch()) return 0;

    $ordS = $pdo->prepare("SELECT COALESCE(MAX(visit_order),0)+1 FROM `schedule` WHERE ma_id=? AND visit_date=?");
    $ordS->execute([$rule['ma_id'], $visitDate]);

    $pdo->prepare("INSERT INTO `schedule`
        (visit_date,ma_id,patient_id,visit_time,visit_order,visit_type,notes,recurring_rule_id,created_by)
        VALUES(?,?,?,?,?,?,?,?,?)")
        ->execute([
            $visitDate,
            $rule['ma_id'], $rule['patient_id'],
            $rule['visit_time'] ?: null,
            (int)$ordS->fetchColumn(),
            $rule['visit_type'],
            $rule['notes'] ?: null,
            $rule['id'],
            $rule['created_by'] ?? null,
        ]);
    return 1;
}

// ─── Helper: generate visits for a rule up to a given date ───────────────────
function rr_generate(PDO $pdo, array $rule, string $generateUntil): int {
    $start = new DateTime($rule['start_date']);
    $end   = $rule['end_date'] ? new DateTime($rule['end_date']) : null;
    $until = new DateTime($generateUntil);
    $until->setTime(23, 59, 59);
    if ($end && $end < $until) $until = clone $end;

    // Respect occurrences cap
    $maxLeft = PHP_INT_MAX;
    if ($rule['occurrences']) {
        $cntS = $pdo->prepare("SELECT COUNT(*) FROM `schedule` WHERE recurring_rule_id=?");
        $cntS->execute([$rule['id']]);
        $maxLeft = max(0, (int)$rule['occurrences'] - (int)$cntS->fetchColumn());
        if ($maxLeft <= 0) return 0;
    }

    $days = (!empty($rule['days_of_week']))
        ? array_map('intval', explode(',', $rule['days_of_week']))
        : [];
    sort($days);

    $generated = 0;

    if ($rule['frequency'] === 'monthly') {
        $d = clone $start;
        while ($d <= $until && $generated < $maxLeft) {
            $generated += _rr_insert($pdo, $rule, $d->format('Y-m-d'));
            $d->modify('+1 month');
        }
    } else {
        $itvWeeks = $rule['frequency'] === 'biweekly' ? 2 : 1;
        // Monday of the week containing start_date
        $mon = clone $start;
        $dow = (int)$mon->format('N');
        if ($dow > 1) $mon->modify('-' . ($dow - 1) . ' days');
        $weekNum = 0;

        while ($mon <= $until && $generated < $maxLeft) {
            if ($weekNum % $itvWeeks === 0) {
                foreach ($days as $isoDay) {
                    $d = clone $mon;
                    $d->modify('+' . ($isoDay - 1) . ' days');
                    if ($d < $start || $d > $until) continue;
                    $generated += _rr_insert($pdo, $rule, $d->format('Y-m-d'));
                    if ($generated >= $maxLeft) break;
                }
            }
            $mon->modify('+7 days');
            $weekNum++;
        }
    }
    return $generated;
}

// ─── Helper: compute next upcoming occurrence date ────────────────────────────
function rr_next_date(array $rule): ?string {
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $start = new DateTime($rule['start_date']);
    if ($rule['end_date'] && new DateTime($rule['end_date']) < $today) return null;
    if ($start > $today) return $start->format('Y-m-d');

    $days = (!empty($rule['days_of_week']))
        ? array_map('intval', explode(',', $rule['days_of_week']))
        : [];
    sort($days);

    if ($rule['frequency'] === 'monthly') {
        $d = clone $start;
        $limit = (clone $today)->modify('+2 years');
        while ($d < $today && $d < $limit) $d->modify('+1 month');
        if ($rule['end_date'] && $d > new DateTime($rule['end_date'])) return null;
        return $d >= $today ? $d->format('Y-m-d') : null;
    }

    $itvWeeks = $rule['frequency'] === 'biweekly' ? 2 : 1;
    $mon = clone $start;
    $dow = (int)$mon->format('N');
    if ($dow > 1) $mon->modify('-' . ($dow - 1) . ' days');
    $limit = (clone $today)->modify('+2 years');
    $weekNum = 0;

    while ($mon <= $limit) {
        if ($weekNum % $itvWeeks === 0) {
            foreach ($days as $isoDay) {
                $d = clone $mon;
                $d->modify('+' . ($isoDay - 1) . ' days');
                if ($d < $today || $d < $start) continue;
                if ($rule['end_date'] && $d > new DateTime($rule['end_date'])) return null;
                return $d->format('Y-m-d');
            }
        }
        $mon->modify('+7 days');
        $weekNum++;
    }
    return null;
}

// ─── Constants ───────────────────────────────────────────────────────────────
$DAY_NAMES  = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
$FREQ_LABELS= ['weekly' => 'Every week', 'biweekly' => 'Every 2 weeks', 'monthly' => 'Every month'];
$VT_LABELS  = ['routine'=>'Routine Visit','new_patient'=>'New Patient','wound_care'=>'Wound Care','awv'=>'AWV','ccm'=>'CCM Visit','il'=>'IL Disclosure'];

// ─── POST: create new rule ────────────────────────────────────────────────────
$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_rule') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { http_response_code(403); die(); }

    $maId      = (int)($_POST['ma_id']      ?? 0);
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $visitType = in_array($_POST['visit_type'] ?? '', array_keys($VT_LABELS)) ? $_POST['visit_type'] : 'routine';
    $visitTime = trim($_POST['visit_time'] ?? '') ?: null;
    $notes     = trim($_POST['notes'] ?? '');
    $frequency = in_array($_POST['frequency'] ?? '', ['weekly','biweekly','monthly']) ? $_POST['frequency'] : 'weekly';
    $daysRaw   = array_filter(array_map('intval', (array)($_POST['days_of_week'] ?? [])), fn($d) => $d >= 1 && $d <= 7);
    sort($daysRaw);
    $daysStr   = implode(',', $daysRaw) ?: null;
    $startDate = $_POST['start_date'] ?? date('Y-m-d');
    $endType   = $_POST['end_type'] ?? 'none'; // none | bydate | bycount
    $endDate   = ($endType === 'bydate' && !empty($_POST['end_date'])) ? $_POST['end_date'] : null;
    $occurrences = ($endType === 'bycount' && isset($_POST['occurrences']) && $_POST['occurrences'] !== '')
                   ? (int)$_POST['occurrences'] : null;
    $genWeeks  = max(1, min(52, (int)($_POST['gen_weeks'] ?? 8)));

    if (!$maId)      $errors[] = 'Please select an MA.';
    if (!$patientId) $errors[] = 'Please select a patient.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $errors[] = 'Invalid start date.';
    if ($frequency !== 'monthly' && empty($daysRaw)) $errors[] = 'Select at least one day of the week.';
    if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) $errors[] = 'Invalid end date.';
    if ($endDate && $endDate < $startDate) $errors[] = 'End date must be on or after start date.';
    if ($occurrences !== null && $occurrences < 1) $errors[] = 'Occurrences must be at least 1.';

    if (!$errors) {
        $pdo->prepare("INSERT INTO `schedule_recurring`
            (ma_id,patient_id,visit_type,visit_time,notes,frequency,days_of_week,start_date,end_date,occurrences,created_by)
            VALUES(?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$maId, $patientId, $visitType, $visitTime, $notes ?: null,
                       $frequency, $daysStr, $startDate, $endDate, $occurrences, $_SESSION['user_id']]);
        $ruleId = (int)$pdo->lastInsertId();

        $rStmt = $pdo->prepare("SELECT * FROM `schedule_recurring` WHERE id=?");
        $rStmt->execute([$ruleId]);
        $newRule = $rStmt->fetch();
        $genUntil = date('Y-m-d', strtotime("+{$genWeeks} weeks", strtotime($startDate)));
        $cnt = rr_generate($pdo, $newRule, $genUntil);

        $success = "Recurring rule created. {$cnt} visit" . ($cnt !== 1 ? 's' : '') . " scheduled.";
    }
}

// ─── POST: generate more visits ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { http_response_code(403); die(); }
    $ruleId   = (int)($_POST['rule_id'] ?? 0);
    $genUntil = $_POST['gen_until'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $genUntil)) $genUntil = date('Y-m-d', strtotime('+4 weeks'));

    $rStmt = $pdo->prepare("SELECT * FROM `schedule_recurring` WHERE id=? AND active=1");
    $rStmt->execute([$ruleId]);
    $rule = $rStmt->fetch();
    $cnt = $rule ? rr_generate($pdo, $rule, $genUntil) : 0;

    $msg = $cnt > 0
        ? "Generated {$cnt} new visit" . ($cnt !== 1 ? 's' : '') . " through " . date('M j, Y', strtotime($genUntil)) . "."
        : "No new visits to generate (all dates already scheduled or past the end).";

    header('Location: ' . BASE_URL . '/admin/recurring_schedule.php?msg=' . urlencode($msg));
    exit;
}

// ─── POST: deactivate rule ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deactivate') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { http_response_code(403); die(); }
    $ruleId = (int)($_POST['rule_id'] ?? 0);
    if ($ruleId) $pdo->prepare("UPDATE `schedule_recurring` SET active=0 WHERE id=?")->execute([$ruleId]);
    header('Location: ' . BASE_URL . '/admin/recurring_schedule.php?msg=' . urlencode('Recurring rule deactivated. Existing visits are unchanged.'));
    exit;
}

// ─── Load data ────────────────────────────────────────────────────────────────
$flashMsg = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';

$allMas      = $pdo->query("SELECT id, full_name FROM staff WHERE active=1 ORDER BY full_name")->fetchAll();
$allPatients = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM patients ORDER BY last_name,first_name")->fetchAll();

$rules = $pdo->query("
    SELECT r.*,
           CONCAT(p.first_name,' ',p.last_name) AS patient_name,
           s.full_name AS ma_name
    FROM   `schedule_recurring` r
    JOIN   patients p ON p.id = r.patient_id
    JOIN   staff    s ON s.id = r.ma_id
    WHERE  r.active = 1
    ORDER  BY s.full_name, p.last_name, p.first_name
")->fetchAll();

// Visit counts per rule
$visitCounts = [];
if ($rules) {
    $ids  = array_column($rules, 'id');
    $ph   = implode(',', array_fill(0, count($ids), '?'));
    $vcSt = $pdo->prepare("SELECT recurring_rule_id, COUNT(*) AS cnt FROM `schedule` WHERE recurring_rule_id IN ($ph) GROUP BY recurring_rule_id");
    $vcSt->execute($ids);
    foreach ($vcSt->fetchAll() as $vc) $visitCounts[$vc['recurring_rule_id']] = (int)$vc['cnt'];
}

include __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?>
<div id="toast" class="fixed top-20 right-4 z-50 flex items-center gap-3 bg-emerald-600 text-white px-5 py-3.5 rounded-2xl shadow-2xl text-sm font-semibold">
    <i class="bi bi-check-circle-fill text-lg"></i> <?= h($success) ?>
</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.remove();},4000);</script>
<?php endif; ?>

<?php if ($flashMsg): ?>
<div id="toast" class="fixed top-20 right-4 z-50 flex items-center gap-3 bg-indigo-600 text-white px-5 py-3.5 rounded-2xl shadow-2xl text-sm font-semibold max-w-xs">
    <i class="bi bi-info-circle-fill text-lg shrink-0"></i>
    <span><?= h($flashMsg) ?></span>
</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.remove();},4000);</script>
<?php endif; ?>

<!-- ── Page header ─────────────────────────────────────────────────────────── -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-800">
            <i class="bi bi-arrow-repeat text-indigo-500 mr-1"></i> Recurring Schedule
        </h2>
        <p class="text-slate-500 text-sm mt-0.5">Create repeating visit patterns — auto-fill the schedule ahead of time</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/admin/schedule_manage.php"
           class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-700 rounded-xl text-sm font-semibold hover:bg-slate-50 transition-colors">
            <i class="bi bi-calendar-week-fill text-indigo-400"></i> Manage Schedule
        </a>
        <button onclick="toggleCreate()"
                class="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-sm transition-colors shadow-sm">
            <i class="bi bi-plus-circle-fill"></i> New Rule
        </button>
    </div>
</div>

<!-- ── Error alert ────────────────────────────────────────────────────────── -->
<?php if ($errors): ?>
<div class="bg-red-50 border border-red-200 rounded-2xl px-5 py-4 mb-5 flex items-start gap-3 text-sm text-red-700">
    <i class="bi bi-exclamation-triangle-fill text-red-500 mt-0.5 shrink-0"></i>
    <ul class="space-y-0.5"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<!-- ── Create Rule form ───────────────────────────────────────────────────── -->
<div id="createRuleCard" class="<?= $errors ? '' : 'hidden' ?> bg-white border border-indigo-100 rounded-2xl shadow-sm mb-6">

    <div class="bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-4 rounded-t-2xl flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-white/20 rounded-xl grid place-items-center">
                <i class="bi bi-arrow-repeat text-white text-xl"></i>
            </div>
            <div>
                <h3 class="text-white font-bold text-base">Create Recurring Rule</h3>
                <p class="text-indigo-200 text-xs">Auto-schedule visits on a repeating pattern</p>
            </div>
        </div>
        <button type="button" onclick="toggleCreate()" class="text-white/60 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition-colors">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <form method="POST" class="p-6 space-y-5" id="createRuleForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="create_rule">

        <!-- Row 1: MA + Patient -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">MA / Staff <span class="text-red-500">*</span></label>
                <select name="ma_id" required
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white transition">
                    <option value="">Select MA...</option>
                    <?php foreach ($allMas as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= (($_POST['ma_id']??0)==$m['id'])?'selected':'' ?>><?= h($m['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Patient <span class="text-red-500">*</span></label>
                <select name="patient_id" required
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white transition">
                    <option value="">Select patient...</option>
                    <?php foreach ($allPatients as $pt): ?>
                    <option value="<?= $pt['id'] ?>" <?= (($_POST['patient_id']??0)==$pt['id'])?'selected':'' ?>><?= h($pt['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Row 2: Visit Type + Time + Notes -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Visit Type</label>
                <select name="visit_type"
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white transition">
                    <?php foreach ($VT_LABELS as $vk => $vl): ?>
                    <option value="<?= $vk ?>" <?= (($_POST['visit_type']??'routine')===$vk)?'selected':'' ?>><?= $vl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Visit Time <span class="font-normal text-slate-400">(optional)</span></label>
                <input type="time" name="visit_time" value="<?= h($_POST['visit_time'] ?? '') ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white transition">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Notes <span class="font-normal text-slate-400">(optional)</span></label>
                <input type="text" name="notes" value="<?= h($_POST['notes'] ?? '') ?>" placeholder="e.g. call ahead, back door..."
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white transition">
            </div>
        </div>

        <!-- Row 3: Frequency + Days of week -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Repeat frequency</label>
                <div class="flex flex-wrap gap-2" id="freqGroup">
                    <?php foreach (['weekly'=>'Every week','biweekly'=>'Every 2 weeks','monthly'=>'Every month'] as $fk => $fl): ?>
                    <label class="freq-pill cursor-pointer select-none">
                        <input type="radio" name="frequency" value="<?= $fk ?>" class="sr-only"
                               <?= (($_POST['frequency']??'weekly')===$fk)?'checked':'' ?>
                               onchange="onFreqChange(this.value)">
                        <span class="inline-block px-4 py-2 rounded-xl border text-sm font-semibold transition-colors
                                     border-slate-200 text-slate-600 bg-white hover:border-indigo-300">
                            <?= $fl ?>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div id="daysSection">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Days of week</label>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach ($DAY_NAMES as $num => $name): ?>
                    <label class="cursor-pointer select-none">
                        <input type="checkbox" name="days_of_week[]" value="<?= $num ?>" class="sr-only"
                               <?= in_array($num, array_map('intval', (array)($_POST['days_of_week']??[]))) ? 'checked' : '' ?>
                               onchange="updatePreview()">
                        <span class="day-cb inline-block w-10 py-1.5 rounded-xl border text-xs font-bold text-center transition-colors
                                     border-slate-200 text-slate-500 bg-white hover:border-indigo-300">
                            <?= $name ?>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Row 4: Start + End condition + Generate ahead -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Start date <span class="text-red-500">*</span></label>
                <input type="date" name="start_date" id="startDate" required
                       value="<?= h($_POST['start_date'] ?? date('Y-m-d')) ?>"
                       onchange="updatePreview()"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white transition">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">End condition</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="end_type" value="none" class="text-indigo-600"
                               <?= (($_POST['end_type']??'none')==='none')?'checked':'' ?>
                               onchange="onEndTypeChange('none')">
                        <span class="text-sm text-slate-700">No end date</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="end_type" value="bydate" class="text-indigo-600"
                               <?= (($_POST['end_type']??'')==='bydate')?'checked':'' ?>
                               onchange="onEndTypeChange('bydate')">
                        <span class="text-sm text-slate-700">End by date</span>
                    </label>
                    <div id="endDateRow" class="<?= (($_POST['end_type']??'')==='bydate')?'':'hidden' ?> pl-5">
                        <input type="date" name="end_date" value="<?= h($_POST['end_date'] ?? '') ?>"
                               class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white transition">
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="end_type" value="bycount" class="text-indigo-600"
                               <?= (($_POST['end_type']??'')==='bycount')?'checked':'' ?>
                               onchange="onEndTypeChange('bycount')">
                        <span class="text-sm text-slate-700">End after N visits</span>
                    </label>
                    <div id="endCountRow" class="<?= (($_POST['end_type']??'')==='bycount')?'':'hidden' ?> pl-5">
                        <input type="number" name="occurrences" min="1" max="999"
                               value="<?= h($_POST['occurrences'] ?? '12') ?>" placeholder="e.g. 12"
                               class="w-24 px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white transition">
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Generate ahead
                    <span class="font-normal text-slate-400">(weeks)</span>
                </label>
                <input type="number" name="gen_weeks" min="1" max="52"
                       value="<?= h($_POST['gen_weeks'] ?? '8') ?>"
                       class="w-28 px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white transition">
                <p class="text-xs text-slate-400 mt-1">How far ahead to schedule on save</p>
            </div>
        </div>

        <!-- Date preview -->
        <div id="previewBox" class="hidden bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3">
            <p class="text-xs font-semibold text-indigo-700 mb-2">
                <i class="bi bi-calendar3 mr-1"></i> Upcoming dates preview (first 10)
            </p>
            <div id="previewList" class="flex flex-wrap gap-1.5 text-xs text-indigo-800"></div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end pt-2 border-t border-slate-100">
            <button type="submit"
                    class="flex items-center gap-2 px-7 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-sm transition-colors shadow-sm">
                <i class="bi bi-arrow-repeat"></i> Create Rule &amp; Generate Visits
            </button>
        </div>
    </form>
</div>

<!-- ── Rules list ─────────────────────────────────────────────────────────── -->
<?php if (empty($rules)): ?>
<div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-12 text-center">
    <div class="w-16 h-16 bg-indigo-50 rounded-2xl grid place-items-center mx-auto mb-4">
        <i class="bi bi-arrow-repeat text-indigo-400 text-3xl"></i>
    </div>
    <p class="text-slate-600 font-semibold text-lg mb-1">No recurring rules yet</p>
    <p class="text-slate-400 text-sm mb-5">Create a rule to automatically schedule repeating visits ahead of time.</p>
    <button onclick="toggleCreate()"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-sm transition-colors">
        <i class="bi bi-plus-circle-fill"></i> New Recurring Rule
    </button>
</div>
<?php else: ?>
<div class="space-y-4">
    <?php foreach ($rules as $rule):
        $nextDate = rr_next_date($rule);
        $totalCnt = $visitCounts[$rule['id']] ?? 0;
        $days     = $rule['days_of_week'] ? array_map('intval', explode(',', $rule['days_of_week'])) : [];
        $dayLabels= array_map(fn($d) => $DAY_NAMES[$d] ?? $d, $days);
    ?>
    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">

        <!-- Rule header -->
        <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 bg-indigo-100 text-indigo-700 rounded-xl grid place-items-center font-bold text-sm shrink-0">
                    <i class="bi bi-arrow-repeat text-base"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-slate-800"><?= h($rule['patient_name']) ?></span>
                        <span class="text-slate-400">·</span>
                        <span class="text-slate-600 text-sm"><?= h($rule['ma_name']) ?></span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-100 text-violet-700">
                            <?= h($VT_LABELS[$rule['visit_type']] ?? $rule['visit_type']) ?>
                        </span>
                    </div>
                    <div class="text-sm text-slate-500 mt-0.5 flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-indigo-600"><?= h($FREQ_LABELS[$rule['frequency']]) ?></span>
                        <?php if ($dayLabels): ?>
                        <span class="text-slate-400">·</span>
                        <span><?= implode(', ', array_map('h', $dayLabels)) ?></span>
                        <?php endif; ?>
                        <span class="text-slate-400">·</span>
                        <span>Starting <?= date('M j, Y', strtotime($rule['start_date'])) ?></span>
                        <?php if ($rule['end_date']): ?>
                        <span class="text-slate-400">·</span>
                        <span>Until <?= date('M j, Y', strtotime($rule['end_date'])) ?></span>
                        <?php elseif ($rule['occurrences']): ?>
                        <span class="text-slate-400">·</span>
                        <span><?= $rule['occurrences'] ?> visits max</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-3 mt-1 text-xs text-slate-400">
                        <span><i class="bi bi-calendar-check mr-1"></i><?= $totalCnt ?> visit<?= $totalCnt !== 1 ? 's' : '' ?> generated</span>
                        <?php if ($nextDate): ?>
                        <span class="text-emerald-600 font-semibold"><i class="bi bi-arrow-right-circle mr-1"></i>Next: <?= date('M j, Y', strtotime($nextDate)) ?></span>
                        <?php else: ?>
                        <span class="text-red-500"><i class="bi bi-x-circle mr-1"></i>No upcoming dates</span>
                        <?php endif; ?>
                        <?php if ($rule['visit_time']): ?>
                        <span><i class="bi bi-clock mr-1"></i><?= date('g:i A', strtotime($rule['visit_time'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex items-center gap-2 shrink-0">
                <button type="button"
                        onclick="toggleGenerate(<?= $rule['id'] ?>)"
                        class="flex items-center gap-1.5 px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-xl transition-colors border border-emerald-200">
                    <i class="bi bi-lightning-charge-fill"></i> Generate More
                </button>
                <form method="POST" onsubmit="return confirm('Deactivate this rule? Existing scheduled visits will remain.')">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="deactivate">
                    <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                    <button type="submit"
                            class="flex items-center gap-1.5 px-3 py-2 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-bold rounded-xl transition-colors border border-red-200">
                        <i class="bi bi-stop-circle"></i> Stop
                    </button>
                </form>
            </div>
        </div>

        <!-- Generate more (inline expand) -->
        <div id="generate-<?= $rule['id'] ?>" class="hidden border-t border-slate-100 bg-slate-50 px-6 py-4">
            <form method="POST" class="flex flex-wrap items-end gap-4">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="generate">
                <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Generate visits through</label>
                    <input type="date" name="gen_until"
                           value="<?= date('Y-m-d', strtotime('+8 weeks')) ?>"
                           class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-400 transition">
                </div>
                <button type="submit"
                        class="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-sm transition-colors shadow-sm">
                    <i class="bi bi-lightning-charge-fill"></i> Generate
                </button>
                <button type="button" onclick="toggleGenerate(<?= $rule['id'] ?>)"
                        class="px-4 py-2.5 text-slate-600 hover:text-slate-800 text-sm font-semibold transition-colors">
                    Cancel
                </button>
            </form>
        </div>

    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
// ── Toggle create form ──────────────────────────────────────────────────────
function toggleCreate() {
    const c = document.getElementById('createRuleCard');
    c.classList.toggle('hidden');
}

// ── Toggle generate sub-form ────────────────────────────────────────────────
function toggleGenerate(id) {
    const el = document.getElementById('generate-' + id);
    if (el) el.classList.toggle('hidden');
}

// ── Frequency radio pill styling ────────────────────────────────────────────
function updateFreqPills() {
    document.querySelectorAll('#freqGroup input[type=radio]').forEach(function(r) {
        const span = r.parentElement.querySelector('span');
        if (r.checked) {
            span.classList.remove('border-slate-200','text-slate-600','bg-white');
            span.classList.add('border-indigo-500','text-indigo-700','bg-indigo-50');
        } else {
            span.classList.remove('border-indigo-500','text-indigo-700','bg-indigo-50');
            span.classList.add('border-slate-200','text-slate-600','bg-white');
        }
    });
}

// ── Day checkbox styling ─────────────────────────────────────────────────────
function updateDayPills() {
    document.querySelectorAll('#daysSection input[type=checkbox]').forEach(function(cb) {
        const span = cb.parentElement.querySelector('span');
        if (cb.checked) {
            span.classList.remove('border-slate-200','text-slate-500','bg-white');
            span.classList.add('border-indigo-500','text-indigo-700','bg-indigo-50');
        } else {
            span.classList.remove('border-indigo-500','text-indigo-700','bg-indigo-50');
            span.classList.add('border-slate-200','text-slate-500','bg-white');
        }
    });
}

// ── Frequency change: show/hide days ────────────────────────────────────────
function onFreqChange(val) {
    updateFreqPills();
    const ds = document.getElementById('daysSection');
    ds.style.opacity = (val === 'monthly') ? '0.35' : '1';
    ds.querySelectorAll('input[type=checkbox]').forEach(cb => cb.disabled = (val === 'monthly'));
    updatePreview();
}

// ── End condition toggle ─────────────────────────────────────────────────────
function onEndTypeChange(val) {
    document.getElementById('endDateRow').classList.toggle('hidden', val !== 'bydate');
    document.getElementById('endCountRow').classList.toggle('hidden', val !== 'bycount');
    updatePreview();
}

// ── Date preview ─────────────────────────────────────────────────────────────
function updatePreview() {
    updateDayPills();
    const freq      = document.querySelector('#freqGroup input:checked')?.value || 'weekly';
    const startVal  = document.getElementById('startDate')?.value;
    const days      = Array.from(document.querySelectorAll('#daysSection input[type=checkbox]:checked'))
                          .map(cb => parseInt(cb.value)).sort();

    if (!startVal) { document.getElementById('previewBox').classList.add('hidden'); return; }
    if (freq !== 'monthly' && days.length === 0) { document.getElementById('previewBox').classList.add('hidden'); return; }

    const dates = [];
    const start = new Date(startVal + 'T00:00:00');

    if (freq === 'monthly') {
        let d = new Date(start);
        for (let i = 0; i < 10; i++) {
            dates.push(new Date(d));
            d.setMonth(d.getMonth() + 1);
        }
    } else {
        const itvWeeks = freq === 'biweekly' ? 2 : 1;
        // Monday of the week containing start
        let mon = new Date(start);
        const dow = mon.getDay() || 7; // 1=Mon, 7=Sun
        if (dow > 1) mon.setDate(mon.getDate() - (dow - 1));

        let weekNum = 0;
        const limit = new Date(start); limit.setFullYear(limit.getFullYear() + 2);

        while (mon <= limit && dates.length < 10) {
            if (weekNum % itvWeeks === 0) {
                for (const isoDay of days) {
                    const d = new Date(mon);
                    d.setDate(d.getDate() + (isoDay - 1));
                    if (d >= start && dates.length < 10) dates.push(new Date(d));
                    if (dates.length >= 10) break;
                }
            }
            mon.setDate(mon.getDate() + 7);
            weekNum++;
        }
    }

    if (dates.length === 0) { document.getElementById('previewBox').classList.add('hidden'); return; }

    const fmtOpts = { month: 'short', day: 'numeric', year: 'numeric' };
    document.getElementById('previewList').innerHTML = dates
        .map(d => `<span class="px-2 py-1 bg-white border border-indigo-200 rounded-lg">${d.toLocaleDateString('en-US', fmtOpts)}</span>`)
        .join('');
    document.getElementById('previewBox').classList.remove('hidden');
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    updateFreqPills();
    updateDayPills();
    const checkedFreq = document.querySelector('#freqGroup input:checked')?.value || 'weekly';
    if (checkedFreq === 'monthly') {
        const ds = document.getElementById('daysSection');
        ds.style.opacity = '0.35';
        ds.querySelectorAll('input[type=checkbox]').forEach(cb => cb.disabled = true);
    }
    updatePreview();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
