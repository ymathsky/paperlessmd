<?php
/**
 * Print Template: Cognitive Wellness Exam
 * Mirrors the exact paper form layout (Step 1 / Step 2 / Step 3 MIS + GDS)
 */
$ptName   = h($patient['first_name'] . ' ' . $patient['last_name']);
$ptDob    = $patient['dob'] ? date('m/d/Y', strtotime($patient['dob'])) : '';
$sigDate  = date('m/d/Y', strtotime($f['created_at']));
$fmDate   = vd($data, 'form_date') ? date('m/d/Y', strtotime(vd($data,'form_date'))) : $sigDate;
$assessedBy = vd($data, 'assessed_by');

$step1Score = (int)vd($data, 'step1_score');
$step2Score = (int)vd($data, 'step2_score');
$step3Score = (int)vd($data, 'step3_score');

// Step 1 interpretation
if ($step1Score >= 11) $s1interp = 'No significant cognitive impairment — further testing not necessary.';
elseif ($step1Score >= 7) $s1interp = 'More information required — further testing is necessary.';
else $s1interp = 'Cognitive impairment is indicated — conduct standard investigations.';

// Answers helper
if (!function_exists('cq')) { function cq(array $d, string $k): string {
    $v = isset($d[$k]) ? (string)$d[$k] : '';
    if ($v === 'correct')   return '<strong style="color:#166534;">CORRECT (1)</strong>';
    if ($v === 'incorrect') return '<strong style="color:#991b1b;">INCORRECT (0)</strong>';
    if ($v !== '')          return '<strong>' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '</strong>';
    return '<span style="color:#aaa;">—</span>';
}}
if (!function_exists('yn2')) { function yn2(array $d, string $k): string {
    $v = strtolower(trim($d[$k] ?? ''));
    if ($v === 'yes')    return '<strong>YES</strong>';
    if ($v === 'no')     return '<strong>NO</strong>';
    if ($v === 'unsure') return '<strong>UNSURE</strong>';
    if ($v === 'na' || $v === 'n/a') return '<strong>N/A</strong>';
    return '<span style="color:#aaa;">—</span>';
}}
?>
<div class="bwc-form">
    <!-- Practice Header -->
    <div class="bwc-header">
        <img src="<?= BASE_URL ?>/assets/img/logo.png" class="bwc-header-logo" alt="Beyond Wound Care Inc.">
        <div class="bwc-header-text">
            <p class="bwc-practice-name">Beyond Wound Care Inc.</p>
            <p>1340 Remington RD, Ste P &nbsp; Schaumburg, IL 60173</p>
            <p>Phone: 847.873.8693 &nbsp;&nbsp; Fax: 847.873.8486</p>
            <p>Email: Support@beyondwoundcare.com</p>
            <p class="bwc-form-title">Cognitive Wellness Exam</p>
        </div>
    </div>

    <!-- Patient Info -->
    <div style="display:flex;justify-content:space-between;border-bottom:1px solid #000;padding-bottom:5pt;margin-bottom:10pt;font-size:10pt;">
        <span>Patient Name: <span class="bwc-fill"><?= $ptName ?></span></span>
        <span>DOB: <span class="bwc-fill"><?= $ptDob ?></span></span>
        <span>Today's Date: <span class="bwc-fill"><?= $fmDate ?></span></span>
    </div>
    <?php if ($assessedBy): ?>
    <div style="font-size:9.5pt;margin-bottom:8pt;">Assessed By: <span class="bwc-fill"><?= h($assessedBy) ?></span></div>
    <?php endif; ?>

    <!-- STEP 1 -->
    <p style="font-size:10.5pt;font-style:italic;text-decoration:underline;font-weight:bold;margin:0 0 4pt;">Step 1: Patient Examination</p>
    <p style="font-size:9pt;font-style:italic;margin:0 0 8pt;">— unless specified, each question should only be asked once</p>

    <!-- Q1: Recall Test (instructions only) -->
    <div style="margin-bottom:8pt;font-size:9.5pt;">
        <p style="margin:0;"><strong>1. Recall Test:</strong> Look directly at the patient and say, "Please listen carefully. I am going to give you a name and address that I want you to repeat back to me now and try to remember: <strong>Sarah Lee, 39 South Street, New York.</strong> Please say the name and address for me now." (Allow a maximum of 4 attempts.)</p>
    </div>

    <!-- Q2–Q4 + Q5 recall table -->
    <table class="bwc-cog-table">
        <tr>
            <td style="width:65%;font-size:9.5pt;"><strong>2. Time Orientation:</strong> What is the date?</td>
            <td><?= cq($data, 'q2') ?>&nbsp;CORRECT (1/1) &nbsp;&nbsp; <?= (vd($data,'q2')==='incorrect') ? '☑' : '☐' ?> INCORRECT (0/1)</td>
        </tr>
        <tr>
            <td style="font-size:9.5pt;"><strong>3a. Clock Drawing — Numbers:</strong> Please mark in all the numbers to indicate the hours of a clock. (Correct spacing required; repeat instructions as needed.)</td>
            <td><?= (vd($data,'q3a')==='correct') ? '☑' : '☐' ?> CORRECT (1/1) &nbsp;&nbsp; <?= (vd($data,'q3a')==='incorrect') ? '☑' : '☐' ?> INCORRECT (0/1)</td>
        </tr>
        <tr>
            <td style="font-size:9.5pt;"><strong>3b. Clock Drawing — Hands (9:20):</strong> Please mark in hands to show 20 minutes past nine o'clock. (If not completed within 3 minutes, move on.)</td>
            <td><?= (vd($data,'q3b')==='correct') ? '☑' : '☐' ?> CORRECT (1/1) &nbsp;&nbsp; <?= (vd($data,'q3b')==='incorrect') ? '☑' : '☐' ?> INCORRECT (0/1)</td>
        </tr>
        <tr>
            <td style="font-size:9.5pt;"><strong>4. Information:</strong> Can you tell me something that happened in the news recently?</td>
            <td><?= (vd($data,'q4')==='correct') ? '☑' : '☐' ?> CORRECT (1/1) &nbsp;&nbsp; <?= (vd($data,'q4')==='incorrect') ? '☑' : '☐' ?> INCORRECT (0/1)</td>
        </tr>
    </table>

    <!-- Q5 Recall -->
    <div style="margin:8pt 0;font-size:9.5pt;">
        <p style="margin:0 0 4pt;"><strong>5. Recall:</strong> What was the name and address I asked you to remember?</p>
        <table class="bwc-cog-table">
            <?php
            $recallMap = [
                'recall_sarah'        => 'Sarah',
                'recall_lee'          => 'Lee',
                'recall_39'           => '39',
                'recall_south__st__'  => 'South (St.)',
                'recall_new_york'     => 'New York',
            ];
            foreach ($recallMap as $fieldKey => $label): ?>
            <tr>
                <td style="width:30%;font-size:9.5pt;">• <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= (vd($data,$fieldKey)==='correct') ? '☑' : '☐' ?> CORRECT (1/1) &nbsp;&nbsp; <?= (vd($data,$fieldKey)==='incorrect') ? '☑' : '☐' ?> INCORRECT (0/1)</td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Q6 Language -->
    <div style="margin-bottom:8pt;font-size:9.5pt;">
        <p style="margin:0 0 3pt;"><strong>6. Language:</strong> Give a stage 3 command: "Place index finger of your right hand on your nose and then on your left ear." (Score 1 point for each stage.)</p>
        <?php $langVal = vd($data, 'q6_language'); ?>
        <p style="margin:0;">
            <?= ($langVal==='3') ? '☑' : '☐' ?> CORRECT (3/3) &nbsp;&nbsp;
            <?= ($langVal==='2') ? '☑' : '☐' ?> INCORRECT (2/3) &nbsp;&nbsp;
            <?= ($langVal==='1') ? '☑' : '☐' ?> INCORRECT (1/3) &nbsp;&nbsp;
            <?= ($langVal==='0') ? '☑' : '☐' ?> INCORRECT (0/3)
        </p>
    </div>

    <!-- Step 1 Score -->
    <div style="page-break-inside:avoid;">
    <div style="border:2px solid #000;padding:6pt 8pt;margin-bottom:6pt;font-size:10pt;">
        <strong>Step 1 Scoring:</strong> <em>To get a total score, add the number of items answered correctly.</em><br>
        Total Correct (score out of 12) = <strong style="font-size:14pt;"><?= $step1Score ?> / 12</strong><br>
        <div style="border:1px solid #ccc;padding:4pt 6pt;margin-top:5pt;font-size:9.5pt;">
            <?= $step1Score >= 11 ? '→' : '' ?> If patient scores 11-12, no significant cognitive impairment and further testing not necessary.<br>
            <?= ($step1Score >= 7 && $step1Score < 11) ? '→' : '' ?> If patient scores 7-11, more information required. Further testing is necessary.<br>
            <?= $step1Score < 7 ? '→' : '' ?> If patient scores 0-6, cognitive impairment is indicated. Conduct standard investigations.
        </div>
    </div>
    </div><!-- /step1-score wrapper -->

    <!-- STEP 2: Informant Interview -->
    <?php
    $informantName = vd($data, 'informant_name');
    $informantRel  = vd($data, 'informant_relationship');
    ?>
    <p style="font-size:10.5pt;font-style:italic;text-decoration:underline;font-weight:bold;margin:4pt 0 2pt;page-break-before:avoid;">Step 2: Informant Interview</p>
    <p style="font-size:9pt;font-style:italic;margin:0 0 3pt;">— skip this step if the patient lives alone</p>
    <div style="font-size:9.5pt;margin-bottom:4pt;">
        Informant's Name: <span class="bwc-fill"><?= $informantName ?></span>
        &nbsp;&nbsp;&nbsp; Relationship to Patient: <span class="bwc-fill"><?= $informantRel ?></span>
    </div>
    <p style="font-size:9pt;font-style:italic;margin:0 0 3pt;">~These 6 questions ask how the patient is compared to when he/she was 5-10 years ago~ Compared to then…</p>

    <?php
    $step2Qs = [
        'sq1' => 'Does the patient have more trouble remembering things that have happened recently?',
        'sq2' => 'Does he/she have more trouble recalling conversations a few days later?',
        'sq3' => 'When speaking, does the patient have more difficulty finding the right word?',
        'sq4' => 'Is the patient less able to manage money and financial affairs?',
        'sq5' => 'Is the patient less able to manage his/her medications independently?',
        'sq6' => 'Does the patient need more assistance with transport?',
    ];
    ?>
    <table class="bwc-cog-table" style="margin-bottom:4pt;page-break-inside:avoid;">
        <?php foreach ($step2Qs as $k => $q): ?>
        <tr>
            <td style="width:70%;font-size:9.5pt;"><?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?></td>
            <td style="font-size:9pt;"><?= yn2($data, $k) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div style="border:1px solid #000;padding:4pt 8pt;margin-bottom:6pt;font-size:9.5pt;page-break-inside:avoid;">
        <strong>Step 2 Scoring:</strong> <em>Add the number of items answered "NO", "UNSURE", or "N/A".</em><br>
        Total (score out of 6) = <strong style="font-size:13pt;"><?= $step2Score ?> / 6</strong><br>
        <?php if ($step2Score <= 3): ?><strong>→ If a patient scores 0-3, cognitive impairment is indicated.</strong><?php else: ?>If a patient scores 0-3, cognitive impairment is indicated.<?php endif; ?>
    </div>

    <!-- STEP 3: MIS -->
    <p style="font-size:10.5pt;font-style:italic;text-decoration:underline;font-weight:bold;margin:4pt 0 2pt;">Step 3: Memory Impairment Screen (MIS)</p>

    <!-- MIS Word Recall Table -->
    <table style="width:100%;border-collapse:collapse;border:1px solid #000;margin-bottom:6pt;font-size:9.5pt;page-break-inside:avoid;">
        <thead>
            <tr style="background:#f5f5f5;">
                <th style="border:1px solid #ccc;padding:4pt 6pt;text-align:left;">Word</th>
                <th style="border:1px solid #ccc;padding:4pt 6pt;text-align:left;">Cue</th>
                <th style="border:1px solid #ccc;padding:4pt 6pt;text-align:center;">Free Recall (2 pts)</th>
                <th style="border:1px solid #ccc;padding:4pt 6pt;text-align:center;">Cued Recall (1 pt)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $misWords = [
                ['Checkers', 'Game',         'mis_free_checkers',  'mis_cued_checkers'],
                ['Saucer',   'Dish',          'mis_free_saucer',    'mis_cued_saucer'],
                ['Telegram', 'Message',       'mis_free_telegram',  'mis_cued_telegram'],
                ['Red Cross','Organization',  'mis_free_red',       'mis_cued_red'],
            ];
            foreach ($misWords as [$word, $cue, $fk, $ck]): ?>
            <tr>
                <td style="border:1px solid #ccc;padding:5pt 6pt;"><?= htmlspecialchars($word, ENT_QUOTES, 'UTF-8') ?></td>
                <td style="border:1px solid #ccc;padding:5pt 6pt;"><?= htmlspecialchars($cue, ENT_QUOTES, 'UTF-8') ?></td>
                <td style="border:1px solid #ccc;padding:5pt 6pt;text-align:center;"><?= ($data[$fk] ?? '') === '2' ? '☑ 2pts' : '☐' ?></td>
                <td style="border:1px solid #ccc;padding:5pt 6pt;text-align:center;"><?= ($data[$ck] ?? '') === '1' ? '☑ 1pt'  : '☐' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div style="border:1px solid #000;padding:4pt 8pt;margin-bottom:6pt;font-size:9.5pt;page-break-inside:avoid;">
        <strong>Step 3 Scoring:</strong> Maximum score is 8. Total = <strong style="font-size:13pt;"><?= $step3Score ?> / 8</strong><br>
        <?= $step3Score >= 5 ? '→ ' : '' ?>5-8 = No cognitive impairment. &nbsp;&nbsp;
        <?= $step3Score <= 4 ? '→ ' : '' ?>≤ 4 = Possible cognitive impairment.
    </div>

    <!-- Clinical Notes -->
    <?php $notes = vd($data, 'clinical_notes'); if ($notes): ?>
    <div style="border:1px solid #ccc;padding:4pt 8pt;margin-bottom:6pt;font-size:9.5pt;">
        <strong>Clinical Notes:</strong><br><?= nl2br(h($notes)) ?>
    </div>
    <?php endif; ?>

    <!-- Signature -->
    <div class="bwc-sigs" style="margin-top:8pt;">
        <div class="bwc-sig-row">
            <div class="bwc-sig-line">
                <?php if ($f['patient_signature']): ?>
                <img src="<?= h($f['patient_signature']) ?>" class="bwc-sig-img" alt="Patient Signature">
                <?php endif; ?>
            </div>
            <div class="bwc-sig-date">Date: <?= $sigDate ?></div>
        </div>
        <div class="bwc-sig-label">Patient Signature</div>
        <div class="bwc-sig-row" style="margin-top:8pt;">
            <div class="bwc-sig-line">
                <?php if (!empty($f['ma_signature'])): ?>
                <img src="<?= h($f['ma_signature']) ?>" class="bwc-sig-img" alt="MA Signature">
                <?php endif; ?>
            </div>
            <div class="bwc-sig-date">Date: <?= $sigDate ?></div>
        </div>
        <div class="bwc-sig-label">Assessed By: <?= h($assessedBy) ?></div>

        <div class="bwc-sig-row" style="margin-top:8pt;">
            <div class="bwc-sig-line">
                <?php if (!empty($f['provider_signature'])): ?>
                <img src="<?= h($f['provider_signature']) ?>" class="bwc-sig-img" alt="Provider Signature">
                <?php endif; ?>
            </div>
            <div class="bwc-sig-date">Date: <?= $sigDate ?></div>
        </div>
        <div class="bwc-sig-label">Provider Signature<?php if (!empty($f['provider_name'])): ?> — <?= h($f['provider_name']) ?><?php endif; ?></div>
    </div>
</div>
