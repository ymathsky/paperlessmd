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
$visitId    = (int)($_POST['visit_id'] ?? 0);
$signature  = $_POST['patient_signature'] ?? '';
$maSig      = $_POST['ma_signature'] ?? '';
$poaName    = trim($_POST['poa_name'] ?? '');
$poaRel     = trim($_POST['poa_relationship'] ?? '');
$_editOverride = (isAdmin() || isMa() || isPcc()) && ($_POST['edit_override'] ?? '') === '1';
// Missed visit: when missed_visit_reason is set, vitals and signatures are not required.
$isMissedVisit = ($formType === 'vital_cs' && !empty(trim($_POST['missed_visit_reason'] ?? '')));
// vital_cs is draft only when signatures are absent (in-progress visit); once both sigs are
// present (End Visit confirmed) treat it as signed so the record is finalised.
// Missed visits bypass this — they are always finalised on submit.
$forceDraft = ($formType === 'vital_cs' && !$isMissedVisit && (!$signature || !$maSig));

$allowed = ['vital_cs', 'new_patient', 'abn', 'pf_signup', 'ccm_consent', 'cognitive_wellness', 'medicare_awv', 'il_disclosure', 'wound_care_consent', 'informed_consent_wound', 'rpm_consent', 'new_patient_pocket', 'new_patient_pocket_pc'];
if (!$patientId || !in_array($formType, $allowed, true)) {
    die('Invalid form submission.');
}

// Verify patient
$chk = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
$chk->execute([$patientId]);
if (!$chk->fetch()) {
    die('Patient not found.');
}

// Non-admin: require a scheduled visit today for this patient
// (exempt intake forms — new patients won't have a visit on schedule yet)
$intakeForms = ['new_patient', 'new_patient_pocket', 'new_patient_pocket_pc', 'pf_signup'];
if (!isAdmin() && !in_array($formType, $intakeForms, true)) {
    // Missed visits allow any schedule status (the visit may already be marked 'missed')
    $schedSql = $isMissedVisit
        ? "SELECT id FROM `schedule` WHERE patient_id = ? AND visit_date = CURDATE() LIMIT 1"
        : "SELECT id FROM `schedule` WHERE patient_id = ? AND visit_date = CURDATE() AND status != 'missed' LIMIT 1";
    $schedChk = $pdo->prepare($schedSql);
    $schedChk->execute([$patientId]);
    if (!$schedChk->fetchColumn()) {
        http_response_code(403);
        die('No visit scheduled for this patient today. Add a visit on the Schedule page first.');
    }
}

// Collect form fields (exclude meta)
$excludeKeys = ['csrf_token', 'patient_id', 'form_type', 'patient_signature', 'ma_signature', 'poa_name', 'poa_relationship', 'med_count', 'draft_submission_id'];
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

// Validate patient_signature format if provided
if ($signature && !preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $signature)) {
    $signature = '';
}

$_isNewPatientPocket = ($formType === 'new_patient_pocket' || $formType === 'new_patient_pocket_pc');
$_prevSigned = null;
if ($_editOverride && (
        !$signature ||
    !$maSig
    )) {
    $prevSigStmt = $pdo->prepare("SELECT patient_signature, ma_signature, provider_signature, provider_name
                                  FROM form_submissions
                                  WHERE patient_id = ? AND form_type = ? AND status IN ('signed','uploaded')
                                  ORDER BY created_at DESC
                                  LIMIT 1");
    $prevSigStmt->execute([$patientId, $formType]);
    $_prevSigned = $prevSigStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($_prevSigned) {
        // New Patient Packet must capture a fresh patient signature on submit.
        if (!$signature && !$_isNewPatientPocket && !empty($_prevSigned['patient_signature'])) {
            $signature = (string)$_prevSigned['patient_signature'];
        }
        if (!$maSig && !empty($_prevSigned['ma_signature'])) {
            $maSig = (string)$_prevSigned['ma_signature'];
        }
    }
}

// Validate med_handwriting if provided (strip if invalid)
if (!empty($formData['med_handwriting'])) {
    if (!preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $formData['med_handwriting'])) {
        unset($formData['med_handwriting']);
    }
}

// Validate additional handwriting pages (med_handwriting_2 … med_handwriting_5)
for ($hwi = 2; $hwi <= 5; $hwi++) {
    $hwKey = 'med_handwriting_' . $hwi;
    if (!empty($formData[$hwKey])) {
        if (!preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $formData[$hwKey])) {
            unset($formData[$hwKey]);
        }
    }
}

// Validate med_pdf if provided (base64 PDF data URI)
if (!empty($formData['med_pdf'])) {
    if (!preg_match('/^data:application\/pdf;base64,[A-Za-z0-9+\/=]+$/', $formData['med_pdf'])) {
        unset($formData['med_pdf']);
    }
}

