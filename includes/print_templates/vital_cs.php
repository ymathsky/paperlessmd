<?php
/**
 * Print Template: Visit Consent Form (vital_cs)
 * Matches the exact paper form layout required by Beyond Wound Care Inc.
 * Variables available: $data (form_data array), $patient (patient row), $f (form submission row)
 */
$ptName  = h($patient['first_name'] . ' ' . $patient['last_name']);
$ptDob   = $patient['dob'] ? date('m/d/Y', strtotime($patient['dob'])) : '';
$maName  = h($f['ma_name'] ?? '');
$sigDate = date('m/d/Y', strtotime($f['created_at']));

// Field helpers (defined in export_pdf.php; guards prevent redeclaration)
if (!function_exists('vd')) {
    function vd(array $d, string $k): string {
        return isset($d[$k]) ? htmlspecialchars((string)$d[$k], ENT_QUOTES, 'UTF-8') : '';
    }
}
if (!function_exists('vdArr')) {
    function vdArr(array $d, string $k): array {
        if (!isset($d[$k])) return [];
        return is_array($d[$k]) ? $d[$k] : array_filter(array_map('trim', explode(',', (string)$d[$k])));
    }
}

$provider = vd($data, 'provider_name') ?: '_______________________';
$visitType = vd($data, 'visit_type');
$fuWeeks  = vd($data, 'fu_weeks');
$timeIn   = vd($data, 'time_in');
$timeOut  = vd($data, 'time_out');
$homebound = vd($data, 'homebound');   // 'homebound' or 'not_homebound'
$missedReason = vd($data, 'missed_visit_reason');

$bp      = vd($data, 'bp');
$pulse   = vd($data, 'pulse');
$temp    = vd($data, 'temp');
$o2sat   = vd($data, 'o2sat');
$glucose = vd($data, 'glucose');
$height  = vd($data, 'height');
$weight  = vd($data, 'weight');
$resp    = vd($data, 'resp');

$chiefComplaint = vd($data, 'chief_complaint');
$pharmacyName   = vd($data, 'pharmacy_name');
$pharmacyPhone  = vd($data, 'pharmacy_phone');
$assistiveDevice = vd($data, 'assistive_device');
$allergies      = vd($data, 'allergies');
$races          = vdArr($data, 'race');

// ICD-10 codes
$icdCodes = vdArr($data, 'icd10_codes');

// Medications (med_type_1 through med_type_6)
$meds = [];
for ($i = 1; $i <= 6; $i++) {
    $meds[] = [
        'type' => vd($data, "med_type_$i"),
        'name' => vd($data, "med_name_$i"),
        'freq' => vd($data, "med_freq_$i"),
    ];
}

