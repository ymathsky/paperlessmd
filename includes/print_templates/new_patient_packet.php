<?php
/**
 * Print Template: New Patient Packet
 * Combined: CS · CCM · ABN · Wound Care Consent · PHI Authorization · Patient Fusion
 */
$ptName  = h($patient['first_name'] . ' ' . $patient['last_name']);
$sigDate = date('m/d/Y', strtotime($f['created_at']));
$fd      = is_string($f['form_data']) ? (json_decode($f['form_data'], true) ?? []) : ($f['form_data'] ?? []);

function _npp(array $fd, string $k, string $fb = '&nbsp;'): string {
    $v = $fd[$k] ?? '';
    return trim($v) !== '' ? h($v) : $fb;
}
function _nppLine(array $fd, string $k): string {
    return '<span style="display:inline-block;min-width:150pt;border-bottom:1px solid #999;vertical-align:bottom;padding-bottom:1pt;">'
        . _npp($fd,$k,'') . '</span>';
}

$visitDate = $fd['form_date'] ? date('m/d/Y', strtotime($fd['form_date'])) : $sigDate;
$icdList   = $fd['icd10_codes'] ?? [];
if (!is_array($icdList)) $icdList = [];
$raceList  = $fd['race'] ?? [];
if (!is_array($raceList)) $raceList = explode(',', (string)$raceList);
$raceStr   = implode(', ', array_map('h', array_filter($raceList)));
$recordTypeLabels = [
    'all'=>'Complete/All','discharge_summary'=>'Discharge Summary','inpatient'=>'Inpatient',
    'outpatient'=>'Outpatient','psychiatric'=>'Psychiatric','psych_eval'=>'Psych Evaluation',
    'mental_health'=>'Mental Health','alcohol_substance'=>'Alcohol & Substance','hiv_aids'=>'HIV/AIDS',
    'genetic'=>'Genetic Information','lab'=>'Lab/Pathology','xray'=>'Radiology','other'=>'Other',
];
$selectedRecords = $fd['record_types'] ?? [];
if (!is_array($selectedRecords)) $selectedRecords = [];
$selectedPurposes = $fd['purposes'] ?? [];
if (!is_array($selectedPurposes)) $selectedPurposes = [];
$purposeLabels = [
    'personal_use'=>'Personal Use','continuity_care'=>'Continuity of Care',
    'placement_transfer'=>'Placement/Transfer','legal'=>'Legal/Judicial',
    'insurance'=>'Insurance/Benefits','research'=>'Research','other'=>'Other',
];
?>
<style>
  @page { size: letter; margin: 0.4in 0.5in; }
  .npp-section-break { page-break-before: always; }
  .npp-divider { border:0; border-top:2pt solid #3730a3; margin:10pt 0 8pt; }
  .npp-sub-divider { border:0; border-top:0.5pt solid #c7d2fe; margin:7pt 0 5pt; }
  .npp-section-title { font-size:11pt; font-weight:bold; color:#3730a3; text-transform:uppercase;
                        letter-spacing:0.05em; border-bottom:1.5pt solid #3730a3; padding-bottom:3pt; margin:0 0 8pt; }
  .npp-sub-title { font-size:9.5pt; font-weight:bold; color:#1e1b4b; margin:6pt 0 3pt; }
  .npp-body { font-size:9pt; line-height:1.5; color:#1e293b; }
  .npp-label { font-size:7.5pt; color:#64748b; font-weight:bold; text-transform:uppercase; letter-spacing:0.05em; }
  .npp-field { font-size:9pt; border-bottom:0.5pt solid #94a3b8; min-height:14pt; padding-bottom:1pt; display:block; }
  .npp-grid2 { display:table; width:100%; table-layout:fixed; }
  .npp-grid2 > div { display:table-cell; vertical-align:top; padding-right:12pt; }
  .npp-grid2 > div:last-child { padding-right:0; }
  .npp-grid4 { display:table; width:100%; table-layout:fixed; }
  .npp-grid4 > div { display:table-cell; vertical-align:top; padding-right:8pt; }
  .npp-grid4 > div:last-child { padding-right:0; }
</style>

<div class="bwc-form">

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- HEADER                                                  -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <div class="bwc-header">
        <img src="<?= BASE_URL ?>/assets/img/logo.png" class="bwc-header-logo" alt="Beyond Wound Care Inc.">
        <div class="bwc-header-text">
            <p class="bwc-practice-name">Beyond Wound Care Inc.</p>
            <p>1340 Remington RD, Ste P &nbsp; Schaumburg, IL 60173</p>
            <p>Phone: 847.873.8693 &nbsp;&nbsp; Fax: 847.873.8486</p>
            <p>Email: Support@beyondwoundcare.com</p>
        </div>
    </div>

    <p style="text-align:center;font-size:13pt;font-weight:bold;color:#3730a3;margin:6pt 0 2pt;letter-spacing:0.08em;">
        NEW PATIENT PACKET
    </p>
    <p style="text-align:center;font-size:9pt;color:#64748b;margin:0 0 8pt;">
        <?= h($ptName) ?> &nbsp;&bull;&nbsp; DOB: <?= $patient['dob'] ? date('m/d/Y', strtotime($patient['dob'])) : '&nbsp;' ?> &nbsp;&bull;&nbsp; Date: <?= $visitDate ?>
    </p>

    <hr class="npp-divider">


    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- SECTION 1 — VISIT CONSENT (CS)                         -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <p class="npp-section-title">Section 1 &mdash; Visit Consent (CS)</p>

    <div class="npp-grid2" style="margin-bottom:7pt;">
        <div>
            <span class="npp-label">Provider</span>
            <span class="npp-field"><?= _npp($fd,'provider_name') ?></span>
        </div>
        <div>
            <span class="npp-label">Date of Visit</span>
            <span class="npp-field"><?= $visitDate ?></span>
        </div>
    </div>

    <div class="npp-grid4" style="margin-bottom:7pt;">
        <div>
            <span class="npp-label">Visit Type</span>
            <span class="npp-field"><?= _npp($fd,'visit_type') ?></span>
        </div>
        <div>
            <span class="npp-label">Homebound</span>
            <span class="npp-field"><?= $fd['homebound'] === 'homebound' ? 'IS Homebound' : ($fd['homebound'] === 'not_homebound' ? 'NOT Homebound' : '&nbsp;') ?></span>
        </div>
        <div>
            <span class="npp-label">Time In</span>
            <span class="npp-field"><?= _npp($fd,'time_in') ?></span>
        </div>
        <div>
            <span class="npp-label">Time Out</span>
            <span class="npp-field"><?= _npp($fd,'time_out') ?></span>
        </div>
    </div>

    <!-- Vitals table -->
    <span class="npp-sub-title">Vital Signs</span>
    <table style="width:100%;border-collapse:collapse;font-size:8.5pt;margin-bottom:7pt;">
        <thead>
            <tr style="background:#e0e7ff;">
                <?php foreach (['BP','Pulse','Temp','O2Sat','Glucose','Height','Weight','Resp'] as $vh): ?>
                <th style="padding:3pt 5pt;text-align:center;border:0.5pt solid #c7d2fe;font-size:7.5pt;color:#3730a3;"><?= $vh ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <?php foreach (['bp','pulse','temp','o2sat','glucose','height','weight','resp'] as $vk): ?>
                <td style="padding:4pt 5pt;text-align:center;border:0.5pt solid #e2e8f0;"><?= _npp($fd,$vk,'—') ?></td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>

    <?php if (!empty($fd['chief_complaint'])): ?>
    <span class="npp-label">Chief Complaint / Notes</span>
    <p class="npp-body" style="margin:2pt 0 7pt;border:0.5pt solid #e2e8f0;padding:4pt 6pt;border-radius:3pt;background:#f8fafc;"><?= nl2br(h($fd['chief_complaint'])) ?></p>
    <?php endif; ?>

    <?php if (!empty($icdList)): ?>
    <span class="npp-label">ICD-10 Codes</span>
    <p class="npp-body" style="margin:2pt 0 7pt;"><?= implode(' &nbsp; ', array_map('h', $icdList)) ?></p>
    <?php endif; ?>

    <div class="npp-grid2" style="margin-bottom:7pt;">
        <div>
            <span class="npp-label">Pharmacy</span>
            <span class="npp-field"><?= _npp($fd,'pharmacy_name') ?></span>
        </div>
        <div>
            <span class="npp-label">Pharmacy Phone</span>
            <span class="npp-field"><?= _npp($fd,'pharmacy_phone') ?></span>
        </div>
    </div>

    <div class="npp-grid2" style="margin-bottom:7pt;">
        <div>
            <span class="npp-label">Allergies</span>
            <span class="npp-field"><?= _npp($fd,'allergies') ?></span>
        </div>
        <div>
            <span class="npp-label">Race</span>
            <span class="npp-field"><?= $raceStr ?: '&nbsp;' ?></span>
        </div>
    </div>

    <!-- Medications table -->
    <span class="npp-sub-title">Medication List</span>
    <table style="width:100%;border-collapse:collapse;font-size:8.5pt;margin-bottom:8pt;">
        <thead>
            <tr style="background:#e0e7ff;">
                <th style="padding:3pt 5pt;text-align:left;border:0.5pt solid #c7d2fe;font-size:7.5pt;color:#3730a3;width:22%">New / Refill</th>
                <th style="padding:3pt 5pt;text-align:left;border:0.5pt solid #c7d2fe;font-size:7.5pt;color:#3730a3;">Medication &amp; Dose</th>
                <th style="padding:3pt 5pt;text-align:left;border:0.5pt solid #c7d2fe;font-size:7.5pt;color:#3730a3;width:22%">Frequency</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 1; $i <= 6; $i++):
                $mtype = $fd["med_type_$i"] ?? '';
                $mname = $fd["med_name_$i"] ?? '';
                $mfreq = $fd["med_freq_$i"] ?? '';
                if (trim($mname) === '' && trim($mtype) === '') continue;
            ?>
            <tr style="<?= $i % 2 === 0 ? 'background:#f8fafc;' : '' ?>">
                <td style="padding:3pt 5pt;border:0.5pt solid #e2e8f0;"><?= h($mtype) ?></td>
                <td style="padding:3pt 5pt;border:0.5pt solid #e2e8f0;"><?= h($mname) ?></td>
                <td style="padding:3pt 5pt;border:0.5pt solid #e2e8f0;"><?= h($mfreq) ?></td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>


    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- SECTION 2 — CCM CONSENT                                -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <hr class="npp-divider">
    <p class="npp-section-title">Section 2 &mdash; Chronic Care Management (CCM) Consent</p>

    <p class="npp-body" style="margin-bottom:7pt;text-align:justify;">
        By signing this packet, I consent to <strong><?= h(PRACTICE_NAME) ?></strong> providing Chronic Care Management (CCM) Services.
        CCM Services are available because I have been diagnosed with two (2) or more chronic conditions expected to last at least twelve (12) months.
        CCM Services include 24/7 access to a health care provider, systematic assessment of health care needs, medication reviews, a plan of care, and management of care transitions.
    </p>

    <p class="npp-body" style="margin-bottom:5pt;"><strong>By signing, I acknowledge and agree to the following:</strong></p>
    <table style="width:100%;border-collapse:collapse;font-size:8.5pt;margin-bottom:7pt;">
        <?php
        $ccmAcks = [
            'ccm_ack_consent'    => 'I consent to the Provider providing CCM Services to me.',
            'ccm_ack_electronic' => 'I authorize electronic communication of my medical information with other treating providers.',
            'ccm_ack_one_only'   => 'I acknowledge that only one practitioner can furnish CCM Services during a calendar month.',
            'ccm_ack_copay'      => 'I understand that cost-sharing will apply to CCM Services.',
        ];
        foreach ($ccmAcks as $key => $text):
            $checked = !empty($fd[$key]);
        ?>
        <tr>
            <td style="padding:2pt 5pt;width:14pt;vertical-align:top;font-size:10pt;"><?= $checked ? '&#x2611;' : '&#x2610;' ?></td>
            <td style="padding:2pt 0;font-size:8.5pt;color:#1e293b;"><?= h($text) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="npp-grid2" style="margin-bottom:7pt;">
        <div>
            <span class="npp-label">Witness Name</span>
            <span class="npp-field"><?= _npp($fd,'ccm_witness_name') ?></span>
        </div>
        <div>
            <span class="npp-label">CCM Date</span>
            <span class="npp-field"><?= $fd['ccm_date'] ? date('m/d/Y', strtotime($fd['ccm_date'])) : '&nbsp;' ?></span>
        </div>
    </div>


    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- SECTION 3 — ABN                                        -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <hr class="npp-divider">
    <p class="npp-section-title">Section 3 &mdash; Advance Beneficiary Notice of Non-Coverage (ABN) &mdash; Form CMS-R-131</p>

    <p class="npp-body" style="margin-bottom:5pt;">
        <strong>NOTE:</strong> If Medicare doesn't pay for the Home visit below, you may have to pay.
        Medicare does not pay for everything, even some care that you or your health care provider have good reason to think you need.
    </p>

    <table style="width:100%;border-collapse:collapse;font-size:8.5pt;margin-bottom:7pt;">
        <thead>
            <tr style="background:#fef3c7;">
                <th style="padding:3pt 6pt;text-align:left;border:0.5pt solid #fde68a;color:#92400e;font-size:7.5pt;">D. Service / Item</th>
                <th style="padding:3pt 6pt;text-align:left;border:0.5pt solid #fde68a;color:#92400e;font-size:7.5pt;">E. Reason Medicare May Not Pay</th>
                <th style="padding:3pt 6pt;text-align:left;border:0.5pt solid #fde68a;color:#92400e;font-size:7.5pt;width:15%;">F. Est. Cost</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding:4pt 6pt;border:0.5pt solid #e2e8f0;vertical-align:top;"><?= nl2br(h($fd['service_description'] ?? '')) ?></td>
                <td style="padding:4pt 6pt;border:0.5pt solid #e2e8f0;vertical-align:top;"><?= nl2br(h($fd['reason_not_paid'] ?? '')) ?></td>
                <td style="padding:4pt 6pt;border:0.5pt solid #e2e8f0;vertical-align:top;"><?= _npp($fd,'estimated_cost','20%') ?></td>
            </tr>
        </tbody>
    </table>

    <p class="npp-body" style="margin-bottom:4pt;"><strong>G. OPTION selected:</strong></p>
    <?php
    $optTexts = [
        1 => 'Option 1 — I want the service listed above. Bill Medicare; I may be responsible for payment if Medicare doesn\'t pay; I can appeal.',
        2 => 'Option 2 — I want the service, but do not bill Medicare. I am responsible for payment.',
        3 => 'Option 3 — I do not want the service listed above.',
    ];
    $selectedOpt = (int)($fd['patient_option'] ?? 0);
    foreach ($optTexts as $opt => $text): ?>
    <p class="npp-body" style="margin:2pt 0;">
        <span style="font-size:11pt;margin-right:3pt;"><?= $selectedOpt === $opt ? '&#x25C9;' : '&#x25CB;' ?></span>
        <span><?= h($text) ?></span>
    </p>
    <?php endforeach; ?>


    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- SECTION 4 — WOUND CARE CONSENT                         -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <hr class="npp-divider npp-section-break">
    <p class="npp-section-title">Section 4 &mdash; Informed Consent for Wound Care Treatment</p>

    <div class="npp-grid2" style="margin-bottom:7pt;">
        <div>
            <span class="npp-label">Patient Name</span>
            <span class="npp-field"><?= _npp($fd,'wc_patient_name', h($ptName)) ?></span>
        </div>
        <div>
            <span class="npp-label">Date of Birth</span>
            <span class="npp-field"><?= _npp($fd,'wc_dob') ?></span>
        </div>
    </div>

    <p class="npp-body" style="margin-bottom:6pt;text-align:justify;">
        Patient/caregiver hereby voluntarily consents to wound care treatment by the provider (MD/NP) of <strong>BEYOND WOUND CARE INC.</strong>
        This consent is valid and remains in effect as long as the patient remains active and receives services at BEYOND WOUND CARE INC.
        <strong>Patient has the right to give or refuse consent to any proposed service or treatment.</strong>
    </p>

    <table style="width:100%;border-collapse:collapse;font-size:8.5pt;margin-bottom:7pt;">
        <?php
        $wcSections = [
            'General Description of Wound Care Treatment' => 'Treatment may include: debridement, dressing changes, skin grafts, off-loading devices, diagnostic procedures, laboratory work, imaging, and administration of medications prescribed by a physician or NP.',
            'Benefits of Wound Care Treatment'           => 'Enhanced wound healing and reduced risks of amputation and infection.',
            'Risks and Side Effects'                     => 'May include: infection, pain, inflammation, bleeding, allergic reactions, removal of healthy tissue, delayed healing, possible scarring, and possible damage to blood vessels, surrounding tissues, and nerves.',
            'Likelihood of Achieving Goals'              => 'The treatment plan is designed to optimize outcomes; however, any service or treatment carries the risk of unsuccessful results, complications, and injuries from both known and unforeseen causes.',
            'Wound Debridement'                          => 'Debridement means the removal of unhealthy tissue from a wound to promote healing. Multiple debridements may be necessary during the course of treatment.',
            'Patient Identification and Wound Images'    => 'Images may be taken for monitoring wound progress and continuity of care. Images are treated as protected health information (PHI) per HIPAA.',
            'Use and Disclosure of PHI'                  => 'Patient consents to use of PHI for education and quality assessment, in compliance with HIPAA and applicable federal privacy regulations.',
            'Financial Responsibility'                   => 'Regardless of insurance benefits, patient is responsible for any amount not covered by insurance.',
        ];
        $wcNum = 1;
        foreach ($wcSections as $title => $body): ?>
        <tr>
            <td style="padding:3pt 5pt;vertical-align:top;width:18pt;font-size:8.5pt;font-weight:bold;color:#3730a3;"><?= $wcNum++ ?>.</td>
            <td style="padding:3pt 0;vertical-align:top;">
                <strong><?= h($title) ?>:</strong> <?= h($body) ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <p class="npp-body" style="text-align:justify;margin-bottom:8pt;">
        The patient/caregiver or POA hereby acknowledges that he or she has read and agrees to the contents of sections 1 through 8 above. Patient understands the nature of his or her medical condition, the risks, alternatives and benefits of treatment, and the consequences of failure to seek or delay treatment. By signing this packet, patient consents to the care, treatment and services described herein.
    </p>


    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- SECTION 5 — PHI AUTHORIZATION (IL DHS)                 -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <hr class="npp-divider">
    <p class="npp-section-title">Section 5 &mdash; Authorization to Disclose / Obtain PHI (IL DHS)</p>

    <p class="npp-body" style="margin-bottom:5pt;">
        I authorize <strong><?= h(PRACTICE_NAME) ?></strong> to:
        <strong><?= isset($fd['auth_type']) ? h(ucwords(str_replace('_',' ',$fd['auth_type']))) : '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' ?></strong>
        the following types of records:
    </p>

    <?php if (!empty($selectedRecords)): ?>
    <p class="npp-body" style="margin-bottom:5pt;">
        <?= implode(' &nbsp;&bull;&nbsp; ', array_map(function($k) use ($recordTypeLabels) {
            return h($recordTypeLabels[$k] ?? $k);
        }, $selectedRecords)) ?>
    </p>
    <?php endif; ?>

    <div class="npp-grid4" style="margin-bottom:6pt;">
        <div>
            <span class="npp-label">Patient Name</span>
            <span class="npp-field"><?= _npp($fd,'patient_name', h($ptName)) ?></span>
        </div>
        <div>
            <span class="npp-label">Date of Birth</span>
            <span class="npp-field"><?= _npp($fd,'patient_dob') ?></span>
        </div>
        <div>
            <span class="npp-label">SSN (last 4)</span>
            <span class="npp-field"><?= _npp($fd,'patient_ssn','XXXX') ?></span>
        </div>
        <div>
            <span class="npp-label">Expiration</span>
            <span class="npp-field"><?= $fd['expiration_date'] ? date('m/d/Y', strtotime($fd['expiration_date'])) : '1 year from date signed' ?></span>
        </div>
    </div>

    <?php if (!empty($selectedPurposes)): ?>
    <p class="npp-body" style="margin-bottom:5pt;">
        <strong>Purpose:</strong>
        <?= implode(' &nbsp;&bull;&nbsp; ', array_map(function($k) use ($purposeLabels) {
            return h($purposeLabels[$k] ?? $k);
        }, $selectedPurposes)) ?>
    </p>
    <?php endif; ?>

    <?php for ($i = 1; $i <= 2; $i++):
        $rname = trim($fd["recipient_name_$i"] ?? '');
        $raddr = trim($fd["recipient_address_$i"] ?? '');
        if ($rname === '' && $raddr === '') continue; ?>
    <p class="npp-body" style="margin-bottom:3pt;">
        <strong>Recipient <?= $i ?>:</strong> <?= h($rname) ?><?= $raddr ? ' &mdash; ' . h($raddr) : '' ?>
    </p>
    <?php endfor; ?>

    <div style="margin-top:5pt;background:#f8fafc;border:0.5pt solid #e2e8f0;padding:5pt;font-size:7.5pt;color:#64748b;line-height:1.45;border-radius:3pt;">
        <strong style="color:#475569;">Right to Revoke:</strong> You may revoke this authorization in writing at any time to <?= h(PRACTICE_NAME) ?>.
        <strong style="color:#475569;margin-left:6pt;">Consequences:</strong> Your treatment may not be conditioned on signing this authorization.
        <strong style="color:#475569;margin-left:6pt;">Re-disclosure:</strong> Information disclosed may be subject to re-disclosure by the recipient unless otherwise prohibited by law.
    </div>


    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- SECTION 6 — PATIENT FUSION PORTAL                      -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <hr class="npp-divider">
    <p class="npp-section-title">Section 6 &mdash; Patient Fusion Portal Consent</p>

    <p class="npp-body" style="margin-bottom:5pt;text-align:justify;">
        I acknowledge that I have read and fully understand this consent form. I have been given the risks and benefits of Patient Fusion and understand the risks associated with online communications between our office and patients. By signing this packet and providing an e-mail address, I hereby give my informed consent to participate in Patient Fusion. By declining and not providing an email, my signature indicates that I am informed about Patient Fusion being offered to me, but I do not wish to participate.
    </p>

    <?php $pfDecision = $fd['pf_decision'] ?? ''; ?>
    <p class="npp-body" style="margin-bottom:5pt;">
        <span style="font-size:11pt;margin-right:4pt;"><?= $pfDecision === 'participate' ? '&#x25C9;' : '&#x25CB;' ?></span> <strong>Participate in Patient Fusion</strong>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <span style="font-size:11pt;margin-right:4pt;"><?= $pfDecision === 'decline' ? '&#x25C9;' : '&#x25CB;' ?></span> <strong>Decline</strong>
    </p>

    <?php if (!empty($fd['patient_email'])): ?>
    <p class="npp-body" style="margin-bottom:5pt;">
        <strong>Email:</strong> <?= h($fd['patient_email']) ?>
    </p>
    <?php endif; ?>


    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- COMBINED SIGNATURE PAGE                                 -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <hr class="npp-divider npp-section-break" style="page-break-before:always;">
    <p class="npp-section-title">Signatures &mdash; New Patient Packet</p>

    <p class="npp-body" style="margin-bottom:10pt;text-align:justify;">
        By signing below, <strong><?= h($ptName) ?></strong> (or authorized representative) acknowledges having read, understood, and agreed to all sections of this New Patient Packet, including:
        (1) Visit Consent / CS, (2) CCM Consent, (3) Advance Beneficiary Notice, (4) Informed Consent for Wound Care Treatment, (5) Authorization to Disclose / Obtain PHI, and (6) Patient Fusion Portal Consent.
        The patient has had the opportunity to ask questions and has received answers to all questions. The attending provider confirms medical supervision, authorization of the care plan, and accuracy of the clinical information recorded herein.
    </p>

    <!-- Patient Signature -->
    <table style="width:100%;border-collapse:collapse;font-size:9.5pt;margin-bottom:10pt;" class="bwc-sigs">
        <tr>
            <td style="width:65%;padding-right:20pt;vertical-align:bottom;">
                <div style="position:relative;min-height:38pt;border-bottom:1px solid #000;">
                    <?php if (!empty($f['patient_signature'])): ?>
                    <img src="<?= h($f['patient_signature']) ?>" class="bwc-sig-img" alt="Patient Signature">
                    <?php endif; ?>
                </div>
                <div class="bwc-sig-label">
                    Signature of Patient or Authorized Representative
                    <?php if (!empty($f['poa_name'])): ?>
                    <br><span style="font-size:8pt;color:#666;">POA: <?= h($f['poa_name']) ?><?= !empty($f['poa_relationship']) ? ' (' . h($f['poa_relationship']) . ')' : '' ?></span>
                    <?php endif; ?>
                </div>
            </td>
            <td style="width:35%;vertical-align:bottom;">
                <div style="min-height:38pt;border-bottom:1px solid #000;padding-bottom:2pt;">
                    <span style="font-size:10pt;"><?= $sigDate ?></span>
                </div>
                <div class="bwc-sig-label">Date</div>
            </td>
        </tr>
    </table>

    <!-- MA Signature -->
    <table style="width:100%;border-collapse:collapse;font-size:9.5pt;margin-bottom:10pt;" class="bwc-sigs">
        <tr>
            <td style="width:45%;padding-right:20pt;vertical-align:bottom;">
                <div style="position:relative;min-height:38pt;border-bottom:1px solid #000;">
                    <?php if (!empty($f['ma_signature'])): ?>
                    <img src="<?= h($f['ma_signature']) ?>" class="bwc-sig-img" alt="MA Signature">
                    <?php endif; ?>
                </div>
                <div class="bwc-sig-label">Medical Assistant Signature</div>
            </td>
            <td style="width:30%;padding-right:20pt;vertical-align:bottom;">
                <div style="min-height:38pt;border-bottom:1px solid #000;padding-bottom:2pt;padding-top:16pt;">
                    <?= h($fd['ma_name'] ?? '') ?>
                </div>
                <div class="bwc-sig-label">MA Name (Print)</div>
            </td>
            <td style="width:25%;vertical-align:bottom;">
                <div style="min-height:38pt;border-bottom:1px solid #000;padding-bottom:2pt;">
                    <span style="font-size:10pt;"><?= $sigDate ?></span>
                </div>
                <div class="bwc-sig-label">Date</div>
            </td>
        </tr>
    </table>

    <!-- Provider Signature -->
    <table style="width:100%;border-collapse:collapse;font-size:9.5pt;margin-bottom:10pt;" class="bwc-sigs">
        <tr>
            <td style="width:45%;padding-right:20pt;vertical-align:bottom;">
                <div style="position:relative;min-height:38pt;border-bottom:1px solid #000;">
                    <?php if (!empty($fd['provider_signature'])): ?>
                    <img src="<?= h($fd['provider_signature']) ?>" class="bwc-sig-img" alt="Provider Signature">
                    <?php endif; ?>
                </div>
                <div class="bwc-sig-label">Provider / Physician Signature</div>
            </td>
            <td style="width:30%;padding-right:20pt;vertical-align:bottom;">
                <div style="min-height:38pt;border-bottom:1px solid #000;padding-bottom:2pt;padding-top:16pt;">
                    <?= h($fd['provider_print_name'] ?? $fd['provider_name'] ?? '') ?>
                </div>
                <div class="bwc-sig-label">Provider Name (Print)</div>
            </td>
            <td style="width:25%;vertical-align:bottom;">
                <div style="min-height:38pt;border-bottom:1px solid #000;padding-bottom:2pt;">
                    <?php if (!empty($fd['provider_npi'])): ?>
                    <span style="font-size:8.5pt;color:#475569;">NPI: <?= h($fd['provider_npi']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="bwc-sig-label">Provider NPI</div>
            </td>
        </tr>
    </table>

    <!-- Footer note -->
    <p style="font-size:7pt;color:#94a3b8;text-align:center;margin-top:10pt;border-top:0.5pt solid #e2e8f0;padding-top:4pt;">
        New Patient Packet &mdash; <?= h(PRACTICE_NAME) ?> &mdash; Generated <?= $sigDate ?> &mdash; Patient: <?= h($ptName) ?>
    </p>

</div><!-- /.bwc-form -->
