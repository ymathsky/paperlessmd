<?php
/**
 * GET /api/visit_check.php?id=<schedule_id>
 * Returns {"active": true|false} — true when the visit is still en_route.
 * Used by the floating Return-to-Visit chip in header.php.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['active' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT status FROM `schedule` WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['active' => ($row && $row['status'] === 'en_route')]);
