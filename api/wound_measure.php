<?php
/**
 * api/wound_measure.php
 * Proxy to the Python wound measurement service (127.0.0.1:5001)
 * Saves annotated image + measurements to DB.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (empty($input['image'])) {
    echo json_encode(['success' => false, 'error' => 'No image data']);
    exit;
}

// Validate it's a base64 image data URL
if (!preg_match('/^data:image\/(jpeg|png|webp);base64,/', $input['image'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid image format']);
    exit;
}

// 20 MB hard limit
if (strlen($input['image']) > 20 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'Image too large']);
    exit;
}

// ── Forward to Python service ─────────────────────────────────────────────────
$ch = curl_init('http://127.0.0.1:5001/measure');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['image' => $input['image']]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 45,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $httpCode !== 200) {
    echo json_encode(['success' => false, 'error' => 'Measurement service unavailable — ensure wound_service.py is running']);
    exit;
}

$result = json_decode($response, true);
if (!$result || !($result['success'] ?? false)) {
    echo json_encode($result ?: ['success' => false, 'error' => 'Measurement failed']);
    exit;
}

// ── Save annotated image to disk ──────────────────────────────────────────────
$annotatedUrl = null;
if (!empty($result['annotated_image'])) {
    $uploadDir = __DIR__ . '/../uploads/wound_annotated/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $filename = 'ann_' . time() . '_' . bin2hex(random_bytes(8)) . '.jpg';
    $decoded  = base64_decode($result['annotated_image'], true);
    if ($decoded !== false) {
        file_put_contents($uploadDir . $filename, $decoded);
        $annotatedUrl = BASE_URL . '/uploads/wound_annotated/' . $filename;
    }
}

// ── Persist to wound_measurements table ──────────────────────────────────────
$measurementId = null;
$patientId = (int)($input['patient_id'] ?? 0);
$photoId   = (int)($input['photo_id']   ?? 0);

if ($patientId > 0 && ($result['wound_detected'] ?? false)) {
    try {
        $ti = $result['tissue_info'] ?? [];
        $stmt = $pdo->prepare("
            INSERT INTO wound_measurements
                (patient_id, photo_id, area_cm2, length_cm, width_cm,
                 ruler_detected, annotated_photo_path, measured_at, wound_site, recorded_by,
                 granulation_pct, slough_pct, eschar_pct, analysis_confidence)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'Unspecified', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $patientId,
            $photoId ?: null,
            $result['area_cm2']       ?? null,
            $result['length_cm']      ?? null,
            $result['width_cm']       ?? null,
            ($result['ruler_detected'] ?? false) ? 1 : 0,
            $annotatedUrl,
            $_SESSION['user_id'],
            isset($ti['granulation_pct']) ? (int)$ti['granulation_pct'] : null,
            isset($ti['slough_pct'])      ? (int)$ti['slough_pct']      : null,
            isset($ti['eschar_pct'])      ? (int)$ti['eschar_pct']      : null,
            $ti['confidence'] ?? null,
        ]);
        $measurementId = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('wound_measure DB error: ' . $e->getMessage());
    }
}

echo json_encode([
    'success'        => true,
    'method'         => $result['method']          ?? 'opencv',
    'ruler_detected' => $result['ruler_detected']  ?? false,
    'wound_detected' => $result['wound_detected']  ?? false,
    'area_cm2'       => $result['area_cm2']        ?? null,
    'length_cm'      => $result['length_cm']       ?? null,
    'width_cm'       => $result['width_cm']        ?? null,
    'annotated_url'  => $annotatedUrl,
    'measurement_id' => $measurementId,
    'tissue_info'    => $result['tissue_info']     ?? null,
]);
