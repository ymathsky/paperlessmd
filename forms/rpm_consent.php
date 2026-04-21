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
$_dupQ = $pdo->prepare("SELECT id FROM form_submissions WHERE patient_id = ? AND form_type = 'rpm_consent' AND status IN ('signed','uploaded') AND DATE(created_at) = CURDATE() LIMIT 1");
$_dupQ->execute([$patient_id]);
if ($_dupId = $_dupQ->fetchColumn()) { header('Location: ' . BASE_URL . '/view_document.php?id=' . (int)$_dupId . '&already_signed=1'); exit; }

$pageTitle = 'RPM Consent Form';
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
    <span class="text-slate-700 font-semibold">RPM Consent Form</span>
</nav>

<div class="max-w-3xl mx-auto">
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

    <!-- Header bar -->
    <div class="bg-gradient-to-r from-teal-700 to-teal-600 px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-xl">
            <i class="bi bi-broadcast text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-white font-bold text-lg">Remote Patient Monitoring (RPM) Consent Form</h2>
            <p class="text-teal-100 text-sm"><?= h(PRACTICE_NAME) ?></p>
        </div>
    </div>

    <form id="mainForm" method="POST" action="<?= BASE_URL ?>/api/save_form.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="form_type"  value="rpm_consent">

        <div class="px-6 py-6 space-y-6">

            <!-- Patient name -->
            <div class="max-w-sm">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Patient Name</label>
                <input type="text" name="patient_name"
                       value="<?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition focus:bg-white">
            </div>

            <!-- Consent text -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-sm text-slate-700 space-y-4 leading-relaxed max-h-[500px] overflow-y-auto">
                <p>I understand and agree to participate in the <strong>Remote Patient Monitoring (RPM) Program</strong>. I acknowledge the following:</p>

                <div>
                    <p class="font-bold text-slate-800 mb-1">Purpose of RPM Services</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>RPM services allow my healthcare provider to monitor my blood pressure remotely.</li>
                        <li>The goal of this program is to improve my health outcomes and detect potential health concerns early.</li>
                        <li>My healthcare provider will review my transmitted readings and contact me if necessary.</li>
                    </ul>
                </div>

                <div>
                    <p class="font-bold text-slate-800 mb-1">Equipment Use</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>I agree to use the RPM device as instructed.</li>
                        <li>I understand that I am responsible for properly caring for the equipment provided to me.</li>
                        <li>I will notify the healthcare team if the device is damaged, lost, or not working properly.</li>
                        <li>I agree to return the device if I discontinue participation in the program.</li>
                        <li>I understand that I am the only person who should be using the RPM.</li>
                    </ul>
                </div>

                <div>
                    <p class="font-bold text-slate-800 mb-1">Patient Responsibilities</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>I agree to take readings as instructed by my healthcare provider.</li>
                        <li>I agree to follow instructions regarding device use.</li>
                        <li>I understand that RPM services do not replace emergency medical care.</li>
                        <li>In case of emergency, I will call <strong>911</strong> or seek immediate medical attention.</li>
                    </ul>
                </div>

                <div>
                    <p class="font-bold text-slate-800 mb-1">Communication</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>I authorize the healthcare team to contact me regarding my readings.</li>
                        <li>I understand that I will be contacted every 30 days via phone to review and discuss my results.</li>
                    </ul>
                </div>

                <div>
                    <p class="font-bold text-slate-800 mb-1">Privacy and Confidentiality</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>My health information will be transmitted electronically and stored securely in accordance with applicable privacy laws (including HIPAA).</li>
                        <li>I understand that reasonable safeguards are used to protect my information.</li>
                    </ul>
                </div>

                <div>
                    <p class="font-bold text-slate-800 mb-1">Voluntary Participation</p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Participation in RPM services is voluntary.</li>
                        <li>I may withdraw from the program at any time by notifying my healthcare provider.</li>
                        <li>I understand that discontinuing RPM services will not affect my access to other medical care.</li>
                    </ul>
                </div>

                <p>I have read (or had read to me) this consent form. I understand the information provided and have had the opportunity to ask questions. I voluntarily agree to participate in the Remote Patient Monitoring (RPM) Program.</p>
            </div>

            <!-- RPM Serial # -->
            <div class="max-w-sm">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">RPM Device Serial #</label>
                <input type="text" name="rpm_serial"
                       placeholder="Enter serial number"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition focus:bg-white">
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
                        class="flex items-center gap-2 px-8 py-3 bg-teal-700 hover:bg-teal-800
                               text-white font-bold rounded-xl transition-all shadow-md hover:shadow-lg text-sm active:scale-95">
                    <i class="bi bi-check-circle-fill"></i> Sign &amp; Submit
                </button>
            </div>

        </div><!-- /px-6 -->
    </form>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
