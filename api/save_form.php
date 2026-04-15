<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireNotBillingApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/patients.php');
    exit;
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Invalid request.');
}

$patientId  = (int)($_POST['patient_id'] ?? 0);
$formType   = $_POST['form_type'] ?? '';
$signature  = $_POST['patient_signature'] ?? '';
$maSig      = $_POST['ma_signature'] ?? '';
$poaName    = trim($_POST['poa_name'] ?? '');
$poaRel     = trim($_POST['poa_relationship'] ?? '');

$allowed = ['vital_cs', 'new_patient', 'abn', 'pf_signup', 'ccm_consent', 'cognitive_wellness', 'medicare_awv', 'il_disclosure'];
if (!$patientId || !in_array($formType, $allowed, true)) {
    die('Invalid form submission.');
}

// Verify patient
$chk = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
$chk->execute([$patientId]);
if (!$chk->fetch()) {
    die('Patient not found.');
}

// Collect form fields (exclude meta)
$excludeKeys = ['csrf_token', 'patient_id', 'form_type', 'patient_signature', 'ma_signature', 'poa_name', 'poa_relationship'];
$formData    = [];
foreach ($_POST as $key => $value) {
    if (!in_array($key, $excludeKeys, true)) {
        $formData[$key] = is_array($value) ? $value : trim((string)$value);
    }
}

// Validate ma_signature format if provided
if ($maSig && !preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $maSig)) {
    $maSig = '';
}

$status = $signature ? 'signed' : 'draft';

// One-signature rule: if submitting with a signature and one already exists today, redirect
if ($signature) {
    $dupChk = $pdo->prepare("
        SELECT id FROM form_submissions
        WHERE patient_id = ? AND form_type = ? AND status IN ('signed','uploaded')
        AND DATE(created_at) = CURDATE()
        LIMIT 1
    ");
    $dupChk->execute([$patientId, $formType]);
    if ($dupId = $dupChk->fetchColumn()) {
        header('Location: ' . BASE_URL . '/view_document.php?id=' . (int)$dupId . '&already_signed=1');
        exit;
    }
}

$stmt   = $pdo->prepare("
    INSERT INTO form_submissions
        (patient_id, form_type, form_data, patient_signature, ma_signature, poa_name, poa_relationship, ma_id, status, signed_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->execute([
    $patientId,
    $formType,
    json_encode($formData, JSON_UNESCAPED_UNICODE),
    $signature ?: null,
    $maSig     ?: null,
    $poaName   ?: null,
    $poaRel    ?: null,
    $_SESSION['user_id'],
    $status,
]);

$newId = $pdo->lastInsertId();

require_once __DIR__ . '/../includes/audit.php';
auditLog($pdo, 'form_create', 'form', (int)$newId, $formType, 'patient_id=' . $patientId);

// ── Medication reconciliation for Visit Consent forms ─────────────────────
if ($formType === 'vital_cs') {
    $staffId = (int)$_SESSION['user_id'];
    try {
        for ($i = 1; $i <= 6; $i++) {
            $medId   = (int)($formData["med_id_$i"] ?? 0);
            $medName = trim($formData["med_name_$i"] ?? '');
            $medType = trim($formData["med_type_$i"] ?? '');
            $medFreq = trim($formData["med_freq_$i"] ?? '');

            if ($medId > 0) {
                // Existing tracked medication
                $fetch = $pdo->prepare("SELECT * FROM patient_medications WHERE id = ? AND patient_id = ?");
                $fetch->execute([$medId, $patientId]);
                $existing = $fetch->fetch();
                if (!$existing) continue;

                if ($medType === 'D/C') {
                    $pdo->prepare("UPDATE patient_medications SET status='discontinued', updated_by=? WHERE id=?")
                        ->execute([$staffId, $medId]);
                    $pdo->prepare("INSERT INTO medication_history
                        (medication_id, patient_id, action, prev_name, prev_frequency, prev_status,
                         new_name, new_frequency, new_status, changed_by, form_submission_id)
                        VALUES (?,?,'discontinued',?,?,?,?,?,'discontinued',?,?)")
                        ->execute([$medId, $patientId,
                            $existing['med_name'], $existing['med_frequency'], $existing['status'],
                            $existing['med_name'], $existing['med_frequency'],
                            $staffId, $newId]);
                } elseif ($medName !== '' &&
                          ($existing['med_name'] !== $medName || $existing['med_frequency'] !== $medFreq)) {
                    $pdo->prepare("UPDATE patient_medications SET med_name=?, med_frequency=?, updated_by=? WHERE id=?")
                        ->execute([$medName, $medFreq, $staffId, $medId]);
                    $pdo->prepare("INSERT INTO medication_history
                        (medication_id, patient_id, action, prev_name, prev_frequency, prev_status,
                         new_name, new_frequency, new_status, changed_by, form_submission_id)
                        VALUES (?,?,'modified',?,?,?,?,?,?,?,?)")
                        ->execute([$medId, $patientId,
                            $existing['med_name'], $existing['med_frequency'], $existing['status'],
                            $medName, $medFreq, $existing['status'],
                            $staffId, $newId]);
                }
            } elseif ($medName !== '' && $medType !== '' && $medType !== 'D/C') {
                // New, untracked row: add to master list
                $sortQ = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM patient_medications WHERE patient_id = ?");
                $sortQ->execute([$patientId]);
                $sortOrder = (int)$sortQ->fetchColumn();
                $pdo->prepare("INSERT INTO patient_medications
                    (patient_id, med_name, med_frequency, status, sort_order, added_by)
                    VALUES (?, ?, ?, 'active', ?, ?)")
                    ->execute([$patientId, $medName, $medFreq, $sortOrder, $staffId]);
                $newMedId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO medication_history
                    (medication_id, patient_id, action, new_name, new_frequency, new_status, changed_by, form_submission_id)
                    VALUES (?,?,'added',?,?,'active',?,?)")
                    ->execute([$newMedId, $patientId, $medName, $medFreq, $staffId, $newId]);
            }
        }
    } catch (PDOException $e) {
        // Table not yet migrated — skip reconciliation silently
    }
}

header('Location: ' . BASE_URL . '/view_document.php?id=' . $newId);
exit;
