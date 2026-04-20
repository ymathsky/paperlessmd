<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireNotBilling();

$patient_id = (int)($_GET['patient_id'] ?? 0);
if (!$patient_id) { header('Location: ' . BASE_URL . '/patients.php'); exit; }
$pStmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$pStmt->execute([$patient_id]);
$patient = $pStmt->fetch();
if (!$patient) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

// One-signature rule: redirect to existing signed form if already signed today
$_dupQ = $pdo->prepare("SELECT id FROM form_submissions WHERE patient_id = ? AND form_type = 'medicare_awv' AND status IN ('signed','uploaded') AND DATE(created_at) = CURDATE() LIMIT 1");
$_dupQ->execute([$patient_id]);
if ($_dupId = $_dupQ->fetchColumn()) { header('Location: ' . BASE_URL . '/view_document.php?id=' . (int)$_dupId . '&already_signed=1'); exit; }

$pageTitle = 'Medicare AWV Health History';
$activeNav = 'patients';

$extraJs = '<script>
// GDS auto-score
const gdsYesScore  = [2,5,6,10,14];  // 1-based question numbers that score YES=1
const gdsNoScore   = [1,3,4,7,8,9,11,12,13,15];
function calcGDS() {
    let score = 0;
    for (let i=1;i<=15;i++){
        const el = document.querySelector(`input[name="gds_q${i}"]:checked`);
        if (!el) continue;
        if (gdsYesScore.includes(i)  && el.value === "yes")  score++;
        if (gdsNoScore.includes(i)   && el.value === "no")   score++;
    }
    document.getElementById("gds_total").value = score;
    document.getElementById("gds_display").textContent = score;
    const box = document.getElementById("gds_result");
    if (score <= 5) { box.className = "mt-3 p-3 rounded-xl text-sm bg-emerald-50 border border-emerald-200 text-emerald-800"; box.textContent = "Score " + score + "/15 — Normal (0–5)"; }
    else { box.className = "mt-3 p-3 rounded-xl text-sm bg-rose-50 border border-rose-200 text-rose-800"; box.textContent = "Score " + score + "/15 — Possible depression indicated (>5). Consider further evaluation."; }
}
document.addEventListener("change", function(e) {
    if (e.target.name && e.target.name.startsWith("gds_")) calcGDS();
});
</script>';

include __DIR__ . '/../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6 flex-wrap">
    <a href="<?= BASE_URL ?>/patients.php" class="hover:text-blue-600 font-medium">Patients</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patient_id ?>" class="hover:text-blue-600 font-medium">
        <?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>
    </a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold">Medicare AWV Health History</span>
</nav>

<?php
// Helper to render a yes/no radio row
function yn($name, $label, $note = '') {
    echo '<div class="flex items-start gap-2 py-2.5 border-b border-slate-100 last:border-0">';
    echo '<div class="flex-1 text-sm text-slate-700 leading-snug">' . h($label);
    if ($note) echo ' <span class="text-xs text-slate-400 italic">' . h($note) . '</span>';
    echo '</div>';
    echo '<div class="flex gap-3 shrink-0">';
    foreach (['yes' => 'YES', 'no' => 'NO'] as $val => $lbl) {
        echo '<label class="flex items-center gap-1.5 cursor-pointer">';
        echo '<input type="radio" name="' . h($name) . '" value="' . $val . '" class="w-3.5 h-3.5 text-sky-600 border-slate-300 focus:ring-sky-400">';
        echo '<span class="text-xs font-semibold text-slate-600">' . $lbl . '</span>';
        echo '</label>';
    }
    echo '</div>';
    echo '</div>';
}
function sectionHeader($num, $title, $icon='bi-circle-fill', $color='sky') {
    echo '<div class="bg-' . $color . '-50 border border-' . $color . '-200 rounded-xl px-4 py-3 mb-3 flex items-center gap-2">';
    echo '<i class="bi ' . $icon . ' text-' . $color . '-600 text-base"></i>';
    echo '<h3 class="font-bold text-' . $color . '-700 text-sm uppercase tracking-wide">' . h($title) . '</h3>';
    echo '</div>';
}
?>

<div class="max-w-3xl">

