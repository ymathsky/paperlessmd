<?php
/**
 * Print Template: New Patient Pocket
 * Renders as 6 SEPARATE forms, each with its own header + signature block.
 * Forms: 1-CS  2-CCM  3-ABN  4-Wound Care  5-PHI  6-Patient Fusion
 */
$ptName  = h($patient['first_name'] . ' ' . $patient['last_name']);
$ptDob   = !empty($patient['dob']) ? date('m/d/Y', strtotime($patient['dob'])) : '';
$sigDate = date('m/d/Y', strtotime($f['created_at']));
$fd      = is_string($f['form_data']) ? (json_decode($f['form_data'], true) ?? []) : ($f['form_data'] ?? []);
$visitDate = !empty($fd['form_date']) ? date('m/d/Y', strtotime($fd['form_date'])) : $sigDate;

/* ΓöÇΓöÇ Helpers ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ */
function _npp(array $fd, string $k, string $fb = '&nbsp;'): string {
    $v = $fd[$k] ?? '';
    return trim((string)$v) !== '' ? h($v) : $fb;
}
function _nppChk(bool $checked): string {
    return $checked ? '&#x2611;' : '&#x2610;';
}

/* ΓöÇΓöÇ Shared data ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ */
$icdList = $fd['icd10_codes'] ?? [];
if (!is_array($icdList)) $icdList = [];

$raceList = $fd['race'] ?? [];
if (!is_array($raceList)) $raceList = array_filter(explode(',', (string)$raceList));
$raceStr  = implode(', ', array_map('h', $raceList));

$selectedRecords  = is_array($fd['record_types'] ?? null) ? $fd['record_types']  : [];
$selectedPurposes = is_array($fd['purposes']     ?? null) ? $fd['purposes']      : [];
$recordTypeLabels = [
    'all'=>'Complete/All','discharge_summary'=>'Discharge Summary','inpatient'=>'Inpatient',
    'outpatient'=>'Outpatient','psychiatric'=>'Psychiatric','psych_eval'=>'Psych Evaluation',
    'mental_health'=>'Mental Health','alcohol_substance'=>'Alcohol & Substance','hiv_aids'=>'HIV/AIDS',
    'genetic'=>'Genetic Information','lab'=>'Lab/Pathology','xray'=>'Radiology','other'=>'Other',
];
$purposeLabels = [
    'personal_use'=>'Personal Use','continuity_care'=>'Continuity of Care',
    'placement_transfer'=>'Placement/Transfer','legal'=>'Legal/Judicial',
    'insurance'=>'Insurance/Benefits','research'=>'Research','other'=>'Other',
];

/* ΓöÇΓöÇ Reusable output functions ΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇΓöÇ */

function nppFormHeader(string $formTitle, string $ptName, string $ptDob, string $visitDate): void { ?>
<div class="bwc-header">
    <img src="<?= BASE_URL ?>/assets/img/logo.png" class="bwc-header-logo" alt="Beyond Wound Care">
    <div class="bwc-header-text">
        <p class="bwc-practice-name"><?= h(PRACTICE_NAME) ?></p>
        <p><?= h(PRACTICE_ADDRESS) ?></p>
        <p>Phone: <?= h(PRACTICE_PHONE) ?> &nbsp;&nbsp; Fax: <?= h(PRACTICE_FAX) ?></p>
        <p>Email: <?= h(PRACTICE_EMAIL) ?></p>
    </div>
</div>
<p style="text-align:center;font-size:12pt;font-weight:bold;text-transform:uppercase;letter-spacing:.06em;margin:4pt 0 2pt;">
    <?= h($formTitle) ?>
</p>
<p style="text-align:center;font-size:8.5pt;color:#555;margin:0 0 6pt;">
    Patient: <strong><?= $ptName ?></strong>
    &nbsp;&bull;&nbsp; DOB: <strong><?= h($ptDob) ?></strong>
    &nbsp;&bull;&nbsp; Date: <strong><?= h($visitDate) ?></strong>
</p>
<hr style="border:0;border-top:1.5pt solid #3730a3;margin:0 0 8pt;">
<?php }

