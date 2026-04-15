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
    const tpl       = document.getElementById("photoRowTemplate");
    const addBtn    = document.getElementById("addPhotoBtn");

    // ── Camera modal state ────────────────────────────────────────────────────
    let stream       = null;
    let targetRow    = null; // the photo-row that triggered the camera

    const modal      = document.getElementById("cameraModal");
    const video      = document.getElementById("cameraVideo");
    const canvas     = document.getElementById("cameraCanvas");
    const snapBtn    = document.getElementById("cameraSnapBtn");
    const closeBtn   = document.getElementById("cameraCloseBtn");
    const switchBtn  = document.getElementById("cameraSwitchBtn");
    const retakeBtn  = document.getElementById("cameraRetakeBtn");
    const useBtn     = document.getElementById("cameraUseBtn");
    const preview    = document.getElementById("cameraPreview");

    let facingMode   = "environment"; // rear camera default
    let capturedBlob = null;

    async function openCamera(row) {
        targetRow  = row;
        capturedBlob = null;
        canvas.classList.add("hidden");
        video.classList.remove("hidden");
        retakeBtn.classList.add("hidden");
        useBtn.classList.add("hidden");
        snapBtn.classList.remove("hidden");
        modal.classList.remove("hidden");
        document.body.style.overflow = "hidden";
        await startStream();
    }

    async function startStream() {
        if (stream) { stream.getTracks().forEach(t => t.stop()); }
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode, width: { ideal: 1920 }, height: { ideal: 1080 } },
                audio: false
            });
            video.srcObject = stream;
            video.play();
        } catch (err) {
            closeCamera();
            alert("Camera not available: " + err.message);
        }
    }

    function closeCamera() {
        if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
        modal.classList.add("hidden");
        document.body.style.overflow = "";
        targetRow = null;
    }

    function snapPhoto() {
        canvas.width  = video.videoWidth  || 1280;
        canvas.height = video.videoHeight || 720;
        canvas.getContext("2d").drawImage(video, 0, 0, canvas.width, canvas.height);
        video.classList.add("hidden");
        canvas.classList.remove("hidden");
        snapBtn.classList.add("hidden");
        retakeBtn.classList.remove("hidden");
        useBtn.classList.remove("hidden");
        // Pause stream (save battery) but keep tracks alive for retake
        if (stream) stream.getTracks().forEach(t => t.enabled = false);
        canvas.toBlob(blob => { capturedBlob = blob; }, "image/jpeg", 0.92);
    }

    function retake() {
        capturedBlob = null;
        canvas.classList.add("hidden");
        video.classList.remove("hidden");
        retakeBtn.classList.add("hidden");
        useBtn.classList.add("hidden");
        snapBtn.classList.remove("hidden");
        if (stream) stream.getTracks().forEach(t => t.enabled = true);
    }

    function usePhoto() {
        if (!capturedBlob || !targetRow) return;
        const ts   = new Date().toISOString().replace(/[:.]/g,"-");
        const file = new File([capturedBlob], "wound-" + ts + ".jpg", { type: "image/jpeg" });
        const dt   = new DataTransfer();
        dt.items.add(file);
        const fi   = targetRow.querySelector("input[type=file]");
        fi.files   = dt.files;
        // Show thumbnail
        showThumb(targetRow, URL.createObjectURL(capturedBlob));
        closeCamera();
    }

    function showThumb(row, src) {
        const wrap = row.querySelector(".photo-thumb-wrap");
        wrap.innerHTML =
            `<div class="relative inline-block">
                <img src="${src}" alt="Preview"
                     class="w-24 h-24 object-cover rounded-xl border-2 border-violet-400 shadow-sm">
                <span class="absolute -top-1.5 -right-1.5 bg-emerald-500 text-white text-[10px] font-bold
                             px-1.5 py-0.5 rounded-full shadow">✓</span>
             </div>`;
        wrap.classList.remove("hidden");
    }

    snapBtn.addEventListener("click",  snapPhoto);
    retakeBtn.addEventListener("click", retake);
    useBtn.addEventListener("click",   usePhoto);
    closeBtn.addEventListener("click", closeCamera);
    switchBtn.addEventListener("click", async () => {
        facingMode = facingMode === "environment" ? "user" : "environment";
        await startStream();
    });
    // Close on backdrop click
    modal.addEventListener("click", e => { if (e.target === modal) closeCamera(); });

    // ── Row builder ────────────────────────────────────────────────────────────
    function addRow() {
        count++;
        const clone = tpl.content.cloneNode(true);
        clone.querySelectorAll("[data-idx]").forEach(el => {
            el.setAttribute("name", el.getAttribute("data-idx").replace("N", count));
        });
        clone.querySelector(".row-num").textContent = "Photo " + count;

        const row = clone.querySelector(".photo-row");

        // Remove button
        clone.querySelector(".remove-row-btn").addEventListener("click", function() {
            this.closest(".photo-row").remove();
        });

        // Camera button
        clone.querySelector(".camera-capture-btn").addEventListener("click", function() {
            openCamera(this.closest(".photo-row"));
        });

        // File input change → show thumb
        clone.querySelector("input[type=file]").addEventListener("change", function() {
            if (this.files[0]) {
                showThumb(this.closest(".photo-row"), URL.createObjectURL(this.files[0]));
            }
        });

        container.appendChild(clone);
    }

    addBtn.addEventListener("click", addRow);
    addRow();

    // ── Form validation ────────────────────────────────────────────────────────
    document.getElementById("woundForm").addEventListener("submit", function(e) {
        let valid = true;
        document.querySelectorAll(".photo-row").forEach(row => {
            const fi = row.querySelector("input[type=file]");
            if (!fi.files.length) {
                valid = false;
                fi.closest(".file-input-wrap").classList.add("ring-2","ring-red-400","rounded-xl");
            }
        });
        if (!valid) { e.preventDefault(); alert("Please attach or capture a photo for each row, or remove empty rows."); }
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
            <!-- Thumbnail preview -->
            <div class="photo-thumb-wrap hidden mb-1"></div>
            <!-- Source buttons -->
            <div class="flex gap-2">
                <label class="file-input-wrap flex-1 flex items-center gap-2 px-4 py-2.5 border border-slate-200
                              rounded-xl bg-white cursor-pointer hover:border-violet-300 transition-colors
                              text-sm text-slate-500 font-medium">
                    <i class="bi bi-folder2-open text-violet-500"></i>
                    <span>Choose File</span>
                    <input type="file" name="photos_N[]" accept="image/*" data-idx="photos_N[]"
                           class="sr-only">
                </label>
                <button type="button"
                        class="camera-capture-btn flex items-center gap-2 px-4 py-2.5 bg-violet-600
                               hover:bg-violet-700 active:scale-95 text-white text-sm font-semibold
                               rounded-xl transition-all shadow-sm whitespace-nowrap">
                    <i class="bi bi-camera-fill"></i> Take Photo
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Location / Site</label>
                    <select name="location_N[]" data-idx="location_N[]"
                            class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-white
                                   focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent">
                        <option value="">-- Select site --</option>
                        <option>Left foot</option><option>Right foot</option>
                        <option>Left heel</option><option>Right heel</option>
                        <option>Left ankle</option><option>Right ankle</option>
                        <option>Left lower leg</option><option>Right lower leg</option>
                        <option>Left arm</option><option>Right arm</option>
                        <option>Abdomen</option><option>Back</option>
                        <option>Sacrum / Coccyx</option><option>Left hip</option><option>Right hip</option>
                        <option>Face</option><option>Other</option>
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

<!-- ── Camera Modal ──────────────────────────────────────────────────────────── -->
<div id="cameraModal" class="hidden fixed inset-0 z-50 bg-black/90 flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between px-4 py-3 bg-black/60">
        <span class="text-white font-bold text-sm tracking-wide">
            <i class="bi bi-camera-fill text-violet-400 mr-2"></i>Take Wound Photo
        </span>
        <div class="flex items-center gap-2">
            <button id="cameraSwitchBtn"
                    class="text-white/70 hover:text-white px-3 py-1.5 rounded-xl text-xs font-semibold
                           bg-white/10 hover:bg-white/20 transition-colors flex items-center gap-1.5">
                <i class="bi bi-arrow-repeat"></i> Flip
            </button>
            <button id="cameraCloseBtn"
                    class="text-white/70 hover:text-white px-3 py-1.5 rounded-xl text-xs font-semibold
                           bg-white/10 hover:bg-white/20 transition-colors flex items-center gap-1.5">
                <i class="bi bi-x-lg"></i> Close
            </button>
        </div>
    </div>

    <!-- Viewfinder -->
    <div class="flex-1 relative flex items-center justify-center overflow-hidden bg-black">
        <video id="cameraVideo" autoplay playsinline muted
               class="max-h-full max-w-full w-full object-contain"></video>
        <canvas id="cameraCanvas"
                class="hidden max-h-full max-w-full w-full object-contain"></canvas>
        <!-- Aiming guide -->
        <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
            <div class="w-64 h-64 border-2 border-white/30 rounded-2xl"></div>
        </div>
    </div>

    <!-- Controls -->
    <div class="px-6 py-5 bg-black/60 flex items-center justify-center gap-4">
        <!-- Snap -->
        <button id="cameraSnapBtn"
                class="w-16 h-16 bg-white hover:bg-slate-100 active:scale-90 rounded-full shadow-xl
                       flex items-center justify-center transition-all">
            <i class="bi bi-circle-fill text-violet-600 text-3xl"></i>
        </button>
        <!-- Retake (shown after snap) -->
        <button id="cameraRetakeBtn"
                class="hidden px-5 py-3 bg-white/10 hover:bg-white/20 text-white text-sm font-semibold
                       rounded-xl transition-colors flex items-center gap-2">
            <i class="bi bi-arrow-counterclockwise"></i> Retake
        </button>
        <!-- Use photo (shown after snap) -->
        <button id="cameraUseBtn"
                class="hidden px-6 py-3 bg-violet-600 hover:bg-violet-500 active:scale-95 text-white
                       text-sm font-bold rounded-xl shadow-lg transition-all flex items-center gap-2">
            <i class="bi bi-check-circle-fill"></i> Use Photo
        </button>
    </div>
</div>
<!-- preview element (offscreen, used for object URLs cleanup) -->
<img id="cameraPreview" class="hidden" alt="">

<?php include __DIR__ . '/../includes/footer.php'; ?>