<div id="wiz-resume-banner" class="hidden flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-2xl px-5 py-3.5 mb-4 text-sm text-blue-800">
    <i class="bi bi-floppy-fill text-blue-500 text-lg shrink-0"></i>
    <div class="flex-1"><span class="font-semibold">Unsaved draft found.</span> Resume where you left off?</div>
    <button id="wiz-resume-yes" class="px-4 py-1.5 bg-blue-600 text-white text-xs font-bold rounded-lg hover:bg-blue-700 transition-colors">Resume</button>
    <button id="wiz-resume-no"  class="px-4 py-1.5 bg-white border border-blue-200 text-blue-600 text-xs font-bold rounded-lg hover:bg-blue-50 transition-colors ml-1">Start fresh</button>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="bg-gradient-to-r from-sky-700 to-sky-600 px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-xl">
            <i class="bi bi-clipboard2-pulse text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-white font-bold text-lg">Annual Wellness Visit — Health History Questionnaire</h2>
            <p class="text-sky-100 text-sm"><?= h($patient['first_name'] . ' ' . $patient['last_name']) ?> &mdash; <?= h(PRACTICE_NAME) ?></p>
        </div>
    </div>

    <div id="wiz-header" class="px-6 pt-5 pb-2"></div>

    <form id="mainForm" method="POST" action="<?= BASE_URL ?>/api/save_form.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="form_type"  value="medicare_awv">
        <input type="hidden" name="gds_score"  id="gds_total" value="0">
        <input type="hidden" id="wiz-form-key" value="medicare_awv_<?= $patient_id ?>">

        <div class="px-6 pb-2">

            <!-- Step 0: Demographics & Lifestyle -->
            <div class="wiz-step space-y-8 py-4" data-step="0" data-title="Demographics &amp; Lifestyle" data-icon="bi-person-badge">

            <!-- Patient Info Header -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Patient Name</label>
                    <input type="text" name="patient_name"
                           value="<?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date</label>
                    <input type="date" name="form_date" value="<?= date('Y-m-d') ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition focus:bg-white">
                </div>
            </div>

            <!-- SECTION A: Demographics / Lifestyle -->
            <div>
                <?php sectionHeader('A','Section A — Demographics & Lifestyle','bi-person-badge','sky'); ?>
                <div class="border border-slate-200 rounded-xl divide-y divide-slate-100">
                    <div class="p-4">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">1. Race / Ethnicity</label>
                        <input type="text" name="q1_race"
                               class="w-full px-4 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition focus:bg-white">
                    </div>
                    <div class="p-4">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">2. Who lives with you at home?</label>
                        <input type="text" name="q2_home"
                               class="w-full px-4 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition focus:bg-white">
                    </div>
                    <div class="p-4">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">3. Education level</label>
                        <select name="q3_education"
                                class="w-full px-4 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50
                                       focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition focus:bg-white">
                            <option value="">Select...</option>
                            <option>Less than high school</option>
                            <option>High school / GED</option>
                            <option>Some college</option>
                            <option>College graduate</option>
                            <option>Post-graduate</option>
                        </select>
                    </div>
                    <div class="p-4">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">4. Primary language</label>
                        <input type="text" name="q4_language"
                               class="w-full px-4 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition focus:bg-white">
                    </div>
                    <?php yn('q5_employed','5. Are you currently employed?'); ?>
                    <?php yn('q6_smoke','6. Do you currently smoke tobacco products?'); ?>
                    <div class="p-4">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">7. How much alcohol do you drink per week? (drinks/week)</label>
                        <input type="number" name="q7_alcohol" min="0"
                               class="w-full px-4 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition focus:bg-white">
                    </div>
                    <?php yn('q8_exercise','8. Do you exercise regularly? (30+ min/day, 5 days/week)'); ?>
                    <div class="p-4">
                        <label class="block text-sm font-semibold text-slate-700 mb-2">9. Which of the following best describes your diet?</label>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach (['Well balanced','Low sodium','Low fat','Low carb','Diabetic','Other'] as $d): ?>
                            <label class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg cursor-pointer text-sm
                                          hover:border-sky-300 hover:bg-sky-50 has-[:checked]:border-sky-400 has-[:checked]:bg-sky-50">
                                <input type="checkbox" name="q9_diet[]" value="<?= h($d) ?>"
                                       class="w-3.5 h-3.5 text-sky-600 border-slate-300 rounded focus:ring-sky-400">
                                <span><?= h($d) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php yn('q10_seatbelt','10. Do you always wear a seatbelt?'); ?>
                </div>
            </div>

            <!-- SECTION B: Physical / Functional Status -->
            <div>
                <?php sectionHeader('B','Section B — Physical & Functional Status','bi-heart-pulse','sky'); ?>
                <div class="border border-slate-200 rounded-xl divide-y divide-slate-100 px-4">
                    <?php yn('q11_adl_bath',   '11. Do you need help bathing?'); ?>
                    <?php yn('q12_adl_dress',  '12. Do you need help dressing?'); ?>
                    <?php yn('q13_adl_toilet', '13. Do you need help with toileting?'); ?>
                    <?php yn('q14_adl_transfer','14. Do you need help transferring (e.g., bed to chair)?'); ?>
                    <?php yn('q15_adl_eating', '15. Do you need help eating?'); ?>
                    <?php yn('q16_adl_finance','16. Do you need help managing finances or medications?'); ?>
                </div>
            </div>

            <!-- SECTION C: Falls / Safety -->
            <div>
                <?php sectionHeader('C','Section C — Falls & Safety','bi-exclamation-triangle','sky'); ?>
                <div class="border border-slate-200 rounded-xl divide-y divide-slate-100 px-4">
                    <?php yn('q17_falls', '17. Have you fallen in the past 12 months?'); ?>
                    <?php yn('q18_afraid','18. Are you afraid of falling?'); ?>
                    <div class="py-2.5">
                        <div class="flex items-start gap-2 py-2">
                            <div class="flex-1 text-sm text-slate-700">19. If you have fallen, how many times? And have you been injured?</div>
                        </div>
                        <div class="flex gap-4 mt-1">
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Times fallen</label>
                                <input type="number" name="q19_fall_count" min="0"
                                       class="w-28 px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition focus:bg-white">
                            </div>
                            <div style="flex:1">
                                <label class="block text-xs text-slate-500 mb-1">Injury details</label>
                                <input type="text" name="q19_fall_injury"
                                       class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition focus:bg-white">
                            </div>
                        </div>
                    </div>
                    <?php yn('q20_drive','20. Do you still drive?'); ?>
                    <?php yn('q21_needhelp_drive','21. Do you need help with transportation?'); ?>
                    <?php yn('q22_smoke_home','22. Is there smoking in your home?'); ?>
                    <?php yn('q23_gun_home','23. Are there guns or firearms in your home?'); ?>
                </div>
            </div>

            </div><!-- /step 0 -->

            <!-- Step 1: Health & Cognition -->
            <div class="wiz-step hidden space-y-8 py-4" data-step="1" data-title="Health &amp; Cognition" data-icon="bi-heart-pulse">

            <!-- SECTION D: Memory / Cognitive -->
            <div>
                <?php sectionHeader('D','Section D — Memory & Cognition','bi-brain','sky'); ?>
                <div class="border border-slate-200 rounded-xl divide-y divide-slate-100 px-4">
                    <?php yn('q24_memory_concern','24. Do you have concerns about your memory?'); ?>
                    <?php yn('q25_fam_memory',    '25. Does your family have concerns about your memory?'); ?>
                    <?php yn('q26_confuse',        '26. Do you ever feel confused?'); ?>
                </div>
            </div>

            <!-- SECTION E: Emotional / Psychosocial -->
            <div>
                <?php sectionHeader('E','Section E — Emotional & Psychosocial','bi-emoji-smile','sky'); ?>
                <div class="border border-slate-200 rounded-xl divide-y divide-slate-100 px-4">
                    <?php
                    // Q27: frequency grid
                    $q27Items = [
                        'Feeling sad or depressed',
                        'Feeling anxious or nervous',
                        'Feeling hopeless',
                        'Feeling irritable or angry',
                        'Feeling lonely or isolated',
                        'Loss of interest in activities you used to enjoy',
                    ];
                    $freqOpts = ['Never','Seldom','Sometimes','Often','Always'];
                    ?>
                    <div class="py-3">
                        <p class="text-sm text-slate-700 font-semibold mb-3">27. How often in the past month have you experienced:</p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="bg-slate-50">
                                        <th class="px-3 py-2 text-left font-semibold text-slate-500">Symptom</th>
                                        <?php foreach ($freqOpts as $f): ?>
                                        <th class="px-3 py-2 text-center font-semibold text-slate-500"><?= h($f) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($q27Items as $idx => $item): ?>
                                    <tr>
                                        <td class="px-3 py-2 text-slate-700"><?= h($item) ?></td>
                                        <?php foreach ($freqOpts as $f): ?>
                                        <td class="px-3 py-2 text-center">
                                            <input type="radio" name="q27_<?= $idx ?>" value="<?= strtolower($f) ?>"
                                                   class="w-3.5 h-3.5 text-sky-600 border-slate-300 focus:ring-sky-400">
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php yn('q28_abuse','28. Do you feel safe in your current living situation?'); ?>
                    <?php yn('q29_social','29. Do you participate in social activities (clubs, church, family gatherings)?'); ?>
                    <?php yn('q30_caregiver_stress','30. Do you have stress related to caregiving responsibilities?'); ?>
                </div>
            </div>

            <!-- SECTION F: Continence / Ambulation / Senses -->
            <div>
                <?php sectionHeader('F','Section F — Continence, Ambulation & Senses','bi-person-walking','sky'); ?>
                <div class="border border-slate-200 rounded-xl divide-y divide-slate-100 px-4">
                    <?php yn('q31_incontinence','31. Do you have problems with bladder or bowel control?'); ?>
                    <?php yn('q32_assistive',   '32. Do you use any assistive devices to walk?', '(cane, walker, wheelchair)'); ?>
                    <?php yn('q33_vision',      '33. Do you have difficulty with your vision even with glasses?'); ?>
                    <?php yn('q34_hearing',     '34. Do you have difficulty with hearing?'); ?>
                    <?php yn('q35_pain',        '35. Do you have persistent pain that interferes with daily activities?'); ?>
                </div>
            </div>

            <!-- SECTION G: Medical History -->
            <div>
                <?php sectionHeader('G','Section G — Hospital / SNF Risk','bi-hospital','sky'); ?>
                <div class="border border-slate-200 rounded-xl divide-y divide-slate-100 px-4">
                    <?php yn('q36_hospital','36. Have you been hospitalized in the past 12 months?'); ?>
                    <?php yn('q37_snf',     '37. Have you been in a nursing home or skilled nursing facility in the past 12 months?'); ?>
                    <div class="py-3">
                        <label class="block text-sm text-slate-700 mb-1.5">38. List any chronic conditions you have been diagnosed with:</label>
                        <textarea name="q38_conditions" rows="2"
                                  class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50
                                         focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition focus:bg-white resize-none"></textarea>
                    </div>
                    <div class="py-3">
                        <label class="block text-sm text-slate-700 mb-1.5">39. Are you currently taking any medications? If yes, list:</label>
                        <textarea name="q39_meds" rows="2"
                                  class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50
                                         focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition focus:bg-white resize-none"></textarea>
                    </div>
                    <?php yn('q40_advance_directive','40. Do you have an advance directive (living will or healthcare power of attorney)?'); ?>
                </div>
            </div>

            </div><!-- /step 1 -->

            <!-- Step 2: Screenings & GDS -->
            <div class="wiz-step hidden space-y-8 py-4" data-step="2" data-title="Screenings &amp; GDS" data-icon="bi-shield-check">

            <!-- Preventive Screening History -->
            <div>
                <?php sectionHeader('SCR','Screening & Immunization History','bi-shield-check','sky'); ?>
                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase w-1/2">Item</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">Yes</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">No</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Date / Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php
                            $screenings = ['Colonoscopy','Mammogram','Pap Smear','Cardiac Stress Test','Hearing Exam','Eye Exam',
                                           'Flu Vaccine','Pneumonia Vaccine','Shingles Vaccine','Chicken Pox Vaccine (history)'];
                            foreach ($screenings as $sc):
                                $key = 'scr_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($sc));
                            ?>
                            <tr>
                                <td class="px-4 py-3 text-slate-700"><?= h($sc) ?></td>
                                <td class="px-4 py-3 text-center">
                                    <input type="radio" name="<?= $key ?>" value="yes"
                                           class="w-3.5 h-3.5 text-sky-600 border-slate-300 focus:ring-sky-400">
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <input type="radio" name="<?= $key ?>" value="no"
                                           class="w-3.5 h-3.5 text-sky-600 border-slate-300 focus:ring-sky-400">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" name="<?= $key ?>_date" placeholder="Date / location"
                                           class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-xs bg-slate-50
                                                  focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-transparent transition focus:bg-white">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Geriatric Depression Scale -->
            <div>
                <div class="bg-rose-50 border border-rose-200 rounded-xl px-4 py-3 mb-3 flex items-center gap-2">
                    <i class="bi bi-activity text-rose-600 text-base"></i>
                    <h3 class="font-bold text-rose-700 text-sm uppercase tracking-wide">Geriatric Depression Scale (GDS) — Short Form</h3>
                </div>
                <p class="text-xs text-slate-500 italic mb-3">Choose the best answer for how you have felt over the past week.</p>
                <?php
                $gdsQuestions = [
                    [1, 'Are you basically satisfied with your life?',                         'yes', 'no'],
                    [2, 'Have you dropped many of your activities and interests?',              'no',  'yes'],
                    [3, 'Do you feel that your life is empty?',                                'no',  'yes'],
                    [4, 'Do you often get bored?',                                             'no',  'yes'],
                    [5, 'Are you in good spirits most of the time?',                           'yes', 'no'],
                    [6, 'Are you afraid that something bad is going to happen to you?',        'no',  'yes'],
                    [7, 'Do you feel happy most of the time?',                                 'yes', 'no'],
                    [8, 'Do you often feel helpless?',                                         'no',  'yes'],
                    [9, 'Do you prefer to stay at home rather than going out and doing new things?','no','yes'],
                    [10,'Do you feel you have more problems with memory than most?',            'no',  'yes'],
                    [11,'Do you think it is wonderful to be alive now?',                       'yes', 'no'],
                    [12,'Do you feel pretty worthless the way you are now?',                   'no',  'yes'],
                    [13,'Do you feel full of energy?',                                         'yes', 'no'],
                    [14,'Do you feel that your situation is hopeless?',                        'no',  'yes'],
                    [15,'Do you think that most people are better off than you are?',          'no',  'yes'],
                ];
                ?>
                <div class="border border-slate-200 rounded-xl divide-y divide-slate-100 px-4">
                    <?php foreach ($gdsQuestions as [$n, $q, $normYes, $normNo]): ?>
                    <div class="flex items-start gap-2 py-2.5">
                        <span class="text-xs font-bold text-slate-400 w-5 shrink-0 mt-0.5"><?= $n ?>.</span>
                        <div class="flex-1 text-sm text-slate-700 leading-snug"><?= h($q) ?></div>
                        <div class="flex gap-3 shrink-0">
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="gds_q<?= $n ?>" value="yes" class="w-3.5 h-3.5 text-rose-500 border-slate-300 focus:ring-rose-400">
                                <span class="text-xs font-semibold text-slate-600">YES</span>
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="gds_q<?= $n ?>" value="no" class="w-3.5 h-3.5 text-rose-500 border-slate-300 focus:ring-rose-400">
                                <span class="text-xs font-semibold text-slate-600">NO</span>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 mt-3">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-bold text-rose-700">GDS Score:</span>
                        <span class="text-2xl font-extrabold text-rose-800" id="gds_display">0</span>
                        <span class="text-sm text-rose-600">/ 15 &nbsp;|&nbsp; 0–5 = Normal &nbsp;|&nbsp; &gt;5 = Possible depression</span>
                    </div>
                    <div id="gds_result" class="mt-3 p-3 rounded-xl text-sm bg-slate-100 border border-slate-200 text-slate-500">
                        Select answers above to calculate score.
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Clinical Notes / Follow-up Plan</label>
                <textarea name="clinical_notes" rows="3"
                          class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition focus:bg-white resize-none"
                          placeholder="Any additional observations or care plan notes..."></textarea>
            </div>

            </div><!-- /step 2 -->

            <!-- Step 3: Sign & Submit -->
            <div class="wiz-step hidden py-4" data-step="3" data-title="Sign" data-icon="bi-pen">
                <?php include __DIR__ . '/../includes/sig_block.php'; ?>
            </div><!-- /step 3 -->

            <?php
            $accentClass = 'bg-sky-600 hover:bg-sky-700';
            $cancelUrl   = BASE_URL . '/patient_view.php?id=' . $patient_id;
            include __DIR__ . '/../includes/wiz_nav.php';
            ?>

        </div><!-- /px-6 -->
    </form>
</div><!-- /card -->
</div><!-- /max-w-3xl -->

<?php include __DIR__ . '/../includes/footer.php'; ?>
