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
$_coName = ($patient['company'] ?? '') === 'Visiting Medical Physician Inc.' ? 'Visiting Medical Physician Inc.' : 'Beyond Wound Care Inc.';
$_coUC   = strtoupper($_coName);
$_coAbb  = ($_coName === 'Visiting Medical Physician Inc.') ? 'VMP' : 'BWC';

// One-signature rule
$_dupQ = $pdo->prepare("SELECT id FROM form_submissions WHERE patient_id = ? AND form_type = 'informed_consent_wound' AND status IN ('signed','uploaded') AND DATE(created_at) = CURDATE() LIMIT 1");
$_dupQ->execute([$patient_id]);
if ($_dupId = $_dupQ->fetchColumn()) { header('Location: ' . BASE_URL . '/view_document.php?id=' . (int)$_dupId . '&already_signed=1'); exit; }

$pageTitle = 'Informed Consent for Wound Care';
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
    <span class="text-slate-700 font-semibold">Informed Consent for Wound Care</span>
</nav>

<div class="max-w-3xl mx-auto">
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

    <!-- Header bar -->
    <div class="bg-gradient-to-r from-red-800 to-red-700 px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-xl">
            <i class="bi bi-file-earmark-medical-fill text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-white font-bold text-lg">Informed Consent for Wound Care Treatment</h2>
            <p class="text-red-100 text-sm"><?= h(PRACTICE_NAME) ?></p>
        </div>
    </div>

    <form id="mainForm" method="POST" action="<?= BASE_URL ?>/api/save_form.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="form_type"  value="informed_consent_wound">

        <div class="px-6 py-6 space-y-6">

            <!-- Patient name / DOB -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Patient Name</label>
                    <input type="text" name="patient_name"
                           value="<?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date of Birth</label>
                    <input type="text" name="dob"
                           value="<?= $patient['dob'] ? date('m/d/Y', strtotime($patient['dob'])) : '' ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition focus:bg-white">
                </div>
            </div>

            <!-- Full consent text (read-only display) -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-sm text-slate-700 space-y-4 leading-relaxed max-h-[480px] overflow-y-auto">
                <p>Patient/caregiver hereby voluntarily consents to wound care treatment by the provider (MD/NP) of <strong><?= $_coUC ?></strong>. Patient/Caregiver understands that this consent form will be valid and remain in effect as long as the patient remains active and receives services and treatments at <?= $_coUC ?>. A new consent form will be obtained when a patient is discharged and returns for services and treatments. <strong>Patient has the right to give or refuse consent to any proposed service or treatment.</strong></p>

                <ol class="space-y-3 pl-4 list-decimal">
                    <li><strong>General Description of Wound Care Treatment:</strong> Patient acknowledges that physician/NP has explained their treatment for wound care, which can include, but not be limited to: debridement, dressing changes, skin grafts, off-loading devices, physical examinations and treatment, diagnostic procedures, laboratory work (such as wound care cultures), request x-rays, other imaging studies and administration of medications prescribed by a physician and or NP. Patient acknowledges that the physician/NP has given them the opportunity to ask any questions related to the services or treatments being provided and that the physician/NP answered all questions.</li>
                    <li><strong>Benefits of Wound Care Treatment:</strong> Patient acknowledges that physician/NP has explained the benefits of wound care treatment, which include enhanced wound healing and reduced risks of amputation and infection.</li>
                    <li><strong>Risks and Side Effects of Wound Care Treatment:</strong> Patient acknowledges that physician/NP has explained that wound care treatment may cause side effects and risks including, but not limited to: infection, pain and inflammation, bleeding, allergic reaction to topical and injected local anesthetics or skin prep solutions, removal of healthy tissue, delayed healing or failure to heal, possible scarring and possible damage to: blood vessels, surrounding tissues, and nerves.</li>
                    <li><strong>Likelihood of achieving goals:</strong> Patient acknowledges that physician/NP has explained the proposed treatment plan that they are more than likely to have optimized treatment outcomes; however, any service or treatment carry the risk of unsuccessful results, complications and injuries, from both known and unforeseen causes.</li>
                    <li><strong>General Description of Wound Debridement:</strong> Patient acknowledges that physician/NP has explained that wound debridement means the removal of unhealthy tissue from a wound to promote healing. During the course of treatment, multiple wound debridement's may be necessary.</li>
                    <li><strong>Risks/Side Effects of Wound Debridement:</strong> Patient acknowledges the physician/NP has explained the risks and/or complications of wound debridement include, but are not limited to: potential scarring, possible allergic reactions to topical and injected local anesthetics or skin prep solutions, excessive bleeding, removal of healthy tissue, infection, ongoing pain and inflammation, and failure to heal. Patient specifically acknowledges that physician/NP has explained that bleeding after debridement may cause rapid deterioration of an already compromised patient.</li>
                    <li><strong>Patient Identification and Wound Images:</strong> Patient/caregiver understands and consents that images (digital, film, etc.) may be taken by <?= $_coAbb ?> of patient's wounds with their surrounding anatomic features. The purpose of these images is to monitor the progress of wound treatment and ensure continuity of care. Patient/caregiver further agrees that their referring physician/NP may receive communications, including these images, regarding patient's treatment plan and results. The images are considered protected health information and will be handled in accordance with federal laws regarding the privacy, security and confidentiality of such information. Patient understands that <?= $_coUC ?> will retain ownership rights to these images, but the patient will be allowed access to view or obtain copies according to state and Federal law. Patient waives any and all rights to royalties or other compensation for these images. Images that identify the patient will only be released and/or used outside <?= $_coUC ?> (<?= $_coAbb ?>) upon written authorization from the patient or patient's legal representative.</li>
                    <li><strong>Use and Disclosure of Protected Health Information (PHI):</strong> Patient consents to <?= $_coAbb ?> use of PHI, results of patient's medical history and physical examination and wound images obtained during the course of patient's wound care treatment and stored in <?= $_coUC ?> wound database for purposes of education, and quality assessment. Patient's PHI may be disclosed by <?= $_coUC ?> to its affiliated companies, and third parties who have executed a Business Associate Agreement. Disclosure of patient's PHI shall be in compliance with the privacy regulations of the Health Insurance Portability and Accountability Act of 1996 (HIPAA). Patient/caregiver specifically authorizes use and disclosure of patient's PHI by <?= $_coUC ?>, its affiliates, and business associates for purposes related to treatment, payment and health care operations. If patient wishes to request a restriction to how his/her PHI may be used or disclosed, patient may send a written request for restriction to <?= $_coUC ?>.</li>
                    <li><strong>Financial Responsibility:</strong> Patient/caregiver understands that regardless of his or her assigned insurance benefits, patient is responsible for any amount not covered by insurance. Patient authorizes medical information to be released to any payor and their respective agent to determine benefits or the benefits payable for related services.</li>
                </ol>

                <p>The patient/caregiver or POA hereby acknowledges that he or she has read and agrees to the contents of sections 1 through 9 of these documents. Patient agrees that his or her medical condition has been explained to him or her by the physician/NP. Patient agrees that the risks, benefits and alternatives of all care, treatment and services that patient will undergo while a patient at <?= $_coAbb ?> have been discussed with patient/caregiver by physician/NP. Patient understands the nature of his or her medical condition, the risks, alternatives and benefits of treatment, and the consequences of failure to seek or delay treatment for any conditions. Patient has read this document, or had it read to him/her and understands the contents herein. The patient has had the opportunity to ask questions of the physician and has received answers to all of his or her questions.</p>

                <p>By signing below, patient consents to the care, treatment and services described in this document and orally by the physician, consents to the creation of images to record his or her wounds and consents to the transfer of health information protected by HIPAA. The Physician has explained to the patient (or his or her legal representative), the nature of the treatment, reasonable alternatives, benefits, risks, side effects, likelihood of achieving patient's goals, complications and consequences which are/or may be associated with the treatment or procedure(s).</p>
            </div>

            <!-- MA name -->
            <div class="max-w-xs">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">MA / Staff Name</label>
                <input type="text" name="ma_name" value="<?= h($_SESSION['full_name'] ?? '') ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition focus:bg-white">
            </div>

            <!-- Signature -->
            <?php include __DIR__ . '/../includes/sig_block.php'; ?>

            <!-- Submit -->
            <div class="flex items-center justify-between pt-2 border-t border-slate-100 mt-4">
                <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patient_id ?>"
                   class="px-5 py-2.5 text-sm font-semibold text-slate-500 hover:text-slate-700 transition-colors">
                    Cancel
                </a>
                <button type="submit" id="submitBtn"
                        class="flex items-center gap-2 px-8 py-3 bg-red-700 hover:bg-red-800
                               text-white font-bold rounded-xl transition-all shadow-md hover:shadow-lg text-sm active:scale-95">
                    <i class="bi bi-check-circle-fill"></i> Sign &amp; Submit
                </button>
            </div>

        </div><!-- /px-6 -->
    </form>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
