<?php
/**
 * GET  /api/mobile/schedule.php?date=YYYY-MM-DD
 * POST /api/mobile/schedule.php  – update visit status
 *      body: { "id": int, "status": "pending|en_route|completed|missed" }
 */
require_once __DIR__ . '/helpers.php';
header('Content-Type: application/json');
cors();

$user = requireToken();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT sc.*,
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               p.address  AS patient_address,
               p.phone    AS patient_phone,
               p.id       AS patient_id
        FROM `schedule` sc
        JOIN patients p ON p.id = sc.patient_id
        WHERE sc.ma_id = ? AND sc.visit_date = ?
        ORDER BY sc.visit_order ASC, sc.visit_time ASC
    ");
    $stmt->execute([$user['id'], $date]);
    $visits = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $counts = ['pending' => 0, 'en_route' => 0, 'completed' => 0, 'missed' => 0];
    foreach ($visits as $v) {
        if (isset($counts[$v['status']])) $counts[$v['status']]++;
    }

    jsonOk(['date' => $date, 'visits' => $visits, 'counts' => $counts]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b      = json_decode(file_get_contents('php://input'), true) ?? [];
    $vid    = (int)($b['id']     ?? 0);
    $status = $b['status'] ?? '';
    $allowed = ['pending','en_route','completed','missed'];
    if (!$vid || !in_array($status, $allowed, true)) jsonError('id and valid status required', 422);

    $stmt = $pdo->prepare("UPDATE `schedule` SET status = ? WHERE id = ? AND ma_id = ?");
    $stmt->execute([$status, $vid, $user['id']]);
    if ($stmt->rowCount() === 0) jsonError('Visit not found or forbidden', 403);
    jsonOk(['updated' => true]);
}

jsonError('Method not allowed', 405);
