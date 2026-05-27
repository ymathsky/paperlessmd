<?php
/**
 * api/save_rx.php
 *
 * Save a prescription (RX) for a patient.
 *
 * POST JSON:
 *   { csrf, patient_id, company, prescriber, date, notes, meds: [{name, dosage, frequency, qty, refills}] }
 *
 * Saves each medication to:
 *   - medications table (full detail for record-keeping)
 *   - patient_medications table (syncs active med list, feeds CS form auto-fill)
 *
 * Response: { ok, rx_id, med_ids: [] }
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit.php';

requireNotBillingApi();
if (!canAccessClinical()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !verifyCsrf($body['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$patientId = (int)($body['patient_id'] ?? 0);
if (!$patientId) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Missing patient_id']);
    exit;
}

$company    = in_array($body['company'] ?? '', ['vmp', 'bwc'], true) ? $body['company'] : 'bwc';
$prescriber = trim($body['prescriber'] ?? '');
$rxDate     = trim($body['date'] ?? date('Y-m-d'));
$notes      = trim($body['notes'] ?? '');
$meds       = is_array($body['meds'] ?? null) ? $body['meds'] : [];
$userId     = (int)$_SESSION['user_id'];

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rxDate)) {
    $rxDate = date('Y-m-d');
}

// Filter out empty medication rows
$validMeds = [];
foreach ($meds as $m) {
    $name = trim($m['name'] ?? '');
    if ($name !== '') {
        $validMeds[] = [
            'name'      => $name,
            'dosage'    => trim($m['dosage']    ?? ''),
            'frequency' => trim($m['frequency'] ?? ''),
            'qty'       => trim($m['qty']       ?? ''),
            'refills'   => trim($m['refills']   ?? ''),
        ];
    }
}

if (empty($validMeds)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'At least one medication is required']);
    exit;
}

// Verify patient exists
$patientCheck = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
$patientCheck->execute([$patientId]);
if (!$patientCheck->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Patient not found']);
    exit;
}

$medIds = [];

try {
    $pdo->beginTransaction();

    foreach ($validMeds as $m) {
        // Full display name = "DrugName Dosage" if dosage is separate, otherwise just name
        $fullName = $m['name'];
        if ($m['dosage'] && stripos($m['name'], $m['dosage']) === false) {
            $fullName = $m['name'] . ' ' . $m['dosage'];
        }

        // Save to medications table (detailed RX record)
        $insStmt = $pdo->prepare("
            INSERT INTO medications (patient_id, name, dosage, frequency, prescriber, start_date, active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $insStmt->execute([
            $patientId,
            $fullName,
            $m['dosage'],
            $m['frequency'],
            $prescriber,
            $rxDate,
        ]);
        $medIds[] = (int)$pdo->lastInsertId();

        // Upsert into patient_medications (exact name match, active status)
        $pmCheck = $pdo->prepare("
            SELECT id FROM patient_medications
            WHERE patient_id = ? AND med_name = ? AND status = 'active'
            LIMIT 1
        ");
        $pmCheck->execute([$patientId, $fullName]);
        $existingPmId = $pmCheck->fetchColumn();

        if ($existingPmId) {
            // Update frequency if different
            $pdo->prepare("
                UPDATE patient_medications
                SET med_frequency = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$m['frequency'], $userId, $existingPmId]);
        } else {
            // Insert new entry into master med list
            $pdo->prepare("
                INSERT INTO patient_medications (patient_id, med_name, med_frequency, status, added_by)
                VALUES (?, ?, ?, 'active', ?)
            ")->execute([$patientId, $fullName, $m['frequency'], $userId]);
        }
    }

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
    exit;
}

// Audit log
try {
    auditLog($pdo, $userId, 'rx_saved', 'patient', $patientId,
        sprintf('RX saved: %d medication(s), company=%s, prescriber=%s',
            count($validMeds), strtoupper($company), $prescriber)
    );
} catch (Throwable $e) { /* non-fatal */ }

echo json_encode(['ok' => true, 'med_ids' => $medIds]);
