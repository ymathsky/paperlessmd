<?php
/**
 * Print Template: Medicare Annual Wellness Visit (AWV)
 * Mirrors the exact paper form layout
 */
$ptName    = h($patient['first_name'] . ' ' . $patient['last_name']);
$ptDob     = $patient['dob'] ? date('m/d/Y', strtotime($patient['dob'])) : '';
$sigDate   = date('m/d/Y', strtotime($f['created_at']));
$fmDate    = vd($data, 'form_date') ? date('m/d/Y', strtotime(vd($data,'form_date'))) : $sigDate;
$gdsScore  = (int)vd($data, 'gds_score');
$notes     = vd($data, 'clinical_notes');

// Build answer helper for simple text fields
if (!function_exists('awvY')) { function awvY(array $d, string $k): string {
    $v = strtolower(trim($d[$k] ?? ''));
    if ($v === 'yes') return '<strong>Yes</strong>';
    if ($v === 'no')  return '<strong>No</strong>';
    if ($v !== '')    return '<strong>' . htmlspecialchars($d[$k], ENT_QUOTES, 'UTF-8') . '</strong>';
    return '—';
}}
if (!function_exists('awvFill')) { function awvFill(array $d, string $k): string {
    $v = trim($d[$k] ?? '');
    return $v !== '' ? '<strong>' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '</strong>' : '—';
}}
// Multi-select fields stored as comma string or array
if (!function_exists('awvArr')) { function awvArr(array $d, string $k): array {
    if (!isset($d[$k])) return [];
    return is_array($d[$k]) ? $d[$k] : array_filter(array_map('trim', explode(',', (string)$d[$k])));
}}
?>
<div class="bwc-form">
    <!-- Header -->
    <div class="bwc-header">
        <p class="bwc-practice-name">Beyond Wound Care Inc.</p>
        <p>1340 Remington RD, Ste P &nbsp; Schaumburg, IL 60173</p>
        <p>Phone: 847.873.8693 &nbsp;&nbsp; Fax: 847.873.8486</p>
        <p>Email: Support@beyondwoundcare.com</p>
        <p class="bwc-form-title">MEDICARE HEALTH HISTORY FORM FOR ANNUAL WELLNESS VISIT</p>
    </div>

    <!-- Patient Info Row -->
    <table style="width:100%;border-collapse:collapse;margin-bottom:8pt;font-size:9.5pt;">
        <tr>
            <td>Patient Name: <span class="bwc-fill"><?= $ptName ?></span></td>
            <td>DOB: <span class="bwc-fill"><?= $ptDob ?></span></td>
            <td>Date: <span class="bwc-fill"><?= $fmDate ?></span></td>
        </tr>
    </table>

    <!-- SECTION A: Health History -->
    <div style="background:#333;color:#fff;font-weight:bold;padding:3pt 6pt;font-size:10pt;margin-bottom:4pt;">SECTION A: HEALTH HISTORY</div>
    <table style="width:100%;font-size:9.5pt;border-collapse:collapse;">
        <?php
        $sectionA = [
            ['q1_race',       '1. Race/Ethnicity:'],
            ['q2_home',       '2. Current living situation:'],
            ['q3_education',  '3. Highest education level:'],
            ['q4_language',   '4. Language(s) spoken at home:'],
            ['q5_employed',   '5. Are you currently employed?'],
            ['q6_smoke',      '6. Do you smoke or use tobacco?'],
            ['q7_alcohol',    '7. Do you drink alcoholic beverages?'],
            ['q8_exercise',   '8. How many minutes of moderate physical activity do you get per week?'],
            ['q10_seatbelt',  '10. Do you always wear your seatbelt?'],
        ];
        foreach ($sectionA as [$k, $label]): ?>
        <tr>
            <td style="width:65%;padding:2pt 4pt;vertical-align:top;"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
            <td style="padding:2pt 4pt;"><?= awvFill($data, $k) ?></td>
        </tr>
        <?php endforeach; ?>
        <!-- Q9 Diet: multi-select -->
        <tr>
            <td style="padding:2pt 4pt;vertical-align:top;">9. Which best describes your usual diet?</td>
            <td style="padding:2pt 4pt;"><?php
                $diets = awvArr($data, 'q9_diet');
                echo $diets ? '<strong>' . implode(', ', array_map('htmlspecialchars', $diets)) . '</strong>' : '—';
            ?></td>
        </tr>
    </table>

    <!-- SECTION B: Functional Ability / ADL -->
    <div style="background:#333;color:#fff;font-weight:bold;padding:3pt 6pt;font-size:10pt;margin:6pt 0 4pt;">SECTION B: FUNCTIONAL ABILITY & SAFETY (ADL)</div>
    <p style="font-size:9pt;font-style:italic;margin:0 0 4pt;">"Are you able to independent perform the following activities? If not, do you have adequate help?"</p>
    <table style="width:100%;font-size:9.5pt;border-collapse:collapse;">
        <?php
        $adlQs = [
            ['q11_adl_bath',    '11. Bathing or washing'],
            ['q12_adl_dress',   '12. Dressing and undressing'],
            ['q13_adl_eat',     '13. Eating and feeding'],
            ['q14_adl_transfer','14. Moving in/out of bed, chair, or wheelchair'],
            ['q15_adl_toilet',  '15. Using the toilet'],
            ['q16_adl_finance', '16. Managing finances'],
        ];
        foreach ($adlQs as [$k, $label]): ?>
        <tr>
            <td style="width:70%;padding:2pt 4pt;"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
            <td style="padding:2pt 4pt;"><?= awvY($data, $k) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <!-- SECTION C: Falls -->
    <div style="background:#333;color:#fff;font-weight:bold;padding:3pt 6pt;font-size:10pt;margin:6pt 0 4pt;">SECTION C: FALL RISK</div>
    <table style="width:100%;font-size:9.5pt;border-collapse:collapse;">
        <tr>
            <td style="width:70%;padding:2pt 4pt;">17. In the past year, have you had any falls?</td>
            <td style="padding:2pt 4pt;"><?= awvY($data, 'q17_falls') ?></td>
        </tr>
        <tr>
            <td style="padding:2pt 4pt;">18. Are you afraid of falling?</td>
            <td style="padding:2pt 4pt;"><?= awvY($data, 'q18_afraid') ?></td>
        </tr>
        <?php if (vd($data,'q17_falls') === 'yes'): ?>
        <tr>
            <td style="padding:2pt 4pt;">Number of falls in past year:</td>
            <td style="padding:2pt 4pt;"><?= awvFill($data, 'q19_fall_count') ?></td>
        </tr>
        <tr>
            <td style="padding:2pt 4pt;">Any fall resulted in injury?</td>
            <td style="padding:2pt 4pt;"><?= awvY($data, 'q19_fall_injury') ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <!-- SECTION D: Preventive Screenings -->
    <div style="background:#333;color:#fff;font-weight:bold;padding:3pt 6pt;font-size:10pt;margin:6pt 0 4pt;">SECTION D: PREVENTIVE CARE</div>
    <?php
    $screenings = [
        'q20_colonoscopy'      => 'Colonoscopy',
        'q21_mammogram'        => 'Mammogram',
        'q22_pap'              => 'Pap Smear',
        'q23_psa'              => 'PSA / Prostate Screen',
        'q24_flu_shot'         => 'Flu Vaccine',
        'q25_pneumonia'        => 'Pneumonia Vaccine',
        'q26_shingles'         => 'Shingles Vaccine',
    ];
    $hasScreening = false;
    foreach ($screenings as $sk => $_) { if (vd($data, $sk)) { $hasScreening = true; break; } }
    ?>
    <table style="width:100%;font-size:9.5pt;border-collapse:collapse;">
        <?php foreach ($screenings as $sk => $label): $val = vd($data, $sk); if (!$val) continue; ?>
        <tr>
            <td style="width:50%;padding:2pt 4pt;"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
            <td style="padding:2pt 4pt;"><strong><?= h($val) ?></strong></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$hasScreening): ?>
        <tr><td colspan="2" style="padding:2pt 4pt;color:#666;font-style:italic;">None recorded.</td></tr>
        <?php endif; ?>
    </table>

    <!-- SECTION E: GDS (Geriatric Depression Scale) -->
    <div style="background:#333;color:#fff;font-weight:bold;padding:3pt 6pt;font-size:10pt;margin:6pt 0 4pt;page-break-before:always;padding-top:14pt;">SECTION E: GERIATRIC DEPRESSION SCALE (GDS)</div>
    <p style="font-size:9pt;font-style:italic;margin:0 0 4pt;">Instructions: For each question, circle the answer that best describes how you have felt over the past week.</p>
    <?php
    $gdsQs = [
        'gds_q1'  => ['Are you basically satisfied with your life?',           'YES', 'no'],
        'gds_q2'  => ['Have you dropped many of your activities and interests?','yes', 'NO'],
        'gds_q3'  => ['Do you feel that your life is empty?',                  'yes', 'NO'],
        'gds_q4'  => ['Do you often get bored?',                               'yes', 'NO'],
        'gds_q5'  => ['Are you in good spirits most of the time?',             'YES', 'no'],
        'gds_q6'  => ['Are you afraid that something bad is going to happen?', 'yes', 'NO'],
        'gds_q7'  => ['Do you feel happy most of the time?',                   'YES', 'no'],
        'gds_q8'  => ['Do you often feel helpless?',                           'yes', 'NO'],
        'gds_q9'  => ['Do you prefer to stay at home, rather than going out?', 'yes', 'NO'],
        'gds_q10' => ['Do you feel you have more problems with memory than most?','yes','NO'],
        'gds_q11' => ['Do you think it is wonderful to be alive now?',         'YES', 'no'],
        'gds_q12' => ['Do you feel pretty worthless the way you are now?',     'yes', 'NO'],
        'gds_q13' => ['Do you feel full of energy?',                           'YES', 'no'],
        'gds_q14' => ['Do you feel that your situation is hopeless?',          'yes', 'NO'],
        'gds_q15' => ['Do you think that most people are better off than you?','yes', 'NO'],
    ];
    ?>
    <table style="width:100%;border-collapse:collapse;font-size:9pt;">
        <thead>
            <tr style="background:#eee;">
                <th style="border:1px solid #ccc;padding:3pt 5pt;text-align:left;">#</th>
                <th style="border:1px solid #ccc;padding:3pt 5pt;text-align:left;">Question</th>
                <th style="border:1px solid #ccc;padding:3pt 5pt;text-align:center;">Yes</th>
                <th style="border:1px solid #ccc;padding:3pt 5pt;text-align:center;">No</th>
            </tr>
        </thead>
        <tbody>
        <?php $gNum = 1; foreach ($gdsQs as $gk => $gd): ?>
        <?php $gv = strtolower(trim($data[$gk] ?? '')); $isYes = $gv === 'yes'; $isNo = $gv === 'no'; ?>
        <tr>
            <td style="border:1px solid #ccc;padding:3pt 5pt;"><?= $gNum++ ?></td>
            <td style="border:1px solid #ccc;padding:3pt 5pt;"><?= htmlspecialchars($gd[0], ENT_QUOTES, 'UTF-8') ?></td>
            <td style="border:1px solid #ccc;padding:3pt 5pt;text-align:center;"><?= $isYes ? '☑' : '☐' ?></td>
            <td style="border:1px solid #ccc;padding:3pt 5pt;text-align:center;"><?= $isNo  ? '☑' : '☐' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div style="margin:6pt 0;font-size:9.5pt;padding:4pt 8pt;border:1px solid #000;">
        <strong>GDS Total Score:</strong> <strong style="font-size:14pt;"><?= $gdsScore ?> / 15</strong>
        &nbsp;&nbsp;&nbsp;
        <?php if ($gdsScore <= 5): ?>
        <span style="color:#166534;font-weight:bold;">NORMAL (0-5)</span>
        <?php elseif ($gdsScore <= 9): ?>
        <span style="color:#854d0e;font-weight:bold;">MILD DEPRESSION SUGGESTED (6-9)</span>
        <?php else: ?>
        <span style="color:#991b1b;font-weight:bold;">SEVERE DEPRESSION LIKELY (&gt;9)</span>
        <?php endif; ?>
    </div>

    <!-- Questions 38-39: Conditions / Meds -->
    <?php $q38 = vd($data, 'q38_conditions'); $q39 = vd($data, 'q39_meds'); ?>
    <?php if ($q38 || $q39): ?>
    <table style="width:100%;font-size:9.5pt;border-collapse:collapse;margin-top:6pt;">
        <?php if ($q38): ?>
        <tr>
            <td style="width:50%;padding:2pt 4pt;font-weight:bold;vertical-align:top;">Medical Conditions:</td>
            <td style="padding:2pt 4pt;"><?= nl2br(h($q38)) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($q39): ?>
        <tr>
            <td style="padding:2pt 4pt;font-weight:bold;vertical-align:top;">Current Medications:</td>
            <td style="padding:2pt 4pt;"><?= nl2br(h($q39)) ?></td>
        </tr>
        <?php endif; ?>
    </table>
    <?php endif; ?>

    <!-- Clinical Notes -->
    <?php if ($notes): ?>
    <div style="border:1px solid #ccc;padding:6pt 8pt;margin-top:8pt;font-size:9.5pt;">
        <strong>Clinical Notes:</strong><br><?= nl2br(h($notes)) ?>
    </div>
    <?php endif; ?>

    <!-- Signature -->
    <div class="bwc-sigs" style="margin-top:16pt;">
        <div class="bwc-sig-row">
            <div class="bwc-sig-line">
                <?php if ($f['patient_signature']): ?>
                <img src="<?= h($f['patient_signature']) ?>" class="bwc-sig-img" alt="Patient Signature">
                <?php endif; ?>
            </div>
            <div class="bwc-sig-date">Date: <?= $sigDate ?></div>
        </div>
        <div class="bwc-sig-label">Patient / Representative Signature</div>

        <div class="bwc-sig-row" style="margin-top:16pt;">
            <div class="bwc-sig-line">
                <?php if (!empty($f['ma_signature'])): ?>
                <img src="<?= h($f['ma_signature']) ?>" class="bwc-sig-img" alt="MA Signature">
                <?php endif; ?>
            </div>
            <div class="bwc-sig-date">Date: <?= $sigDate ?></div>
        </div>
        <div class="bwc-sig-label">Medical Assistant Signature — <?= h($f['ma_name'] ?? '') ?></div>

        <div class="bwc-sig-row" style="margin-top:16pt;">
            <div class="bwc-sig-line">
                <?php if (!empty($f['provider_signature'])): ?>
                <img src="<?= h($f['provider_signature']) ?>" class="bwc-sig-img" alt="Provider Signature">
                <?php endif; ?>
            </div>
            <div class="bwc-sig-date">Date: <?= $sigDate ?></div>
        </div>
        <div class="bwc-sig-label">Provider Signature / credentials<?php if (!empty($f['provider_name'])): ?> — <?= h($f['provider_name']) ?><?php endif; ?></div>
    </div>
</div>
