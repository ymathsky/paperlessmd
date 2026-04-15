<?php
/**
 * api/pf_push.php
 * ───────────────────────────────────────────────────────────────
 * Accepts a POST with:
 *   form_id        — local form_submission ID
 *   pf_patient_id  — the Practice Fusion Patient FHIR ID
 *   pdf_data       — base64-encoded PDF (generated client-side via window.print / html2pdf)
 *
 * Returns JSON { success, message, pf_doc_id }
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pf_client.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$formId       = (int)($_POST['form_id']       ?? 0);
$pfPatientId  = trim($_POST['pf_patient_id']  ?? '');
$pdfBase64    = $_POST['pdf_data']             ?? '';

if (!$formId || !$pfPatientId || !$pdfBase64) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate pdf_data is actual base64
if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $pdfBase64)) {
    echo json_encode(['success' => false, 'message' => 'Invalid PDF data']);
    exit;
}

// Load form
$stmt = $pdo->prepare("
    SELECT fs.*, p.first_name, p.last_name, p.dob, p.id AS patient_id
    FROM form_submissions fs
    JOIN patients p ON p.id = fs.patient_id
    WHERE fs.id = ?
");
$stmt->execute([$formId]);
$form = $stmt->fetch();

if (!$form) {
    echo json_encode(['success' => false, 'message' => 'Form not found']);
    exit;
}

$formTitles = [
    'vital_cs'    => 'Vital CS Consent',
    'new_patient' => 'New Patient Consent',
    'abn'         => 'Advance Beneficiary Notice (ABN)',
    'pf_signup'   => 'Practice Fusion Portal Registration',
    'ccm_consent' => 'Chronic Care Management Consent',
];
$title    = $formTitles[$form['form_type']] ?? 'Signed Consent Form';
$dateTime = date('c', strtotime($form['created_at']));

try {
    $pf    = new PracticeFusionClient();
    $pfDoc = $pf->uploadDocument($pfPatientId, $title, $form['form_type'], $pdfBase64, $dateTime);

    $pfDocId = $pfDoc['id'] ?? null;

    // Update DB: store PF patient ID, mark as uploaded
    $upd = $pdo->prepare("
        UPDATE form_submissions
        SET status = 'uploaded',
            pf_uploaded_at = NOW(),
            pf_uploaded_by = ?,
            pf_patient_id  = ?,
            pf_doc_id      = ?
        WHERE id = ?
    ");
    $upd->execute([$_SESSION['user_id'], $pfPatientId, $pfDocId, $formId]);

    // Also save PF patient id on local patient record for future use
    $pdo->prepare("UPDATE patients SET pf_patient_id = ? WHERE id = ?")
        ->execute([$pfPatientId, $form['patient_id']]);

    echo json_encode([
        'success'    => true,
        'message'    => 'Document uploaded to Practice Fusion.',
        'pf_doc_id'  => $pfDocId,
    ]);
} catch (RuntimeException $e) {
    error_log('PF push error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Practice Fusion error: ' . $e->getMessage(),
    ]);
}
