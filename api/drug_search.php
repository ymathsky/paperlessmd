<?php
/**
 * Drug Bank autocomplete endpoint.
 * GET ?q=  — returns JSON array of up to 15 matching drug names.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo json_encode([]); exit; }

// Results that START with the query come first, then any CONTAINS match
$stmt = $pdo->prepare("
    SELECT DISTINCT name, category
    FROM drug_bank
    WHERE active = 1 AND name LIKE ?
    ORDER BY
        CASE WHEN name LIKE ? THEN 0 ELSE 1 END,
        name
    LIMIT 15
");
$stmt->execute(['%' . $q . '%', $q . '%']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(array_values($rows));
