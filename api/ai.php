<?php
/**
 * api/ai.php — Gemini AI proxy for PaperlessMD
 *
 * Actions (POST JSON):
 *   soap_draft    — draft SOAP note from chief complaint + vitals
 *   wound_draft   — describe wound from text measurements / notes
 *   wound_photo   — analyze wound photo (base64 image)
 *   billing_codes — suggest ICD-10 / CPT codes from visit summary
 *   message_reply — draft a reply to an internal message
 *   chat          — general staff assistant question
 */

error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

// ── Key check ─────────────────────────────────────────────────────────────────
$apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
if ($apiKey === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'AI not configured. Add GEMINI_API_KEY to config.']);
    exit;
}

// ── Request parsing ───────────────────────────────────────────────────────────
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

if (!verifyCsrf($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// ── Rate limit: 30 req/min per user (simple session counter) ─────────────────
$rlKey = 'ai_rl_' . date('Hi'); // per-minute bucket
$_SESSION[$rlKey] = ($_SESSION[$rlKey] ?? 0) + 1;
if ($_SESSION[$rlKey] > 30) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many AI requests. Wait a moment.']);
    exit;
}

// ── Build prompt by action ────────────────────────────────────────────────────
$systemPrompt = 'You are a clinical documentation assistant for Beyond Wound Care Inc., '
              . 'a wound care medical practice. Be concise, professional, and clinically accurate. '
              . 'Never invent patient data. Use standard medical terminology.';

$userPrompt = '';
$imageData  = null; // ['mime' => 'image/jpeg', 'b64' => '...']

switch ($action) {

    case 'soap_draft':
        $cc      = $input['chief_complaint'] ?? '';
        $vitals  = $input['vitals']          ?? '';
        $hx      = $input['history']         ?? '';
        if (!$cc) { badRequest('chief_complaint required'); }
        $userPrompt = "Draft a SOAP note for a wound care visit.\n"
                    . "Chief Complaint: $cc\n"
                    . ($vitals ? "Vitals: $vitals\n" : '')
                    . ($hx     ? "Relevant History: $hx\n" : '')
                    . "\nProvide Subjective, Objective, Assessment, and Plan sections. "
                    . "Flag any fields that need clinician review with [REVIEW].";
        break;

    case 'wound_draft':
        $location = $input['location']    ?? '';
        $size     = $input['size']        ?? '';
        $notes    = $input['notes']       ?? '';
        if (!$location) { badRequest('location required'); }
        $userPrompt = "Write a clinical wound description for documentation.\n"
                    . "Location: $location\n"
                    . ($size  ? "Size: $size\n" : '')
                    . ($notes ? "Clinician notes: $notes\n" : '')
                    . "\nInclude: wound bed appearance, surrounding tissue, exudate, edges, "
                    . "and a suggested wound stage/classification if applicable. Be concise.";
        break;

    case 'wound_photo':
        $b64  = $input['image_b64']   ?? '';
        $mime = $input['image_mime']  ?? 'image/jpeg';
        $loc  = $input['location']    ?? 'unspecified';
        if (!$b64) { badRequest('image_b64 required'); }
        // Basic base64 validation
        if (!preg_match('/^[A-Za-z0-9+\/]+=*$/', substr($b64, 0, 100))) {
            badRequest('Invalid image data');
        }
        $imageData  = ['mime' => $mime, 'b64' => $b64];
        $userPrompt = "Analyze this wound photo from a wound care patient. "
                    . "Location: $loc. "
                    . "Describe: wound bed, tissue type (granulation/slough/eschar %), "
                    . "wound edges, periwound skin, estimated stage/classification, "
                    . "and recommended next steps. Flag anything requiring urgent clinician review.";
        break;

    case 'billing_codes':
        $summary = $input['visit_summary'] ?? '';
        if (!$summary) { badRequest('visit_summary required'); }
        $userPrompt = "Based on this wound care visit summary, suggest appropriate ICD-10 diagnosis codes "
                    . "and CPT procedure codes. List each code with its description.\n\n"
                    . "Visit Summary:\n$summary\n\n"
                    . "Format: ICD-10: [code] - [description], CPT: [code] - [description]. "
                    . "Note that final code selection must be verified by a certified coder.";
        break;

    case 'message_reply':
        $thread  = $input['thread_body'] ?? '';
        $context = $input['context']     ?? '';
        if (!$thread) { badRequest('thread_body required'); }
        $userPrompt = "Draft a professional internal reply to this staff message thread.\n\n"
                    . "Thread:\n$thread\n\n"
                    . ($context ? "Additional context: $context\n" : '')
                    . "\nReply should be brief, friendly, and professional. Do not include a salutation or signature.";
        break;

    case 'chat':
        $question = $input['question'] ?? '';
        if (!$question) { badRequest('question required'); }
        $userPrompt = $question;
        break;

    case 'icd_suggest':
        $cc = $input['chief_complaint'] ?? '';
        if (!$cc) { badRequest('chief_complaint required'); }
        $userPrompt = "Based on this wound care chief complaint / clinical notes, suggest the most appropriate ICD-10 diagnosis codes.\n\n"
                    . "Chief Complaint: $cc\n\n"
                    . "Return ONLY a valid JSON array of objects. Each object must have: "
                    . "\"code\" (ICD-10 string), \"description\" (full description string), \"confidence\" (\"high\"|\"medium\"|\"low\"). "
                    . "Limit to 6 most clinically relevant codes. No markdown, no explanation — pure JSON only. "
                    . "Example: [{\"code\":\"L89.153\",\"description\":\"Pressure ulcer of sacral region, stage 3\",\"confidence\":\"high\"}]";
        break;

    case 'autofill':
        $freeText = $input['free_text'] ?? '';
        $fields   = $input['fields']    ?? [];
        if (!$freeText) { badRequest('free_text required'); }
        $fieldList = is_array($fields) ? implode(', ', array_map('strval', $fields)) : (string)$fields;
        $userPrompt = "Extract the following form fields from this clinical text and return ONLY valid JSON.\n\n"
                    . "Fields to extract: $fieldList\n\n"
                    . "Clinical text: $freeText\n\n"
                    . "Return ONLY a JSON object with the field names as keys and extracted values as strings. "
                    . "Use null for fields not found in the text. No explanation or markdown.";
        break;

    case 'summarize_patient':
        $history = $input['patient_history'] ?? '';
        if (!$history) { badRequest('patient_history required'); }
        $userPrompt = "Summarize this wound care patient's visit history in a concise clinical summary.\n\n"
                    . "History:\n$history\n\n"
                    . "Include: active diagnoses, wound status trends, current treatments, allergies/medications, "
                    . "and any outstanding concerns. Keep the summary under 200 words. Use standard medical terminology.";
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
        exit;
}

// ── Call Gemini API (with one retry on 429) ─────────────────────────────────
$model = 'gemini-2.0-flash';
$url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

$parts = [];
if ($imageData) {
    $parts[] = ['inline_data' => ['mime_type' => $imageData['mime'], 'data' => $imageData['b64']]];
}
$parts[] = ['text' => $userPrompt];

$payload = [
    'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
    'contents'           => [['role' => 'user', 'parts' => $parts]],
    'generationConfig'   => [
        'temperature'     => 0.3,
        'maxOutputTokens' => 1024,
    ],
];

// Exponential backoff: try up to 3 times on 429
$maxAttempts  = 3;
$retryDelays  = [0, 15, 30]; // seconds before each attempt
$raw = ''; $code = 0; $err = '';
for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
    if ($retryDelays[$attempt] > 0) { sleep($retryDelays[$attempt]); }
    [$raw, $code, $err, $retryAfter] = geminiPost($url, $payload);
    if ($code !== 429) { break; }
    // Honor server's Retry-After if it's shorter than our next delay
    if ($retryAfter > 0 && $retryAfter < $retryDelays[$attempt + 1] ?? 30) {
        $retryDelays[$attempt + 1] = (int)$retryAfter;
    }
}

