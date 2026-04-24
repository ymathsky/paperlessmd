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
$_dupQ = $pdo->prepare("SELECT id FROM form_submissions WHERE patient_id = ? AND form_type = 'cognitive_wellness' AND status IN ('signed','uploaded') AND DATE(created_at) = CURDATE() LIMIT 1");
$_dupQ->execute([$patient_id]);
if ($_dupId = $_dupQ->fetchColumn()) { header('Location: ' . BASE_URL . '/view_document.php?id=' . (int)$_dupId . '&already_signed=1'); exit; }

$pageTitle = 'Cognitive Wellness Exam';
$activeNav = 'patients';

$extraJs = '<script>
// Auto-calculate Step 1 score
function calcStep1() {
    let score = 0;
    document.querySelectorAll(".step1-score:checked").forEach(el => score += parseInt(el.value));
    document.getElementById("step1_total").value = score;
    document.getElementById("step1_display").textContent = score;
    const box = document.getElementById("step1_result");
    if (score >= 11) { box.className = "mt-3 p-3 rounded-xl text-sm bg-emerald-50 border border-emerald-200 text-emerald-800"; box.textContent = "Score " + score + "/12 — No significant cognitive impairment. Further testing not necessary."; }
    else if (score >= 7) { box.className = "mt-3 p-3 rounded-xl text-sm bg-amber-50 border border-amber-200 text-amber-800"; box.textContent = "Score " + score + "/12 — More information required. Further testing is necessary."; }
    else { box.className = "mt-3 p-3 rounded-xl text-sm bg-red-50 border border-red-200 text-red-800"; box.textContent = "Score " + score + "/12 — Cognitive impairment is indicated. Conduct standard investigations."; }
}
// Step 2
function calcStep2() {
    let score = 0;
    document.querySelectorAll(".step2-no:checked,.step2-unsure:checked,.step2-na:checked").forEach(() => score++);
    document.getElementById("step2_total").value = score;
    document.getElementById("step2_display").textContent = score;
    const box = document.getElementById("step2_result");
    if (score <= 3) { box.className = "mt-3 p-3 rounded-xl text-sm bg-red-50 border border-red-200 text-red-800"; box.textContent = "Score " + score + "/6 — Cognitive impairment is indicated. Conduct standard investigations."; }
    else { box.className = "mt-3 p-3 rounded-xl text-sm bg-emerald-50 border border-emerald-200 text-emerald-800"; box.textContent = "Score " + score + "/6 — No cognitive impairment indicated."; }
}
// Step 3
function calcStep3() {
    let score = 0;
    document.querySelectorAll(".step3-score:checked").forEach(el => score += parseInt(el.value));
    document.getElementById("step3_total").value = score;
    document.getElementById("step3_display").textContent = score;
    const box = document.getElementById("step3_result");
    if (score >= 5) { box.className = "mt-3 p-3 rounded-xl text-sm bg-emerald-50 border border-emerald-200 text-emerald-800"; box.textContent = "Score " + score + "/8 — No cognitive impairment."; }
    else { box.className = "mt-3 p-3 rounded-xl text-sm bg-red-50 border border-red-200 text-red-800"; box.textContent = "Score " + score + "/8 — Possible cognitive impairment."; }
}
document.addEventListener("change", function(e) {
    if (e.target.classList.contains("step1-score")) calcStep1();
    if (e.target.classList.contains("step2-no") || e.target.classList.contains("step2-unsure") || e.target.classList.contains("step2-na")) calcStep2();
    if (e.target.classList.contains("step3-score")) calcStep3();
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
    <span class="text-slate-700 font-semibold">Cognitive Wellness Exam</span>
</nav>

<div class="max-w-3xl mx-auto">

<div id="wiz-resume-banner" class="hidden flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-2xl px-5 py-3.5 mb-4 text-sm text-blue-800">
    <i class="bi bi-floppy-fill text-blue-500 text-lg shrink-0"></i>
    <div class="flex-1"><span class="font-semibold">Unsaved draft found.</span> Resume where you left off?</div>
    <button id="wiz-resume-yes" class="px-4 py-1.5 bg-blue-600 text-white text-xs font-bold rounded-lg hover:bg-blue-700 transition-colors">Resume</button>
    <button id="wiz-resume-no"  class="px-4 py-1.5 bg-white border border-blue-200 text-blue-600 text-xs font-bold rounded-lg hover:bg-blue-50 transition-colors ml-1">Start fresh</button>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="bg-gradient-to-r from-violet-700 to-violet-600 px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-xl">
            <i class="bi bi-brain text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-white font-bold text-lg">Cognitive Wellness Exam</h2>
            <p class="text-violet-100 text-sm"><?= h($patient['first_name'] . ' ' . $patient['last_name']) ?> &mdash; <?= h(PRACTICE_NAME) ?></p>
        </div>
    </div>

    <div id="wiz-header" class="px-6 pt-5 pb-2"></div>

    <form id="mainForm" method="POST" action="<?= BASE_URL ?>/api/save_form.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="form_type"  value="cognitive_wellness">
        <input type="hidden" name="step1_score" id="step1_total" value="0">
        <input type="hidden" name="step2_score" id="step2_total" value="0">
        <input type="hidden" name="step3_score" id="step3_total" value="0">
        <input type="hidden" id="wiz-form-key" value="cognitive_wellness_<?= $patient_id ?>">

        <div class="px-6 pb-2">
        <?php include __DIR__ . '/../includes/form_company_selector.php'; ?>

            <!-- Step 0: Patient Exam -->
            <div class="wiz-step space-y-6 py-4" data-step="0" data-title="Patient Exam" data-icon="bi-person-bounding-box">

            <!-- Header -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Today's Date</label>
                    <input type="date" name="form_date" value="<?= date('Y-m-d') ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Assessed By</label>
                    <input type="text" name="assessed_by" value="<?= h($_SESSION['full_name'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition focus:bg-white">
                </div>
            </div>

            <!-- STEP 1 -->
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-7 h-7 bg-violet-600 text-white rounded-lg grid place-items-center text-xs font-bold">1</div>
                    <h3 class="font-bold text-slate-700 text-base">Patient Examination</h3>
                    <span class="text-xs text-slate-500 italic">— unless specified, each question should only be asked once</span>
                </div>

                <!-- Q1 Recall -->
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-4">
                    <p class="text-sm font-bold text-slate-700 mb-2">1. Recall Test</p>
                    <p class="text-sm text-slate-600 italic leading-relaxed mb-3">
                        Look directly at the patient and say: "Please listen carefully. I am going to give you a name and address that I want you to repeat back to me now and try to remember. Remember this name and address because I am going to ask you to tell it to me again in a few minutes:
                        <strong class="text-violet-700">Sarah Lee, 39 South Street, New York.</strong>
                        Please say the name and address for me now." (Allow a maximum of 4 attempts.)
                    </p>
                    <!-- This is scored as part of Q5 recall -->
                    <p class="text-xs text-slate-400 italic">Scored in Question 5 (Recall)</p>
                </div>

                <!-- Q2 Time Orientation -->
                <?php
                $step1Questions = [
                    ['q' => '2', 'label' => 'Time Orientation', 'desc' => 'What is the date?', 'max' => 1],
                    ['q' => '3a', 'label' => 'Clock Drawing — Numbers', 'desc' => 'Please mark in all the numbers to indicate the hours of a clock. (Correct spacing required; repeat instructions as needed.)', 'max' => 1],
                    ['q' => '3b', 'label' => 'Clock Drawing — Hands (9:20)', 'desc' => 'Please mark in hands to show 20 minutes past nine o\'clock (9:20). (Repeat instructions as needed. If clock is not completed within 3 minutes, move on.)', 'max' => 1],
                    ['q' => '4', 'label' => 'Information', 'desc' => 'Can you tell me something that happened in the news recently? (Recently = in the last week. If a general answer is given, ask for details. Only specific answers score.)', 'max' => 1],
                ];
                foreach ($step1Questions as $sq): ?>
                <div class="border border-slate-200 rounded-xl p-4 mb-3">
                    <p class="text-sm font-bold text-slate-700 mb-1"><?= $sq['q'] ?>. <?= h($sq['label']) ?></p>
                    <p class="text-sm text-slate-500 italic mb-3"><?= h($sq['desc']) ?></p>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 px-4 py-2.5 border border-slate-200 rounded-xl cursor-pointer
                                      hover:border-emerald-300 hover:bg-emerald-50 transition-colors has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50">
                            <input type="radio" name="q<?= $sq['q'] ?>" value="correct" class="step1-score w-4 h-4 text-emerald-600 border-slate-300 focus:ring-emerald-400">
                            <span class="text-sm font-semibold text-emerald-700">CORRECT (1/1)</span>
                        </label>
                        <label class="flex items-center gap-2 px-4 py-2.5 border border-slate-200 rounded-xl cursor-pointer
                                      hover:border-red-300 hover:bg-red-50 transition-colors has-[:checked]:border-red-400 has-[:checked]:bg-red-50">
                            <input type="radio" name="q<?= $sq['q'] ?>" value="incorrect" class="step1-score w-4 h-4 text-red-500 border-slate-300 focus:ring-red-400">
                            <span class="text-sm font-semibold text-red-600">INCORRECT (0/1)</span>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Q5 Recall -->
                <div class="border border-slate-200 rounded-xl p-4 mb-3">
                    <p class="text-sm font-bold text-slate-700 mb-3">5. Recall — What was the name and address I asked you to remember?</p>
                    <?php
                    $recallItems = ['Sarah','Lee','39','South (St.)','New York'];
                    foreach ($recallItems as $ri): ?>
                    <div class="flex items-center gap-4 mb-2">
                        <span class="text-sm text-slate-600 w-20 font-medium"><?= h($ri) ?></span>
                        <label class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg cursor-pointer
                                      hover:border-emerald-300 hover:bg-emerald-50 transition-colors has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50">
                            <input type="radio" name="recall_<?= strtolower(str_replace([' ','(','.',')'], '_', $ri)) ?>" value="correct"
                                   class="step1-score w-3.5 h-3.5 text-emerald-600 border-slate-300 focus:ring-emerald-400">
                            <span class="text-xs font-semibold text-emerald-700">CORRECT (1)</span>
                        </label>
                        <label class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg cursor-pointer
                                      hover:border-red-300 hover:bg-red-50 transition-colors has-[:checked]:border-red-400 has-[:checked]:bg-red-50">
                            <input type="radio" name="recall_<?= strtolower(str_replace([' ','(','.',')'], '_', $ri)) ?>" value="incorrect"
                                   class="step1-score w-3.5 h-3.5 text-red-500 border-slate-300 focus:ring-red-400">
                            <span class="text-xs font-semibold text-red-600">INCORRECT (0)</span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Q6 Language -->
                <div class="border border-slate-200 rounded-xl p-4 mb-4">
                    <p class="text-sm font-bold text-slate-700 mb-1">6. Language</p>
                    <p class="text-sm text-slate-500 italic mb-3">Give a stage 3 command: "Place index finger of your right hand on your nose and then on your left ear." (Score 1 point for each stage.)</p>
                    <div class="flex flex-wrap gap-3">
                        <?php
                        $langOpts = [['3/3','correct','3'],['2/3','partial2','2'],['1/3','partial1','1'],['0/3','incorrect','0']];
                        foreach ($langOpts as [$label,$val,$pts]): ?>
                        <label class="flex items-center gap-2 px-4 py-2.5 border border-slate-200 rounded-xl cursor-pointer text-sm
                                      hover:border-violet-300 hover:bg-violet-50 transition-colors has-[:checked]:border-violet-400 has-[:checked]:bg-violet-50">
                            <input type="radio" name="q6_language" value="<?= $pts ?>" class="step1-score w-4 h-4 text-violet-600 border-slate-300 focus:ring-violet-400">
                            <span class="font-semibold text-slate-700"><?= $pts ?>/3</span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Step 1 Score Summary -->
                <div class="bg-violet-50 border border-violet-200 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-bold text-violet-700">Step 1 Total Score:</span>
                        <span class="text-2xl font-extrabold text-violet-800" id="step1_display">0</span>
                        <span class="text-sm text-violet-600">/ 12</span>
                    </div>
                    <div id="step1_result" class="mt-3 p-3 rounded-xl text-sm bg-slate-100 border border-slate-200 text-slate-500">
                        Select answers above to calculate score.
                    </div>
                </div>
            </div>

            </div><!-- /step 0 -->

            <!-- Step 1: Informant Interview -->
            <div class="wiz-step hidden space-y-6 py-4" data-step="1" data-title="Informant Interview" data-icon="bi-people-fill">

            <!-- STEP 2 -->
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-7 h-7 bg-violet-600 text-white rounded-lg grid place-items-center text-xs font-bold">2</div>
                    <h3 class="font-bold text-slate-700 text-base">Informant Interview</h3>
                    <span class="text-xs text-slate-500 italic">— skip if patient lives alone</span>
                </div>
                <p class="text-sm text-slate-500 italic mb-4 ml-9">These 6 questions ask how the patient is compared to when he/she was 5-10 years ago</p>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Informant's Name</label>
                        <input type="text" name="informant_name"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition focus:bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Relationship to Patient</label>
                        <input type="text" name="informant_relationship"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition focus:bg-white">
                    </div>
                </div>

                <?php
                $step2Qs = [
                    ['n'=>'s2q1','text'=>'Does the patient have more trouble remembering things that have happened recently than he/she used to?'],
                    ['n'=>'s2q2','text'=>'Does he/she have more trouble recalling conversations a few days later?'],
                    ['n'=>'s2q3','text'=>'When speaking, does the patient have more difficulty in finding the right word or tend to use the wrong words more often?'],
                    ['n'=>'s2q4','text'=>'Is the patient less able to manage money and financial affairs (paying bills, budgeting, etc.)?'],
                    ['n'=>'s2q5','text'=>'Is the patient less able to manage his/her medications independently?'],
                    ['n'=>'s2q6','text'=>'Does the patient need more assistance with transport (either private or public)? (If patient has difficulties only due to physical problems, e.g. bad leg, check "NO")'],
                ];
                foreach ($step2Qs as $i => $q): ?>
                <div class="border border-slate-200 rounded-xl p-4 mb-2">
                    <p class="text-sm text-slate-700 mb-3"><?= ($i+1) ?>. <?= h($q['text']) ?></p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach (['YES' => '', 'NO' => 'step2-no', 'UNSURE' => 'step2-unsure', 'N/A' => 'step2-na'] as $opt => $cls): ?>
                        <label class="flex items-center gap-2 px-3.5 py-2 border border-slate-200 rounded-lg cursor-pointer text-xs font-semibold
                                      hover:border-violet-300 hover:bg-violet-50 transition-colors has-[:checked]:border-violet-400 has-[:checked]:bg-violet-50">
                            <input type="radio" name="<?= $q['n'] ?>" value="<?= strtolower(str_replace('/','',$opt)) ?>"
                                   class="<?= $cls ?> w-3.5 h-3.5 text-violet-600 border-slate-300 focus:ring-violet-400">
                            <span><?= $opt ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="bg-violet-50 border border-violet-200 rounded-xl p-4 mt-3">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-bold text-violet-700">Step 2 Score (NO + UNSURE + N/A):</span>
                        <span class="text-2xl font-extrabold text-violet-800" id="step2_display">0</span>
                        <span class="text-sm text-violet-600">/ 6</span>
                    </div>
                    <div id="step2_result" class="mt-3 p-3 rounded-xl text-sm bg-slate-100 border border-slate-200 text-slate-500">
                        Select answers above to calculate score.
                    </div>
                </div>
            </div>

            </div><!-- /step 1 -->

            <!-- Step 2: MIS & Notes -->
            <div class="wiz-step hidden space-y-6 py-4" data-step="2" data-title="MIS &amp; Notes" data-icon="bi-brain">

            <!-- STEP 3 MIS -->
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-7 h-7 bg-violet-600 text-white rounded-lg grid place-items-center text-xs font-bold">3</div>
                    <h3 class="font-bold text-slate-700 text-base">Memory Impairment Screen (MIS)</h3>
                </div>

                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-4 text-sm text-slate-600 space-y-2 leading-relaxed">
                    <p class="font-semibold text-slate-700">Instructions:</p>
                    <ol class="list-decimal list-inside space-y-1.5">
                        <li>Show patient a sheet of paper with the 4 items below in 24-point or greater uppercase letters, and ask the patient to read the items aloud.</li>
                        <li>Tell patient that each item belongs to a different category. Give a category cue and ask patient to indicate which of the words belongs in the stated category. Allow up to 5 attempts.</li>
                        <li>When patient identifies all 4 words, remove the sheet. Tell patient he/she will be asked to remember the words in a few minutes.</li>
                        <li>Engage patient in distractor activity: count to 20 and back, ask what is 7 less than 100, spell WORLD backwards.</li>
                        <li><strong>FREE RECALL – 2 points per word:</strong> Ask patient to state as many of the 4 words as he/she can remember.</li>
                        <li><strong>CUED RECALL – 1 point per word:</strong> Read the category cue for each word not recalled.</li>
                    </ol>
                </div>

                <!-- Word card display -->
                <div class="bg-white border-2 border-violet-300 rounded-2xl p-6 mb-4 text-center">
                    <p class="text-xs font-bold text-violet-500 uppercase tracking-widest mb-4">WORD LIST</p>
                    <div class="grid grid-cols-2 gap-3">
                        <?php foreach (['CHECKERS','SAUCER','TELEGRAM','RED CROSS'] as $word): ?>
                        <span class="text-2xl font-black text-slate-800 tracking-wide py-2"><?= $word ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Scoring Table -->
                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Word</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Cue</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Free Recall (2 pts)</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Cued Recall (1 pt)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php
                            $mis = [['Checkers','Game'],['Saucer','Dish'],['Telegram','Message'],['Red Cross','Organization']];
                            foreach ($mis as $m): ?>
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-700"><?= $m[0] ?></td>
                                <td class="px-4 py-3 text-slate-500"><?= $m[1] ?></td>
                                <td class="px-4 py-3">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="mis_free_<?= strtolower($m[0]) ?>" value="2"
                                               class="step3-score w-4 h-4 text-violet-600 border-slate-300 rounded focus:ring-violet-400">
                                        <span class="text-xs text-violet-700 font-semibold">2 pts</span>
                                    </label>
                                </td>
                                <td class="px-4 py-3">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="mis_cued_<?= strtolower($m[0]) ?>" value="1"
                                               class="step3-score w-4 h-4 text-violet-600 border-slate-300 rounded focus:ring-violet-400">
                                        <span class="text-xs text-violet-700 font-semibold">1 pt</span>
                                    </label>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="bg-violet-50 border border-violet-200 rounded-xl p-4 mt-3">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-bold text-violet-700">Step 3 MIS Score:</span>
                        <span class="text-2xl font-extrabold text-violet-800" id="step3_display">0</span>
                        <span class="text-sm text-violet-600">/ 8 &nbsp;|&nbsp; 5-8 = No impairment &nbsp;|&nbsp; ≤4 = Possible impairment</span>
                    </div>
                    <div id="step3_result" class="mt-3 p-3 rounded-xl text-sm bg-slate-100 border border-slate-200 text-slate-500">
                        Check boxes above to calculate score.
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Clinical Notes</label>
                <textarea name="clinical_notes" rows="3"
                          class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition focus:bg-white resize-none"
                          placeholder="Additional observations or follow-up plan..."></textarea>
            </div>

            </div><!-- /step 2 -->

            <!-- Step 3: Sign & Submit -->
            <div class="wiz-step hidden py-4" data-step="3" data-title="Sign" data-icon="bi-pen">
                <?php include __DIR__ . '/../includes/sig_block.php'; ?>
            </div><!-- /step 3 -->

            <?php
            $accentClass = 'bg-violet-600 hover:bg-violet-700';
            $cancelUrl   = BASE_URL . '/patient_view.php?id=' . $patient_id;
            include __DIR__ . '/../includes/wiz_nav.php';
            ?>

        </div><!-- /px-6 -->
    </form>
</div><!-- /card -->
</div><!-- /max-w-3xl -->

<?php include __DIR__ . '/../includes/footer.php'; ?>
