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

$pageTitle = 'Wound Care Photos';
$activeNav = 'patients';

$extraJs = '<script>
(function() {
    let count = 0;
    const container = document.getElementById("photoContainer");
    const tpl = document.getElementById("photoRowTemplate");
    const addBtn = document.getElementById("addPhotoBtn");

    function addRow() {
        count++;
        const clone = tpl.content.cloneNode(true);
        clone.querySelectorAll("[data-idx]").forEach(el => {
            el.setAttribute("name", el.getAttribute("data-idx").replace("N", count));
        });
        clone.querySelector(".row-num").textContent = "Photo " + count;
        clone.querySelector(".remove-row-btn").addEventListener("click", function() {
            this.closest(".photo-row").remove();
        });
        container.appendChild(clone);
    }

    addBtn.addEventListener("click", addRow);
    addRow(); // start with one row

    document.getElementById("woundForm").addEventListener("submit", function(e) {
        const rows = document.querySelectorAll(".photo-row");
        let valid = true;
        rows.forEach(row => {
            const file = row.querySelector("input[type=file]");
            if (!file.files.length) { valid = false; file.classList.add("ring-2","ring-red-400"); }
        });
        if (!valid) { e.preventDefault(); alert("Please attach a photo for each row, or remove empty rows."); }
    });
})();
</script>';

include __DIR__ . '/../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6 flex-wrap">
    <a href="<?= BASE_URL ?>/patients.php" class="hover:text-blue-600 font-medium">Patients</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patient_id ?>" class="hover:text-blue-600 font-medium">
        <?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>
    </a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold">Wound Care Photos</span>
</nav>

<div class="max-w-3xl">
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-5">
    <div class="bg-gradient-to-r from-violet-600 to-violet-500 px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-xl">
            <i class="bi bi-camera-fill text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-white font-bold text-lg">Wound Care Photos</h2>
            <p class="text-violet-100 text-sm"><?= h($patient['first_name'] . ' ' . $patient['last_name']) ?></p>
        </div>
    </div>

    <form id="woundForm" method="POST" action="<?= BASE_URL ?>/api/upload_photo.php"
          enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">

        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <label class="block text-sm font-bold text-slate-700">Photos</label>
                <button id="addPhotoBtn" type="button"
                        class="inline-flex items-center gap-1.5 text-sm font-semibold text-violet-700
                               bg-violet-100 hover:bg-violet-200 px-3.5 py-2 rounded-xl transition-colors">
                    <i class="bi bi-plus-circle-fill"></i> Add Another
                </button>
            </div>

            <!-- Photo Rows Container -->
            <div id="photoContainer" class="space-y-4 mb-6"></div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date</label>
                    <input type="date" name="wound_date" value="<?= date('Y-m-d') ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition focus:bg-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">MA Name</label>
                    <input type="text" name="ma_name" value="<?= h($_SESSION['full_name'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition focus:bg-white">
                </div>
            </div>
        </div>

        <div class="px-6 pb-6 flex flex-col sm:flex-row gap-3">
            <button type="submit"
                    class="flex-1 sm:flex-none flex items-center justify-center gap-2
                           bg-violet-600 hover:bg-violet-700 active:scale-95 text-white font-bold
                           px-10 py-3.5 rounded-xl transition-all shadow-md hover:shadow-lg text-base">
                <i class="bi bi-cloud-upload-fill text-xl"></i> Upload Photos
            </button>
            <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patient_id ?>"
               class="flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl text-sm font-semibold
                      text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>
</div>

<!-- Photo Row Template -->
<template id="photoRowTemplate">
    <div class="photo-row bg-slate-50 border border-slate-200 rounded-2xl p-4">
        <div class="flex items-center justify-between mb-3">
            <span class="row-num text-sm font-bold text-slate-600"></span>
            <button type="button"
                    class="remove-row-btn text-red-400 hover:text-red-600 text-sm font-medium
                           hover:bg-red-50 px-3 py-1.5 rounded-lg transition-colors">
                <i class="bi bi-trash"></i> Remove
            </button>
        </div>
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Photo File <span class="text-red-400">*</span></label>
                <input type="file" name="photos_N[]" accept="image/*" capture="environment" data-idx="photos_N[]"
                       class="w-full px-4 py-2.5 border border-slate-200 bg-white rounded-xl text-sm
                              file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0
                              file:bg-violet-100 file:text-violet-700 file:font-semibold
                              hover:file:bg-violet-200 transition-colors cursor-pointer">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Location / Site</label>
                    <select name="location_N[]" data-idx="location_N[]"
                            class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white
                                   focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                        <option value="">-- Select site --</option>
                        <option>Left foot</option><option>Right foot</option>
                        <option>Left leg</option><option>Right leg</option>
                        <option>Left arm</option><option>Right arm</option>
                        <option>Abdomen</option><option>Back</option>
                        <option>Sacrum / Coccyx</option><option>Face</option>
                        <option>Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Description</label>
                    <input type="text" name="description_N[]" data-idx="description_N[]"
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white
                                  focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                           placeholder="Size, color, drainage...">
                </div>
            </div>
        </div>
    </div>
</template>

<?php include __DIR__ . '/../includes/footer.php'; ?>