if ($err) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'AI request failed: ' . $err]);
    exit;
}

$resp = json_decode($raw, true);

if ($code === 429) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'The AI service is still busy. Please wait 30 seconds and try again.', 'retry_after' => 30]);
    exit;
}

if ($code !== 200) {
    $msg = $resp['error']['message'] ?? 'AI service error';
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

$text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? '';
if (!$text) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Empty response from AI']);
    exit;
}

// Audit log AI usage (no PHI stored in log)
try {
    auditLog($pdo, 'ai_' . $action, null, null, $action);
} catch (\Throwable $e) { /* non-fatal */ }

echo json_encode(['ok' => true, 'text' => $text]);

// ── Helpers ───────────────────────────────────────────────────────────────────
function geminiPost(string $url, array $payload): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_HEADER         => true, // include response headers
    ]);
    $response   = curl_exec($ch);
    $code       = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err        = curl_error($ch);
    curl_close($ch);

    $headers = substr($response, 0, $headerSize);
    $raw     = substr($response, $headerSize);

    // Parse Retry-After header (seconds)
    $retryAfter = 0;
    if (preg_match('/^Retry-After:\s*(\d+)/mi', $headers, $m)) {
        $retryAfter = (int) $m[1];
    }

    return [$raw, $code, $err, $retryAfter];
}

function badRequest(string $msg): void {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}
