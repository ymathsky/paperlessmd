<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireNotBilling();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

$patient = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$patient->execute([$id]);
$patient = $patient->fetch();
if (!$patient) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

$pageTitle = 'Edit ' . $patient['first_name'] . ' ' . $patient['last_name'];
$activeNav = 'patients';

// Load current diagnoses for display
try {
    $dxStmt = $pdo->prepare("SELECT icd_code, icd_desc FROM patient_diagnoses WHERE patient_id = ? ORDER BY added_at DESC");
    $dxStmt->execute([$id]);
    $patientDxList = $dxStmt->fetchAll();
} catch (PDOException $e) {
    $patientDxList = [];
}

$error = '';
$vals  = $patient;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $vals = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name'  => trim($_POST['last_name']  ?? ''),
        'dob'        => trim($_POST['dob']         ?? ''),
        'phone'      => trim($_POST['phone']       ?? ''),
        'email'      => trim($_POST['email']       ?? ''),
        'address'    => trim($_POST['address']     ?? ''),
        'insurance'  => trim($_POST['insurance']   ?? ''),
        'pcp'        => trim($_POST['pcp']         ?? ''),
    ];
    if (!$vals['first_name'] || !$vals['last_name']) {
        $error = 'First and last name are required.';
    } else {
        $stmt = $pdo->prepare("UPDATE patients
            SET first_name=?, last_name=?, dob=?, phone=?, email=?, address=?, insurance=?, pcp=?
            WHERE id=?");
        $stmt->execute([...array_values($vals), $id]);
        header('Location: ' . BASE_URL . '/patient_view.php?id=' . $id . '&msg=updated');
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6 flex-wrap">
    <a href="<?= BASE_URL ?>/patients.php" class="hover:text-blue-600 transition-colors font-medium">Patients</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $id ?>" class="hover:text-blue-600 transition-colors font-medium">
        <?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>
    </a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold">Edit</span>
</nav>

<div class="max-w-2xl">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
            <h2 class="text-white font-bold text-lg flex items-center gap-2">
                <i class="bi bi-pencil-fill"></i> Edit Patient
            </h2>
            <p class="text-blue-200 text-sm mt-0.5"><?= h($patient['first_name'] . ' ' . $patient['last_name']) ?></p>
        </div>

        <div class="p-6">
            <?php if ($error): ?>
            <div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
                <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i> <?= h($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                            First Name <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="first_name" value="<?= h($vals['first_name']) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition"
                               required autofocus>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Last Name <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="last_name" value="<?= h($vals['last_name']) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition"
                               required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date of Birth</label>
                        <input type="date" name="dob" value="<?= h($vals['dob']) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Phone</label>
                        <input type="tel" name="phone" value="<?= h($vals['phone']) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email Address</label>
                    <input type="email" name="email" value="<?= h($vals['email']) ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Address</label>
                    <input type="text" name="address" value="<?= h($vals['address']) ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Insurance</label>
                        <input type="text" name="insurance" value="<?= h($vals['insurance']) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">PCP</label>
                        <input type="text" name="pcp" value="<?= h($vals['pcp']) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
                    </div>
                </div>

                <?php if (!empty($patientDxList)): ?>
                <div class="mb-5">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        <i class="bi bi-clipboard2-pulse text-orange-500 mr-1"></i>Active Diagnoses
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($patientDxList as $dx): ?>
                        <span class="inline-flex items-center gap-1.5 bg-orange-50 border border-orange-200 text-orange-700 text-xs font-medium px-2.5 py-1 rounded-full">
                            <span class="font-mono font-bold"><?= h($dx['icd_code']) ?></span>
                            <span class="text-orange-600"><?= h($dx['icd_desc']) ?></span>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Manage diagnoses from the <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $id ?>&tab=diagnoses" class="text-orange-600 hover:underline">Diagnoses tab</a>.</p>
                </div>
                <?php endif; ?>

                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit"
                            class="flex-1 sm:flex-none flex items-center justify-center gap-2
                                   bg-blue-600 hover:bg-blue-700 active:scale-95 text-white font-semibold
                                   px-8 py-3 rounded-xl transition-all shadow-sm hover:shadow-md">
                        <i class="bi bi-check-circle-fill"></i> Save Changes
                    </button>
                    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $id ?>"
                       class="flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold
                              text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
