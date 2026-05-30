<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$pageTitle = 'Daily Summary';
$activeNav = 'daily_summary';
$myId      = (int)$_SESSION['user_id'];
$myRole    = $_SESSION['role'] ?? '';
$canSeeAll = in_array($myRole, ['admin', 'provider', 'pcc'], true);

// ── Date filter ─────────────────────────────────────────────────────────────
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$isToday   = $date === date('Y-m-d');
$dateLabel = date('D, M j, Y', strtotime($date));
$prevDate  = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate  = date('Y-m-d', strtotime($date . ' +1 day'));

// ── Determine which MAs to show ─────────────────────────────────────────────
if ($canSeeAll) {
    $maRows = $pdo->query(
        "SELECT s.id, s.full_name
         FROM staff s
         WHERE s.active = 1 AND s.role = 'ma'
         ORDER BY s.full_name"
    )->fetchAll();
} else {
    $maRows = [['id' => $myId, 'full_name' => $_SESSION['full_name'] ?? 'Me']];
}
$maIds = array_column($maRows, 'id');

// ── Bail early if no MA staff ────────────────────────────────────────────────
if (empty($maIds)) {
    include __DIR__ . '/includes/header.php';
    echo '<div style="max-width:640px;margin:4rem auto;text-align:center;color:#94a3b8;">No MA staff found.</div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$inPh = implode(',', array_fill(0, count($maIds), '?'));

// ── 1. Schedule visits ───────────────────────────────────────────────────────
$vsStmt = $pdo->prepare("
    SELECT sc.id, sc.ma_id, sc.patient_id, sc.status,
           sc.visit_started_at, sc.visit_ended_at,
           sc.visit_type, sc.notes,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
           p.address AS patient_address
    FROM `schedule` sc
    JOIN patients p ON p.id = sc.patient_id
    WHERE sc.ma_id IN ($inPh) AND sc.visit_date = ?
    ORDER BY sc.ma_id, sc.visit_order ASC, sc.visit_time ASC
");
$vsStmt->execute(array_merge($maIds, [$date]));
$allVisits = $vsStmt->fetchAll();

// Group visits by ma_id
$visitsByMa = [];
foreach ($allVisits as $v) $visitsByMa[(int)$v['ma_id']][] = $v;

// ── 2. Missed visit reasons ──────────────────────────────────────────────────
$missedPids  = array_unique(array_column(
    array_filter($allVisits, fn($v) => $v['status'] === 'missed'),
    'patient_id'
));
$missedReasons = [];
if ($missedPids) {
    $mIn = implode(',', array_fill(0, count($missedPids), '?'));
    $mrS = $pdo->prepare("
        SELECT patient_id,
               JSON_UNQUOTE(JSON_EXTRACT(form_data, '$.missed_visit_reason')) AS reason
        FROM form_submissions
        WHERE patient_id IN ($mIn) AND form_type = 'vital_cs'
          AND DATE(created_at) = ?
        ORDER BY created_at DESC
    ");
    $mrS->execute(array_merge($missedPids, [$date]));
    foreach ($mrS->fetchAll() as $mr) {
        $pid = (int)$mr['patient_id'];
        if (!isset($missedReasons[$pid]) && !empty($mr['reason'])) {
            $missedReasons[$pid] = $mr['reason'];
        }
    }
}

// ── 3. Forms submitted per MA ────────────────────────────────────────────────
$fmS = $pdo->prepare("
    SELECT ma_id, form_type, COUNT(*) AS cnt
    FROM form_submissions
    WHERE ma_id IN ($inPh) AND DATE(created_at) = ?
    GROUP BY ma_id, form_type
");
$fmS->execute(array_merge($maIds, [$date]));
$formsByMa = [];
foreach ($fmS->fetchAll() as $f) {
    $mid = (int)$f['ma_id'];
    if (!isset($formsByMa[$mid])) $formsByMa[$mid] = ['total' => 0, 'types' => []];
    $formsByMa[$mid]['total'] += (int)$f['cnt'];
    $formsByMa[$mid]['types'][$f['form_type']] = (int)$f['cnt'];
}

// ── 4. Medication reconciliations per MA ─────────────────────────────────────
$medsByMa = [];
try {
    $mdS = $pdo->prepare("
        SELECT changed_by, COUNT(*) AS cnt
        FROM medication_history
        WHERE changed_by IN ($inPh) AND DATE(changed_at) = ?
        GROUP BY changed_by
    ");
    $mdS->execute(array_merge($maIds, [$date]));
    foreach ($mdS->fetchAll() as $r) $medsByMa[(int)$r['changed_by']] = (int)$r['cnt'];
} catch (PDOException $e) {}

// ── 5. Wounds documented per MA ──────────────────────────────────────────────
$woundsByMa = [];
try {
    $wdS = $pdo->prepare("
        SELECT recorded_by, COUNT(*) AS cnt
        FROM wound_measurements
        WHERE recorded_by IN ($inPh) AND measured_at = ?
        GROUP BY recorded_by
    ");
    $wdS->execute(array_merge($maIds, [$date]));
    foreach ($wdS->fetchAll() as $r) $woundsByMa[(int)$r['recorded_by']] = (int)$r['cnt'];
} catch (PDOException $e) {}

// ── 6. Messages sent per MA ──────────────────────────────────────────────────
$msgsByMa = [];
try {
    $mgS = $pdo->prepare("
        SELECT from_user_id, COUNT(*) AS cnt
        FROM messages
        WHERE from_user_id IN ($inPh) AND DATE(created_at) = ?
        GROUP BY from_user_id
    ");
    $mgS->execute(array_merge($maIds, [$date]));
    foreach ($mgS->fetchAll() as $r) $msgsByMa[(int)$r['from_user_id']] = (int)$r['cnt'];
} catch (PDOException $e) {}

// ── Human-readable form type labels ─────────────────────────────────────────
$formLabels = [
    'vital_cs'              => 'Visit Consent',
    'new_patient'           => 'New Patient',
    'new_patient_pocket'    => 'New Patient Pocket',
    'new_patient_pocket_pc' => 'New Patient PC',
    'wound_care_consent'    => 'Wound Care Consent',
    'informed_consent_wound'=> 'Informed Consent',
    'abn'                   => 'ABN',
    'ccm_consent'           => 'CCM Consent',
    'medicare_awv'          => 'Annual Wellness',
    'cognitive_wellness'    => 'Cognitive Wellness',
    'rpm_consent'           => 'RPM Consent',
    'il_disclosure'         => 'IL Disclosure',
    'pf_signup'             => 'PF Signup',
];

include __DIR__ . '/includes/header.php';
?>

<style>
.ds-card {
    background: #1e293b;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,0.22), 0 1px 4px rgba(0,0,0,0.12);
    margin-bottom: 20px;
}
.ds-card-hd {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
    padding: 18px 20px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.ds-stat-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 14px 20px 0;
}
.ds-stat {
    display: flex;
    align-items: center;
    gap: 7px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 10px;
    padding: 8px 12px;
    font-size: 13px;
    color: #cbd5e1;
    white-space: nowrap;
}
.ds-stat strong { color: #f1f5f9; font-size: 15px; font-weight: 800; margin-right: 2px; }
.ds-stat i { font-size: 14px; flex-shrink: 0; }
.ds-metric-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 10px 20px 14px;
    border-top: 1px solid rgba(255,255,255,0.06);
    margin-top: 12px;
}
.ds-metric {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #94a3b8;
}
.ds-metric strong { color: #e2e8f0; font-weight: 700; }
.ds-metric i { font-size: 13px; }
.ds-patient-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 20px;
    border-top: 1px solid rgba(255,255,255,0.05);
    font-size: 13px;
    color: #cbd5e1;
}
.ds-patient-row:last-child { padding-bottom: 14px; }
.ds-toggle {
    width: 100%;
    background: transparent;
    border: none;
    border-top: 1px solid rgba(255,255,255,0.06);
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
    padding: 10px 20px;
    text-align: left;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: color 0.15s;
}
.ds-toggle:hover { color: #94a3b8; }
.ds-breakdown { display: none; }
.ds-breakdown.open { display: block; }
.ds-date-nav {
    display: flex;
    align-items: center;
    gap: 8px;
}
.ds-date-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    color: #94a3b8;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.15s;
}
.ds-date-btn:hover { background: rgba(255,255,255,0.15); color: #f1f5f9; }
.ds-date-label {
    font-size: 15px;
    font-weight: 700;
    color: #f1f5f9;
    padding: 0 4px;
}
.ds-empty {
    text-align: center;
    padding: 40px 20px;
    color: #475569;
    font-size: 14px;
}
.ds-empty i { font-size: 36px; display: block; margin-bottom: 10px; color: #334155; }

/* Light mode overrides */
.light .ds-card { background: #fff; box-shadow: 0 2px 16px rgba(0,0,0,0.10); }
.light .ds-stat { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
.light .ds-stat strong { color: #1e293b; }
.light .ds-metric { color: #64748b; }
.light .ds-metric strong { color: #1e293b; }
.light .ds-patient-row { color: #475569; border-color: #f1f5f9; }
.light .ds-toggle { color: #94a3b8; border-color: #f1f5f9; }
.light .ds-toggle:hover { color: #475569; }
.light .ds-metric-row { border-color: #f1f5f9; }
.light .ds-empty { color: #94a3b8; }
.light .ds-empty i { color: #e2e8f0; }
</style>

<div class="page-fade max-w-2xl mx-auto px-4 pb-10">

    <!-- Page header -->
    <div class="flex items-center justify-between gap-4 mb-6 pt-1">
        <div>
            <h1 style="font-size:22px;font-weight:900;color:#f1f5f9;letter-spacing:-0.02em;margin:0 0 2px;">
                <i class="bi bi-bar-chart-line-fill" style="color:#818cf8;margin-right:8px;"></i>Daily Summary
            </h1>
            <p style="font-size:13px;color:#64748b;margin:0;">Activity breakdown by MA</p>
        </div>

        <!-- Date navigation -->
        <div class="ds-date-nav">
            <a href="?date=<?= h($prevDate) ?>" class="ds-date-btn" title="Previous day">
                <i class="bi bi-chevron-left"></i>
            </a>
            <div style="position:relative;">
                <span class="ds-date-label" style="cursor:pointer;" onclick="document.getElementById('dsDateInput').showPicker()">
                    <?= $isToday ? 'Today' : $dateLabel ?>
                </span>
                <input type="date" id="dsDateInput" value="<?= h($date) ?>"
                       style="position:absolute;opacity:0;width:1px;height:1px;pointer-events:none;"
                       onchange="location.href='?date='+this.value">
            </div>
            <a href="?date=<?= h($nextDate) ?>" class="ds-date-btn <?= $date >= date('Y-m-d') ? 'opacity-30 pointer-events-none' : '' ?>" title="Next day">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>

    <?php if (empty($maRows)): ?>
    <div class="ds-card">
        <div class="ds-empty"><i class="bi bi-person-x"></i>No MA staff found.</div>
    </div>
    <?php else: ?>

    <?php foreach ($maRows as $ma):
        $mid      = (int)$ma['id'];
        $mvVisits = $visitsByMa[$mid] ?? [];
        $mForms   = $formsByMa[$mid]  ?? ['total' => 0, 'types' => []];
        $mMeds    = $medsByMa[$mid]   ?? 0;
        $mWounds  = $woundsByMa[$mid] ?? 0;
        $mMsgs    = $msgsByMa[$mid]   ?? 0;

        // Visit status counts
        $vCounts  = ['completed' => 0, 'missed' => 0, 'pending' => 0, 'en_route' => 0];
        foreach ($mvVisits as $v) $vCounts[$v['status'] ?? 'pending']++;
        $totalV   = count($mvVisits);

        // Time on site calculation
        $durations = [];
        foreach ($mvVisits as $v) {
            if (!empty($v['visit_started_at']) && !empty($v['visit_ended_at'])) {
                $secs = strtotime($v['visit_ended_at']) - strtotime($v['visit_started_at']);
                if ($secs > 0 && $secs < 86400) $durations[] = $secs;
            }
        }
        $avgMins = count($durations) ? round(array_sum($durations) / count($durations) / 60) : null;
        $totalMins = $durations ? round(array_sum($durations) / 60) : null;

        // Route stops (distinct non-empty addresses)
        $stops = count(array_unique(array_filter(array_column($mvVisits, 'patient_address'))));

        // Nothing at all for this MA today?
        $hasActivity = $totalV > 0 || $mForms['total'] > 0 || $mMeds > 0 || $mWounds > 0 || $mMsgs > 0;
    ?>
    <div class="ds-card">

        <!-- Header -->
        <div class="ds-card-hd">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:38px;height:38px;background:rgba(255,255,255,0.15);border-radius:12px;
                            display:flex;align-items:center;justify-content:center;
                            font-size:13px;font-weight:900;color:#fff;flex-shrink:0;letter-spacing:-0.02em;">
                    <?= strtoupper(mb_substr($ma['full_name'], 0, 2)) ?>
                </div>
                <div>
                    <div style="font-size:17px;font-weight:900;color:#fff;line-height:1.2;">
                        <?= h($ma['full_name']) ?>
                    </div>
                    <div style="font-size:11px;color:rgba(255,255,255,0.6);margin-top:1px;"><?= h($dateLabel) ?></div>
                </div>
            </div>
            <?php if ($canSeeAll): ?>
            <a href="<?= BASE_URL ?>/schedule.php?ma_id=<?= $mid ?>&date=<?= h($date) ?>"
               style="display:flex;align-items:center;gap:5px;background:rgba(255,255,255,0.12);
                      border:1px solid rgba(255,255,255,0.2);border-radius:10px;padding:6px 12px;
                      color:rgba(255,255,255,0.8);font-size:12px;font-weight:700;text-decoration:none;
                      transition:background 0.15s;flex-shrink:0;"
               onmouseover="this.style.background='rgba(255,255,255,0.22)'"
               onmouseout="this.style.background='rgba(255,255,255,0.12)'">
                <i class="bi bi-calendar3" style="font-size:12px;"></i> Schedule
            </a>
            <?php endif; ?>
        </div>

        <?php if (!$hasActivity): ?>
        <div class="ds-empty" style="padding:28px 20px;">
            <i class="bi bi-calendar-x" style="font-size:28px;display:block;margin-bottom:8px;color:#334155;"></i>
            No activity recorded
        </div>
        <?php else: ?>

        <!-- Visit stats -->
        <div class="ds-stat-row">
            <div class="ds-stat">
                <i class="bi bi-calendar-check" style="color:#818cf8;"></i>
                <strong><?= $totalV ?></strong> visit<?= $totalV !== 1 ? 's' : '' ?>
            </div>
            <?php if ($vCounts['completed']): ?>
            <div class="ds-stat">
                <i class="bi bi-check-circle-fill" style="color:#34d399;"></i>
                <strong><?= $vCounts['completed'] ?></strong> completed
            </div>
            <?php endif; ?>
            <?php if ($vCounts['missed']): ?>
            <div class="ds-stat">
                <i class="bi bi-x-circle-fill" style="color:#f87171;"></i>
                <strong><?= $vCounts['missed'] ?></strong> missed
            </div>
            <?php endif; ?>
            <?php if ($vCounts['pending']): ?>
            <div class="ds-stat">
                <i class="bi bi-clock" style="color:#94a3b8;"></i>
                <strong><?= $vCounts['pending'] ?></strong> pending
            </div>
            <?php endif; ?>
            <?php if ($vCounts['en_route']): ?>
            <div class="ds-stat">
                <i class="bi bi-car-front-fill" style="color:#60a5fa;"></i>
                <strong><?= $vCounts['en_route'] ?></strong> en route
            </div>
            <?php endif; ?>
        </div>

        <!-- Secondary metrics -->
        <div class="ds-metric-row">
            <?php if ($avgMins !== null): ?>
            <div class="ds-metric">
                <i class="bi bi-stopwatch-fill" style="color:#a78bfa;"></i>
                Avg on site: <strong><?= $avgMins ?> min</strong>
            </div>
            <span style="color:#334155;font-size:13px;">·</span>
            <div class="ds-metric">
                <i class="bi bi-clock-history" style="color:#a78bfa;"></i>
                Total: <strong><?= $totalMins >= 60 ? floor($totalMins/60).'h '.($totalMins%60).'m' : $totalMins.'m' ?></strong>
            </div>
            <span style="color:#334155;font-size:13px;">·</span>
            <?php endif; ?>
            <?php if ($stops > 0): ?>
            <div class="ds-metric">
                <i class="bi bi-geo-alt-fill" style="color:#fb923c;"></i>
                <strong><?= $stops ?></strong> stop<?= $stops !== 1 ? 's' : '' ?>
            </div>
            <?php endif; ?>
            <?php if ($mForms['total'] > 0): ?>
            <?php if ($stops > 0 || $avgMins !== null): ?><span style="color:#334155;font-size:13px;">·</span><?php endif; ?>
            <div class="ds-metric">
                <i class="bi bi-file-earmark-check-fill" style="color:#34d399;"></i>
                <strong><?= $mForms['total'] ?></strong> form<?= $mForms['total'] !== 1 ? 's' : '' ?>
            </div>
            <?php endif; ?>
            <?php if ($mMeds > 0): ?>
            <span style="color:#334155;font-size:13px;">·</span>
            <div class="ds-metric">
                <i class="bi bi-capsule" style="color:#f472b6;"></i>
                <strong><?= $mMeds ?></strong> med<?= $mMeds !== 1 ? 's' : '' ?> recon
            </div>
            <?php endif; ?>
            <?php if ($mWounds > 0): ?>
            <span style="color:#334155;font-size:13px;">·</span>
            <div class="ds-metric">
                <i class="bi bi-bandaid-fill" style="color:#fb923c;"></i>
                <strong><?= $mWounds ?></strong> wound<?= $mWounds !== 1 ? 's' : '' ?>
            </div>
            <?php endif; ?>
            <?php if ($mMsgs > 0): ?>
            <span style="color:#334155;font-size:13px;">·</span>
            <div class="ds-metric">
                <i class="bi bi-chat-dots-fill" style="color:#60a5fa;"></i>
                <strong><?= $mMsgs ?></strong> msg<?= $mMsgs !== 1 ? 's' : '' ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($mForms['total'] > 0 && count($mForms['types']) > 1): ?>
        <!-- Form type breakdown chips -->
        <div style="display:flex;flex-wrap:wrap;gap:6px;padding:0 20px 14px;">
            <?php foreach ($mForms['types'] as $ft => $fc): ?>
            <span style="background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.25);
                         color:#a5b4fc;border-radius:8px;padding:3px 10px;font-size:11px;font-weight:700;">
                <?= h($formLabels[$ft] ?? $ft) ?> &times;<?= $fc ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($mvVisits): ?>
        <!-- Toggle patient breakdown -->
        <button class="ds-toggle" onclick="dsToggle(this)" data-target="breakdown-<?= $mid ?>">
            <i class="bi bi-people-fill" style="font-size:12px;"></i>
            Patient breakdown
            <i class="bi bi-chevron-down" style="font-size:10px;margin-left:auto;transition:transform 0.2s;" id="chev-<?= $mid ?>"></i>
        </button>
        <div class="ds-breakdown" id="breakdown-<?= $mid ?>">
            <?php foreach ($mvVisits as $pv):
                $statusIcon  = ['completed'=>'bi-check-circle-fill','missed'=>'bi-x-circle-fill','en_route'=>'bi-car-front-fill','pending'=>'bi-clock'][$pv['status']] ?? 'bi-circle';
                $statusColor = ['completed'=>'#34d399','missed'=>'#f87171','en_route'=>'#60a5fa','pending'=>'#94a3b8'][$pv['status']] ?? '#94a3b8';
                $duration = null;
                if (!empty($pv['visit_started_at']) && !empty($pv['visit_ended_at'])) {
                    $s = strtotime($pv['visit_ended_at']) - strtotime($pv['visit_started_at']);
                    if ($s > 0 && $s < 86400) $duration = round($s / 60);
                }
                $mreason = $missedReasons[(int)$pv['patient_id']] ?? null;
            ?>
            <div class="ds-patient-row">
                <i class="bi <?= $statusIcon ?>" style="color:<?= $statusColor ?>;font-size:15px;flex-shrink:0;margin-top:1px;"></i>
                <div style="flex:1;min-width:0;">
                    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= (int)$pv['patient_id'] ?>"
                       style="font-weight:700;color:#e2e8f0;text-decoration:none;font-size:13px;"
                       onmouseover="this.style.color='#818cf8'" onmouseout="this.style.color='#e2e8f0'">
                        <?= h($pv['patient_name']) ?>
                    </a>
                    <?php if (!empty($pv['visit_started_at'])): ?>
                    <span style="color:#475569;font-size:11px;margin-left:6px;">
                        <?= date('g:i A', strtotime($pv['visit_started_at'])) ?>
                        <?php if (!empty($pv['visit_ended_at'])): ?>
                        → <?= date('g:i A', strtotime($pv['visit_ended_at'])) ?>
                        <?php endif; ?>
                        <?php if ($duration): ?>
                        <span style="color:#64748b;">(<?= $duration ?> min)</span>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($mreason): ?>
                    <div style="display:flex;align-items:center;gap:5px;margin-top:3px;
                                font-size:11px;color:#fca5a5;">
                        <i class="bi bi-calendar-x-fill" style="font-size:11px;flex-shrink:0;"></i>
                        <?= h($mreason) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($pv['status'] === 'completed'): ?>
                <span style="background:rgba(52,211,153,0.12);color:#34d399;border:1px solid rgba(52,211,153,0.25);
                             border-radius:8px;padding:2px 8px;font-size:11px;font-weight:700;flex-shrink:0;">
                    Done
                </span>
                <?php elseif ($pv['status'] === 'missed'): ?>
                <span style="background:rgba(248,113,113,0.12);color:#f87171;border:1px solid rgba(248,113,113,0.25);
                             border-radius:8px;padding:2px 8px;font-size:11px;font-weight:700;flex-shrink:0;">
                    Missed
                </span>
                <?php elseif ($pv['status'] === 'en_route'): ?>
                <span style="background:rgba(96,165,250,0.12);color:#60a5fa;border:1px solid rgba(96,165,250,0.25);
                             border-radius:8px;padding:2px 8px;font-size:11px;font-weight:700;flex-shrink:0;">
                    En Route
                </span>
                <?php else: ?>
                <span style="background:rgba(148,163,184,0.10);color:#94a3b8;border:1px solid rgba(148,163,184,0.15);
                             border-radius:8px;padding:2px 8px;font-size:11px;font-weight:700;flex-shrink:0;">
                    Pending
                </span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; // hasActivity ?>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

</div>

<script>
function dsToggle(btn) {
    var targetId = btn.dataset.target;
    var bd  = document.getElementById(targetId);
    var chv = document.getElementById('chev-' + targetId.replace('breakdown-', ''));
    if (!bd) return;
    var open = bd.classList.toggle('open');
    if (chv) chv.style.transform = open ? 'rotate(180deg)' : '';
}

// Date label: show "Today" dynamically for today
(function() {
    var input = document.getElementById('dsDateInput');
    if (!input) return;
    input.addEventListener('change', function() {
        if (this.value) location.href = '?date=' + this.value;
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
