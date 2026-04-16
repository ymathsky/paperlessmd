<?php
/**
 * GET /api/mobile/dashboard.php
 * Returns summary stats for the authenticated user.
 */
require_once __DIR__ . '/helpers.php';
header('Content-Type: application/json');
cors();

$user  = requireToken();
$today = date('Y-m-d');
$role  = $user['role'];

$totalPatients = (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$formsToday    = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE DATE(created_at) = '$today'")->fetchColumn();
$pendingUpload = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE status = 'signed'")->fetchColumn();

$draftStmt = $pdo->prepare("SELECT COUNT(*) FROM form_submissions WHERE status = 'draft' AND ma_id = ?");
$draftStmt->execute([$user['id']]);
$draftCount = (int)$draftStmt->fetchColumn();

// Today's schedule count for this user
$scStmt = $pdo->prepare("SELECT COUNT(*) FROM `schedule` WHERE visit_date = ? AND ma_id = ?");
$scStmt->execute([$today, $user['id']]);
$scheduleToday = (int)$scStmt->fetchColumn();

// Recent forms (last 10)
$recent = $pdo->prepare("
    SELECT fs.id, fs.form_type, fs.status, fs.created_at,
           CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.id AS patient_id
    FROM form_submissions fs
    JOIN patients p ON p.id = fs.patient_id
    WHERE fs.ma_id = ?
    ORDER BY fs.created_at DESC LIMIT 10
");
$recent->execute([$user['id']]);

jsonOk([
    'stats' => [
        'total_patients' => $totalPatients,
        'forms_today'    => $formsToday,
        'pending_upload' => $pendingUpload,
        'needs_signature'=> $draftCount,
        'schedule_today' => $scheduleToday,
    ],
    'recent_forms' => $recent->fetchAll(\PDO::FETCH_ASSOC),
    'greeting_name' => explode(' ', $user['full_name'])[0],
    'role'          => $role,
]);
