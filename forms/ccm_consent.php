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
$_coName = 'Beyond Wound Care Inc.';
$_coUC   = strtoupper($_coName);

// One-signature rule: redirect to existing signed form if already signed today
$_dupQ = $pdo->prepare("SELECT id FROM form_submissions WHERE patient_id = ? AND form_type = 'ccm_consent' AND status IN ('signed','uploaded') AND DATE(created_at) = CURDATE() LIMIT 1");
$_dupQ->execute([$patient_id]);
if ($_dupId = $_dupQ->fetchColumn()) { header('Location: ' . BASE_URL . '/view_document.php?id=' . (int)$_dupId . '&already_signed=1'); exit; }

$pageTitle = 'CCM Consent';
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
    <span class="text-slate-700 font-semibold">CCM Consent</span>
</nav>

<div class="max-w-3xl mx-auto">

<div id="wiz-resume-banner" class="hidden flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-2xl px-5 py-3.5 mb-4 text-sm text-blue-800">
    <i class="bi bi-floppy-fill text-blue-500 text-lg shrink-0"></i>
    <div class="flex-1"><span class="font-semibold">Unsaved draft found.</span> Resume where you left off?</div>
    <button id="wiz-resume-yes" class="px-4 py-1.5 bg-blue-600 text-white text-xs font-bold rounded-lg hover:bg-blue-700 transition-colors">Resume</button>
    <button id="wiz-resume-no"  class="px-4 py-1.5 bg-white border border-blue-200 text-blue-600 text-xs font-bold rounded-lg hover:bg-blue-50 transition-colors ml-1">Start fresh</button>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="bg-gradient-to-r from-emerald-700 to-emerald-600 px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-xl">
            <i class="bi bi-calendar2-heart-fill text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-white font-bold text-lg">Consent Agreement for Provision of Chronic Care Management</h2>
            <p class="text-emerald-100 text-sm"><?= h(PRACTICE_NAME) ?></p>
        </div>
    </div>

    <div id="wiz-header" class="px-6 pt-5 pb-2"></div>

    <form id="mainForm" method="POST" action="<?= BASE_URL ?>/api/save_form.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="form_type"  value="ccm_consent">
        <input type="hidden" id="wiz-form-key" value="ccm_consent_<?= $patient_id ?>">

        <div class="px-6 pb-2">
        <?php include __DIR__ . '/../includes/form_company_selector.php'; ?>

            <!-- Step 0: About CCM -->
            <div class="wiz-step space-y-6 py-4" data-step="0" data-title="About CCM" data-icon="bi-heart-pulse">

            <div class="max-w-xs">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date</label>
                <input type="date" name="form_date" value="<?= date('Y-m-d') ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition focus:bg-white">
            </div>

            <!-- Agreement Text -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-sm text-slate-700 space-y-3 leading-relaxed">
                <p>
                    By signing this Agreement, you consent to <strong class="co-name-display">Beyond Wound Care Inc.</strong> (referred to as "Provider"),
                    providing chronic care management services (referred to as "CCM Services") to you as more fully described below.
                </p>
                <p>
                    CCM Services are available to you because you have been diagnosed with two (2) or more chronic conditions
                    which are expected to last at least twelve (12) months and which place you at significant risk of further decline.
                </p>
                <p>
                    CCM Services include 24-hours-a-day, 7-days-a-week access to a health care provider in Provider's practice to
                    address acute chronic care needs; systematic assessment of your health care needs; processes to assure that you
                    timely receive preventative care services; medication reviews and oversight; a plan of care covering your health
                    issues; and management of care transitions among health care providers and settings.
                </p>
            </div>

            <!-- Provider Obligations -->
            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-2">Provider's Obligations</h3>
                <p class="text-xs text-slate-500 italic mb-3">When providing CCM Services, the Provider must:</p>
                <ul class="text-sm text-slate-700 space-y-2">
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-emerald-500 mt-0.5 flex-shrink-0"></i>
                        Explain to you (and your caregiver, if applicable), and offer to you, all the CCM Services that are applicable to your conditions.
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-emerald-500 mt-0.5 flex-shrink-0"></i>
                        Provide to you a written or electronic copy of your care plan.
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi bi-check-circle text-emerald-500 mt-0.5 flex-shrink-0"></i>
                        If you revoke this Agreement, provide you with a written confirmation of the revocation, stating the effective date of the revocation.
                    </li>
                </ul>
            </div>

            </div><!-- /step 0 -->

            <!-- Step 1: Acknowledgments -->
            <div class="wiz-step hidden space-y-6 py-4" data-step="1" data-title="Acknowledgments" data-icon="bi-clipboard2-check-fill">

                <div>
                <h3 class="text-sm font-bold text-slate-700 mb-2">Beneficiary Acknowledgment and Authorization</h3>
                <p class="text-xs text-slate-500 italic mb-3">By signing this Agreement, you agree to the following:</p>
                <div class="space-y-2">
                    <?php
                    $auths = [
                        'ack_consent'   => 'I consent to the Provider providing CCM Services to me.',
                        'ack_electronic'=> 'I authorize electronic communication of my medical information with other treating providers as part of coordination of my care.',
                        'ack_one_only'  => 'I acknowledge that only one practitioner can furnish CCM Services to me during a calendar month.',
                        'ack_copay'     => 'I understand that cost-sharing will apply to CCM Services, so I may be billed for a portion of CCM Services even though CCM Services will not involve a face-to-face meeting with the Provider.',
                    ];
                    foreach ($auths as $name => $text): ?>
                    <label class="flex items-start gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer
                                  hover:border-emerald-300 hover:bg-emerald-50/50 transition-colors has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50">
                        <input type="checkbox" name="<?= $name ?>" value="1"
                               required data-label="<?= h($text) ?>"
                               class="mt-0.5 w-4 h-4 text-emerald-600 border-slate-300 rounded focus:ring-emerald-400 flex-shrink-0">
                        <span class="text-sm text-slate-700"><?= h($text) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Beneficiary Rights -->
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                <h3 class="text-sm font-bold text-emerald-800 mb-2">Beneficiary Rights</h3>
                <ul class="text-sm text-emerald-700 space-y-1.5">
                    <li class="flex items-start gap-2">
                        <i class="bi bi-info-circle flex-shrink-0 mt-0.5"></i>
                        The Provider will provide you with a written or electronic copy of your care plan.
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="bi bi-info-circle flex-shrink-0 mt-0.5"></i>
                        You have the right to stop CCM Services at any time by revoking this Agreement effective at the end of the then-current month. You may revoke this agreement verbally or in writing to <strong class="co-name-display">Beyond Wound Care Inc.</strong>.
                    </li>
                </ul>
            </div>

            <!-- Witness info -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Witness Name (Print)</label>
                    <input type="text" name="witness_name"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">MA / Staff Name</label>
                    <input type="text" name="ma_name" value="<?= h($_SESSION['full_name'] ?? '') ?>"
                           required data-label="MA / Staff Name"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition focus:bg-white">
                </div>
            </div>

            </div><!-- /step 1 -->

            <!-- Step 2: Sign & Submit -->
            <div class="wiz-step hidden py-4" data-step="2" data-title="Sign" data-icon="bi-pen">
                <?php include __DIR__ . '/../includes/sig_block.php'; ?>
            </div><!-- /step 2 -->

            <?php
            $accentClass = 'bg-emerald-600 hover:bg-emerald-700';
            $cancelUrl   = BASE_URL . '/patient_view.php?id=' . $patient_id;
            include __DIR__ . '/../includes/wiz_nav.php';
            ?>

        </div><!-- /px-6 -->
    </form>
</div><!-- /card -->
</div><!-- /max-w-3xl -->

<?php include __DIR__ . '/../includes/footer.php'; ?>
