<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['patients' => [], 'forms' => []]);
    exit;
}

$like = '%' . $q . '%';

// Patients
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, dob, phone, status, company
    FROM patients
    WHERE first_name LIKE ? OR last_name LIKE ?
       OR phone LIKE ? OR dob LIKE ?
       OR CONCAT(first_name, ' ', last_name) LIKE ?
       OR CONCAT(last_name, ', ', first_name) LIKE ?
    ORDER BY last_name, first_name
    LIMIT 8
");
$stmt->execute([$like, $like, $like, $like, $like, $like]);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Forms
$stmt = $pdo->prepare("
    SELECT fs.id, fs.form_type, fs.status, fs.created_at,
           p.first_name, p.last_name, p.id AS patient_id
    FROM form_submissions fs
    JOIN patients p ON p.id = fs.patient_id
    WHERE p.first_name LIKE ? OR p.last_name LIKE ?
       OR fs.form_type LIKE ?
       OR CONCAT(p.first_name, ' ', p.last_name) LIKE ?
    ORDER BY fs.created_at DESC
    LIMIT 6
");
$stmt->execute([$like, $like, $like, $like]);
$forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['patients' => $patients, 'forms' => $forms]);
