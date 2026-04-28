<?php
/**
 * api/update_location.php
 * Receive and store the MA's current GPS location.
 *
 * POST JSON body:
 *   { "csrf": "...", "lat": 40.7128, "lng": -74.0060, "accuracy": 15.5 }
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

if (!verifyCsrf($body['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Only MA and admin roles should be sending locations
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['ma', 'admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not permitted']);
    exit;
}

$lat      = isset($body['lat'])      ? (float)$body['lat']      : null;
$lng      = isset($body['lng'])      ? (float)$body['lng']      : null;
$accuracy = isset($body['accuracy']) ? (float)$body['accuracy'] : null;

// Basic validation: latitude -90..90, longitude -180..180
if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid coordinates']);
    exit;
}

try {
    // Upsert approach: keep only the latest per staff — insert new row, then prune old rows
    $pdo->prepare("
        INSERT INTO ma_locations (staff_id, latitude, longitude, accuracy)
        VALUES (?, ?, ?, ?)
    ")->execute([(int)$_SESSION['user_id'], $lat, $lng, $accuracy]);

    // Keep only the 100 most recent rows per MA to avoid table bloat
    $pdo->prepare("
        DELETE FROM ma_locations
        WHERE staff_id = ?
          AND id NOT IN (
              SELECT id FROM (
                  SELECT id FROM ma_locations
                  WHERE staff_id = ?
                  ORDER BY recorded_at DESC
                  LIMIT 100
              ) AS t
          )
    ")->execute([(int)$_SESSION['user_id'], (int)$_SESSION['user_id']]);

    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
