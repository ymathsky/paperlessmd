<?php
/**
 * api/get_locations.php
 * Return the latest known location for every active MA/admin.
 * Admin-only endpoint.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Check whether last_active_at column exists (graceful if migration not yet run)
    $hasLastActive = false;
    try {
        $pdo->query("SELECT last_active_at FROM staff LIMIT 0");
        $hasLastActive = true;
    } catch (PDOException $e) { /* column not yet added on this server */ }

    $activeCol = $hasLastActive ? 's.last_active_at,' : 'NULL AS last_active_at,';

    // All active MA/admin staff with their latest GPS location (if any) + last_active_at
    $rows = $pdo->query("
        SELECT
            s.id             AS staff_id,
            s.full_name,
            s.role,
            $activeCol
            ml.latitude,
            ml.longitude,
            ml.accuracy,
            ml.recorded_at
        FROM staff s
        LEFT JOIN ma_locations ml ON ml.id = (
            SELECT id FROM ma_locations
            WHERE staff_id = s.id
            ORDER BY recorded_at DESC
            LIMIT 1
        )
        WHERE s.active = 1
          AND s.role IN ('ma', 'admin')
        ORDER BY s.full_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'locations' => $rows]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
