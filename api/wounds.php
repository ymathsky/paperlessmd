<?php
/**
 * API: Wound Measurements
 * Actions: add, delete, list
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

if (!canAccessClinical()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? ($_GET['action'] ?? '');

// CSRF check (list via GET uses session token; mutating actions use body token)
if ($action !== 'list' && !verifyCsrf($body['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

switch ($action) {

    case 'add':
        $pid     = (int)($body['patient_id'] ?? 0);
        $date    = trim($body['measured_at'] ?? date('Y-m-d'));
        $site    = trim($body['wound_site'] ?? '') ?: 'Unspecified';
        $len     = round((float)($body['length_cm'] ?? 0), 1);
        $wid     = round((float)($body['width_cm']  ?? 0), 1);
        $dep     = round((float)($body['depth_cm']  ?? 0), 1);
        $notes   = trim($body['notes'] ?? '');
        $visitId = !empty($body['visit_id']) ? (int)$body['visit_id'] : null;

        if (!$pid || $len <= 0 || $wid <= 0) {
            echo json_encode(['error' => 'Patient ID, length, and width are required.']);
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $stmt = $pdo->prepare("
            INSERT INTO wound_measurements
                (patient_id, visit_id, measured_at, wound_site, length_cm, width_cm, depth_cm, notes, recorded_by)
            VALUES (?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([$pid, $visitId, $date, $site, $len, $wid, $dep, $notes ?: null, $_SESSION['user_id']]);
        $newId = (int)$pdo->lastInsertId();

        // Return the new row for instant UI update
        $row = $pdo->prepare("
            SELECT wm.*, s.full_name AS recorded_by_name
            FROM wound_measurements wm
            LEFT JOIN staff s ON s.id = wm.recorded_by
            WHERE wm.id = ?
        ");
        $row->execute([$newId]);
        echo json_encode(['ok' => true, 'measurement' => $row->fetch(PDO::FETCH_ASSOC)]);
        break;

    case 'delete':
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Admins only']);
            exit;
        }
        $id = (int)($body['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'Missing ID']); exit; }
        $pdo->prepare("DELETE FROM wound_measurements WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    case 'list':
        $pid = (int)($_GET['patient_id'] ?? $body['patient_id'] ?? 0);
        if (!$pid) { echo json_encode(['error' => 'Missing patient_id']); exit; }
        $stmt = $pdo->prepare("
            SELECT wm.*, s.full_name AS recorded_by_name
            FROM wound_measurements wm
            LEFT JOIN staff s ON s.id = wm.recorded_by
            WHERE wm.patient_id = ?
            ORDER BY wm.measured_at ASC, wm.id ASC
        ");
        $stmt->execute([$pid]);
        echo json_encode(['ok' => true, 'measurements' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
