<?php
/**
 * GET  /api/mobile/forms.php?submission_id=X  – fetch saved form data
 * POST /api/mobile/forms.php                  – save/update a form submission
 *      body: { patient_id, form_type, form_data:{}, signature?, status? }
 */
require_once __DIR__ . '/helpers.php';
header('Content-Type: application/json');
cors();

$user = requireToken();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sid = (int)($_GET['submission_id'] ?? 0);
    if (!$sid) jsonError('submission_id required', 422);

    $stmt = $pdo->prepare("
        SELECT fs.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name
        FROM form_submissions fs
        JOIN patients p ON p.id = fs.patient_id
        WHERE fs.id = ?
        LIMIT 1
    ");
    $stmt->execute([$sid]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$row) jsonError('Not found', 404);
    if ($row['form_data']) $row['form_data'] = json_decode($row['form_data'], true);
    jsonOk($row);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b          = json_decode(file_get_contents('php://input'), true) ?? [];
    $patientId  = (int)($b['patient_id'] ?? 0);
    $formType   = $b['form_type'] ?? '';
    $formData   = isset($b['form_data']) ? json_encode($b['form_data']) : '{}';
    $signature  = $b['signature']  ?? null;   // base64 PNG data URL
    $status     = in_array($b['status'] ?? '', ['draft','signed']) ? $b['status'] : 'draft';

    if (!$patientId || !$formType) jsonError('patient_id and form_type required', 422);

    // If submission_id provided – update
    if (!empty($b['submission_id'])) {
        $sid = (int)$b['submission_id'];
        $stmt = $pdo->prepare("
            UPDATE form_submissions SET form_data=?, status=?, updated_at=NOW()
            WHERE id=? AND ma_id=?
        ");
        $stmt->execute([$formData, $status, $sid, $user['id']]);
        jsonOk(['submission_id' => $sid, 'action' => 'updated']);
    }

    // Insert new
    $stmt = $pdo->prepare("
        INSERT INTO form_submissions (patient_id, ma_id, form_type, form_data, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$patientId, $user['id'], $formType, $formData, $status]);
    $sid = $pdo->lastInsertId();

    // Save signature if provided
    if ($signature && preg_match('/^data:image\/(png|jpeg);base64,/', $signature)) {
        $imgData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $signature));
        $sigDir  = __DIR__ . '/../../uploads/signatures/';
        if (!is_dir($sigDir)) mkdir($sigDir, 0775, true);
        $sigFile = $sigDir . 'sig_' . $sid . '.png';
        file_put_contents($sigFile, $imgData);
        $pdo->prepare("UPDATE form_submissions SET signature_path=? WHERE id=?")->execute(['uploads/signatures/sig_' . $sid . '.png', $sid]);
    }

    jsonOk(['submission_id' => $sid, 'action' => 'created'], 201);
}

jsonError('Method not allowed', 405);