// Visit type checkboxes
$vtOptions = ['New', 'Follow Up', 'Sick', 'Post Hospital F/U'];
?>
<div class="bwc-form">
    <!-- Practice Header -->
    <div class="bwc-header">
        <p class="bwc-practice-name">Beyond Wound Care Inc.</p>
        <p>1340 Remington RD, STE P, Schaumburg, IL 60173</p>
        <p>Phone: 847-873-8693 &nbsp;&nbsp; Fax: 847-873-8486</p>
        <p>Support@beyondwoundcare.com</p>
        <p class="bwc-form-title">CONSENT FORM</p>
    </div>

    <!-- Patient / Provider Lines -->
    <div class="bwc-patient-line">
        I, <span class="bwc-fill"><?= $ptName ?></span>, Date of Birth <span class="bwc-fill"><?= $ptDob ?></span> was seen today
        <div style="font-size:9pt;color:#666;margin-top:1pt;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(Patient Name)</div>
    </div>
    <div class="bwc-provider-line">
        By <span class="bwc-fill"><?= $provider ?></span> from Beyond Wound Care Inc.
        <div style="font-size:9pt;color:#666;margin-top:1pt;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(Provider)</div>
    </div>

    <!-- Visit Type Row -->
    <div class="bwc-visit-row">
        <strong>
        <?php foreach ($vtOptions as $vt): ?>
            <span style="margin-right:14pt;">
                <?= ($visitType === $vt) ? '☑' : '☐' ?> <?= htmlspecialchars($vt, ENT_QUOTES, 'UTF-8') ?>
            </span>
        <?php endforeach; ?>
        </strong>
        &nbsp;&nbsp;F/U IN: <span class="bwc-fill-sm"><?= $fuWeeks ?: '___' ?></span> WEEKS
        &nbsp;&nbsp;Time in: <span class="bwc-fill-sm"><?= $timeIn ?: '_______' ?></span>
        &nbsp;&nbsp;Time Out: <span class="bwc-fill-sm"><?= $timeOut ?: '_______' ?></span>
    </div>

    <!-- Homebound -->
    <div class="bwc-homebound-row">
        <span style="margin-right:60pt;">Patient <strong><u><?= $homebound === 'homebound' ? '☑' : '☐' ?> IS HOMEBOUND</u></strong></span>
        <span>Patient <strong><u><?= $homebound === 'not_homebound' ? '☑' : '☐' ?> IS NOT HOMEBOUND</u></strong></span>
    </div>

    <!-- Missed Visit -->
    <div class="bwc-row">
        <strong>MISSED VISIT:</strong>&nbsp; Reason: <span class="bwc-fill"><?= $missedReason ?: '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' ?></span>
    </div>

    <!-- Vitals Grid -->
    <table class="bwc-vitals-table">
        <tr>
            <td><strong>BP:</strong><br><?= $bp ?></td>
            <td><strong>PULSE:</strong><br><?= $pulse ?></td>
            <td><strong>TEMP:</strong><br><?= $temp ?></td>
            <td><strong>O2SAT:</strong><br><?= $o2sat ?></td>
        </tr>
        <tr>
            <td><strong>GLUCOSE:</strong><br><?= $glucose ?><br><small>Checked or Per patient</small></td>
            <td><strong>HEIGHT:</strong><br><?= $height ?><br><small>Checked or Per patient</small></td>
            <td><strong>WEIGHT:</strong><br><?= $weight ?><br><small>Checked or Per patient</small></td>
            <td><strong>RESP:</strong><br><?= $resp ?></td>
        </tr>
        <tr>
            <td colspan="4" style="min-height:52pt;"><strong>Chief Complaint | Notes:</strong><br><?= nl2br($chiefComplaint) ?></td>
        </tr>
    </table>

    <!-- Pharmacy / Assistive / Race / Allergies -->
    <div class="bwc-row">
        Pharmacy: <span class="bwc-fill"><?= $pharmacyName ?></span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        Phone: <span class="bwc-fill"><?= $pharmacyPhone ?></span>
    </div>
    <div class="bwc-row">
        Assistive Device: <span class="bwc-fill"><?= $assistiveDevice ?></span>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        Race:
        <?php $raceOptions = ['African American','Caucasian','Latin','Asian','Pacific Islander','Other'];
        foreach ($raceOptions as $r): ?>
            <span class="bwc-race-chip<?= in_array($r, $races) ? ' bwc-checked' : '' ?>"><?= htmlspecialchars($r, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endforeach; ?>
    </div>
    <div class="bwc-row">
        Allergies: <span class="bwc-fill"><?= $allergies ?></span>
    </div>

    <!-- ICD-10 codes (if any) -->
    <?php if (!empty($icdCodes)): ?>
    <div class="bwc-row" style="margin-top:4pt;">
        <strong>Diagnosis / ICD-10 Codes:</strong>
        <span style="margin-left:6pt;"><?= implode(' &nbsp;|&nbsp; ', array_map('htmlspecialchars', $icdCodes)) ?></span>
    </div>
    <?php endif; ?>

    <!-- Medication List -->
    <table class="bwc-med-table">
        <thead>
            <tr>
                <td colspan="3"><strong>MEDICATION LIST &amp; REFERRALS:</strong></td>
            </tr>
            <tr class="bwc-med-header">
                <th style="width:15%;">New/Refill</th>
                <th style="width:60%;">Medication &amp; Dose</th>
                <th style="width:25%;">Frequency</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($meds as $med): ?>
            <tr>
                <td><?= $med['type'] ?></td>
                <td><?= $med['name'] ?></td>
                <td><?= $med['freq'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Signatures -->
    <div class="bwc-sigs">
        <div class="bwc-sig-row">
            <div class="bwc-sig-line">
                <?php if ($f['patient_signature']): ?>
                <img src="<?= h($f['patient_signature']) ?>" class="bwc-sig-img" alt="Patient Signature">
                <?php endif; ?>
            </div>
            <div class="bwc-sig-date">Date: <?= $sigDate ?></div>
        </div>
        <div class="bwc-sig-label">Patient Signature</div>

        <div class="bwc-sig-row" style="margin-top:18pt;">
            <div class="bwc-sig-line"></div>
            <div class="bwc-sig-date">Date: <?= $sigDate ?></div>
        </div>
        <div class="bwc-sig-label">Medical Assistant Signature / Initials — <?= $maName ?></div>

        <div class="bwc-sig-row" style="margin-top:18pt;">
            <div class="bwc-sig-line"></div>
            <div class="bwc-sig-date">Date:</div>
        </div>
        <div class="bwc-sig-label">Provider Signature</div>
    </div>
</div>
