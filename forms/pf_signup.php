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

$pageTitle = 'Patient Fusion Portal Consent';
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
    <span class="text-slate-700 font-semibold">Patient Fusion Portal</span>
</nav>

<div class="max-w-2xl">
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-5">
    <div class="bg-gradient-to-r from-cyan-700 to-cyan-600 px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-xl">
            <i class="bi bi-envelope-at-fill text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-white font-bold text-lg">Patient Fusion Portal Consent</h2>
            <p class="text-cyan-100 text-sm"><?= h($patient['first_name'] . ' ' . $patient['last_name']) ?></p>
        </div>
    </div>

    <form id="mainForm" method="POST" action="<?= BASE_URL ?>/api/save_form.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="form_type"  value="pf_signup">

        <div class="p-6 space-y-6">

            <!-- Consent Text -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-sm text-slate-700 space-y-3 leading-relaxed">
                <p>
                    I acknowledge that I have read and fully understand this consent form. I have been given the
                    risks and benefits of Patient Fusion and understand the risks associated with online communications
                    between our office and patients.
                </p>
                <p>
                    By signing below and providing an e-mail address, I hereby give my informed consent to
                    participate in Patient Fusion and I hereby agree to and accept the provisions contained above.
                </p>
                <p>
                    I acknowledge that the e-mail address provided belongs to me or my authorized representative
                    and that I will receive Patient Fusion enrollment instructions, including applicable terms of
                    service, to the address if I agree to participate.
                </p>
                <p class="font-bold underline decoration-2">
                    By declining and not providing an email, my signature indicated that I am informed about
                    Patient Fusion being offered to me, but I do not wish to participate.
                </p>
                <p>
                    I understand I may choose to participate at any time in the future by requesting to update my
                    response to this agreement with the practice.
                </p>
                <p>
                    A copy of this agreement will be provided to you and one will also be included in your medical
                    record with our practice.
                </p>
            </div>

            <!-- Participate / Decline -->
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-3">Please check one:</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer
                                  hover:border-cyan-400 hover:bg-cyan-50/50 transition-colors has-[:checked]:border-cyan-500 has-[:checked]:bg-cyan-50">
                        <input type="radio" name="pf_decision" value="participate" id="pf_participate"
                               class="w-4 h-4 text-cyan-600 border-slate-300 focus:ring-cyan-400">
                        <span class="font-semibold text-slate-700">Participate in Patient Fusion</span>
                    </label>
                    <label class="flex items-center gap-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer
                                  hover:border-red-300 hover:bg-red-50/50 transition-colors has-[:checked]:border-red-400 has-[:checked]:bg-red-50">
                        <input type="radio" name="pf_decision" value="decline"
                               class="w-4 h-4 text-red-500 border-slate-300 focus:ring-red-400">
                        <span class="font-semibold text-slate-700">Decline</span>
                    </label>
                </div>
            </div>

            <!-- Email (shown when participating) -->
            <div id="emailSection">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Email, if participating:
                </label>
                <input type="email" name="patient_email" value="<?= h($patient['email'] ?? '') ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition focus:bg-white"
                       placeholder="patient@email.com">
            </div>

            <!-- Representative -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-3">
                    Representative <span class="text-slate-400 font-normal">(if signing on behalf of patient)</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Representative Name</label>
                        <input type="text" name="representative_name"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition focus:bg-white"
                               placeholder="Full name">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Date</label>
                        <input type="date" name="form_date" value="<?= date('Y-m-d') ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition focus:bg-white">
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
                   bg-cyan-600 hover:bg-cyan-700 active:scale-95 text-white font-bold
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