// Provider signature is optional for New Patient Packet; keep if valid.
$providerSig       = null;
$providerPrintName = null;
if ($_isNewPatientPocket) {
    $providerSig = $formData['provider_signature'] ?? '';
    if ($providerSig && !preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $providerSig)) {
        unset($formData['provider_signature']);
        $providerSig = '';
    }
    $providerPrintName = trim($formData['provider_print_name'] ?? '') ?: null;
}

// Require both signatures (skipped for missed visits — no patient present)
if (!$forceDraft && !$isMissedVisit) {
    if (!$signature) {
        http_response_code(400);
        die('Patient signature is required.');
    }
    if (!$maSig) {
        http_response_code(400);
        die('MA signature is required.');
    }
}

$status = $forceDraft ? 'draft' : 'signed';

// One-signature rule: if submitting with a signature and one already exists today, redirect
// Admin with edit_override=1 bypasses this check to allow re-signing
if (!$forceDraft && $signature && !$_editOverride) {
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
        (patient_id, form_type, form_data, patient_signature, ma_signature, poa_name, poa_relationship, ma_id, status, signed_at, provider_signature, provider_name)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
    $status === 'signed' ? date('Y-m-d H:i:s') : null,
    $providerSig       ?: null,
    $providerPrintName ?: null,
]);

$newId = $pdo->lastInsertId();

if (in_array($formType, ['vital_cs', 'new_patient_pocket', 'new_patient_pocket_pc'], true)) {
    try {
        if (array_key_exists('pharmacy_name', $formData) || array_key_exists('pharmacy_phone', $formData) || array_key_exists('pharmacy_address', $formData)) {
            $pdo->prepare("UPDATE patients
                           SET pharmacy_name = COALESCE(NULLIF(?, ''), pharmacy_name),
                               pharmacy_phone = COALESCE(NULLIF(?, ''), pharmacy_phone),
                               pharmacy_address = COALESCE(NULLIF(?, ''), pharmacy_address)
                           WHERE id = ?")
                ->execute([
                    trim((string)($formData['pharmacy_name'] ?? '')),
                    trim((string)($formData['pharmacy_phone'] ?? '')),
                    trim((string)($formData['pharmacy_address'] ?? '')),
                    $patientId,
                ]);
        }
    } catch (PDOException $e) {
        // Skip pharmacy sync if patient extras columns are unavailable.
    }
}

require_once __DIR__ . '/../includes/audit.php';
auditLog($pdo, 'form_create', 'form', (int)$newId, $formType, 'patient_id=' . $patientId);

// ── Mark scheduled visit as completed or missed ───────────────────────────
if ($status === 'signed' && $visitId > 0) {
    if ($isMissedVisit) {
        $pdo->prepare(
            "UPDATE `schedule` SET status = 'missed', visit_ended_at = NOW()
             WHERE id = ? AND patient_id = ? AND status NOT IN ('completed','missed')"
        )->execute([$visitId, $patientId]);
    } else {
        $pdo->prepare(
            "UPDATE `schedule` SET status = 'completed', visit_ended_at = NOW()
             WHERE id = ? AND patient_id = ? AND status != 'completed'"
        )->execute([$visitId, $patientId]);
    }
}


// ── Draft: redirect back to form to continue ──────────────────────────────
if ($formType === 'vital_cs' && $status === 'draft') {
    $q = [
        'patient_id'   => $patientId,
        'edit'         => 1,
        'draft_saved'  => 1,
    ];
    if ($visitId > 0) {
        $q['visit_id'] = $visitId;
    }
    header('Location: ' . BASE_URL . '/forms/vital_cs.php?' . http_build_query($q));
    exit;
}

// ── Signed: redirect browser immediately; email + reconciliation run after ─
if ($isMissedVisit) {
    header('Location: ' . BASE_URL . '/schedule.php');
    exit;
}
header('Location: ' . BASE_URL . '/view_document.php?id=' . $newId);
if (function_exists('fastcgi_finish_request')) {
    // PHP-FPM: flush the redirect to the client now so the browser navigates
    // right away, while the script continues running in the background.
    session_write_close();
    while (ob_get_level()) ob_end_clean();
    fastcgi_finish_request();
}
ignore_user_abort(true);

// ── Email notification (background — browser already navigated) ────────────
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/notifications.php';
notifyFormSigned($pdo, (int)$newId, $patientId, $formType, (int)$_SESSION['user_id']);

// ── Medication reconciliation for Visit Consent forms ─────────────────────
if (in_array($formType, ['vital_cs', 'new_patient_pocket', 'new_patient_pocket_pc'], true)) {
    $staffId  = (int)$_SESSION['user_id'];
    // Use submitted med_count (dynamic rows), read from $_POST since it's excluded from $formData; cap at 30
    $medCount = min(30, max(6, (int)($_POST['med_count'] ?? 6)));
    try {
        for ($i = 1; $i <= $medCount; $i++) {
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

