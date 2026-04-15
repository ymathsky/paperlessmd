<?php
/**
 * api/icd10_search.php
 * Returns matching ICD-10 wound care codes as JSON.
 * Query: ?q=search+terms  — returns up to 20 results.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$q = strtolower(trim($_GET['q'] ?? ''));
if ($q === '') {
    echo '[]';
    exit;
}

$dataFile = __DIR__ . '/../data/icd10_wound.json';
if (!file_exists($dataFile)) {
    echo '[]';
    exit;
}

$codes = json_decode(file_get_contents($dataFile), true);
if (!is_array($codes)) {
    echo '[]';
    exit;
}

// Split query into tokens and require all tokens to match
$tokens = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);

$results = [];
foreach ($codes as $entry) {
    $haystack = strtolower($entry['code'] . ' ' . $entry['desc'] . ' ' . $entry['cat']);
    foreach ($tokens as $token) {
        if (strpos($haystack, $token) === false) {
            continue 2; // token not found — skip entry
        }
    }
    // Boost: exact code prefix match scored first
    $entry['_score'] = str_starts_with(strtolower($entry['code']), $q) ? 1 : 0;
    $results[] = $entry;
    if (count($results) >= 30) break;
}

// Sort: exact code prefix first, then alphabetical by code
usort($results, fn($a,$b) => $b['_score'] <=> $a['_score'] ?: strcmp($a['code'], $b['code']));

// Strip internal score field and return top 20
$results = array_slice($results, 0, 20);
foreach ($results as &$r) unset($r['_score']);

echo json_encode(array_values($results), JSON_UNESCAPED_UNICODE);
