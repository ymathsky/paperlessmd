<?php
/**
 * api/provider_sig.php
 * Returns saved_provider_signature for a given provider name.
 * GET ?name=Provider%20Name
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireNotBillingApi();

header('Content-Type: application/json; charset=utf-8');

$name = trim($_GET['name'] ?? '');
if (mb_strlen($name) < 2) { echo json_encode(['sig' => null, 'npi' => null]); exit; }

$stmt = $pdo->prepare("
    SELECT saved_provider_signature, saved_provider_npi
    FROM staff
    WHERE full_name = ? AND COALESCE(saved_provider_signature,'') != ''
    LIMIT 1
");
$stmt->execute([$name]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$sig = $row ? (string)($row['saved_provider_signature'] ?? '') : '';
$npi = $row ? (string)($row['saved_provider_npi'] ?? '') : '';

// Validate format
if ($sig && !preg_match('/^data:image\/(png|jpeg);base64,[A-Za-z0-9+\/=]+$/', $sig)) {
    $sig = '';
}

echo json_encode(['sig' => $sig ?: null, 'npi' => $npi ?: null]);
