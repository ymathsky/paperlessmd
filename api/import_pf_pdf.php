<?php
/**
 * api/import_pf_pdf.php
 * Upload a Practice Fusion PDF, extract text with pdftotext,
 * parse medication lines, save the PDF to disk, record in DB.
 *
 * POST multipart/form-data:
 *   csrf       - CSRF token
 *   patient_id - int
 *   pdf        - file upload (application/pdf)
 *
 * Returns JSON:
 *   { ok: true, meds: [{name, frequency, qty, refills}], doc_id: int, filename: string }
 *   { ok: false, error: "..." }
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!verifyCsrf($_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$patientId = (int)($_POST['patient_id'] ?? 0);
if ($patientId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid patient']);
    exit;
}

// Verify patient exists
$ps = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
$ps->execute([$patientId]);
if (!$ps->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Patient not found']);
    exit;
}

// File validation
if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    $uploadErr = $_FILES['pdf']['error'] ?? -1;
    echo json_encode(['ok' => false, 'error' => 'Upload error code: ' . $uploadErr]);
    exit;
}

$file    = $_FILES['pdf'];
$maxSize = 20 * 1024 * 1024; // 20 MB
if ($file['size'] > $maxSize) {
    echo json_encode(['ok' => false, 'error' => 'File too large (max 20 MB)']);
    exit;
}

// Validate MIME type via finfo
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
if ($mime !== 'application/pdf') {
    echo json_encode(['ok' => false, 'error' => 'Only PDF files are accepted']);
    exit;
}

// Create storage directory
$pfDir = dirname(__DIR__) . '/uploads/pf_pdfs/';
if (!is_dir($pfDir)) {
    mkdir($pfDir, 0755, true);
}

// Save file
$filename = 'pf_' . $patientId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
$destPath = $pfDir . $filename;
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['ok' => false, 'error' => 'Failed to save uploaded file']);
    exit;
}

// ── Extract text with pdftotext ───────────────────────────────────────────
$pdftotext = '/usr/bin/pdftotext';
if (!is_executable($pdftotext)) {
    // Fallback search
    $pdftotext = trim(shell_exec('which pdftotext 2>/dev/null') ?: '');
}
if (!$pdftotext || !is_executable($pdftotext)) {
    echo json_encode(['ok' => false, 'error' => 'pdftotext not available on server']);
    exit;
}

$escapedPath = escapeshellarg($destPath);
$rawText = shell_exec($pdftotext . ' -layout ' . $escapedPath . ' - 2>/dev/null');
if ($rawText === null) {
    echo json_encode(['ok' => false, 'error' => 'Failed to extract text from PDF']);
    exit;
}

// ── Parse medications ─────────────────────────────────────────────────────
$meds = parsePfMedications($rawText);

// ── Save record to DB ─────────────────────────────────────────────────────
$formData = json_encode([
    'filename'       => $filename,
    'original_name'  => basename($file['name']),
    'parsed_med_count' => count($meds),
]);

$ins = $pdo->prepare("
    INSERT INTO form_submissions
        (patient_id, form_type, form_data, status, ma_id, signed_at)
    VALUES (?, 'pf_upload', ?, 'uploaded', ?, NOW())
");
$ins->execute([$patientId, $formData, (int)$_SESSION['user_id']]);
$docId = (int)$pdo->lastInsertId();

echo json_encode([
    'ok'       => true,
    'meds'     => $meds,
    'doc_id'   => $docId,
    'filename' => $filename,
    'raw_count' => count($meds),
]);

// ── Medication parser ─────────────────────────────────────────────────────
function parsePfMedications(string $text): array
{
    $lines = preg_split('/\r?\n/', $text);
    $meds  = [];
    $inSection = false;

    // Section header patterns (Practice Fusion uses several)
    $sectionStartRx = '/\b(active\s+medications?|current\s+medications?|medications?\s+list|medication\s+summary|reconciled\s+medications?)\b/i';
    // Section end patterns
    $sectionEndRx   = '/\b(allergies|problems?|diagnos|immuniz|vital|lab\s+result|social\s+history|review\s+of\s+system|past\s+medical|surgical|family\s+history|encounter\s+detail)\b/i';

    // Columns to skip (header rows in Practice Fusion tables)
    $skipRx = '/^\s*(drug\s*name|medication\s*name|name|dose|sig|directions?|route|frequency|prescriber|start\s*date|end\s*date|qty|refill|status|ordered\s*by|no\s+active|none\s+on\s+file|no\s+known|was\s+medication\s+reconciliation\s+completed\??|no\s+selection\s+made|historical|active|inactive|daily|weekly|monthly|tablet|capsule|mouth\s+daily|every\s+\d+\s*(?:hours?|days?|weeks?|months?)|times?\s+per\s+week)\b/i';

    $looksLikeFrequency = static function (string $value): bool {
        return (bool) preg_match('/\b(QD|BID|TID|QID|Q\d+H|daily|twice|three\s*times|four\s*times|as\s*needed|PRN|weekly|monthly|nightly|every\s*\d+|once\s+daily|two\s+times\s+daily|three\s+times\s+daily)\b/i', $value);
    };

    $looksLikeMedicationRow = static function (string $name, string $line) use ($looksLikeFrequency): bool {
        $name = trim($name);
        if ($name === '') return false;
        if (!preg_match('/[a-zA-Z]{2,}/', $name)) return false;
        if (preg_match('/^\d+$/', $name)) return false;
        if (preg_match('/^[^a-zA-Z]*(?:qty|quantity|refill|refills|sig|directions?|route|prescriber|script|status)\b/i', $name)) return false;
        if (preg_match('/\b(?:qty|quantity|refill|refills|script|prescriber)\b/i', $name)
            && !preg_match('/\b(\d+(?:\.\d+)?)\s*(?:mg|mcg|g|ml|unit|units|tab(?:let)?s?|cap(?:sule)?s?|patch|puff|spray|drop|drops|supp|suppository)\b/i', $name)) return false;
        if (preg_match('/\b(not\s+active|inactive|discontinued|expired|deleted|allerg|problem|diagnos|immuniz|vital|plan|note|instruction|report|summary|reconciliation|selection\s+made|historical|script|active|daily|weekly|monthly|tablet|capsule|mouth\s+daily|every\s+\d+\s*(?:hours?|days?|weeks?|months?)|times?\s+per\s+week)\b/i', $name)) return false;
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}$/', $name)) return false;

        // Medication rows almost always carry dose, frequency, or tabular metadata.
        if (preg_match('/\b(\d+(?:\.\d+)?)\s*(?:mg|mcg|g|ml|unit|units|tab(?:let)?s?|cap(?:sule)?s?|patch|puff|spray|drop|drops|supp|suppository)\b/i', $line)) return true;
        if ($looksLikeFrequency($line)) return true;
        if (preg_match('/\b(sig|directions?|route|prescriber)[:\s]/i', $line)
            && preg_match('/\b(\d+(?:\.\d+)?)\s*(?:mg|mcg|g|ml|unit|units|tab(?:let)?s?|cap(?:sule)?s?|patch|puff|spray|drop|drops|supp|suppository)\b/i', $line)) return true;
        return false;
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;

        // Detect section start
        if (!$inSection && preg_match($sectionStartRx, $trimmed)) {
            $inSection = true;
            continue;
        }

        // Detect section end
        if ($inSection && preg_match($sectionEndRx, $trimmed)) {
            break;
        }

        if (!$inSection) continue;

        // Skip table headers / empty markers
        if (preg_match($skipRx, $trimmed)) continue;

        // Skip lines that are obviously not medication rows.
        if (preg_match('/\b(allergies?|problems?|diagnoses?|encounter|history|vitals?|blood\s*pressure|pulse|temp|weight|height|resp|oxygen|smoking|social\s+history)\b/i', $trimmed)) {
            continue;
        }

        // Skip very short lines (page numbers, separators)
        if (strlen($trimmed) < 3) continue;
        if (preg_match('/^[\d\s\-\|=_\.]+$/', $trimmed)) continue;

        // Try to parse: "Drug Name  [dose]  [frequency]  [qty/refills]"
        // Practice Fusion layout: columns separated by 2+ spaces
        $cols = preg_split('/\s{2,}/', $trimmed);

        $name      = '';
        $frequency = '';
        $qty       = '';
        $refills   = '';

        if (count($cols) >= 1) {
            $name = trim($cols[0]);
        }
        if (count($cols) >= 2) {
            // Second column is often Sig/frequency or dose
            $c1 = trim($cols[1]);
            // If it looks like a frequency keyword use it as frequency
            if ($looksLikeFrequency($c1)) {
                $frequency = $c1;
            } elseif (count($cols) >= 3) {
                $frequency = trim($cols[2]);
            }
        }
        // Look for qty pattern anywhere in the line
        if (preg_match('/\b(qty|quantity)[:\s]+(\d+)/i', $trimmed, $m)) {
            $qty = $m[2];
        } elseif (preg_match('/\b(\d+)\s*(?:tab(?:let)?s?|cap(?:sule)?s?|ml|mg|mcg|unit)\b/i', $trimmed, $m)) {
            $qty = $m[1];
        }
        if (preg_match('/\b(refill[s]?)[:\s]+(\d+)/i', $trimmed, $m)) {
            $refills = $m[2];
        }

        // Only add if the row resembles a medication entry.
        if ($looksLikeMedicationRow($name, $trimmed)) {
            $meds[] = [
                'name'      => $name,
                'frequency' => $frequency,
                'qty'       => $qty,
                'refills'   => $refills,
            ];
        }
    }

    // Deduplicate by name (case-insensitive)
    $seen  = [];
    $dedup = [];
    foreach ($meds as $m) {
        $key = strtolower($m['name']);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $dedup[] = $m;
        }
    }

    return $dedup;
}
