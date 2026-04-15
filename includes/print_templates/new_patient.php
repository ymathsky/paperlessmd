<?php
/**
 * Print Template: New Patient Consent (new_patient)
 */
$ptName  = h($patient['first_name'] . ' ' . $patient['last_name']);
$ptDob   = $patient['dob'] ? date('m/d/Y', strtotime($patient['dob'])) : '';
$sigDate = date('m/d/Y', strtotime($f['created_at']));
$maName  = h($f['ma_name'] ?? '');
$fmDate  = vd($data, 'form_date') ? date('m/d/Y', strtotime(vd($data,'form_date'))) : $sigDate;

$consentTreatment = vd($data, 'consent_treatment');
$consentHipaa     = vd($data, 'consent_hipaa');
$consentFinancial = vd($data, 'consent_financial');
$emergName        = vd($data, 'emergency_name');
$emergRel         = vd($data, 'emergency_relationship');
$emergPhone       = vd($data, 'emergency_phone');
?>
<div class="bwc-form">
    <!-- Practice Header -->
    <div class="bwc-header">
        <p class="bwc-practice-name">Beyond Wound Care Inc.</p>
        <p>1340 Remington RD, STE P, Schaumburg, IL 60173</p>
        <p>Phone: 847-873-8693 &nbsp;&nbsp; Fax: 847-873-8486</p>
        <p>Support@beyondwoundcare.com</p>
        <p class="bwc-form-title">NEW PATIENT CONSENT FORM</p>
    </div>

    <!-- Patient Info -->
    <div style="display:flex;justify-content:space-between;border-bottom:1px solid #000;padding-bottom:6pt;margin-bottom:10pt;font-size:10pt;">
        <span><strong>Patient Name:</strong> <?= $ptName ?></span>
        <span><strong>Date of Birth:</strong> <?= $ptDob ?></span>
        <span><strong>Date:</strong> <?= $fmDate ?></span>
    </div>

    <!-- Consent for Treatment -->
    <div style="margin-bottom:10pt;">
        <p style="font-size:10.5pt;font-weight:bold;margin:0 0 3pt;"><?= ($consentTreatment == '1') ? '☑' : '☐' ?> &nbsp;Consent for Treatment</p>
        <div style="font-size:9.5pt;line-height:1.5;margin-left:16pt;">
            <p style="margin:0 0 4pt;">I hereby consent to and authorize Beyond Wound Care Inc. and its staff to provide medical care, treatment, and services deemed appropriate for my condition. I understand that this care may include diagnostic tests, medications, and other treatment procedures as determined by my healthcare provider.</p>
        </div>
    </div>

    <!-- HIPAA Acknowledgment -->
    <div style="margin-bottom:10pt;">
        <p style="font-size:10.5pt;font-weight:bold;margin:0 0 3pt;"><?= ($consentHipaa == '1') ? '☑' : '☐' ?> &nbsp;HIPAA Privacy Notice Acknowledgment</p>
        <div style="font-size:9.5pt;line-height:1.5;margin-left:16pt;">
            <p style="margin:0 0 4pt;">I acknowledge that I have received and reviewed the Notice of Privacy Practices for Beyond Wound Care Inc. I understand that my medical information may be used and disclosed as described in that notice for treatment, payment, and healthcare operations.</p>
        </div>
    </div>

    <!-- Financial Responsibility -->
    <div style="margin-bottom:14pt;">
        <p style="font-size:10.5pt;font-weight:bold;margin:0 0 3pt;"><?= ($consentFinancial == '1') ? '☑' : '☐' ?> &nbsp;Financial Responsibility</p>
        <div style="font-size:9.5pt;line-height:1.5;margin-left:16pt;">
            <p style="margin:0 0 4pt;">I understand that I am responsible for any charges not covered by my insurance. I authorize Beyond Wound Care Inc. to submit claims to my insurance company on my behalf, and I authorize payment of benefits directly to the provider. I agree to pay any applicable co-payments, deductibles, or non-covered services at the time of service or upon billing.</p>
        </div>
    </div>

    <!-- Emergency Contact -->
    <div style="border:1px solid #ccc;padding:8pt;border-radius:4pt;margin-bottom:16pt;font-size:10pt;">
        <p style="margin:0 0 6pt;font-weight:bold;">Emergency Contact</p>
        <div style="display:flex;gap:30pt;">
            <span>Name: <span class="bwc-fill"><?= $emergName ?></span></span>
            <span>Relationship: <span class="bwc-fill"><?= $emergRel ?></span></span>
            <span>Phone: <span class="bwc-fill"><?= $emergPhone ?></span></span>
        </div>
    </div>

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
        <div class="bwc-sig-label">Medical Assistant Signature — <?= $maName ?></div>
    </div>
</div>
