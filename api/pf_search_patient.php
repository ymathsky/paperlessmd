<?php
/**
 * api/pf_search_patient.php
 * Searches Practice Fusion for a patient by name/DOB.
 * Returns JSON array of matched patients.
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

$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name']  ?? '');
$dob       = trim($_POST['dob']        ?? '');

if (!$firstName || !$lastName) {
    echo json_encode(['success' => false, 'message' => 'First and last name are required']);
    exit;
}

try {
    $pf       = new PracticeFusionClient();
    $patients = $pf->searchPatient($firstName, $lastName, $dob ?: null);

    // Return simplified list
    $results = array_map(function ($pt) {
        $nameEntry = $pt['name'][0] ?? [];
        $given     = implode(' ', $nameEntry['given'] ?? []);
        $family    = $nameEntry['family'] ?? '';
        $dob       = $pt['birthDate'] ?? '';
        $id        = $pt['id'] ?? '';
        return [
            'pf_id'      => $id,
            'name'       => trim($given . ' ' . $family),
            'dob'        => $dob ? date('m/d/Y', strtotime($dob)) : '',
            'dob_raw'    => $dob,
        ];
    }, $patients);

    echo json_encode(['success' => true, 'patients' => $results]);
} catch (RuntimeException $e) {
    error_log('PF patient search error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
