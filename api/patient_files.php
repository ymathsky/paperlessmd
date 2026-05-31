<?php
/**
 * api/patient_files.php
 * List and delete patient file attachments.
 *
 * GET  ?patient_id=&category=   → { ok, files: [{id,filename,original_name,url,uploaded_at,uploaded_by_name}] }
 * POST {action:'delete', id, csrf, patient_id} → { ok }
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $patientId = (int)($_GET['patient_id'] ?? 0);
    $category  = preg_replace('/[^a-z0-9_]/', '', strtolower($_GET['category'] ?? ''));

    if ($patientId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid patient_id']);
        exit;
    }

    $where = 'pf.patient_id = ?';
    $params = [$patientId];
    if ($category !== '') {
        $where  .= ' AND pf.category = ?';
        $params[] = $category;
    }

    $rows = $pdo->prepare("
        SELECT pf.id, pf.filename, pf.original_name, pf.category, pf.uploaded_at,
               s.full_name AS uploaded_by_name
        FROM   patient_files pf
        LEFT JOIN staff s ON s.id = pf.uploaded_by
        WHERE  $where
        ORDER BY pf.uploaded_at DESC
    ");
    $rows->execute($params);
    $files = $rows->fetchAll(PDO::FETCH_ASSOC);

    foreach ($files as &$f) {
        $f['url'] = BASE_URL . '/uploads/patient_files/' . rawurlencode($f['filename']);
    }
    unset($f);

    echo json_encode(['ok' => true, 'files' => $files]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    if (!verifyCsrf($input['csrf'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $action    = $input['action'] ?? '';
    $patientId = (int)($input['patient_id'] ?? 0);
    $fileId    = (int)($input['id'] ?? 0);

    if ($action === 'delete') {
        if (!isAdmin() && !isMa() && !isPcc()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Not authorized']);
            exit;
        }
        // Fetch filename first
        $row = $pdo->prepare("SELECT filename FROM patient_files WHERE id = ? AND patient_id = ?");
        $row->execute([$fileId, $patientId]);
        $rec = $row->fetch(PDO::FETCH_ASSOC);
        if (!$rec) {
            echo json_encode(['ok' => false, 'error' => 'File not found']);
            exit;
        }
        // Remove from disk
        $path = dirname(__DIR__) . '/uploads/patient_files/' . $rec['filename'];
        if (file_exists($path)) {
            unlink($path);
        }
        // Remove from DB
        $pdo->prepare("DELETE FROM patient_files WHERE id = ? AND patient_id = ?")->execute([$fileId, $patientId]);
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
