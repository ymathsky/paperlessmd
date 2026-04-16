<?php
/**
 * GET  /api/mobile/patients.php?q=&page=1&per_page=20
 * POST /api/mobile/patients.php  { patient fields }
 */
require_once __DIR__ . '/helpers.php';
header('Content-Type: application/json');
cors();

$user = requireToken();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $q       = trim($_GET['q'] ?? '');
    $page    = max(1, (int)($_GET['page']     ?? 1));
    $perPage = min(100, (int)($_GET['per_page'] ?? 20));
    $offset  = ($page - 1) * $perPage;

    $where  = ['1=1'];
    $params = [];
    if ($q !== '') {
        $where[]  = "(p.first_name LIKE ? OR p.last_name LIKE ? OR p.phone LIKE ? OR p.dob LIKE ?)";
        $like     = "%$q%";
        $params   = [$like, $like, $like, $like];
    }

    $whereStr = implode(' AND ', $where);
    $stmt = $pdo->prepare("
        SELECT p.id, p.first_name, p.last_name, p.dob, p.phone, p.insurance, p.address,
               COUNT(DISTINCT fs.id) AS form_count,
               COUNT(DISTINCT wp.id) AS photo_count
        FROM patients p
        LEFT JOIN form_submissions fs ON fs.patient_id = p.id
        LEFT JOIN wound_photos wp     ON wp.patient_id = p.id
        WHERE $whereStr
        GROUP BY p.id
        ORDER BY p.last_name, p.first_name
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $patients = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT p.id) FROM patients p WHERE $whereStr");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    jsonOk([
        'patients' => $patients,
        'total'    => $total,
        'page'     => $page,
        'pages'    => (int)ceil($total / $perPage),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $required = ['first_name', 'last_name', 'dob'];
    foreach ($required as $f) {
        if (empty($b[$f])) jsonError("Field '$f' is required", 422);
    }
    $stmt = $pdo->prepare("
        INSERT INTO patients (first_name, last_name, dob, phone, address, insurance, insurance_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        trim($b['first_name']),
        trim($b['last_name']),
        $b['dob'],
        $b['phone']        ?? '',
        $b['address']      ?? '',
        $b['insurance']    ?? '',
        $b['insurance_id'] ?? '',
    ]);
    $id = $pdo->lastInsertId();
    jsonOk(['id' => $id], 201);
}

jsonError('Method not allowed', 405);
