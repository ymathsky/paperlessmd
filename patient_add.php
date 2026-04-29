<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/audit.php';
requireNotBilling();

$pageTitle = 'Add Patient';
$activeNav = 'patients';

$error = '';
$vals  = ['first_name'=>'','last_name'=>'','dob'=>'','phone'=>'','email'=>'','address'=>'','insurance'=>'','insurance_id'=>'','pcp'=>'','race'=>'','pharmacy_name'=>'','pharmacy_phone'=>'','pharmacy_address'=>'','assigned_ma'=>''];

// Load staff for MA assignment dropdown
$maStaff = $pdo->query("SELECT id, full_name, role FROM staff WHERE active=1 ORDER BY full_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $vals = [
        'first_name'       => trim($_POST['first_name'] ?? ''),
        'last_name'        => trim($_POST['last_name']  ?? ''),
        'dob'              => trim($_POST['dob']         ?? ''),
        'phone'            => trim($_POST['phone']       ?? ''),
        'email'            => trim($_POST['email']       ?? ''),
        'address'          => trim($_POST['address']     ?? ''),
        'insurance'        => trim($_POST['insurance']   ?? ''),
        'insurance_id'     => trim($_POST['insurance_id'] ?? ''),
        'pcp'              => trim($_POST['pcp']         ?? ''),
        'race'             => trim($_POST['race']        ?? ''),
        'pharmacy_name'    => trim($_POST['pharmacy_name']    ?? ''),
        'pharmacy_phone'   => trim($_POST['pharmacy_phone']   ?? ''),
        'pharmacy_address' => trim($_POST['pharmacy_address'] ?? ''),
        'assigned_ma'      => isAdmin() ? ((int)($_POST['assigned_ma'] ?? 0) ?: null) : (int)$_SESSION['user_id'],
    ];
    // Photo uploads
    foreach (['insurance_photo', 'insurance_photo_back', 'sss_photo'] as $_pk) {
        $raw = trim($_POST[$_pk] ?? '');
        $vals[$_pk] = ($raw && preg_match('/^data:image\/(jpeg|png|webp|gif);base64,[A-Za-z0-9+\/=]+$/', $raw)) ? $raw : null;
    }
    if (!$vals['first_name'] || !$vals['last_name']) {
        $error = 'First and last name are required.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO patients
            (first_name, last_name, dob, phone, email, address, insurance, insurance_id, pcp,
             race, pharmacy_name, pharmacy_phone, pharmacy_address,
             insurance_photo, insurance_photo_back, sss_photo,
             assigned_ma, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->execute([
            $vals['first_name'], $vals['last_name'], $vals['dob'] ?: null,
            $vals['phone'], $vals['email'], $vals['address'],
            $vals['insurance'], $vals['insurance_id'], $vals['pcp'],
            $vals['race'], $vals['pharmacy_name'], $vals['pharmacy_phone'], $vals['pharmacy_address'],
            $vals['insurance_photo'], $vals['insurance_photo_back'], $vals['sss_photo'],
            $vals['assigned_ma'],
        ]);
        $id = $pdo->lastInsertId();
        auditLog($pdo, 'patient_add', 'patient', (int)$id, $vals['first_name'] . ' ' . $vals['last_name']);
        header('Location: ' . BASE_URL . '/patient_view.php?id=' . $id . '&msg=created');
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6">
    <a href="<?= BASE_URL ?>/patients.php" class="hover:text-blue-600 transition-colors font-medium">Patients</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold">Add New Patient</span>
</nav>

<div class="max-w-2xl mx-auto">
    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
            <h2 class="text-white font-bold text-lg flex items-center gap-2">
                <i class="bi bi-person-plus-fill"></i> New Patient
            </h2>
            <p class="text-blue-200 text-sm mt-0.5">Enter patient information below</p>
        </div>

        <div class="p-6">
            <?php if ($error): ?>
            <div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
                <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i> <?= h($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                <!-- Name row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                            First Name <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="first_name" value="<?= h($vals['first_name']) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition"
                               placeholder="First name" required autofocus>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Last Name <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="last_name" value="<?= h($vals['last_name']) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition"
                               placeholder="Last name" required>
                    </div>
                </div>

                <!-- DOB + Phone -->
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
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition"
                               placeholder="(555) 555-5555">
                    </div>
                </div>

                <!-- Email -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email Address</label>
                        <input type="email" name="email" value="<?= h($vals['email']) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition"
                               placeholder="patient@email.com">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Phone</label>
                        <input type="tel" name="phone" value="<?= h($vals['phone']) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition"
                               placeholder="(555) 555-5555">
                    </div>
                </div>

                <!-- Address -->
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Address</label>
                    <input type="text" name="address" value="<?= h($vals['address']) ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition"
                           placeholder="Street, City, State ZIP">
                </div>

                <!-- Insurance + PCP -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Insurance</label>
                        <input type="text" name="insurance" value="<?= h($vals['insurance']) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition"
                               placeholder="Insurance provider">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Insurance Member ID</label>
                        <input type="text" name="insurance_id" value="<?= h($vals['insurance_id'] ?? '') ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition"
                               placeholder="Member / policy number">
                    </div>
                </div>

                <!-- Insurance Card Photos -->
                <div class="mb-4 p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
                    <p class="text-sm font-bold text-slate-700"><i class="bi bi-credit-card-2-front text-blue-500 mr-1"></i> Insurance Card Photos</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Front of Card</label>
                            <input type="hidden" name="insurance_photo" id="insPhotoFrontData" value="">
                            <label class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-slate-200 rounded-xl
                                          text-xs font-semibold text-slate-600 cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                                <i class="bi bi-camera text-blue-500"></i> Upload Photo
                                <input type="file" accept="image/*" class="sr-only pat-photo-input"
                                       data-target="insPhotoFrontData" data-thumb="insPhotoFrontThumb">
                            </label>
                            <img id="insPhotoFrontThumb" src="" class="hidden h-14 mt-2 rounded-lg border border-slate-200 object-cover">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Back of Card</label>
                            <input type="hidden" name="insurance_photo_back" id="insPhotoBackData" value="">
                            <label class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-slate-200 rounded-xl
                                          text-xs font-semibold text-slate-600 cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                                <i class="bi bi-camera text-blue-500"></i> Upload Photo
                                <input type="file" accept="image/*" class="sr-only pat-photo-input"
                                       data-target="insPhotoBackData" data-thumb="insPhotoBackThumb">
                            </label>
                            <img id="insPhotoBackThumb" src="" class="hidden h-14 mt-2 rounded-lg border border-slate-200 object-cover">
                        </div>
                    </div>
                </div>

                <!-- SSS / Gov ID -->
                <div class="mb-4 p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
                    <p class="text-sm font-bold text-slate-700"><i class="bi bi-person-vcard text-indigo-500 mr-1"></i> SSS / Government ID Card</p>
                    <input type="hidden" name="sss_photo" id="sssPhotoData" value="">
                    <label class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-slate-200 rounded-xl
                                  text-xs font-semibold text-slate-600 cursor-pointer hover:bg-indigo-50 hover:border-indigo-300 transition-colors">
                        <i class="bi bi-camera text-indigo-500"></i> Upload Photo
                        <input type="file" accept="image/*" class="sr-only pat-photo-input"
                               data-target="sssPhotoData" data-thumb="sssPhotoThumb">
                    </label>
                    <img id="sssPhotoThumb" src="" class="hidden h-14 mt-2 rounded-lg border border-slate-200 object-cover">
                </div>

                <!-- Race / PCP -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Race / Ethnicity</label>
                        <select name="race"
                                class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                       focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
                            <option value="">— Select —</option>
                            <?php foreach ([
                                'American Indian or Alaska Native','Asian','Black or African American',
                                'Hispanic or Latino','Native Hawaiian or Other Pacific Islander',
                                'White / Caucasian','Two or More Races','Other','Unknown / Declined to State',
                            ] as $r): ?>
                            <option value="<?= h($r) ?>" <?= ($vals['race'] ?? '') === $r ? 'selected' : '' ?>><?= h($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">PCP</label>
                        <input type="text" name="pcp" value="<?= h($vals['pcp']) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition"
                               placeholder="Primary care physician">
                    </div>
                </div>

                <!-- Pharmacy -->
                <div class="mb-4 p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
                    <p class="text-sm font-bold text-slate-700"><i class="bi bi-prescription2 text-emerald-500 mr-1"></i> Pharmacy Details</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Pharmacy Name</label>
                            <input type="text" name="pharmacy_name" value="<?= h($vals['pharmacy_name'] ?? '') ?>"
                                   class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white
                                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                   placeholder="CVS, Walgreens…">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Pharmacy Phone</label>
                            <input type="tel" name="pharmacy_phone" value="<?= h($vals['pharmacy_phone'] ?? '') ?>"
                                   class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white
                                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                   placeholder="(555) 555-5555">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Pharmacy Address</label>
                            <input type="text" name="pharmacy_address" value="<?= h($vals['pharmacy_address'] ?? '') ?>"
                                   class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white
                                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                   placeholder="Street, City, State ZIP">
                        </div>
                    </div>
                </div>

                <!-- Assigned MA -->
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        <i class="bi bi-person-badge mr-1"></i> Assigned MA
                    </label>
                    <?php if (isAdmin()): ?>
                    <select name="assigned_ma"
                            class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($maStaff as $sf): ?>
                        <option value="<?= $sf['id'] ?>" <?= ((int)($vals['assigned_ma'] ?? 0) === (int)$sf['id']) ? 'selected' : '' ?>>
                            <?= h($sf['full_name']) ?> (<?= h($sf['role']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <?php
                        $myName = '';
                        foreach ($maStaff as $sf) { if ((int)$sf['id'] === (int)$_SESSION['user_id']) { $myName = $sf['full_name']; break; } }
                    ?>
                    <div class="px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 text-slate-700">
                        <i class="bi bi-person-fill mr-1 text-blue-500"></i> <?= h($myName) ?> <span class="text-slate-400">(you)</span>
                    </div>
                    <input type="hidden" name="assigned_ma" value="<?= (int)$_SESSION['user_id'] ?>">
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit"
                            class="flex-1 sm:flex-none flex items-center justify-center gap-2
                                   bg-blue-600 hover:bg-blue-700 active:scale-95 text-white font-semibold
                                   px-8 py-3 rounded-xl transition-all shadow-sm hover:shadow-md">
                        <i class="bi bi-person-check-fill"></i> Save Patient
                    </button>
                    <a href="<?= BASE_URL ?>/patients.php"
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
<script>
(function () {
    document.querySelectorAll('.pat-photo-input').forEach(function (input) {
        input.addEventListener('change', function () {
            var file = input.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            if (file.size > 8 * 1024 * 1024) { alert('Image must be under 8 MB.'); input.value = ''; return; }
            var reader = new FileReader();
            reader.onload = function (e) {
                var targetInput = document.getElementById(input.dataset.target);
                var thumb       = document.getElementById(input.dataset.thumb);
                if (targetInput) targetInput.value = e.target.result;
                if (thumb) { thumb.src = e.target.result; thumb.classList.remove('hidden'); }
            };
            reader.readAsDataURL(file);
        });
    });
})();
</script>
