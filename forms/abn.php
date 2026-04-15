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
$_dupQ = $pdo->prepare("SELECT id FROM form_submissions WHERE patient_id = ? AND form_type = 'abn' AND status IN ('signed','uploaded') AND DATE(created_at) = CURDATE() LIMIT 1");
$_dupQ->execute([$patient_id]);
if ($_dupId = $_dupQ->fetchColumn()) { header('Location: ' . BASE_URL . '/view_document.php?id=' . (int)$_dupId . '&already_signed=1'); exit; }

$pageTitle = 'Advance Beneficiary Notice (ABN)';
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
    <span class="text-slate-700 font-semibold">ABN</span>
</nav>

<div class="max-w-3xl">
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-5">
    <div class="bg-gradient-to-r from-amber-600 to-amber-500 px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-xl">
            <i class="bi bi-file-earmark-ruled-fill text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-white font-bold text-lg">Advance Beneficiary Notice of Non-coverage</h2>
            <p class="text-amber-100 text-sm">Form CMS-R-131 &mdash; <?= h($patient['first_name'] . ' ' . $patient['last_name']) ?></p>
        </div>
    </div>

    <form id="mainForm" method="POST" action="<?= BASE_URL ?>/api/save_form.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="form_type"  value="abn">

        <div class="p-6 space-y-6">

            <!-- A/B/C header fields -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">A. Notifier</label>
                    <input type="text" name="notifier" value="<?= h(PRACTICE_NAME) ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent transition focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">C. Identification Number</label>
                    <input type="text" name="id_number"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent transition focus:bg-white"
                           placeholder="Optional">
                </div>
            </div>

            <!-- Notice Banner -->
            <div class="bg-amber-50 border-2 border-amber-300 rounded-xl p-4">
                <p class="text-sm font-bold text-amber-900 mb-1">
                    NOTE: If Medicare doesn't pay for the Home visit below, you may have to pay.
                </p>
                <p class="text-sm text-amber-800 leading-relaxed">
                    Medicare does not pay for everything, even some care that you or your health care provider
                    have good reason to think you need. We expect Medicare may not pay for the <strong>20%</strong> below.
                </p>
            </div>

            <!-- D / E / F Table -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-3">Service Description</label>
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <div class="grid grid-cols-1 sm:grid-cols-3 border-b border-slate-200">
                        <div class="bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-500 uppercase tracking-wide border-b sm:border-b-0 sm:border-r border-slate-200">D. Service / Item</div>
                        <div class="bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-500 uppercase tracking-wide border-b sm:border-b-0 sm:border-r border-slate-200">E. Reason Medicare May Not Pay</div>
                        <div class="bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-500 uppercase tracking-wide">F. Estimated Cost</div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3">
                        <div class="p-4 sm:border-r border-slate-200">
                            <textarea name="service_description" rows="4"
                                      class="w-full text-sm bg-transparent border-0 focus:outline-none resize-none text-slate-700"
                                      placeholder="Medicare covers 80% of home visit for wound care. If secondary insurance is available, will bill 20% to secondary insurance."
                                      >Medicare covers 80% of home visit for wound care.
