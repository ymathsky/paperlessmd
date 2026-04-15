<?php
/**
 * Print Template: ABN (CMS-R-131)
 * Matches the exact CMS Advance Beneficiary Notice form layout.
 */
$ptName  = h($patient['first_name'] . ' ' . $patient['last_name']);
$sigDate = date('m/d/Y', strtotime($f['created_at']));

$notifier   = vd($data, 'notifier') ?: h(PRACTICE_NAME);
$idNumber   = vd($data, 'id_number');
$serviceDesc= vd($data, 'service_description') ?: 'Medicare covers 80% of home visit for wound care. If secondary insurance is available will bill 20% to secondary insurance.';
$reasonNotPaid = vd($data, 'reason_not_paid') ?: 'Medicare covers 80% leaving 20% to be billed. This will be billed to a secondary insurance if available. If patient does not have a secondary insurance will be billed FOR 20%';
$estimatedCost = vd($data, 'estimated_cost') ?: '20%';
$addlInfo   = vd($data, 'additional_info');
$option     = vd($data, 'abn_option'); // 'option1', 'option2', 'option3'
?>
<div class="bwc-form">
    <!-- Header Row A/B/C -->
    <table style="width:100%;border:2px solid #000;border-collapse:collapse;margin-bottom:0;">
        <tr>
            <td style="padding:5pt 7pt;border-right:1px solid #000;width:50%;vertical-align:top;">
                <strong>A. Notifier:</strong> <?= $notifier ?>
            </td>
            <td style="padding:5pt 7pt;border-right:1px solid #000;width:30%;vertical-align:top;">
                <strong>B. Patient Name:</strong> <?= $ptName ?>
            </td>
            <td style="padding:5pt 7pt;width:20%;vertical-align:top;">
                <strong>C. Identification Number:</strong><br><?= h($idNumber) ?>
            </td>
        </tr>
    </table>

    <!-- Title -->
    <div style="text-align:center;padding:8pt 0 4pt;border:2px solid #000;border-top:none;margin-bottom:0;">
        <p style="font-size:13pt;font-weight:bold;margin:0;">Advance Beneficiary Notice of Non-coverage</p>
        <p style="font-size:13pt;font-weight:bold;margin:0;">(ABN)</p>
    </div>

    <!-- NOTE Box -->
    <div style="border:2px solid #000;border-top:none;padding:6pt 8pt;margin-bottom:0;">
        <p style="margin:0;"><strong><u>NOTE:</u></strong> If Medicare doesn't pay for <strong>D. Home visit____</strong> below, you may have to pay.</p>
        <p style="margin:4pt 0 0;">Medicare does not pay for everything, even some care that you or your health care provider have good reason to think you need. We expect Medicare may not pay for the <strong>D.&nbsp; 20%</strong> below.</p>
    </div>

    <!-- D / E / F Table -->
    <table style="width:100%;border:2px solid #000;border-top:none;border-collapse:collapse;margin-bottom:0;">
        <tr>
            <th style="border-right:1px solid #000;border-bottom:1px solid #000;padding:4pt 6pt;text-align:left;width:35%;background:#f5f5f5;">D.</th>
            <th style="border-right:1px solid #000;border-bottom:1px solid #000;padding:4pt 6pt;text-align:left;width:50%;background:#f5f5f5;">E. Reason Medicare May Not Pay:</th>
            <th style="border-bottom:1px solid #000;padding:4pt 6pt;text-align:left;background:#f5f5f5;">F. Estimated Cost</th>
        </tr>
        <tr>
            <td style="border-right:1px solid #000;padding:6pt 7pt;vertical-align:top;min-height:50pt;"><?= nl2br(h($serviceDesc)) ?></td>
            <td style="border-right:1px solid #000;padding:6pt 7pt;vertical-align:top;"><?= nl2br(h($reasonNotPaid)) ?></td>
            <td style="padding:6pt 7pt;vertical-align:top;font-weight:bold;font-size:12pt;"><?= h($estimatedCost) ?></td>
        </tr>
    </table>

    <!-- What You Need To Do + Options -->
    <div style="border:2px solid #000;border-top:none;padding:6pt 8pt;">
        <p style="margin:0;"><strong>WHAT YOU NEED TO DO NOW:</strong></p>
        <ul style="margin:4pt 0 4pt 16pt;padding:0;font-size:9.5pt;">
            <li>Read this notice, so you can make an informed decision about your care.</li>
            <li>Ask us any questions that you may have after you finish reading.</li>
            <li>Choose an option below about whether to receive the <strong>D. 20%</strong> Listed above.</li>
        </ul>
        <p style="margin:0;font-size:9.5pt;"><strong>Note:</strong> If you choose Option 1 or 2, we may help you to use any other insurance that you might have, but Medicare cannot require us to do this.</p>
    </div>

    <!-- Options Box -->
    <div style="border:2px solid #000;border-top:none;padding:6pt 8pt;">
        <p style="margin:0 0 4pt;font-weight:bold;"><strong>G. OPTIONS:</strong>&nbsp;&nbsp;&nbsp; <strong>Check only one box. We cannot choose a box for you.</strong></p>
        <p style="margin:0 0 4pt;font-size:9.5pt;">
            <?= ($option === 'option1') ? '☑' : '☐' ?>
            <strong>OPTION 1.</strong> I want the <strong>Office visit</strong> listed above. You may ask to be paid now, but I also want Medicare billed for an official decision on payment, which is sent to me on a Medicare Summary Notice (MSN). I understand that if Medicare doesn't pay, I am responsible for payment, but I can appeal to Medicare by following the directions on the MSN. If Medicare does pay, you will refund any payments I made to you, less co-pays or deductibles.
        </p>
        <p style="margin:0 0 4pt;font-size:9.5pt;">
            <?= ($option === 'option2') ? '☑' : '☐' ?>
            <strong>OPTION 2.</strong> I want the <strong>Office visit</strong> listed above, but do not bill Medicare. You may ask to be paid now as I am responsible for payment. I cannot appeal if Medicare is not billed.
        </p>
        <p style="margin:0;font-size:9.5pt;">
            <?= ($option === 'option3') ? '☑' : '☐' ?>
            <strong>OPTION 3.</strong> I don't want the <strong>Office visit</strong> listed above. I understand with this choice I am <strong>not</strong> responsible for payment, and I cannot appeal to see if Medicare would pay.
        </p>
    </div>

    <!-- H. Additional Info -->
    <div style="border:2px solid #000;border-top:none;padding:6pt 8pt;">
        <p style="margin:0;"><strong>H. Additional Information:</strong></p>
        <p style="margin:4pt 0 0;min-height:18pt;"><?= nl2br(h($addlInfo)) ?></p>
    </div>

    <!-- Disclaimer -->
    <div style="border:2px solid #000;border-top:none;padding:5pt 8pt;">
        <p style="margin:0;font-size:9pt;">This notice gives our opinion, not an official Medicare decision. If you have other questions on this notice or Medicare billing, call <strong>1-800-MEDICARE</strong> (1-800-633-4227/TTY: 1-877-486-2048).</p>
        <p style="margin:3pt 0 0;font-size:9pt;">Signing below means that you have received and understand this notice. You may ask to receive a copy.</p>
    </div>

    <!-- Signature Row -->
    <table style="width:100%;border:2px solid #000;border-top:none;border-collapse:collapse;">
        <tr>
            <td style="border-right:1px solid #000;padding:8pt 7pt;width:70%;vertical-align:bottom;">
                <strong>I. Signature:</strong><br>
                <?php if ($f['patient_signature']): ?>
                <img src="<?= h($f['patient_signature']) ?>" class="bwc-sig-img" alt="Signature" style="max-height:40pt;">
                <?php else: ?>&nbsp;<?php endif; ?>
                <div style="border-top:1px solid #000;margin-top:22pt;"></div>
            </td>
            <td style="padding:8pt 7pt;vertical-align:bottom;">
                <strong>J. Date:</strong><br>
                <div style="border-top:1px solid #000;margin-top:42pt;"><?= $sigDate ?></div>
            </td>
        </tr>
    </table>

    <!-- Accessibility note -->
    <div style="border:2px solid #000;border-top:none;padding:5pt 8pt;">
        <p style="margin:0;font-size:8.5pt;font-weight:bold;">You have the right to get Medicare information in an accessible format, like large print, Braille, or audio. You also have the right to file a complaint if you feel you've been discriminated against. Visit Medicare.gov/about-us/accessibility-nondiscrimination-notice.</p>
    </div>
    <div style="display:flex;justify-content:space-between;padding:3pt 8pt;font-size:8pt;border:1px solid #ccc;border-top:none;">
        <span>Form CMS-R-131 (Exp.01/31/2026)</span>
        <span>Form Approved OMB No. 0938-0566</span>
    </div>
</div>
