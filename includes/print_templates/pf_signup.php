<?php
/**
 * Print Template: Patient Fusion Portal Consent (pf_signup)
 * Matches the exact paper form layout.
 */
$ptName  = h($patient['first_name'] . ' ' . $patient['last_name']);
$ptDob   = $patient['dob'] ? date('m/d/Y', strtotime($patient['dob'])) : '';
$sigDate = date('m/d/Y', strtotime($f['created_at']));

$decision  = vd($data, 'pf_decision');  // 'participate' or 'decline'
$email     = vd($data, 'pf_email');
$repName   = vd($data, 'rep_name');
?>
<div class="bwc-form">
    <!-- Practice Header -->
    <div class="bwc-header">
        <p class="bwc-practice-name">Beyond Wound Care Inc.</p>
        <p>1340 Remington RD, STE P &nbsp; Schaumburg IL 60173</p>
        <p>Phone: 847-873-8693 &nbsp;&nbsp; Fax: 847-873-8486</p>
        <p>Email: Support@beyondwoundcare.com</p>
    </div>

    <!-- Patient Info -->
    <div style="display:flex;justify-content:space-between;padding:6pt 0;border-bottom:1px solid #000;margin-bottom:10pt;">
        <span>Patient Name: <span class="bwc-fill"><?= $ptName ?></span></span>
        <span>Date of Birth: <span class="bwc-fill"><?= $ptDob ?></span></span>
    </div>

    <!-- Consent Text -->
    <div style="font-size:10pt;line-height:1.5;margin-bottom:10pt;">
        <p style="margin:0 0 8pt;">I acknowledge that I have read and fully understand this consent form. I have been given the risks and benefits of Patient Fusion and under the risks associated with online communications between our office and patients.</p>
        <p style="margin:0 0 8pt;">By signing below and providing an e-mail address, I hereby give my informed consent to participle in Patient Fusion and I hereby agree to and accept of the provision contained above.</p>
        <p style="margin:0 0 8pt;">I acknowledge that the e-mail address provided belongs to me or my authorized representative and that I will receive Patient Fusion enrollment instruction, including applicable terms of serviced, to the address if I agree to participate.</p>
        <p style="margin:0 0 8pt;font-weight:bold;text-decoration:underline;">By declining and not providing an email, my signature indicated that I am informed about Patient Fusion being offered to me, but I do not wish to participate.</p>
        <p style="margin:0 0 8pt;">I understand I may choose to participate at any time in the future by requesting to update my response to this agreement with the practice.</p>
        <p style="margin:0;">A copy of this agreement will be provided to you and one will also be included in your medical record with our practice.</p>
    </div>

    <!-- Decision -->
    <div style="margin-bottom:10pt;font-size:10pt;">
        <strong>Please check one:</strong>&nbsp;&nbsp;&nbsp;
        <?= ($decision === 'participate') ? '☑' : '☐' ?> Participate in Patient Fusion
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        <?= ($decision === 'decline') ? '☑' : '☐' ?> Decline
    </div>

    <!-- Email -->
    <div style="margin-bottom:20pt;font-size:10pt;">
        Email, if participating: <span class="bwc-fill" style="min-width:280pt;"><?= $email ?></span>
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
        <div class="bwc-sig-label">Patient Signature:</div>

        <div class="bwc-sig-row" style="margin-top:18pt;">
            <div class="bwc-sig-line" style="width:240pt;"><?= $repName ?></div>
        </div>
        <div class="bwc-sig-label">Representative Name:</div>

        <div class="bwc-sig-row" style="margin-top:18pt;">
            <div class="bwc-sig-line"></div>
            <div class="bwc-sig-date">Date:</div>
        </div>
        <div class="bwc-sig-label">Representative Signature:</div>
    </div>
</div>