function nppSigBlock(array $f, array $fd, string $sigDate): void { ?>
<div style="margin-top:18pt;border-top:1pt solid #ccc;padding-top:10pt;">
    <!-- Patient -->
    <table style="width:100%;border-collapse:collapse;font-size:9pt;margin-bottom:12pt;">
        <tr>
            <td style="width:64%;padding-right:16pt;vertical-align:bottom;">
                <div style="position:relative;min-height:36pt;border-bottom:1px solid #000;">
                    <?php if (!empty($f['patient_signature'])): ?>
                    <img src="<?= h($f['patient_signature']) ?>" class="bwc-sig-img" alt="Patient Signature">
                    <?php endif; ?>
                </div>
                <div class="bwc-sig-label">
                    Signature of Patient or Authorized Representative
                    <?php if (!empty($f['poa_name'])): ?>
                    <br><span style="font-size:8pt;color:#555;">POA: <?= h($f['poa_name']) ?><?= !empty($f['poa_relationship']) ? ' (' . h($f['poa_relationship']) . ')' : '' ?></span>
                    <?php endif; ?>
                </div>
            </td>
            <td style="width:36%;vertical-align:bottom;">
                <div style="min-height:36pt;border-bottom:1px solid #000;padding-bottom:2pt;font-size:10pt;"><?= $sigDate ?></div>
                <div class="bwc-sig-label">Date</div>
            </td>
        </tr>
    </table>
    <!-- MA -->
    <table style="width:100%;border-collapse:collapse;font-size:9pt;margin-bottom:12pt;">
        <tr>
            <td style="width:44%;padding-right:16pt;vertical-align:bottom;">
                <div style="position:relative;min-height:36pt;border-bottom:1px solid #000;">
                    <?php if (!empty($f['ma_signature'])): ?>
                    <img src="<?= h($f['ma_signature']) ?>" class="bwc-sig-img" alt="MA Signature">
                    <?php endif; ?>
                </div>
                <div class="bwc-sig-label">Medical Assistant Signature</div>
            </td>
            <td style="width:30%;padding-right:16pt;vertical-align:bottom;">
                <div style="min-height:36pt;border-bottom:1px solid #000;padding-top:18pt;padding-bottom:2pt;"><?= h($fd['ma_name'] ?? '') ?></div>
                <div class="bwc-sig-label">MA Name (Print)</div>
            </td>
            <td style="width:26%;vertical-align:bottom;">
                <div style="min-height:36pt;border-bottom:1px solid #000;padding-bottom:2pt;font-size:10pt;"><?= $sigDate ?></div>
                <div class="bwc-sig-label">Date</div>
            </td>
        </tr>
    </table>
    <!-- Provider -->
    <table style="width:100%;border-collapse:collapse;font-size:9pt;">
        <tr>
            <td style="width:44%;padding-right:16pt;vertical-align:bottom;">
                <div style="position:relative;min-height:36pt;border-bottom:1px solid #000;">
                    <?php if (!empty($fd['provider_signature'])): ?>
                    <img src="<?= h($fd['provider_signature']) ?>" class="bwc-sig-img" alt="Provider Signature">
                    <?php endif; ?>
                </div>
                <div class="bwc-sig-label">Provider / Physician Signature</div>
            </td>
            <td style="width:30%;padding-right:16pt;vertical-align:bottom;">
                <div style="min-height:36pt;border-bottom:1px solid #000;padding-top:18pt;padding-bottom:2pt;"><?= h($fd['provider_print_name'] ?? $fd['provider_name'] ?? '') ?></div>
                <div class="bwc-sig-label">Provider Name (Print)</div>
            </td>
            <td style="width:26%;vertical-align:bottom;">
                <div style="min-height:36pt;border-bottom:1px solid #000;padding-bottom:2pt;font-size:8.5pt;color:#555;">
                    <?php if (!empty($fd['provider_npi'])): ?>NPI: <?= h($fd['provider_npi']) ?><?php endif; ?>
                </div>
                <div class="bwc-sig-label">Provider NPI</div>
            </td>
        </tr>
    </table>
</div>
<?php }