If secondary insurance is available will bill 20% to secondary insurance.</textarea>
                        </div>
                        <div class="p-4 sm:border-r border-slate-200">
                            <textarea name="reason_not_paid" rows="4"
                                      class="w-full text-sm bg-transparent border-0 focus:outline-none resize-none text-slate-700"
                                      placeholder="Medicare covers 80% leaving 20% to be billed..."
                                      >Medicare covers 80% leaving 20% to be billed. This will be billed to a secondary insurance if available. If patient does not have a secondary insurance will be billed FOR 20%</textarea>
                        </div>
                        <div class="p-4">
                            <input type="text" name="estimated_cost" value="20%"
                                   class="w-full text-sm bg-transparent border-b border-slate-300 focus:outline-none focus:border-amber-400 text-slate-700 font-semibold py-1">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Date -->
            <div class="max-w-xs">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date</label>
                <input type="date" name="form_date" value="<?= date('Y-m-d') ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent transition focus:bg-white">
            </div>

            <!-- What you need to do -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                <p class="text-sm font-bold text-slate-700 mb-2">WHAT YOU NEED TO DO NOW:</p>
                <ul class="text-sm text-slate-600 space-y-1 list-disc list-inside">
                    <li>Read this notice, so you can make an informed decision about your care.</li>
                    <li>Ask us any questions that you may have after you finish reading.</li>
                    <li>Choose an option below about whether to receive the <strong>20%</strong> listed above.</li>
                </ul>
                <p class="text-xs text-slate-500 mt-2">Note: If you choose Option 1 or 2, we may help you to use any other insurance that you might have, but Medicare cannot require us to do this.</p>
            </div>

            <!-- G. Options -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-3">G. OPTIONS â€” Check only one box. We cannot choose a box for you.</label>
                <div class="space-y-3">
                    <label class="flex items-start gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer
                                  hover:border-amber-300 hover:bg-amber-50/50 transition-colors has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50">
                        <input type="radio" name="patient_option" value="1"
                               class="mt-0.5 w-4 h-4 text-amber-500 border-slate-300 focus:ring-amber-400 flex-shrink-0">
                        <div class="text-sm text-slate-700">
                            <span class="font-bold">OPTION 1.</span> I want the Office visit listed above. You may ask to be paid now, but I also want Medicare billed for an official decision on payment, which is sent to me on a Medicare Summary Notice (MSN). I understand that if Medicare doesn't pay, I am responsible for payment, but I can appeal to Medicare by following the directions on the MSN. If Medicare does pay, you will refund any payments I made to you, less co-pays or deductibles.
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer
                                  hover:border-amber-300 hover:bg-amber-50/50 transition-colors has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50">
                        <input type="radio" name="patient_option" value="2"
                               class="mt-0.5 w-4 h-4 text-amber-500 border-slate-300 focus:ring-amber-400 flex-shrink-0">
                        <div class="text-sm text-slate-700">
                            <span class="font-bold">OPTION 2.</span> I want the Office visit listed above, but do not bill Medicare. You may ask to be paid now as I am responsible for payment. I cannot appeal if Medicare is not billed.
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer
                                  hover:border-amber-300 hover:bg-amber-50/50 transition-colors has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50">
                        <input type="radio" name="patient_option" value="3"
                               class="mt-0.5 w-4 h-4 text-amber-500 border-slate-300 focus:ring-amber-400 flex-shrink-0">
                        <div class="text-sm text-slate-700">
                            <span class="font-bold">OPTION 3.</span> I don't want the Office visit listed above. I understand with this choice I am <strong>not</strong> responsible for payment, and I cannot appeal to see if Medicare would pay.
                        </div>
                    </label>
                </div>
            </div>

            <!-- H. Additional Information -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">H. Additional Information</label>
                <textarea name="additional_info" rows="2"
                          class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent transition focus:bg-white resize-none"
                          placeholder="Additional notes..."></textarea>
            </div>

            <p class="text-xs text-slate-500 leading-relaxed">
                This notice gives our opinion, not an official Medicare decision. If you have other questions on this notice or Medicare billing, call <strong>1-800-MEDICARE</strong> (1-800-633-4227/TTY: 1-877-486-2048).
                Signing below means that you have received and understand this notice. You may ask to receive a copy.
            </p>
            <p class="text-xs text-slate-400">Form CMS-R-131 (Exp.01/31/2026) &mdash; Form Approved OMB No. 0938-0566</p>

        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/sig_block.php'; ?>

<div class="mt-5 flex flex-col sm:flex-row gap-3">
    <button id="submitBtn" type="button"
            class="flex-1 sm:flex-none flex items-center justify-center gap-2
                   bg-amber-500 hover:bg-amber-600 active:scale-95 text-white font-bold
                   px-10 py-3.5 rounded-xl transition-all shadow-md hover:shadow-lg text-base">
        <i class="bi bi-check2-circle text-xl"></i> Submit &amp; Save
    </button>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patient_id ?>"
       class="flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl text-sm font-semibold
              text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors">
        Cancel
    </a>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
