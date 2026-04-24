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

// One-signature rule
$_dupQ = $pdo->prepare("SELECT id FROM form_submissions WHERE patient_id = ? AND form_type = 'wound_care_consent' AND status IN ('signed','uploaded') AND DATE(created_at) = CURDATE() LIMIT 1");
$_dupQ->execute([$patient_id]);
if ($_dupId = $_dupQ->fetchColumn()) { header('Location: ' . BASE_URL . '/view_document.php?id=' . (int)$_dupId . '&already_signed=1'); exit; }

$pageTitle = 'Wound Care Consent';
$activeNav = 'patients';
include __DIR__ . '/../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6 flex-wrap">
    <a href="<?= BASE_URL ?>/patients.php" class="hover:text-blue-600 font-medium">Patients</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patient_id ?>" class="hover:text-blue-600 font-medium">
        <?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>
    </a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold">Wound Care Consent</span>
</nav>

<div class="max-w-3xl mx-auto">

<div id="wiz-resume-banner" class="hidden flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-2xl px-5 py-3.5 mb-4 text-sm text-blue-800">
    <i class="bi bi-floppy-fill text-blue-500 text-lg shrink-0"></i>
    <div class="flex-1"><span class="font-semibold">Unsaved draft found.</span> Resume where you left off?</div>
    <button id="wiz-resume-yes" class="px-4 py-1.5 bg-blue-600 text-white text-xs font-bold rounded-lg hover:bg-blue-700 transition-colors">Resume</button>
    <button id="wiz-resume-no"  class="px-4 py-1.5 bg-white border border-blue-200 text-blue-600 text-xs font-bold rounded-lg hover:bg-blue-50 transition-colors ml-1">Start fresh</button>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="bg-gradient-to-r from-rose-700 to-rose-600 px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-xl">
            <i class="bi bi-bandaid-fill text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-white font-bold text-lg">Informed Consent for Wound Care Treatment</h2>
            <p class="text-rose-100 text-sm"><?= h(PRACTICE_NAME) ?></p>
        </div>
    </div>

    <div id="wiz-header" class="px-6 pt-5 pb-2"></div>

    <form id="mainForm" method="POST" action="<?= BASE_URL ?>/api/save_form.php" novalidate>
        <input type="hidden" name="csrf_token"  value="<?= csrfToken() ?>">
        <input type="hidden" name="patient_id"  value="<?= $patient_id ?>">
        <input type="hidden" name="form_type"   value="wound_care_consent">
        <input type="hidden" id="wiz-form-key"  value="wound_care_consent_<?= $patient_id ?>">

        <div class="px-6 pb-2">
        <?php include __DIR__ . '/../includes/form_company_selector.php'; ?>

            <!-- ── Step 0: Consent Overview ──────────────────────────────── -->
            <div class="wiz-step space-y-5 py-4" data-step="0" data-title="Overview" data-icon="bi-file-medical">

                <div class="max-w-xs">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date</label>
                    <input type="date" name="form_date" value="<?= date('Y-m-d') ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent transition focus:bg-white">
                </div>

                <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-sm text-slate-700 space-y-3 leading-relaxed">
                    <p>Patient/caregiver hereby voluntarily consents to wound care treatment by the provider (MD/NP) of <strong>BEYOND WOUND CARE INC.</strong> This consent form will remain valid as long as the patient is active and receiving services.</p>
                    <p><strong>Patient has the right to give or refuse consent to any proposed service or treatment.</strong></p>
                </div>

                <!-- Sections 1–6 static text -->
                <?php
                $sections = [
                    ['General Description of Wound Care Treatment',
                     'Patient acknowledges that physician/NP has explained their treatment for wound care, which can include, but not be limited to: debridement, dressing changes, skin grafts, off-loading devices, physical examinations and treatment, diagnostic procedures, laboratory work (such as wound care cultures), request x-rays, other imaging studies and administration of medications prescribed by a physician and or NP. Patient acknowledges that the physician/NP has given them the opportunity to ask any questions related to the services or treatments being provided and that the physician/NP answered all questions.'],
                    ['Benefits of Wound Care Treatment',
                     'Patient acknowledges that physician/NP has explained the benefits of wound care treatment, which include enhanced wound healing and reduced risks of amputation and infection.'],
                    ['Risks and Side Effects of Wound Care Treatment',
                     'Patient acknowledges that physician/NP has explained that wound care treatment may cause side effects and risks including, but not limited to: infection, pain and inflammation, bleeding, allergic reaction to topical and injected local anesthetics or skin prep solutions, removal of healthy tissue, delayed healing or failure to heal, possible scarring and possible damage to: blood vessels, surrounding tissues, and nerves.'],
                    ['Likelihood of Achieving Goals',
                     'Patient acknowledges that physician/NP has explained the proposed treatment plan that they are more than likely to have optimized treatment outcomes; however, any service or treatment carry the risk of unsuccessful results, complications and injuries, from both known and unforeseen causes.'],
                    ['General Description of Wound Debridement',
                     'Patient acknowledges that physician/NP has explained that wound debridement means the removal of unhealthy tissue from a wound to promote healing. During the course of treatment, multiple wound debridement\'s may be necessary.'],
                    ['Risks/Side Effects of Wound Debridement',
                     'Patient acknowledges the physician/NP has explained the risks and/or complications of wound debridement include, but are not limited to: potential scarring, possible allergic reactions to topical and injected local anesthetics or skin prep solutions, excessive bleeding, removal of healthy tissue, infection, ongoing pain and inflammation, and failure to heal. Patient specifically acknowledges that physician/NP has explained that bleeding after debridement may cause rapid deterioration of an already compromised patient.'],
                ];
                foreach ($sections as $i => $s): ?>
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <button type="button" onclick="this.nextElementSibling.classList.toggle('hidden')"
                            class="w-full flex items-center justify-between px-4 py-3 bg-slate-50 hover:bg-slate-100 transition text-left">
                        <span class="text-sm font-semibold text-slate-700"><?= ($i+1) . '. ' . htmlspecialchars($s[0]) ?></span>
                        <i class="bi bi-chevron-down text-slate-400 text-xs"></i>
                    </button>
                    <div class="px-4 py-3 text-sm text-slate-600 leading-relaxed hidden">
                        <?= htmlspecialchars($s[1]) ?>
                    </div>
                </div>
                <?php endforeach; ?>

            </div><!-- /step 0 -->

            <!-- ── Step 1: Images & PHI ──────────────────────────────────── -->
            <div class="wiz-step hidden space-y-5 py-4" data-step="1" data-title="Images &amp; PHI" data-icon="bi-camera-fill">

                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50">
                        <p class="text-sm font-semibold text-slate-700">7. Patient Identification and Wound Images</p>
                    </div>
                    <div class="px-4 py-3 text-sm text-slate-600 leading-relaxed space-y-2">
                        <p>Patient/caregiver understands and consents that images (digital, film, etc.) may be taken by BWC of patient's wounds with their surrounding anatomic features. The purpose of these images is to monitor the progress of wound treatment and ensure continuity of care.</p>
                        <p>Patient/caregiver further agrees that their referring physician/NP may receive communications, including these images, regarding patient's treatment plan and results. The images are considered protected health information and will be handled in accordance with federal laws regarding the privacy, security and confidentiality of such information.</p>
                        <p>Patient understands that BEYOND WOUND CARE INC. will retain ownership rights to these images, but the patient will be allowed access to view or obtain copies according to state and Federal law. Patient waives any and all rights to royalties or other compensation for these images. Images that identify the patient will only be released and/or used outside BWC upon written authorization from the patient or patient's legal representative.</p>
                    </div>
                </div>

                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50">
                        <p class="text-sm font-semibold text-slate-700">8. Use and Disclosure of Protected Health Information (PHI)</p>
                    </div>
                    <div class="px-4 py-3 text-sm text-slate-600 leading-relaxed space-y-2">
                        <p>Patient consents to BWC use of PHI, results of patient's medical history and physical examination and wound images obtained during the course of patient's wound care treatment and stored in BEYOND WOUND CARE INC. wound database for purposes of education and quality assessment.</p>
                        <p>Patient's PHI may be disclosed by BEYOND WOUND CARE INC. to its affiliated companies, and third parties who have executed a Business Associate Agreement. Disclosure of patient's PHI shall be in compliance with the privacy regulations of the Health Insurance Portability and Accountability Act of 1996 (HIPAA).</p>
                        <p>Patient/caregiver specifically authorizes use and disclosure of patient's PHI by BEYOND WOUND CARE INC., its affiliates, and business associates for purposes related to treatment, payment and health care operations.</p>
                    </div>
                </div>

                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50">
                        <p class="text-sm font-semibold text-slate-700">9. Financial Responsibility</p>
                    </div>
                    <div class="px-4 py-3 text-sm text-slate-600 leading-relaxed">
                        <p>Patient/caregiver understands that regardless of his or her assigned insurance benefits, patient is responsible for any amount not covered by insurance. Patient authorizes medical information to be released to any payor and their respective agent to determine benefits or the benefits payable for related services.</p>
                    </div>
                </div>

                <!-- Acknowledgment checkboxes -->
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-2">Patient Acknowledgments (Sections 1–9)</h3>
                    <p class="text-xs text-slate-500 italic mb-3">Check each box to confirm the patient/caregiver has read and understands each section.</p>
                    <div class="space-y-2">
                        <?php
                        $acks = [
                            'ack_treatment'    => 'I understand the general description of wound care treatment (Section 1).',
                            'ack_benefits'     => 'I understand the benefits of wound care treatment (Section 2).',
                            'ack_risks'        => 'I understand the risks and side effects of wound care treatment (Section 3).',
                            'ack_goals'        => 'I understand the likelihood of achieving goals (Section 4).',
                            'ack_debridement'  => 'I understand the general description of wound debridement (Section 5).',
                            'ack_debride_risk' => 'I understand the risks/side effects of wound debridement (Section 6).',
                            'ack_images'       => 'I consent to wound images being taken and used as described (Section 7).',
                            'ack_phi'          => 'I authorize use and disclosure of Protected Health Information as described (Section 8).',
                            'ack_financial'    => 'I understand my financial responsibility (Section 9).',
                        ];
                        foreach ($acks as $name => $text): ?>
                        <label class="flex items-start gap-3 p-3 border border-slate-200 rounded-xl cursor-pointer
                                      hover:border-rose-300 hover:bg-rose-50/50 transition-colors has-[:checked]:border-rose-400 has-[:checked]:bg-rose-50">
                            <input type="checkbox" name="<?= $name ?>" value="1"
                                   class="mt-0.5 w-4 h-4 text-rose-600 border-slate-300 rounded focus:ring-rose-400 flex-shrink-0">
                            <span class="text-sm text-slate-700"><?= htmlspecialchars($text) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- MA / Staff info -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Witness Name (Print)</label>
                        <input type="text" name="witness_name"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent transition focus:bg-white"
                               placeholder="Staff witness">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">MA / Staff Name</label>
                        <input type="text" name="ma_name" value="<?= h($_SESSION['full_name'] ?? '') ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-transparent transition focus:bg-white">
                    </div>
                </div>

            </div><!-- /step 1 -->

            <!-- ── Step 2: Sign & Submit ──────────────────────────────────── -->
            <div class="wiz-step hidden py-4" data-step="2" data-title="Sign" data-icon="bi-pen">
                <?php include __DIR__ . '/../includes/sig_block.php'; ?>
            </div><!-- /step 2 -->

            <?php
            $accentClass = 'bg-rose-600 hover:bg-rose-700';
            $cancelUrl   = BASE_URL . '/patient_view.php?id=' . $patient_id;
            include __DIR__ . '/../includes/wiz_nav.php';
            ?>

        </div><!-- /px-6 -->
    </form>
</div><!-- /card -->
</div><!-- /max-w-3xl -->

<?php include __DIR__ . '/../includes/footer.php'; ?>
