<?php
/**
 * Print Template: CCM Consent (ccm_consent)
 * Matches the exact CCM Agreement paper form.
 */
$ptName  = h($patient['first_name'] . ' ' . $patient['last_name']);
$ptDob   = $patient['dob'] ? date('m/d/Y', strtotime($patient['dob'])) : '';
$sigDate = date('m/d/Y', strtotime($f['created_at']));
$maName  = h($f['ma_name'] ?? '');
?>
<div class="bwc-form">
    <!-- Practice Header -->
    <div class="bwc-header">
        <p class="bwc-practice-name">Beyond Wound Care Inc.</p>
        <p>1340 Remington RD, STE P &nbsp; Schaumburg, IL 60173</p>
        <p>Phone: 847-873-8693 &nbsp;&nbsp; Fax: 847-873-8486</p>
        <p>Email: Support@beyondwoundcare.com</p>
    </div>

    <!-- Form Title -->
    <p style="font-size:11pt;font-weight:bold;text-align:center;margin:10pt 0 8pt;">CONSENT AGREEMENT FOR PROVISION OF CHRONIC CARE MANAGEMENT</p>

    <!-- Body Text -->
    <div style="font-size:9.5pt;line-height:1.55;margin-bottom:10pt;">
        <p style="margin:0 0 7pt;">By signing this Agreement, you consent to <span style="text-decoration:underline;font-weight:bold;">BEYOND WOUND CARE INC.</span> (referred to as "Provider"), providing chronic care management services (referred to as "CCM Services") to you as more fully described below.</p>
        <p style="margin:0 0 7pt;">CCM Services are available to you because you have been diagnosed with two (2) or more chronic conditions which are expected to last at least twelve (12) months and which place you at significant risk of further decline.</p>
        <p style="margin:0 0 7pt;">CCM Services include 24-hours-a-day, 7-days-a-week access to a health care provider in Provider's practice to address acute chronic care needs; systematic assessment of your health care needs; processes to assure that you timely receive preventative care services; medication reviews and oversight; a plan of care covering your health issues; and management of care transitions among health care providers and settings. The Provider will discuss with you the specific services that will be available to you and how to access those services.</p>

        <p style="margin:0 0 3pt;"><strong>Provider's Obligations.</strong></p>
        <p style="margin:0 0 3pt;font-style:italic;">When providing CCM Services, the Provider must:</p>
        <ul style="margin:0 0 7pt 16pt;padding:0;font-size:9.5pt;">
            <li>Explain to you (and your caregiver, if applicable), and offer to you, all the CCM Services that are applicable to your conditions.</li>
            <li>Provide to you a written or electronic copy of your care plan.</li>
            <li>If you revoke this Agreement, provide you with a written confirmation of the revocation, stating the effective date of the revocation.</li>
        </ul>

        <p style="margin:0 0 3pt;"><strong>Beneficiary Acknowledgment and Authorization.</strong></p>
        <p style="margin:0 0 3pt;font-style:italic;">By signing this Agreement, you agree to the following:</p>
        <ul style="margin:0 0 7pt 16pt;padding:0;font-size:9.5pt;">
            <li>You consent to the Provider providing CCM Services to you.</li>
            <li>You authorize electronic communication of your medical information with other treating providers as part of coordination of your care.</li>
            <li>You acknowledge that only one practitioner can furnish CCM Services to you during a calendar month.</li>
            <li>You understand that cost-sharing will apply to CCM Services, so you may be billed for a portion of CCM Services even though CCM Services will not involve a face-to-face meeting with the Provider.</li>
        </ul>

        <p style="margin:0 0 3pt;"><strong>Beneficiary Rights.</strong></p>
        <p style="margin:0 0 3pt;font-style:italic;">You have the following rights with respect to CCM Services:</p>
        <ul style="margin:0 0 7pt 16pt;padding:0;font-size:9.5pt;">
            <li>The Provider will provide you with a written or electronic copy of your care plan.</li>
            <li>You have the right to stop CCM Services at any time by revoking this Agreement effective at the end of the then-current month. You may revoke this agreement verbally or in writing to <strong><u>BEYOND WOUND CARE INC.</u></strong> Upon receipt of your revocation, the Provider will give you written confirmation with effective date of revocation.</li>
        </ul>
    </div>

    <!-- Signatures Table -->
    <table style="width:100%;border-collapse:collapse;margin-top:10pt;font-size:10pt;">
        <tr>
            <td style="width:50%;padding-right:20pt;vertical-align:bottom;">
                <strong>Patient Name:</strong> <?= $ptName ?><br><br>
                <strong>Patient Signature:</strong>
                <?php if ($f['patient_signature']): ?>
                <br><img src="<?= h($f['patient_signature']) ?>" class="bwc-sig-img" alt="Patient Signature">
                <?php endif; ?>
                <div style="border-top:1px solid #000;margin-top:20pt;"></div>
            </td>
            <td style="width:50%;vertical-align:bottom;">
                <strong>Date of Birth:</strong> <?= $ptDob ?><br><br>
                <strong>Witness Signature:</strong>
                <?php if (!empty($f['ma_signature'])): ?>
                <br><img src="<?= h($f['ma_signature']) ?>" class="bwc-sig-img" alt="MA Signature">
                <?php endif; ?>
                <div style="border-top:1px solid #000;margin-top:<?= !empty($f['ma_signature']) ? '4pt' : '42pt' ?>;"></div>
            </td>
        </tr>
        <tr>
            <td style="padding-top:12pt;vertical-align:top;">
                Print Name: <?= $ptName ?>
            </td>
            <td style="padding-top:12pt;vertical-align:top;">
                Print Name: <?= $maName ?>
            </td>
        </tr>
        <tr>
            <td style="padding-top:10pt;vertical-align:top;">
                Date: <?= $sigDate ?>
            </td>
            <td style="padding-top:10pt;vertical-align:top;">
                Date: <?= $sigDate ?>
            </td>
        </tr>
    </table>
</div>
