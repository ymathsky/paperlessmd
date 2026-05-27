<?php
/**
 * Morning route push notifications for MAs.
 *
 * Sends each MA a push showing their visit count for today.
 *
 * Call via cron (runs at 7 AM daily server time):
 *   0 7 * * * /usr/bin/php /var/www/paperlessmd/api/push_morning_route.php >> /var/log/pd_push.log 2>&1
 *
 * Or via HTTP with a secret token:
 *   curl https://yourdomain.com/api/push_morning_route.php?token=YOUR_TOKEN
 */

require_once __DIR__ . '/../includes/db.php';

if (file_exists(__DIR__ . '/../includes/WebPush.php')) {
    require_once __DIR__ . '/../includes/WebPush.php';
}

$isCli = (PHP_SAPI === 'cli');

// ── Token-based HTTP authentication ──────────────────────────────────────────
if (!$isCli) {
    // Load or generate a secret token stored in the settings table
    $tokRow = $pdo->query("SELECT value FROM settings WHERE `key` = 'morning_push_token' LIMIT 1")->fetchColumn();
    if (!$tokRow) {
        // Generate and persist a token on first call
        $tokRow = bin2hex(random_bytes(24));
        $pdo->prepare("INSERT INTO settings (`key`, value, label) VALUES ('morning_push_token', ?, 'Morning push cron token')")
            ->execute([$tokRow]);
    }

    $reqToken = trim($_GET['token'] ?? '');
    if (!$reqToken || !hash_equals((string)$tokRow, $reqToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

// ── Main logic ────────────────────────────────────────────────────────────────
if (!function_exists('webpush_notify')) {
    $msg = "WebPush not available — cannot send morning route pushes.\n";
    if ($isCli) { echo $msg; } else { echo json_encode(['error' => $msg]); }
    exit;
}

$today = date('Y-m-d');
$baseUrl = defined('BASE_URL') ? BASE_URL : '';

// Get each MA who has visits today, with their visit count
$stmt = $pdo->prepare("
    SELECT sc.ma_id,
           s.full_name,
           COUNT(DISTINCT sc.id) AS visit_count
    FROM `schedule` sc
    JOIN staff s ON s.id = sc.ma_id
    WHERE sc.visit_date = ?
      AND sc.ma_id IS NOT NULL
      AND s.active = 1
    GROUP BY sc.ma_id
");
$stmt->execute([$today]);
$rows = $stmt->fetchAll();

$sent   = 0;
$skipped = 0;
$log    = [];

foreach ($rows as $row) {
    $staffId = (int)$row['ma_id'];
    $name    = $row['full_name'];
    $count   = (int)$row['visit_count'];

    // Check per-user preference (default ON)
    $prefStmt = $pdo->prepare('SELECT push_prefs FROM staff WHERE id = ? LIMIT 1');
    $prefStmt->execute([$staffId]);
    $prefs = json_decode((string)($prefStmt->fetchColumn() ?: '{}'), true) ?? [];
    if (($prefs['daily_route'] ?? true) === false) {
        $log[] = "Skipped (opted out): {$name} (id={$staffId})";
        $skipped++;
        continue;
    }

    $label   = $count === 1 ? '1 visit scheduled' : "{$count} visits scheduled";
    $url     = $baseUrl . '/schedule.php?date=' . urlencode($today);

    webpush_notify(
        $pdo,
        $staffId,
        'Good morning! Your route for today',
        $label . ' — tap to view your schedule',
        $url
    );

    $log[]  = "Sent to: {$name} (id={$staffId}, {$count} visits)";
    $sent++;
}

$summary = "Morning route push: sent={$sent}, skipped={$skipped}, date={$today}\n";
foreach ($log as $line) {
    $summary .= "  {$line}\n";
}

if ($isCli) {
    echo $summary;
} else {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'sent' => $sent, 'skipped' => $skipped, 'date' => $today, 'log' => $log]);
}
