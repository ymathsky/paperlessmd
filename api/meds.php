<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireNotBillingApi();

header('Content-Type: application/json');

$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'] ?? 'ma';

// ── GET: fetch history for a medication ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action    = $_GET['action'] ?? '';
    $patientId = (int)($_GET['patient_id'] ?? 0);
    $medId     = (int)($_GET['id'] ?? 0);

    if ($action === 'history' && $medId > 0 && $patientId > 0) {
        // Verify ownership
        $chk = $pdo->prepare("SELECT id FROM patient_medications WHERE id = ? AND patient_id = ?");
        $chk->execute([$medId, $patientId]);
        if (!$chk->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Not found']);
            exit;
        }
        $histStmt = $pdo->prepare("
            SELECT mh.*, s.full_name AS changed_by_name
            FROM medication_history mh
            LEFT JOIN staff s ON s.id = mh.changed_by
            WHERE mh.medication_id = ?
            ORDER BY mh.changed_at DESC
        ");
        $histStmt->execute([$medId]);
        $history = $histStmt->fetchAll();
        echo json_encode(['ok' => true, 'history' => $history]);
        exit;
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

// ── POST: mutations ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    // fallback: form-encoded POST
    $input = $_POST;
}

// CSRF check
$csrfField = $input['csrf'] ?? $input['csrf_token'] ?? '';
if (!verifyCsrf($csrfField)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$action    = $input['action'] ?? '';
$patientId = (int)($input['patient_id'] ?? 0);

if (!$patientId) {
    echo json_encode(['ok' => false, 'error' => 'Missing patient_id']);
    exit;
}

// Verify patient exists
$chk = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
$chk->execute([$patientId]);
if (!$chk->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Patient not found']);
    exit;
}

// ── Helper: log history ───────────────────────────────────────────────────────
function logMedHist(PDO $pdo, int $medId, int $patientId, string $action,
                    ?string $prevName, ?string $prevFreq, ?string $prevStatus,
                    ?string $newName,  ?string $newFreq,  ?string $newStatus,
                    int $userId, ?int $formId = null): void
{
    $pdo->prepare("
        INSERT INTO medication_history
            (medication_id, patient_id, action,
             prev_name, prev_frequency, prev_status,
             new_name, new_frequency, new_status,
             changed_by, form_submission_id)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ")->execute([
        $medId, $patientId, $action,
        $prevName, $prevFreq, $prevStatus,
        $newName,  $newFreq,  $newStatus,
        $userId, $formId,
    ]);
}

// ── Helper: fetch med (verify ownership) ─────────────────────────────────────
function fetchMed(PDO $pdo, int $medId, int $patientId): array|false
{
    $s = $pdo->prepare("SELECT * FROM patient_medications WHERE id = ? AND patient_id = ?");
    $s->execute([$medId, $patientId]);
    return $s->fetch();
}

// ── Helper: next sort_order ───────────────────────────────────────────────────
function nextSort(PDO $pdo, int $patientId): int
{
    $s = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM patient_medications WHERE patient_id = ?");
    $s->execute([$patientId]);
    return (int)$s->fetchColumn();
}

// ── Actions ───────────────────────────────────────────────────────────────────
switch ($action) {

    // ── add ──────────────────────────────────────────────────────────────────
    case 'add':
        $name = trim($input['med_name'] ?? '');
        $freq = trim($input['med_frequency'] ?? '');
        if ($name === '') {
            echo json_encode(['ok' => false, 'error' => 'Medication name is required']);
            exit;
        }
        $sort = nextSort($pdo, $patientId);
        $pdo->prepare("
            INSERT INTO patient_medications (patient_id, med_name, med_frequency, status, sort_order, added_by)
            VALUES (?, ?, ?, 'active', ?, ?)
        ")->execute([$patientId, $name, $freq, $sort, $userId]);
        $newId = (int)$pdo->lastInsertId();
        logMedHist($pdo, $newId, $patientId, 'added',
            null, null, null,
            $name, $freq, 'active',
            $userId);
        echo json_encode(['ok' => true, 'id' => $newId]);
        break;

    // ── update ───────────────────────────────────────────────────────────────
    case 'update':
        $medId = (int)($input['id'] ?? 0);
        $name  = trim($input['med_name'] ?? '');
        $freq  = trim($input['med_frequency'] ?? '');
        if (!$medId || $name === '') {
            echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
            exit;
        }
        $existing = fetchMed($pdo, $medId, $patientId);
        if (!$existing) {
            echo json_encode(['ok' => false, 'error' => 'Medication not found']);
            exit;
        }
        if ($existing['med_name'] !== $name || $existing['med_frequency'] !== $freq) {
            $pdo->prepare("UPDATE patient_medications SET med_name=?, med_frequency=?, updated_by=? WHERE id=?")
                ->execute([$name, $freq, $userId, $medId]);
            logMedHist($pdo, $medId, $patientId, 'modified',
                $existing['med_name'], $existing['med_frequency'], $existing['status'],
                $name, $freq, $existing['status'],
                $userId);
        }
        echo json_encode(['ok' => true]);
        break;

    // ── discontinue ──────────────────────────────────────────────────────────
    case 'discontinue':
        $medId = (int)($input['id'] ?? 0);
        $existing = fetchMed($pdo, $medId, $patientId);
        if (!$existing) {
            echo json_encode(['ok' => false, 'error' => 'Medication not found']);
            exit;
        }
        if ($existing['status'] === 'discontinued') {
            echo json_encode(['ok' => true]); // idempotent
            exit;
        }
        $pdo->prepare("UPDATE patient_medications SET status='discontinued', updated_by=? WHERE id=?")
            ->execute([$userId, $medId]);
        logMedHist($pdo, $medId, $patientId, 'discontinued',
            $existing['med_name'], $existing['med_frequency'], 'active',
            $existing['med_name'], $existing['med_frequency'], 'discontinued',
            $userId);
        echo json_encode(['ok' => true]);
        break;

    // ── reactivate ───────────────────────────────────────────────────────────
    case 'reactivate':
        $medId = (int)($input['id'] ?? 0);
        $existing = fetchMed($pdo, $medId, $patientId);
        if (!$existing) {
            echo json_encode(['ok' => false, 'error' => 'Medication not found']);
            exit;
        }
        if ($existing['status'] === 'active') {
            echo json_encode(['ok' => true]);
            exit;
        }
        $pdo->prepare("UPDATE patient_medications SET status='active', updated_by=? WHERE id=?")
            ->execute([$userId, $medId]);
        logMedHist($pdo, $medId, $patientId, 'reactivated',
            $existing['med_name'], $existing['med_frequency'], 'discontinued',
            $existing['med_name'], $existing['med_frequency'], 'active',
            $userId);
        echo json_encode(['ok' => true]);
        break;

    // ── delete (admin only) ───────────────────────────────────────────────────
    case 'delete':
        if ($role !== 'admin') {
            echo json_encode(['ok' => false, 'error' => 'Only admins can permanently delete medications']);
            exit;
        }
        $medId = (int)($input['id'] ?? 0);
        $existing = fetchMed($pdo, $medId, $patientId);
        if (!$existing) {
            echo json_encode(['ok' => false, 'error' => 'Medication not found']);
            exit;
        }
        // History rows cascade-delete with the medication row
        $pdo->prepare("DELETE FROM patient_medications WHERE id = ? AND patient_id = ?")
            ->execute([$medId, $patientId]);
        echo json_encode(['ok' => true]);
        break;

    // ── reorder ──────────────────────────────────────────────────────────────
    case 'reorder':
        $ids = $input['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid ids']);
            exit;
        }
        $upd = $pdo->prepare("UPDATE patient_medications SET sort_order=? WHERE id=? AND patient_id=?");
        foreach ($ids as $idx => $mid) {
            $upd->execute([(int)$idx, (int)$mid, $patientId]);
        }
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}
