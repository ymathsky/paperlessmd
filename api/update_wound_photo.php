<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireNotBillingApi();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (!verifyCsrf($body['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$photoId     = (int)($body['photo_id']      ?? 0);
$action      = $body['action'] ?? 'update';
$location    = trim($body['wound_location'] ?? '');
$description = trim($body['description']    ?? '');
$visitType   = $body['visit_type']  ?? null;   // pre_debridement|post_debridement|post_graft
$lengthCm    = isset($body['length_cm']) && $body['length_cm'] !== '' ? (float)$body['length_cm'] : null;
$widthCm     = isset($body['width_cm'])  && $body['width_cm']  !== '' ? (float)$body['width_cm']  : null;
$depthCm     = isset($body['depth_cm'])  && $body['depth_cm']  !== '' ? (float)$body['depth_cm']  : null;
$allowedVisit = ['pre_debridement','post_debridement','post_graft',null];
if (!in_array($visitType, $allowedVisit, true)) $visitType = null;

if (!$photoId) {
    echo json_encode(['ok' => false, 'error' => 'Invalid photo ID']);
    exit;
}

// ── Delete action ────────────────────────────────────────────────────────────
if ($action === 'delete') {
    if (isAdmin()) {
        $stmt = $pdo->prepare("SELECT filename FROM wound_photos WHERE id = ?");
        $stmt->execute([$photoId]);
    } else {
        $stmt = $pdo->prepare("SELECT filename FROM wound_photos WHERE id = ? AND uploaded_by = ?");
        $stmt->execute([$photoId, (int)$_SESSION['user_id']]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'Photo not found or not authorized']);
        exit;
    }
    $filePath = UPLOAD_DIR . $row['filename'];
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
    $pdo->prepare("DELETE FROM wound_photos WHERE id = ?")->execute([$photoId]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── Update action ────────────────────────────────────────────────────────────
// First verify photo exists and user is authorized
if (isAdmin()) {
    $chk = $pdo->prepare("SELECT id FROM wound_photos WHERE id = ?");
    $chk->execute([$photoId]);
} else {
    $chk = $pdo->prepare("SELECT id FROM wound_photos WHERE id = ? AND uploaded_by = ?");
    $chk->execute([$photoId, (int)$_SESSION['user_id']]);
}
if (!$chk->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Photo not found or not authorized']);
    exit;
}

if (isAdmin()) {
    $pdo->prepare(
        "UPDATE wound_photos SET wound_location=?, description=?, visit_type=?, length_cm=?, width_cm=?, depth_cm=? WHERE id=?"
    )->execute([$location ?: null, $description ?: null, $visitType, $lengthCm, $widthCm, $depthCm, $photoId]);
} else {
    $pdo->prepare(
        "UPDATE wound_photos SET wound_location=?, description=?, visit_type=?, length_cm=?, width_cm=?, depth_cm=? WHERE id=? AND uploaded_by=?"
    )->execute([$location ?: null, $description ?: null, $visitType, $lengthCm, $widthCm, $depthCm, $photoId, (int)$_SESSION['user_id']]);
}

// ── Annotated image generator ────────────────────────────────────────────────
function pvGenAnnotatedPhoto($origFilename, $photoId, $lengthCm, $widthCm, $areaCm2) {
    $srcPath = UPLOAD_DIR . $origFilename;
    if (!file_exists($srcPath)) return null;

    $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
    $img = null;
    if     ($ext === 'jpg' || $ext === 'jpeg') { $img = @imagecreatefromjpeg($srcPath); }
    elseif ($ext === 'png')                    { $img = @imagecreatefrompng($srcPath);  }
    elseif ($ext === 'webp')                   { $img = @imagecreatefromwebp($srcPath); }
    if (!$img) return null;

    $iw       = imagesx($img);
    $ih       = imagesy($img);
    $fontFile = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
    $fontSize = max(18, (int)($iw * 0.028));

    $line1 = 'Area: ' . number_format((float)$areaCm2, 2) . ' sq cm';
    $line2 = 'L: '    . number_format((float)$lengthCm, 1) . '   W: ' . number_format((float)$widthCm, 1) . ' cm';

    $bb1  = imagettfbbox($fontSize, 0, $fontFile, $line1);
    $bb2  = imagettfbbox($fontSize, 0, $fontFile, $line2);
    $tw   = max($bb1[2] - $bb1[0], $bb2[2] - $bb2[0]);
    $lineH = (int)($fontSize * 1.45);
    $pad   = (int)($fontSize * 0.65);
    $boxW  = $tw + $pad * 2;
    $boxH  = $lineH * 2 + $pad * 2;

    $boxX = (int)(($iw - $boxW) / 2);
    $boxY = (int)($ih * 0.28);

    imagealphablending($img, true);
    $bg    = imagecolorallocatealpha($img, 0, 0, 0, 75);
    imagefilledrectangle($img, $boxX, $boxY, $boxX + $boxW, $boxY + $boxH, $bg);

    $white = imagecolorallocate($img, 255, 255, 255);
    imagettftext($img, $fontSize, 0, $boxX + $pad, $boxY + $pad + $fontSize,             $white, $fontFile, $line1);
    imagettftext($img, $fontSize, 0, $boxX + $pad, $boxY + $pad + $fontSize + $lineH,    $white, $fontFile, $line2);

    $filename = 'annotated_edit_p' . $photoId . '_' . time() . '.png';
    imagepng($img, UPLOAD_DIR . $filename, 6);
    imagedestroy($img);
    return 'uploads/photos/' . $filename;
}

// ── Upsert wound_measurements (manual entry row) when measurements provided ───
$retArea = null; $retLen = null; $retWid = null; $retAnnotUrl = null;
$manByName = ''; $manByRole = '';
if ($lengthCm !== null || $widthCm !== null) {
    $ps = $pdo->prepare("SELECT patient_id, filename FROM wound_photos WHERE id = ?");
    $ps->execute([$photoId]);
    $photRow = $ps->fetch(PDO::FETCH_ASSOC);
    if ($photRow) {
        $patId   = (int)$photRow['patient_id'];
        $areaCm2 = ($lengthCm !== null && $widthCm !== null) ? round($lengthCm * $widthCm, 2) : null;
        $staffId = (int)($_SESSION['user_id'] ?? 0);
        $retArea = $areaCm2; $retLen = $lengthCm; $retWid = $widthCm;

        // Fetch staff info for attribution
        if ($staffId) {
            $stS = $pdo->prepare("SELECT full_name, role FROM staff WHERE id = ?");
            $stS->execute([$staffId]);
            $staffInfo = $stS->fetch(PDO::FETCH_ASSOC);
            $manByName = $staffInfo['full_name'] ?? '';
            $manByRole = $staffInfo['role'] ?? '';
        }

        // Regenerate annotated image with updated measurements
        if ($lengthCm !== null && $widthCm !== null && $areaCm2 !== null) {
            $newAnnotPath = pvGenAnnotatedPhoto($photRow['filename'], $photoId, $lengthCm, $widthCm, $areaCm2);
            if ($newAnnotPath) $retAnnotUrl = $newAnnotPath;
        }

        // Look only for the existing manual entry row — never touch AI rows
        $check = $pdo->prepare("SELECT id FROM wound_measurements WHERE photo_id = ? AND entry_type = 'manual' ORDER BY id DESC LIMIT 1");
        $check->execute([$photoId]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $pdo->prepare(
                "UPDATE wound_measurements SET length_cm=?, width_cm=?, area_cm2=?, ruler_detected=0,
                 annotated_photo_path=?, recorded_by=?, measured_at=CURDATE() WHERE id=?"
            )->execute([$lengthCm, $widthCm, $areaCm2, $retAnnotUrl, $staffId, $existing['id']]);
        } else {
            $pdo->prepare(
                "INSERT INTO wound_measurements
                 (patient_id, photo_id, area_cm2, length_cm, width_cm, ruler_detected,
                  annotated_photo_path, measured_at, wound_site, recorded_by, entry_type)
                 VALUES (?, ?, ?, ?, ?, 0, ?, CURDATE(), 'Unspecified', ?, 'manual')"
            )->execute([$patId, $photoId, $areaCm2, $lengthCm, $widthCm, $retAnnotUrl, $staffId]);
        }
    }
}

echo json_encode([
    'ok'           => true,
    'area_cm2'     => $retArea,
    'length_cm'    => $retLen,
    'width_cm'     => $retWid,
    'annotated_url' => $retAnnotUrl,
    'man_by_name'  => $manByName,
    'man_by_role'  => $manByRole,
    'man_date'     => $retArea !== null ? date('M j, Y') : null,
]);
