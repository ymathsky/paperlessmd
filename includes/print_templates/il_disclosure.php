<?php
/**
 * Print Template: Illinois Authorization to Disclose Health Information
 */
$ptName    = vd($data, 'patient_name') ?: h($patient['first_name'] . ' ' . $patient['last_name']);
$ptDob     = vd($data, 'patient_dob')  ?: ($patient['dob'] ? date('m/d/Y', strtotime($patient['dob'])) : '');
$ptSSN     = vd($data, 'patient_ssn');
$ptAlias   = vd($data, 'patient_alias');
$authType  = vd($data, 'auth_type');        // 'disclose' or 'obtain'
$recFrom   = vd($data, 'records_from');
$recTo     = vd($data, 'records_to');
$expDate   = vd($data, 'expiration_date');
$purpose   = vd($data, 'purpose_other');
$method    = vd($data, 'disclosure_method');
$sigDate   = date('m/d/Y', strtotime($f['created_at']));

$recTypes  = vdArr($data, 'record_types');
$purposes  = vdArr($data, 'purposes');

$allRecTypes = [
    'progress_notes'      => 'Progress Notes',
    'lab_reports'         => 'Laboratory Reports',
    'imaging'             => 'Imaging / Radiology',
    'physical_therapy'    => 'Physical Therapy Records',
    'mental_health'       => 'Mental Health Records',
    'substance_abuse'     => 'Substance Abuse Records',
    'hiv_aids'            => 'HIV/AIDS Test Results',
    'other'               => 'Other',
];

