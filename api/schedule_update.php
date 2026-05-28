<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit.php';
requireNotBillingApi();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

// CSRF
if (!verifyCsrf($body['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$action = $body['action'] ?? '';

if ($action === 'status') {
    $id     = (int)($body['id'] ?? 0);
    $status = $body['status'] ?? '';
    $allowed = ['pending', 'en_route', 'completed', 'missed'];
    $fuWeeks = trim((string)($body['fu_weeks'] ?? ''));
    $fuUnit = trim((string)($body['fu_unit'] ?? ''));
    $formSubmissionId = (int)($body['form_submission_id'] ?? 0);
    $formType = trim((string)($body['form_type'] ?? ''));

    if (!$id || !in_array($status, $allowed, true)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    $visitStmt = $pdo->prepare("SELECT id, ma_id, patient_id, visit_date, status FROM `schedule` WHERE id = ? LIMIT 1");
    $visitStmt->execute([$id]);
    $visit = $visitStmt->fetch(PDO::FETCH_ASSOC);
    if (!$visit) {
        echo json_encode(['ok' => false, 'error' => 'Entry not found or not authorized']);
        exit;
    }

    // MAs may only update visits assigned to them
    $_apiRole = $_SESSION['role'] ?? '';
    $_apiUid  = (int)($_SESSION['user_id'] ?? 0);
    if ($_apiRole === 'ma' && (int)$visit['ma_id'] !== $_apiUid) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Not authorized for this visit']);
        exit;
    }

    $autoCompleted = null;
    if ($status === 'en_route') {
        $openStmt = $pdo->prepare("SELECT sc.id, sc.patient_id, CONCAT(p.first_name, ' ', p.last_name) AS patient_name
                                   FROM `schedule` sc
                                   JOIN patients p ON p.id = sc.patient_id
                                   WHERE sc.ma_id = ?
                                     AND sc.status = 'en_route'
                                     AND sc.id <> ?
                                   ORDER BY COALESCE(sc.visit_started_at, sc.updated_at, sc.visit_date) DESC, sc.id DESC
                                   LIMIT 1");
        $openStmt->execute([(int)$visit['ma_id'], $id]);
        $openVisit = $openStmt->fetch(PDO::FETCH_ASSOC);

        if ($openVisit) {
            $pdo->prepare("UPDATE `schedule`
                           SET status = 'completed',
                               visit_ended_at = COALESCE(visit_ended_at, NOW())
                           WHERE id = ?")
                ->execute([(int)$openVisit['id']]);

            $autoCompleted = [
                'id' => (int)$openVisit['id'],
                'patient_id' => (int)$openVisit['patient_id'],
                'patient_name' => (string)$openVisit['patient_name'],
            ];
        }
    }

    // Stamp visit_started_at the first time a visit moves to en_route
    // Stamp visit_ended_at the first time a visit moves to completed
    $startedStamp = ($status === 'en_route')   ? ', visit_started_at = COALESCE(visit_started_at, NOW())' : '';
    $endedStamp   = ($status === 'completed')  ? ', visit_ended_at   = COALESCE(visit_ended_at,   NOW())' : '';

    $stmt = $pdo->prepare("UPDATE `schedule` SET status=?" . $startedStamp . $endedStamp . " WHERE id=?");
    $stmt->execute([$status, $id]);

    $redirectDocumentId = 0;
    if ($status === 'completed' && $formSubmissionId > 0) {
        try {
            $docStmt = $pdo->prepare("SELECT id, form_data FROM form_submissions WHERE id = ? AND patient_id = ? LIMIT 1");
            $docStmt->execute([$formSubmissionId, (int)$visit['patient_id']]);
            $docRow = $docStmt->fetch(PDO::FETCH_ASSOC);
            if ($docRow) {
                $redirectDocumentId = (int)$docRow['id'];
                $formData = json_decode((string)$docRow['form_data'], true);
                if (!is_array($formData)) {
                    $formData = [];
                }
                if ($fuWeeks !== '') {
                    $formData['fu_weeks'] = $fuWeeks;
                    $formData['fu_unit'] = in_array($fuUnit, ['days', 'weeks'], true) ? $fuUnit : 'weeks';
                    $updateDoc = $pdo->prepare("UPDATE form_submissions SET form_data = ? WHERE id = ?");
                    $updateDoc->execute([json_encode($formData, JSON_UNESCAPED_UNICODE), $formSubmissionId]);
                }
            }
        } catch (Throwable $e) {
            // Do not fail the visit status update if follow-up sync cannot be saved.
        }
    }

    if ($status === 'completed' && $redirectDocumentId <= 0) {
        try {
            $latestDocSql = "SELECT id
                             FROM form_submissions
                             WHERE patient_id = ?
                               AND DATE(created_at) = ?";
            $latestDocParams = [(int)$visit['patient_id'], (string)$visit['visit_date']];
            if ($formType !== '') {
                $latestDocSql .= " AND form_type = ?";
                $latestDocParams[] = $formType;
            }
            $latestDocSql .= " ORDER BY created_at DESC, id DESC LIMIT 1";
            $latestDoc = $pdo->prepare($latestDocSql);
            $latestDoc->execute($latestDocParams);
            $redirectDocumentId = (int)($latestDoc->fetchColumn() ?: 0);
            if ($redirectDocumentId <= 0 && $formType !== '') {
                $latestByType = $pdo->prepare("SELECT id
                                               FROM form_submissions
                                               WHERE patient_id = ?
                                                 AND form_type = ?
                                               ORDER BY created_at DESC, id DESC
                                               LIMIT 1");
                $latestByType->execute([(int)$visit['patient_id'], $formType]);
                $redirectDocumentId = (int)($latestByType->fetchColumn() ?: 0);
            }
                        if ($redirectDocumentId <= 0 && in_array($formType, ['new_patient_pocket', 'new_patient_pocket_pc'], true)) {
                                $latestPacket = $pdo->prepare("SELECT id
                                                                                             FROM form_submissions
                                                                                             WHERE patient_id = ?
                                                                                                 AND form_type IN ('new_patient_pocket','new_patient_pocket_pc')
                                                                                             ORDER BY created_at DESC, id DESC
                                                                                             LIMIT 1");
                                $latestPacket->execute([(int)$visit['patient_id']]);
                                $redirectDocumentId = (int)($latestPacket->fetchColumn() ?: 0);
                        }
        } catch (Throwable $e) {
            $redirectDocumentId = 0;
        }
    }

    echo json_encode([
        'ok' => true,
        'auto_completed_visit' => $autoCompleted,
        'redirect_document_id' => $redirectDocumentId,
    ]);
    exit;
}

if ($action === 'undo_end') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
        exit;
    }
    // Admin accounts must supply their password to authorize the undo
    if (isAdmin()) {
        $adminPassword = (string)($body['admin_password'] ?? '');
        if ($adminPassword === '') {
            echo json_encode(['ok' => false, 'error' => 'Password is required.']);
            exit;
        }
        $pwStmt = $pdo->prepare('SELECT password_hash FROM staff WHERE id = ? LIMIT 1');
        $pwStmt->execute([(int)$_SESSION['user_id']]);
        $storedHash = $pwStmt->fetchColumn();
        if (!$storedHash || !password_verify($adminPassword, $storedHash)) {
            echo json_encode(['ok' => false, 'error' => 'Incorrect password.']);
            exit;
        }
    }
    $stmt = $pdo->prepare("UPDATE `schedule` SET status='en_route', visit_ended_at=NULL WHERE id=?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['ok' => false, 'error' => 'Entry not found or not authorized']);
        exit;
    }
    // Fetch patient info for audit label
    $auditRow = $pdo->prepare("SELECT CONCAT(p.first_name,' ',p.last_name) AS patient_name, sc.patient_id, sc.visit_date FROM `schedule` sc JOIN patients p ON p.id = sc.patient_id WHERE sc.id = ? LIMIT 1");
    $auditRow->execute([$id]);
    $auditData = $auditRow->fetch(PDO::FETCH_ASSOC);
    auditLog($pdo, 'visit_undo_end', 'patient',
        $auditData ? (int)$auditData['patient_id'] : null,
        $auditData ? $auditData['patient_name']    : null,
        'schedule_id=' . $id . ($auditData ? '; visit_date=' . $auditData['visit_date'] : '')
    );
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'reset_visit') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
        exit;
    }
    // Admin accounts must supply their password to authorize the reset
    if (isAdmin()) {
        $adminPassword = (string)($body['admin_password'] ?? '');
        if ($adminPassword === '') {
            echo json_encode(['ok' => false, 'error' => 'Password is required.']);
            exit;
        }
        $pwStmt = $pdo->prepare('SELECT password_hash FROM staff WHERE id = ? LIMIT 1');
        $pwStmt->execute([(int)$_SESSION['user_id']]);
        $storedHash = $pwStmt->fetchColumn();
        if (!$storedHash || !password_verify($adminPassword, $storedHash)) {
            echo json_encode(['ok' => false, 'error' => 'Incorrect password.']);
            exit;
        }
    }
    $stmt = $pdo->prepare("UPDATE `schedule` SET status='pending', visit_started_at=NULL WHERE id=?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['ok' => false, 'error' => 'Entry not found or not authorized']);
        exit;
    }
    // Fetch patient info for audit label
    $auditRow = $pdo->prepare("SELECT CONCAT(p.first_name,' ',p.last_name) AS patient_name, sc.patient_id, sc.visit_date FROM `schedule` sc JOIN patients p ON p.id = sc.patient_id WHERE sc.id = ? LIMIT 1");
    $auditRow->execute([$id]);
    $auditData = $auditRow->fetch(PDO::FETCH_ASSOC);
    auditLog($pdo, 'visit_reset', 'patient',
        $auditData ? (int)$auditData['patient_id'] : null,
        $auditData ? $auditData['patient_name']    : null,
        'schedule_id=' . $id . ($auditData ? '; visit_date=' . $auditData['visit_date'] : '')
    );
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'save_note') {
    $id   = (int)($body['id'] ?? 0);
    $note = trim($body['visit_notes'] ?? '');

    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    if (isAdmin()) {
        $stmt = $pdo->prepare("UPDATE `schedule` SET visit_notes=? WHERE id=?");
        $stmt->execute([$note ?: null, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE `schedule` SET visit_notes=? WHERE id=? AND ma_id=?");
        $stmt->execute([$note ?: null, $id, $_SESSION['user_id']]);
    }

    if ($stmt->rowCount() === 0 && !$note) {
        // rowCount is 0 when value didn't change — still OK
    }

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'edit') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    // Only admins can change visit_date / ma_id
    $fields  = [];
    $params  = [];

    if (isset($body['visit_time'])) {
        $vt = trim($body['visit_time']);
        $fields[] = 'visit_time = ?';
        $params[] = $vt !== '' ? $vt : null;
    }
    if (isset($body['visit_type'])) {
        $allowed_types = ['routine','new_patient','wound_care','awv','ccm','il'];
        $vt = $body['visit_type'];
        if (!in_array($vt, $allowed_types, true)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid visit type']);
            exit;
        }
        $fields[] = 'visit_type = ?';
        $params[] = $vt;
    }
    if (isset($body['notes'])) {
        $fields[] = 'notes = ?';
        $params[] = trim($body['notes']) ?: null;
    }
    if (isset($body['provider_name'])) {
        $fields[] = 'provider_name = ?';
        $params[] = trim($body['provider_name']) ?: null;
    }
    if (isset($body['company'])) {
        $allowed_companies = ['Beyond Wound Care Inc.', 'Visiting Medical Physician Inc.'];
        $co = $body['company'];
        if (!in_array($co, $allowed_companies, true)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid company']);
            exit;
        }
        $fields[] = 'company = ?';
        $params[] = $co;
    }
    if (isset($body['visit_subtype'])) {
        $allowed_subtypes = ['wound_care', 'primary_care', ''];
        $vs = $body['visit_subtype'];
        if (!in_array($vs, $allowed_subtypes, true)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid visit subtype']);
            exit;
        }
        $fields[] = 'visit_subtype = ?';
        $params[] = $vs !== '' ? $vs : null;
    }
    if (isset($body['visit_order'])) {
        $fields[] = 'visit_order = ?';
        $params[] = max(1, (int)$body['visit_order']);
    }
    if (isAdmin()) {
        if (isset($body['visit_date'])) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $body['visit_date'])) {
                echo json_encode(['ok' => false, 'error' => 'Invalid date']);
                exit;
            }
            $fields[] = 'visit_date = ?';
            $params[] = $body['visit_date'];
        }
        if (isset($body['ma_id'])) {
            $fields[] = 'ma_id = ?';
            $params[] = (int)$body['ma_id'];
        }
    }

    if (empty($fields)) {
        echo json_encode(['ok' => false, 'error' => 'Nothing to update']);
        exit;
    }

    $params[] = $id;
    $sql = "UPDATE `schedule` SET " . implode(', ', $fields) . " WHERE id=?";
    if (!isAdmin()) {
        $sql .= " AND ma_id=?";
        $params[] = $_SESSION['user_id'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'get') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT sc.*, CONCAT(p.first_name, ' ', p.last_name) AS patient_name
         FROM `schedule` sc
         JOIN patients p ON p.id = sc.patient_id
         WHERE sc.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$visit) {
        echo json_encode(['ok' => false, 'error' => 'Visit not found']);
        exit;
    }

    // MAs may only view their own visits
    $role = $_SESSION['role'] ?? '';
    $uid  = (int)($_SESSION['user_id'] ?? 0);
    if ($role === 'ma' && (int)$visit['ma_id'] !== $uid) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Not authorized']);
        exit;
    }

    echo json_encode(['ok' => true, 'visit' => $visit]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
