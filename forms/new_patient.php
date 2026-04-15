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

$pageTitle = 'New Patient Consent';
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
    <span class="text-slate-700 font-semibold">New Patient Consent</span>
</nav>

<div class="max-w-3xl">
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-5">
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-xl">
            <i class="bi bi-person-plus-fill text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-white font-bold text-lg">New Patient Consent</h2>
            <p class="text-blue-100 text-sm"><?= h($patient['first_name'] . ' ' . $patient['last_name']) ?></p>
        </div>
    </div>

    <form id="mainForm" method="POST" action="<?= BASE_URL ?>/api/save_form.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="form_type"  value="new_patient">

        <div class="p-6 space-y-6">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date</label>
                    <input type="date" name="form_date" value="<?= date('Y-m-d') ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">MA Name</label>
                    <input type="text" name="ma_name" value="<?= h($_SESSION['full_name'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition focus:bg-white">
                </div>
            </div>

            <!-- Consent Sections -->
            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-3">Consent to Treatment</h3>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-slate-600 leading-relaxed mb-3">
                    I, the undersigned, consent to and authorize the delivery of health care services by physicians,
                    medical staff, and other healthcare providers as deemed necessary. I understand that the practice of
                    medicine is not an exact science, and that no specific outcomes or results can be guaranteed.
                </div>
                <label class="flex items-center gap-3 p-3.5 border border-slate-200 rounded-xl cursor-pointer
                              hover:border-blue-300 hover:bg-blue-50/50 transition-colors has-[:checked]:border-blue-400 has-[:checked]:bg-blue-50">
                    <input type="checkbox" name="consent_treatment" value="1"
                           class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-400">
                    <span class="text-sm font-medium text-slate-700">I consent to treatment as described above.</span>
                </label>
            </div>

            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-3">HIPAA Privacy Notice</h3>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-slate-600 leading-relaxed mb-3">
                    I acknowledge receipt of the Notice of Privacy Practices, which explains how my protected health
                    information (PHI) may be used and disclosed. I understand my rights regarding my PHI.
                </div>
                <label class="flex items-center gap-3 p-3.5 border border-slate-200 rounded-xl cursor-pointer
                              hover:border-blue-300 hover:bg-blue-50/50 transition-colors has-[:checked]:border-blue-400 has-[:checked]:bg-blue-50">
                    <input type="checkbox" name="consent_hipaa" value="1"
                           class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-400">
                    <span class="text-sm font-medium text-slate-700">I have received and reviewed the HIPAA Notice of Privacy Practices.</span>
                </label>
            </div>

            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-3">Financial Responsibility</h3>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-slate-600 leading-relaxed mb-3">
                    I agree to pay all charges not covered by my insurance, including co-pays, deductibles, and
                    coinsurance. I authorize the release of medical information to my insurance carrier for billing purposes.
                </div>
                <label class="flex items-center gap-3 p-3.5 border border-slate-200 rounded-xl cursor-pointer
                              hover:border-blue-300 hover:bg-blue-50/50 transition-colors has-[:checked]:border-blue-400 has-[:checked]:bg-blue-50">
                    <input type="checkbox" name="consent_financial" value="1"
                           class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-400">
                    <span class="text-sm font-medium text-slate-700">I accept financial responsibility for all charges.</span>
                </label>
            </div>

            <!-- Emergency Contact -->
            <div>
                <h3 class="text-sm font-bold text-slate-700 mb-3">Emergency Contact</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Name</label>
                        <input type="text" name="emergency_name"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition focus:bg-white"
                               placeholder="Full name">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Relationship</label>
                        <input type="text" name="emergency_relationship"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition focus:bg-white"
                               placeholder="Spouse, Parent...">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Phone</label>
                        <input type="tel" name="emergency_phone"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition focus:bg-white"
                               placeholder="(555) 555-5555">
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/sig_block.php'; ?>

<div class="mt-5 flex flex-col sm:flex-row gap-3">
    <button id="submitBtn" type="button"
            class="flex-1 sm:flex-none flex items-center justify-center gap-2
                   bg-blue-600 hover:bg-blue-700 active:scale-95 text-white font-bold
                   px-10 py-3.5 rounded-xl transition-all shadow-md hover:shadow-lg text-base">
        <i class="bi bi-check2-circle text-xl"></i> Submit & Save
    </button>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patient_id ?>"
       class="flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl text-sm font-semibold
              text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors">
        Cancel
    </a>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