function nppFooter(string $formTitle, string $ptName, string $sigDate): void { ?>
<p style="font-size:7pt;color:#aaa;text-align:center;margin-top:8pt;border-top:.5pt solid #e2e8f0;padding-top:3pt;">
    <?= h($formTitle) ?> &mdash; <?= h(PRACTICE_NAME) ?> &mdash; <?= $sigDate ?> &mdash; <?= $ptName ?>
</p>
<?php }
?>

<style>
  @page { size: letter; margin: 0.4in 0.5in; }
  .npp-doc { page-break-after: always; }
  .npp-doc:last-child { page-break-after: auto; }
  .npp-sub  { font-size:9pt; font-weight:bold; color:#1e1b4b; margin:5pt 0 2pt; }
  .npp-body { font-size:9pt; line-height:1.5; color:#1e293b; }
  .npp-lbl  { font-size:7.5pt; color:#64748b; font-weight:bold; text-transform:uppercase; letter-spacing:.04em; }
  .npp-fld  { font-size:9pt; border-bottom:.5pt solid #94a3b8; min-height:13pt; display:block; padding-bottom:1pt; }
  .npp-g2   { display:table; width:100%; table-layout:fixed; }
  .npp-g2 > div { display:table-cell; vertical-align:top; padding-right:10pt; }
  .npp-g2 > div:last-child { padding-right:0; }
  .npp-g4   { display:table; width:100%; table-layout:fixed; }
  .npp-g4 > div { display:table-cell; vertical-align:top; padding-right:8pt; }
  .npp-g4 > div:last-child { padding-right:0; }
</style>


<?php /* ============================================================ */
      /* FORM 1 &mdash; VISIT CONSENT / CS                                  */
      /* ============================================================ */ ?>
<div class="bwc-form npp-doc">
<?php nppFormHeader('Visit Consent / Clinical Summary (CS)', $ptName, $ptDob, $visitDate); ?>

<div class="npp-g2" style="margin-bottom:6pt;">
    <div><span class="npp-lbl">Provider</span><span class="npp-fld"><?= _npp($fd,'provider_name') ?></span></div>
    <div><span class="npp-lbl">Date of Visit</span><span class="npp-fld"><?= $visitDate ?></span></div>
</div>
<div class="npp-g4" style="margin-bottom:6pt;">
    <div><span class="npp-lbl">Visit Type</span><span class="npp-fld"><?= _npp($fd,'visit_type') ?></span></div>
    <div><span class="npp-lbl">Homebound</span><span class="npp-fld"><?= $fd['homebound'] === 'homebound' ? 'IS Homebound' : ($fd['homebound'] === 'not_homebound' ? 'NOT Homebound' : '&nbsp;') ?></span></div>
    <div><span class="npp-lbl">Time In</span><span class="npp-fld"><?= _npp($fd,'time_in') ?></span></div>
    <div><span class="npp-lbl">Time Out</span><span class="npp-fld"><?= _npp($fd,'time_out') ?></span></div>
</div>

<p class="npp-sub">Vital Signs</p>
<table style="width:100%;border-collapse:collapse;font-size:8.5pt;margin-bottom:6pt;">
    <thead><tr style="background:#e0e7ff;">
        <?php foreach (['BP','Pulse','Temp','O2Sat','Glucose','Height','Weight','Resp'] as $vh): ?>
        <th style="padding:2pt 4pt;text-align:center;border:.5pt solid #c7d2fe;font-size:7.5pt;color:#3730a3;"><?= $vh ?></th>
        <?php endforeach; ?>
    </tr></thead>
    <tbody><tr>
        <?php foreach (['bp','pulse','temp','o2sat','glucose','height','weight','resp'] as $vk): ?>
        <td style="padding:3pt 4pt;text-align:center;border:.5pt solid #e2e8f0;"><?= _npp($fd,$vk,'&mdash;') ?></td>
        <?php endforeach; ?>
    </tr></tbody>
</table>

<?php if (!empty($fd['chief_complaint'])): ?>
<span class="npp-lbl">Chief Complaint / Notes</span>
<p class="npp-body" style="margin:2pt 0 6pt;border:.5pt solid #e2e8f0;padding:3pt 5pt;background:#f8fafc;"><?= nl2br(h($fd['chief_complaint'])) ?></p>
<?php endif; ?>

<?php if (!empty($icdList)): ?>
<span class="npp-lbl">ICD-10 Codes</span>
<p class="npp-body" style="margin:2pt 0 6pt;"><?= implode(' &nbsp;&bull;&nbsp; ', array_map('h', $icdList)) ?></p>
<?php endif; ?>

<div class="npp-g2" style="margin-bottom:6pt;">
    <div><span class="npp-lbl">Pharmacy</span><span class="npp-fld"><?= _npp($fd,'pharmacy_name') ?></span></div>
    <div><span class="npp-lbl">Pharmacy Phone</span><span class="npp-fld"><?= _npp($fd,'pharmacy_phone') ?></span></div>
</div>
<div class="npp-g2" style="margin-bottom:6pt;">
    <div><span class="npp-lbl">Allergies</span><span class="npp-fld"><?= _npp($fd,'allergies') ?></span></div>
    <div><span class="npp-lbl">Race</span><span class="npp-fld"><?= $raceStr ?: '&nbsp;' ?></span></div>
</div>

<p class="npp-sub">Medication List</p>
<table style="width:100%;border-collapse:collapse;font-size:8.5pt;margin-bottom:6pt;">
    <thead><tr style="background:#e0e7ff;">
        <th style="padding:2pt 4pt;text-align:left;border:.5pt solid #c7d2fe;font-size:7.5pt;color:#3730a3;width:22%;">New / Refill</th>
        <th style="padding:2pt 4pt;text-align:left;border:.5pt solid #c7d2fe;font-size:7.5pt;color:#3730a3;">Medication &amp; Dose</th>
        <th style="padding:2pt 4pt;text-align:left;border:.5pt solid #c7d2fe;font-size:7.5pt;color:#3730a3;width:22%;">Frequency</th>
    </tr></thead>
    <tbody>
        <?php for ($i = 1; $i <= 6; $i++):
            $mname = $fd["med_name_$i"] ?? '';
            $mtype = $fd["med_type_$i"] ?? '';
            $mfreq = $fd["med_freq_$i"] ?? '';
            if (trim($mname) === '' && trim($mtype) === '') continue; ?>
        <tr style="<?= $i % 2 === 0 ? 'background:#f8fafc;' : '' ?>">
            <td style="padding:2pt 4pt;border:.5pt solid #e2e8f0;"><?= h($mtype) ?></td>
            <td style="padding:2pt 4pt;border:.5pt solid #e2e8f0;"><?= h($mname) ?></td>
            <td style="padding:2pt 4pt;border:.5pt solid #e2e8f0;"><?= h($mfreq) ?></td>
        </tr>
        <?php endfor; ?>
    </tbody>
</table>

<?php nppSigBlock($f, $fd, $sigDate); ?>
<?php nppFooter('Visit Consent / CS', $ptName, $sigDate); ?>
</div>


<?php /* ============================================================ */
      /* FORM 2 &mdash; CCM CONSENT                                         */
      /* ============================================================ */ ?>
<div class="bwc-form npp-doc">
<?php nppFormHeader('Chronic Care Management (CCM) Consent', $ptName, $ptDob, $visitDate); ?>

<p class="npp-body" style="margin-bottom:6pt;text-align:justify;">
    By signing this form, I consent to <strong><?= h(PRACTICE_NAME) ?></strong> providing Chronic Care Management (CCM) Services.
    CCM Services are available because I have been diagnosed with two (2) or more chronic conditions expected to last at least twelve (12) months.
    CCM Services include 24/7 access to a health care provider, systematic assessment of health care needs, medication reviews, a plan of care, and management of care transitions.
</p>

<p class="npp-body" style="font-weight:bold;margin-bottom:3pt;">Provider's Obligations</p>
<ul style="font-size:9pt;margin:0 0 6pt;padding-left:14pt;line-height:1.6;">
    <li>Explain and offer all CCM Services applicable to your conditions.</li>
    <li>Provide a written or electronic copy of your care plan.</li>
    <li>If you revoke this agreement, provide written confirmation stating the effective date.</li>
</ul>

<p class="npp-body" style="font-weight:bold;margin-bottom:4pt;">Beneficiary Acknowledgment &mdash; by signing I agree to all of the following:</p>
<table style="width:100%;border-collapse:collapse;font-size:8.5pt;margin-bottom:7pt;">
    <?php
    $ccmAcks = [
        'ccm_ack_consent'    => 'I consent to the Provider providing CCM Services to me.',
        'ccm_ack_electronic' => 'I authorize electronic communication of my medical information with other treating providers.',
        'ccm_ack_one_only'   => 'I acknowledge that only one practitioner can furnish CCM Services during a calendar month.',
        'ccm_ack_copay'      => 'I understand that cost-sharing will apply to CCM Services.',
    ];
    foreach ($ccmAcks as $key => $text): ?>
    <tr>
        <td style="padding:2pt 4pt;width:14pt;vertical-align:top;font-size:11pt;"><?= _nppChk(!empty($fd[$key])) ?></td>
        <td style="padding:2pt 0;font-size:8.5pt;"><?= h($text) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<div style="background:#f0fdf4;border:.5pt solid #bbf7d0;padding:5pt;font-size:8pt;color:#166534;margin-bottom:7pt;">
    <strong>Beneficiary Rights:</strong> You have the right to stop CCM Services at any time by revoking this Agreement, effective at the end of the then-current month.
    You may revoke verbally or in writing to <strong><?= h(PRACTICE_NAME) ?></strong>.
</div>

<div class="npp-g2" style="margin-bottom:6pt;">
    <div><span class="npp-lbl">Witness Name (Print)</span><span class="npp-fld"><?= _npp($fd,'ccm_witness_name') ?></span></div>
    <div><span class="npp-lbl">CCM Date</span><span class="npp-fld"><?= !empty($fd['ccm_date']) ? date('m/d/Y', strtotime($fd['ccm_date'])) : '&nbsp;' ?></span></div>
</div>

<?php nppSigBlock($f, $fd, $sigDate); ?>
<?php nppFooter('CCM Consent', $ptName, $sigDate); ?>
</div>


<?php /* ============================================================ */
      /* FORM 3 &mdash; ABN                                                 */
      /* ============================================================ */ ?>
<div class="bwc-form npp-doc">
<?php nppFormHeader('Advance Beneficiary Notice of Non-Coverage (ABN) &mdash; Form CMS-R-131', $ptName, $ptDob, $visitDate); ?>

<div class="npp-g2" style="margin-bottom:6pt;">
    <div><span class="npp-lbl">A. Notifier</span><span class="npp-fld"><?= _npp($fd,'notifier', h(PRACTICE_NAME)) ?></span></div>
    <div><span class="npp-lbl">B. Patient Name</span><span class="npp-fld"><?= $ptName ?></span></div>
</div>
<div class="npp-g2" style="margin-bottom:6pt;">
    <div><span class="npp-lbl">C. ID Number</span><span class="npp-fld"><?= _npp($fd,'id_number') ?></span></div>
    <div></div>
</div>

<div style="background:#fefce8;border:1.5pt solid #fde68a;padding:5pt;font-size:8.5pt;margin-bottom:7pt;">
    <strong>NOTE:</strong> If Medicare doesn't pay for the Home visit below, you may have to pay.
    Medicare does not pay for everything, even some care that you or your health care provider have good reason to think you need.
</div>

<table style="width:100%;border-collapse:collapse;font-size:8.5pt;margin-bottom:7pt;">
    <thead><tr style="background:#fef3c7;">
        <th style="padding:3pt 5pt;text-align:left;border:.5pt solid #fde68a;color:#92400e;">D. Service / Item</th>
        <th style="padding:3pt 5pt;text-align:left;border:.5pt solid #fde68a;color:#92400e;">E. Reason Medicare May Not Pay</th>
        <th style="padding:3pt 5pt;text-align:left;border:.5pt solid #fde68a;color:#92400e;width:15%;">F. Est. Cost</th>
    </tr></thead>
    <tbody><tr>
        <td style="padding:4pt 5pt;border:.5pt solid #e2e8f0;vertical-align:top;"><?= nl2br(h($fd['service_description'] ?? '')) ?></td>
        <td style="padding:4pt 5pt;border:.5pt solid #e2e8f0;vertical-align:top;"><?= nl2br(h($fd['reason_not_paid'] ?? '')) ?></td>
        <td style="padding:4pt 5pt;border:.5pt solid #e2e8f0;vertical-align:top;"><?= _npp($fd,'estimated_cost','20%') ?></td>
    </tr></tbody>
</table>

<p class="npp-body" style="font-weight:bold;margin-bottom:4pt;">G. OPTIONS &mdash; Check only one box.</p>
<?php
$abnOpts = [
    1 => 'OPTION 1. I want the service listed above. You may ask to be paid now, but I also want Medicare billed for an official decision on payment. I understand that if Medicare doesn\'t pay, I am responsible for payment, but I can appeal to Medicare.',
    2 => 'OPTION 2. I want the service listed above, but do not bill Medicare. You may ask to be paid now as I am responsible for payment. I cannot appeal if Medicare is not billed.',
    3 => 'OPTION 3. I don\'t want the service listed above. I understand with this choice I am not responsible for payment, and I cannot appeal to see if Medicare would pay.',
];
$selectedOpt = (int)($fd['patient_option'] ?? 0);
foreach ($abnOpts as $opt => $text): ?>
<p class="npp-body" style="margin:3pt 0;">
    <span style="font-size:11pt;margin-right:4pt;"><?= $selectedOpt === $opt ? '&#x25C9;' : '&#x25CB;' ?></span>
    <?= h($text) ?>
</p>
<?php endforeach; ?>

<?php if (!empty($fd['additional_info'])): ?>
<p class="npp-lbl" style="margin-top:6pt;">H. Additional Information</p>
<p class="npp-body" style="margin:2pt 0 6pt;"><?= nl2br(h($fd['additional_info'])) ?></p>
<?php endif; ?>

<?php nppSigBlock($f, $fd, $sigDate); ?>
<?php nppFooter('Advance Beneficiary Notice (ABN)', $ptName, $sigDate); ?>
</div>


<?php /* ============================================================ */
      /* FORM 4 &mdash; INFORMED CONSENT FOR WOUND CARE                     */
      /* ============================================================ */ ?>
<div class="bwc-form npp-doc">
<?php nppFormHeader('Informed Consent for Wound Care Treatment', $ptName, $ptDob, $visitDate); ?>

<div class="npp-g2" style="margin-bottom:6pt;">
    <div><span class="npp-lbl">Patient Name</span><span class="npp-fld"><?= _npp($fd,'wc_patient_name', $ptName) ?></span></div>
    <div><span class="npp-lbl">Date of Birth</span><span class="npp-fld"><?= _npp($fd,'wc_dob', $ptDob) ?></span></div>
</div>

<p class="npp-body" style="margin-bottom:6pt;text-align:justify;">
    Patient/caregiver hereby voluntarily consents to wound care treatment by the provider (MD/NP) of <strong>BEYOND WOUND CARE INC.</strong>
    Patient/Caregiver understands that this consent form will be valid and remain in effect as long as the patient remains active and receives services and treatments at BEYOND WOUND CARE INC.
    <strong>Patient has the right to give or refuse consent to any proposed service or treatment.</strong>
</p>

<table style="width:100%;border-collapse:collapse;font-size:8.5pt;margin-bottom:7pt;">
    <?php
    $wcItems = [
        ['General Description of Wound Care Treatment',
         'Treatment may include: debridement, dressing changes, skin grafts, off-loading devices, physical examinations, diagnostic procedures, laboratory work, imaging, and administration of medications prescribed by a physician or NP.'],
        ['Benefits of Wound Care Treatment',
         'Enhanced wound healing and reduced risks of amputation and infection.'],
        ['Risks and Side Effects of Wound Care Treatment',
         'May include: infection, pain, inflammation, bleeding, allergic reactions, removal of healthy tissue, delayed healing, possible scarring, and possible damage to blood vessels, surrounding tissues, and nerves.'],
        ['Likelihood of Achieving Goals',
         'The treatment plan is designed to optimize outcomes; however, any service or treatment carries the risk of unsuccessful results, complications, and injuries from both known and unforeseen causes.'],
        ['General Description of Wound Debridement',
         'Debridement means the removal of unhealthy tissue from a wound to promote healing. Multiple debridements may be necessary during the course of treatment.'],
        ['Risks/Side Effects of Wound Debridement',
         'Potential scarring, allergic reactions, excessive bleeding, removal of healthy tissue, infection, ongoing pain and inflammation, and failure to heal.'],
        ['Patient Identification and Wound Images',
         'Images may be taken to monitor wound progress and ensure continuity of care. Images are treated as PHI and handled per HIPAA.'],
        ['Use and Disclosure of PHI',
         'Patient consents to use of PHI for education and quality assessment in compliance with HIPAA.'],
        ['Financial Responsibility',
         'Regardless of insurance benefits, patient is responsible for any amount not covered by insurance.'],
    ];
    foreach ($wcItems as $idx => $item): ?>
    <tr>
        <td style="padding:2pt 5pt;width:16pt;vertical-align:top;font-weight:bold;color:#3730a3;"><?= $idx+1 ?>.</td>
        <td style="padding:2pt 0;vertical-align:top;"><strong><?= h($item[0]) ?>:</strong> <?= h($item[1]) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<p class="npp-body" style="text-align:justify;margin-bottom:6pt;">
    The patient/caregiver or POA hereby acknowledges that he or she has read and agrees to the contents of sections 1 through 9 above. By signing, patient consents to the care, treatment and services described herein.
</p>

<?php nppSigBlock($f, $fd, $sigDate); ?>
<?php nppFooter('Informed Consent for Wound Care Treatment', $ptName, $sigDate); ?>
</div>


<?php /* ============================================================ */
      /* FORM 5 &mdash; PHI AUTHORIZATION (IL DHS)                          */
      /* ============================================================ */ ?>
<div class="bwc-form npp-doc">
<?php nppFormHeader('Authorization to Disclose / Obtain PHI &mdash; Illinois DHS', $ptName, $ptDob, $visitDate); ?>

<p class="npp-body" style="margin-bottom:5pt;">
    I authorize <strong><?= h(PRACTICE_NAME) ?></strong> to
    <strong><?= isset($fd['auth_type']) ? h(ucwords(str_replace('_', ' ', $fd['auth_type']))) : '_______________' ?></strong>
    the following types of records:
</p>

<?php if (!empty($selectedRecords)): ?>
<p class="npp-body" style="margin-bottom:5pt;"><?= implode(' &bull; ', array_map(fn($k) => h($recordTypeLabels[$k] ?? $k), $selectedRecords)) ?></p>
<?php else: ?>
<p class="npp-body" style="border:.5pt solid #e2e8f0;padding:3pt 5pt;min-height:14pt;margin-bottom:5pt;">&nbsp;</p>
<?php endif; ?>

<div class="npp-g4" style="margin-bottom:6pt;">
    <div><span class="npp-lbl">Patient Name</span><span class="npp-fld"><?= _npp($fd,'patient_name', $ptName) ?></span></div>
    <div><span class="npp-lbl">Date of Birth</span><span class="npp-fld"><?= _npp($fd,'patient_dob', $ptDob) ?></span></div>
    <div><span class="npp-lbl">SSN (last 4)</span><span class="npp-fld"><?= _npp($fd,'patient_ssn','XXXX') ?></span></div>
    <div><span class="npp-lbl">Expiration</span><span class="npp-fld"><?= !empty($fd['expiration_date']) ? date('m/d/Y', strtotime($fd['expiration_date'])) : '1 year from date signed' ?></span></div>
</div>

<?php if (!empty($selectedPurposes)): ?>
<p class="npp-body" style="margin-bottom:5pt;"><strong>Purpose:</strong> <?= implode(' &bull; ', array_map(fn($k) => h($purposeLabels[$k] ?? $k), $selectedPurposes)) ?></p>
<?php endif; ?>

<?php for ($i = 1; $i <= 2; $i++):
    $rname = trim($fd["recipient_name_$i"] ?? '');
    $raddr = trim($fd["recipient_address_$i"] ?? '');
    if ($rname === '' && $raddr === '') continue; ?>
<p class="npp-body" style="margin-bottom:3pt;"><strong>Recipient <?= $i ?>:</strong> <?= h($rname) ?><?= $raddr ? ' &mdash; ' . h($raddr) : '' ?></p>
<?php endfor; ?>

<div style="background:#f8fafc;border:.5pt solid #e2e8f0;padding:5pt;font-size:7.5pt;color:#64748b;line-height:1.5;margin-top:5pt;margin-bottom:6pt;">
    <strong style="color:#475569;">Right to Revoke:</strong> You may revoke this authorization in writing at any time to <?= h(PRACTICE_NAME) ?>.
    &nbsp;<strong style="color:#475569;">Consequences:</strong> Treatment may not be conditioned on signing this authorization.
    &nbsp;<strong style="color:#475569;">Re-disclosure:</strong> Information disclosed may be subject to re-disclosure by the recipient unless prohibited by law.
</div>

<?php nppSigBlock($f, $fd, $sigDate); ?>
<?php nppFooter('PHI Authorization (IL DHS)', $ptName, $sigDate); ?>
</div>


<?php /* ============================================================ */
      /* FORM 6 &mdash; PATIENT FUSION PORTAL CONSENT                       */
      /* ============================================================ */ ?>
<div class="bwc-form npp-doc">
<?php nppFormHeader('Patient Fusion Portal &mdash; Consent Form', $ptName, $ptDob, $visitDate); ?>

<p class="npp-body" style="margin-bottom:6pt;text-align:justify;">
    I acknowledge that I have read and fully understand this consent form. I have been given the risks and benefits of Patient Fusion and understand the risks associated with online communications between our office and patients. By signing this form and providing an e-mail address, I hereby give my informed consent to participate in Patient Fusion. By declining and not providing an email, my signature indicates that I am informed about Patient Fusion being offered to me, but I do not wish to participate.
</p>

<p class="npp-body" style="font-weight:bold;margin-bottom:3pt;">Benefits of the Patient Portal</p>
<ul style="font-size:9pt;margin:0 0 6pt;padding-left:14pt;line-height:1.6;">
    <li>View your health records, lab results, and visit summaries</li>
    <li>Request prescription refills and appointment scheduling</li>
    <li>Communicate securely with your care team</li>
    <li>Access your care plan and educational materials</li>
</ul>

<p class="npp-body" style="font-weight:bold;margin-bottom:3pt;">Risks of Online Communications</p>
<ul style="font-size:9pt;margin:0 0 6pt;padding-left:14pt;line-height:1.6;">
    <li>Email / portal messages are not appropriate for urgent or emergency situations</li>
    <li>Security breaches, though unlikely, may occur despite reasonable safeguards</li>
    <li>Messages may not be read immediately if the office is closed</li>
</ul>

<?php $pfDecision = $fd['pf_decision'] ?? ''; ?>
<p class="npp-body" style="margin-bottom:5pt;">
    <span style="font-size:11pt;margin-right:4pt;"><?= $pfDecision === 'participate' ? '&#x25C9;' : '&#x25CB;' ?></span>
    <strong>I consent to participate in Patient Fusion</strong>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <span style="font-size:11pt;margin-right:4pt;"><?= $pfDecision === 'decline' ? '&#x25C9;' : '&#x25CB;' ?></span>
    <strong>I decline to participate</strong>
</p>

<?php if (!empty($fd['patient_email'])): ?>
<p class="npp-body" style="margin-bottom:5pt;"><strong>Email Address:</strong> <?= h($fd['patient_email']) ?></p>
<?php else: ?>
<p class="npp-body" style="margin-bottom:5pt;"><strong>Email Address:</strong> <span style="display:inline-block;min-width:200pt;border-bottom:1px solid #999;">&nbsp;</span></p>
<?php endif; ?>

<?php nppSigBlock($f, $fd, $sigDate); ?>
<?php nppFooter('Patient Fusion Portal Consent', $ptName, $sigDate); ?>
</div>
