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
$_dupQ = $pdo->prepare("SELECT id FROM form_submissions WHERE patient_id = ? AND form_type = 'il_disclosure' AND status IN ('signed','uploaded') AND DATE(created_at) = CURDATE() LIMIT 1");
$_dupQ->execute([$patient_id]);
if ($_dupId = $_dupQ->fetchColumn()) { header('Location: ' . BASE_URL . '/view_document.php?id=' . (int)$_dupId . '&already_signed=1'); exit; }

$dob = $patient['dob'] ?? '';
$formattedDob = $dob ? date('m/d/Y', strtotime($dob)) : '';

$pageTitle = 'IL DHS Authorization to Disclose';
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
    <span class="text-slate-700 font-semibold">IL DHS Authorization to Disclose</span>
</nav>

<div class="max-w-3xl">

<div id="wiz-resume-banner" class="hidden flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-2xl px-5 py-3.5 mb-4 text-sm text-blue-800">
    <i class="bi bi-floppy-fill text-blue-500 text-lg shrink-0"></i>
    <div class="flex-1"><span class="font-semibold">Unsaved draft found.</span> Resume where you left off?</div>
    <button id="wiz-resume-yes" class="px-4 py-1.5 bg-blue-600 text-white text-xs font-bold rounded-lg hover:bg-blue-700 transition-colors">Resume</button>
    <button id="wiz-resume-no"  class="px-4 py-1.5 bg-white border border-blue-200 text-blue-600 text-xs font-bold rounded-lg hover:bg-blue-50 transition-colors ml-1">Start fresh</button>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="bg-gradient-to-r from-slate-700 to-slate-600 px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-xl">
            <i class="bi bi-file-earmark-text text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-white font-bold text-lg">Authorization to Disclose / Obtain Information</h2>
            <p class="text-slate-200 text-sm">State of Illinois — Department of Human Services</p>
        </div>
    </div>

    <div id="wiz-header" class="px-6 pt-5 pb-2"></div>

    <form id="mainForm" method="POST" action="<?= BASE_URL ?>/api/save_form.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
        <input type="hidden" name="form_type"  value="il_disclosure">
        <input type="hidden" id="wiz-form-key" value="il_disclosure_<?= $patient_id ?>">

        <div class="px-6 pb-2">

            <!-- Step 0: Authorization & Records -->
            <div class="wiz-step space-y-7 py-4" data-step="0" data-title="Authorization &amp; Records" data-icon="bi-key-fill">

            <!-- Section 1 -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-7 h-7 bg-slate-700 text-white rounded-lg grid place-items-center text-xs font-bold">1</span>
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wide">Authorization Type</h3>
                </div>
                <p class="text-sm text-slate-700 mb-3">
                    I authorize <strong><?= h(PRACTICE_NAME) ?></strong> to:
                </p>
                <div class="flex flex-wrap gap-4">
                    <?php foreach (['disclose' => 'Disclose information', 'obtain' => 'Obtain information', 'both' => 'Both disclose and obtain'] as $val => $lbl): ?>
                    <label class="flex items-center gap-2 px-4 py-3 border border-slate-200 rounded-xl cursor-pointer text-sm font-medium
                                  hover:border-slate-400 hover:bg-slate-50 has-[:checked]:border-slate-500 has-[:checked]:bg-slate-50 transition-colors">
                        <input type="radio" name="auth_type" value="<?= $val ?>"
                               class="w-4 h-4 text-slate-700 border-slate-300 focus:ring-slate-500">
                        <?= h($lbl) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Section 2 -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-7 h-7 bg-slate-700 text-white rounded-lg grid place-items-center text-xs font-bold">2</span>
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wide">Type of Records</h3>
                </div>
                <p class="text-sm text-slate-500 italic mb-3 leading-relaxed">
                    Note: Checking "Mental Health," "Alcohol & Substance Use," "HIV/AIDS," or other sensitive records does not authorize the release of records for discrimination purposes.
                </p>
                <?php
                $recordTypes = [
                    'all'                => 'Complete / All Records',
                    'discharge_summary'  => 'Discharge Summary',
                    'inpatient'         => 'Inpatient Records',
                    'outpatient'        => 'Outpatient Records',
                    'psychiatric'       => 'Psychiatric Records',
                    'psych_eval'        => 'Psychiatric Evaluation',
                    'mental_health'     => 'Mental Health Records',
                    'alcohol_substance' => 'Alcohol & Substance Use Records (42 CFR Part 2)',
                    'hiv_aids'          => 'HIV / AIDS Records',
                    'genetic'           => 'Genetic Information',
                    'lab'               => 'Laboratory / Pathology Reports',
                    'xray'              => 'Radiology / X-Ray',
                    'other'             => 'Other',
                ];
                ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach ($recordTypes as $key => $label): ?>
                    <label class="flex items-center gap-2.5 px-4 py-3 border border-slate-200 rounded-xl cursor-pointer text-sm
                                  hover:border-slate-400 hover:bg-slate-50 has-[:checked]:border-slate-500 has-[:checked]:bg-slate-50 transition-colors">
                        <input type="checkbox" name="record_types[]" value="<?= h($key) ?>"
                               class="w-4 h-4 text-slate-700 border-slate-300 rounded focus:ring-slate-500">
                        <?= h($label) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date Range of Records</label>
                    <div class="flex gap-3 items-center">
                        <input type="date" name="records_from"
                               class="flex-1 px-4 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent transition focus:bg-white">
                        <span class="text-slate-500 text-sm">to</span>
                        <input type="date" name="records_to" value="<?= date('Y-m-d') ?>"
                               class="flex-1 px-4 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent transition focus:bg-white">
                    </div>
                </div>
            </div>

            </div><!-- /step 0 -->

            <!-- Step 1: Patient ID & Purpose -->
            <div class="wiz-step hidden space-y-7 py-4" data-step="1" data-title="Patient ID &amp; Purpose" data-icon="bi-person-vcard">

            <!-- Section 3 -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-7 h-7 bg-slate-700 text-white rounded-lg grid place-items-center text-xs font-bold">3</span>
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wide">Patient Identification</h3>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Patient Full Name</label>
                        <input type="text" name="patient_name"
                               value="<?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent transition focus:bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date of Birth</label>
                        <input type="text" name="patient_dob" value="<?= h($formattedDob) ?>" placeholder="MM/DD/YYYY"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent transition focus:bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Social Security # (last 4 digits)</label>
                        <input type="text" name="patient_ssn" maxlength="4" placeholder="XXXX"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent transition focus:bg-white">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Alias / Other Name (if applicable)</label>
                        <input type="text" name="patient_alias"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent transition focus:bg-white">
                    </div>
                </div>
            </div>

            <!-- Section 4 -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-7 h-7 bg-slate-700 text-white rounded-lg grid place-items-center text-xs font-bold">4</span>
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wide">Purpose of Disclosure</h3>
                </div>
                <?php
                $purposes = [
                    'personal_use'       => 'Personal Use',
                    'continuity_care'    => 'Continuity of Care',
                    'placement_transfer' => 'Placement / Transfer',
                    'legal'              => 'Legal / Judicial',
                    'insurance'          => 'Insurance / Benefits',
                    'research'           => 'Research',
                    'other'              => 'Other',
                ];
                ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach ($purposes as $key => $label): ?>
                    <label class="flex items-center gap-2.5 px-4 py-3 border border-slate-200 rounded-xl cursor-pointer text-sm
                                  hover:border-slate-400 hover:bg-slate-50 has-[:checked]:border-slate-500 has-[:checked]:bg-slate-50 transition-colors">
                        <input type="checkbox" name="purposes[]" value="<?= h($key) ?>"
                               class="w-4 h-4 text-slate-700 border-slate-300 rounded focus:ring-slate-500">
                        <?= h($label) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Explain Purpose (if Other)</label>
                    <input type="text" name="purpose_other"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent transition focus:bg-white">
                </div>
            </div>

            </div><!-- /step 1 -->

            <!-- Step 2: Recipients & Legal -->
            <div class="wiz-step hidden space-y-7 py-4" data-step="2" data-title="Recipients &amp; Legal" data-icon="bi-building">

            <!-- Section 5 -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-7 h-7 bg-slate-700 text-white rounded-lg grid place-items-center text-xs font-bold">5</span>
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wide">Method of Disclosure</h3>
                </div>
                <div class="flex flex-wrap gap-3">
                    <?php foreach (['Mail','In-Person','Phone','Email','Fax'] as $method): ?>
                    <label class="flex items-center gap-2 px-4 py-3 border border-slate-200 rounded-xl cursor-pointer text-sm font-medium
                                  hover:border-slate-400 hover:bg-slate-50 has-[:checked]:border-slate-500 has-[:checked]:bg-slate-50 transition-colors">
                        <input type="radio" name="disclosure_method" value="<?= strtolower($method) ?>"
                               class="w-4 h-4 text-slate-700 border-slate-300 focus:ring-slate-500">
                        <?= h($method) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Section 6 -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-7 h-7 bg-slate-700 text-white rounded-lg grid place-items-center text-xs font-bold">6</span>
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wide">Disclose To / Obtain From</h3>
                </div>
                <?php for ($i = 1; $i <= 2; $i++): ?>
                <div class="border border-slate-200 rounded-xl p-4 mb-3">
                    <p class="text-xs font-bold text-slate-500 uppercase mb-3">Entry <?= $i ?></p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Name of Facility / Provider / Organization</label>
                            <input type="text" name="recipient_name_<?= $i ?>"
                                   <?= $i===1 ? 'value="' . h(PRACTICE_NAME) . '"' : '' ?>
                                   class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50
                                          focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent transition focus:bg-white">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Address</label>
                            <input type="text" name="recipient_address_<?= $i ?>"
                                   <?= $i===1 ? 'value="' . h(PRACTICE_ADDRESS) . '"' : '' ?>
                                   class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50
                                          focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent transition focus:bg-white">
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <!-- Section 7 -->
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-7 h-7 bg-slate-700 text-white rounded-lg grid place-items-center text-xs font-bold">7</span>
                    <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wide">Authorization Valid Until</h3>
                </div>
                <p class="text-sm text-slate-500 mb-3 italic">This authorization expires on (leave blank for "one year from today"):</p>
                <input type="date" name="expiration_date"
                       class="px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-transparent transition focus:bg-white">
            </div>

            <!-- Sections 8-11: Legal notices -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-xs text-slate-600 leading-relaxed space-y-3">
                <p class="text-xs font-bold text-slate-700 uppercase tracking-wide mb-1">Important Legal Information</p>
                <p><strong>Section 8 — Sensitive Records:</strong> Records pertaining to mental health treatment, alcohol and substance abuse, HIV/AIDS status, or genetic information may only be released pursuant to this authorization if you have specifically checked those boxes in Section 2 above. Re-disclosure of such records by the recipient may be prohibited by federal and state law.</p>
                <p><strong>Section 9 — Right to Revoke:</strong> You have the right to revoke this authorization in writing at any time. To revoke, submit a written request to <?= h(PRACTICE_NAME) ?> at <?= h(PRACTICE_ADDRESS) ?>. Revocation does not apply to actions already taken in reliance on this authorization.</p>
                <p><strong>Section 10 — Consequences of Refusal:</strong> Your treatment, payment, enrollment, and eligibility for benefits generally may NOT be conditioned on signing this authorization, except in limited circumstances. You may refuse to sign this authorization.</p>
                <p><strong>Section 11 — Re-disclosure:</strong> Information disclosed under this authorization may be subject to re-disclosure by the recipient and may no longer be protected by federal HIPAA privacy rules unless otherwise prohibited by law.</p>
            </div>

            </div><!-- /step 2 -->

            <!-- Step 3: Sign & Submit -->
            <div class="wiz-step hidden py-4" data-step="3" data-title="Sign" data-icon="bi-pen">
                <?php include __DIR__ . '/../includes/sig_block.php'; ?>
            </div><!-- /step 3 -->

            <?php
            $accentClass = 'bg-slate-700 hover:bg-slate-800';
            $cancelUrl   = BASE_URL . '/patient_view.php?id=' . $patient_id;
            include __DIR__ . '/../includes/wiz_nav.php';
            ?>

        </div><!-- /px-6 -->
    </form>
</div><!-- /card -->
</div><!-- /max-w-3xl -->

<?php include __DIR__ . '/../includes/footer.php'; ?>