$allPurposes = [
    'continuing_care'     => 'Continuing Medical Care',
    'legal'               => 'Legal',
    'billing'             => 'Billing / Insurance',
    'personal'            => 'Personal Use',
    'research'            => 'Research',
    'other'               => 'Other',
];
?>
<div class="bwc-form">
    <!-- Header -->
    <div class="bwc-header">
        <p class="bwc-practice-name">Beyond Wound Care Inc.</p>
        <p>1340 Remington RD, Ste P &nbsp; Schaumburg, IL 60173</p>
        <p>Phone: 847.873.8693 &nbsp;&nbsp; Fax: 847.873.8486</p>
        <p>Email: Support@beyondwoundcare.com</p>
        <p class="bwc-form-title">AUTHORIZATION TO USE AND DISCLOSE HEALTH INFORMATION</p>
        <p style="font-size:9pt;font-style:italic;">(Illinois Mental Health and Developmental Disabilities Confidentiality Act, 740 ILCS 110/et seq.)</p>
    </div>

    <!-- Authorization Type -->
    <div style="border:2px solid #000;padding:6pt 10pt;margin-bottom:10pt;font-size:10.5pt;">
        <strong>This authorization is to:</strong><br>
        <?= $authType === 'obtain' ? '☑' : '☐' ?> <strong>Obtain</strong> records/information &nbsp;&nbsp;&nbsp;&nbsp;
        <?= $authType === 'disclose' ? '☑' : '☐' ?> <strong>Disclose</strong> records/information
    </div>

    <!-- Patient Information -->
    <div style="border:1px solid #ccc;padding:6pt 8pt;margin-bottom:10pt;">
        <p style="font-weight:bold;text-decoration:underline;margin:0 0 5pt;font-size:9.5pt;">PATIENT INFORMATION</p>
        <table style="width:100%;font-size:9.5pt;border-collapse:collapse;">
            <tr>
                <td style="padding:2pt 4pt;">Full Name: <span class="bwc-fill"><?= $ptName ?></span></td>
                <td style="padding:2pt 4pt;">Date of Birth: <span class="bwc-fill"><?= $ptDob ?></span></td>
            </tr>
            <?php if ($ptSSN): ?>
            <tr>
                <td style="padding:2pt 4pt;">SSN (last 4): <span class="bwc-fill"><?= $ptSSN ?></span></td>
                <td style="padding:2pt 4pt;">Alias/Other name: <span class="bwc-fill"><?= $ptAlias ?: '—' ?></span></td>
            </tr>
            <?php else: ?>
            <tr>
                <td colspan="2" style="padding:2pt 4pt;">Alias/Other name: <span class="bwc-fill"><?= $ptAlias ?: '—' ?></span></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Record Types -->
    <div style="margin-bottom:10pt;font-size:9.5pt;">
        <p style="font-weight:bold;text-decoration:underline;margin:0 0 5pt;">RECORDS TO BE DISCLOSED</p>
        <table style="width:100%;font-size:9.5pt;border-collapse:collapse;">
            <tr>
                <?php $cnt = 0; foreach ($allRecTypes as $rtk => $rtl): $checked = in_array($rtk, $recTypes); ?>
                <td style="width:25%;padding:2pt 4pt;">
                    <?= $checked ? '☑' : '☐' ?> <?= htmlspecialchars($rtl, ENT_QUOTES, 'UTF-8') ?>
                </td>
                <?php $cnt++; if ($cnt % 4 === 0) echo '</tr><tr>'; endforeach; ?>
            </tr>
        </table>
        <div style="margin-top:5pt;">
            Date range: From <span class="bwc-fill"><?= $recFrom ?></span> To <span class="bwc-fill"><?= $recTo ?></span>
        </div>
    </div>

    <!-- Recipient(s) -->
    <div style="margin-bottom:10pt;font-size:9.5pt;">
        <p style="font-weight:bold;text-decoration:underline;margin:0 0 5pt;">RECIPIENT(S) OF INFORMATION</p>
        <?php for ($ri = 1; $ri <= 3; $ri++):
            $rname = vd($data, "recipient_name_{$ri}");
            $raddr = vd($data, "recipient_address_{$ri}");
            if (!$rname && !$raddr) continue; ?>
        <table style="width:100%;font-size:9.5pt;border-collapse:collapse;margin-bottom:4pt;">
            <tr>
                <td style="padding:2pt 4pt;">Name / Organization: <span class="bwc-fill"><?= $rname ?></span></td>
            </tr>
            <tr>
                <td style="padding:2pt 4pt;">Address / Fax: <span class="bwc-fill"><?= $raddr ?></span></td>
            </tr>
        </table>
        <?php endfor; ?>
    </div>

    <!-- Purpose -->
    <div style="margin-bottom:10pt;font-size:9.5pt;">
        <p style="font-weight:bold;text-decoration:underline;margin:0 0 5pt;">PURPOSE OF DISCLOSURE</p>
        <table style="width:100%;font-size:9.5pt;border-collapse:collapse;">
            <tr>
                <?php $pc = 0; foreach ($allPurposes as $pk => $pl): $pchk = in_array($pk, $purposes); ?>
                <td style="width:25%;padding:2pt 4pt;">
                    <?= $pchk ? '☑' : '☐' ?> <?= htmlspecialchars($pl, ENT_QUOTES, 'UTF-8') ?>
                </td>
                <?php $pc++; if ($pc % 4 === 0) echo '</tr><tr>'; endforeach; ?>
            </tr>
        </table>
        <?php if ($purpose): ?>
        <div style="margin-top:5pt;">Other: <span class="bwc-fill" style="min-width:300pt;"><?= $purpose ?></span></div>
        <?php endif; ?>
    </div>

    <!-- Method of Disclosure -->
    <?php if ($method): ?>
    <div style="margin-bottom:10pt;font-size:9.5pt;">
        <p style="font-weight:bold;text-decoration:underline;margin:0 0 5pt;">METHOD OF DISCLOSURE</p>
        <span class="bwc-fill" style="min-width:250pt;"><?= $method ?></span>
    </div>
    <?php endif; ?>

    <!-- Expiration -->
    <div style="border:1px solid #ccc;padding:5pt 8pt;margin-bottom:10pt;font-size:9.5pt;">
        <strong>Expiration Date or Event:</strong> <span class="bwc-fill"><?= $expDate ?: 'One Year from Signature Date' ?></span>
    </div>

    <!-- Rights Notice -->
    <div style="font-size:9pt;border:1px solid #ccc;padding:6pt 8pt;margin-bottom:10pt;line-height:1.5;">
        <p style="margin:0 0 3pt;"><strong>Patient Rights:</strong></p>
        <ul style="margin:0;padding-left:16pt;">
            <li>You have the right to revoke this authorization at any time in writing, except where action has already been taken based on this authorization.</li>
            <li>Treatment, payment, enrollment, or eligibility cannot be conditioned on signing this authorization, unless the disclosure is for research-related treatment or a health plan enrollment or eligibility determination (Illinois HIPAA, 740 ILCS 110).</li>
            <li>Once disclosed, information may be re-disclosed by the recipient and may no longer be protected by federal or state privacy rules.</li>
        </ul>
    </div>

    <!-- Signature -->
    <div class="bwc-sigs">
        <div class="bwc-sig-row">
            <div class="bwc-sig-line">
                <?php if ($f['patient_signature']): ?>
                <img src="<?= h($f['patient_signature']) ?>" class="bwc-sig-img" alt="Patient Signature">
                <?php endif; ?>
            </div>
            <div class="bwc-sig-date">Date: <?= $sigDate ?></div>
        </div>
        <div class="bwc-sig-label">Patient / Authorized Representative Signature</div>

        <div class="bwc-sig-row" style="margin-top:16pt;">
            <div class="bwc-sig-line"></div>
            <div class="bwc-sig-date">Date: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
        </div>
        <div class="bwc-sig-label">Witness Signature</div>
    </div>
</div>
