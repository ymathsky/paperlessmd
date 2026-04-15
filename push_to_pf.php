<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$patient_id = (int)($_GET['patient_id'] ?? 0);
$form_id    = (int)($_GET['form_id']    ?? 0);
$pageTitle  = 'Push to Practice Fusion';
$activeNav  = 'patients';

$patient = null;
if ($patient_id) {
    $s = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $s->execute([$patient_id]);
    $patient = $s->fetch();
}

$form = null;
if ($form_id) {
    $s = $pdo->prepare("
        SELECT fs.*, p.first_name, p.last_name, p.dob, p.id AS pid
        FROM form_submissions fs JOIN patients p ON p.id = fs.patient_id
        WHERE fs.id = ?");
    $s->execute([$form_id]);
    $form = $s->fetch();
    if ($form && !$patient) {
        $patient_id = $form['pid'];
        $patient = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
        $patient->execute([$patient_id]);
        $patient = $patient->fetch();
    }
}

include __DIR__ . '/includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6 flex-wrap no-print">
    <a href="<?= BASE_URL ?>/patients.php" class="hover:text-blue-600 font-medium">Patients</a>
    <?php if ($patient): ?>
    <i class="bi bi-chevron-right text-xs"></i>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patient_id ?>" class="hover:text-blue-600 font-medium">
        <?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>
    </a>
    <?php endif; ?>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold">Push to Practice Fusion</span>
</nav>

<div class="max-w-3xl">

<!-- Banner -->
<div class="bg-gradient-to-r from-indigo-600 to-indigo-500 rounded-2xl p-5 mb-6 flex items-center gap-4 text-white shadow-lg">
    <div class="bg-white/20 p-3 rounded-xl flex-shrink-0">
        <i class="bi bi-cloud-upload-fill text-2xl"></i>
    </div>
    <div>
        <h2 class="font-extrabold text-xl">Push to Practice Fusion</h2>
        <p class="text-indigo-200 text-sm mt-0.5">Upload signed documents to the patient's EHR chart.</p>
    </div>
</div>

<!-- Step Wizard -->
<div id="wizard">

    <!-- Step 1: Search -->
    <div id="step1" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-4">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-8 h-8 bg-indigo-100 text-indigo-700 rounded-xl grid place-items-center font-extrabold text-sm">1</div>
            <h3 class="font-bold text-slate-700">Find Patient in Practice Fusion</h3>
        </div>
        <div class="p-6">
            <?php if ($patient): ?>
            <div class="flex items-center gap-3 mb-4 p-4 bg-slate-50 rounded-xl border border-slate-200">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl grid place-items-center text-white font-bold text-sm flex-shrink-0">
                    <?= strtoupper(substr($patient['first_name'],0,1) . substr($patient['last_name'],0,1)) ?>
                </div>
                <div>
                    <div class="font-semibold text-slate-800"><?= h($patient['first_name'] . ' ' . $patient['last_name']) ?></div>
                    <?php if ($patient['dob']): ?><div class="text-xs text-slate-500">DOB: <?= date('M j, Y', strtotime($patient['dob'])) ?></div><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Search by Name</label>
                    <input type="text" id="pfSearchName"
                           value="<?= $patient ? h($patient['first_name'] . ' ' . $patient['last_name']) : '' ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition focus:bg-white"
                           placeholder="Patient name in PF">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Date of Birth</label>
                    <input type="date" id="pfSearchDob"
                           value="<?= $patient && $patient['dob'] ? h($patient['dob']) : '' ?>"
                           class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition focus:bg-white">
                </div>
            </div>
            <button id="pfSearchBtn"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white
                           font-semibold rounded-xl transition-all shadow-sm text-sm">
                <i class="bi bi-search"></i> Search Practice Fusion
            </button>
            <div id="pfSearchResults" class="mt-4 hidden"></div>
        </div>
    </div>

    <!-- Step 2: Confirm Match -->
    <div id="step2" class="hidden bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-4">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-8 h-8 bg-indigo-100 text-indigo-700 rounded-xl grid place-items-center font-extrabold text-sm">2</div>
            <h3 class="font-bold text-slate-700">Confirm Patient Match</h3>
        </div>
        <div class="p-6">
            <div id="selectedPatientInfo" class="mb-4"></div>
            <input type="hidden" id="pfPatientId">
            <button id="confirmMatchBtn"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white
                           font-semibold rounded-xl transition-all shadow-sm text-sm">
                <i class="bi bi-check2-circle"></i> Confirm &amp; Continue
            </button>
        </div>
    </div>

    <!-- Step 3: Upload -->
    <div id="step3" class="hidden bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-8 h-8 bg-emerald-100 text-emerald-700 rounded-xl grid place-items-center font-extrabold text-sm">3</div>
            <h3 class="font-bold text-slate-700">Upload to EHR</h3>
        </div>
        <div class="p-6">
            <p class="text-sm text-slate-600 mb-4">
                The document will be converted to PDF and uploaded to the matched patient's chart.
            </p>
            <button id="uploadBtn"
                    class="inline-flex items-center gap-2 px-8 py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white
                           font-bold rounded-xl transition-all shadow-md text-base">
                <i class="bi bi-cloud-upload-fill text-xl"></i> Upload to Practice Fusion
            </button>
            <div id="uploadStatus" class="mt-4 hidden"></div>
        </div>
    </div>

</div>
</div>

<?php
$extraJs = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
const BASE = ' . json_encode(BASE_URL) . ';
const FORM_ID = ' . (int)$form_id . ';

// Step 1: Search
document.getElementById("pfSearchBtn").addEventListener("click", function() {
    const name = document.getElementById("pfSearchName").value.trim();
    const dob  = document.getElementById("pfSearchDob").value;
    if (!name) return;
    this.disabled = true;
    this.innerHTML = \'<i class="bi bi-arrow-repeat animate-spin"></i> Searching...\';
    fetch(BASE + "/api/pf_search_patient.php?name=" + encodeURIComponent(name) + "&dob=" + encodeURIComponent(dob))
        .then(r => r.json())
        .then(results => {
            const btn = document.getElementById("pfSearchBtn");
            btn.disabled = false;
            btn.innerHTML = \'<i class="bi bi-search"></i> Search Practice Fusion\';
            const el = document.getElementById("pfSearchResults");
            el.classList.remove("hidden");
            if (!results.length) {
                el.innerHTML = \'<div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-xl text-sm"><i class="bi bi-exclamation-triangle me-2"></i>No patients found. Check name/DOB.</div>\';
                return;
            }
            let html = \'<div class="space-y-2">\';
            results.forEach(p => {
                html += `<div class="flex items-center justify-between p-4 border border-slate-200 rounded-xl hover:border-indigo-300 hover:bg-indigo-50/50 cursor-pointer transition-colors pf-result"
                              data-id="${p.id}" data-name="${p.name}" data-dob="${p.dob||""}">
                    <div>
                        <div class="font-semibold text-slate-800">${p.name}</div>
                        <div class="text-xs text-slate-500">${p.dob ? "DOB: " + p.dob : ""}</div>
                    </div>
                    <span class="text-sm text-indigo-600 font-semibold">Select →</span>
                </div>`;
            });
            html += "</div>";
            el.innerHTML = html;
            // Attach click handlers
            el.querySelectorAll(".pf-result").forEach(row => {
                row.addEventListener("click", function() {
                    document.getElementById("pfPatientId").value = this.dataset.id;
                    document.getElementById("selectedPatientInfo").innerHTML =
                        `<div class="flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-200 rounded-xl">
                            <i class="bi bi-person-check-fill text-emerald-600 text-xl"></i>
                            <div>
                                <div class="font-bold text-slate-800">${this.dataset.name}</div>
                                ${this.dataset.dob ? "<div class=\'text-xs text-slate-500\'>DOB: " + this.dataset.dob + "</div>" : ""}
                            </div>
                        </div>`;
                    document.getElementById("step2").classList.remove("hidden");
                    document.getElementById("step2").scrollIntoView({behavior:"smooth"});
                });
            });
        })
        .catch(() => {
            const btn = document.getElementById("pfSearchBtn");
            btn.disabled = false;
            btn.innerHTML = \'<i class="bi bi-search"></i> Search Practice Fusion\';
            document.getElementById("pfSearchResults").innerHTML =
                \'<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">Connection error. Check PF configuration.</div>\';
        });
});

// Step 2: Confirm
document.getElementById("confirmMatchBtn").addEventListener("click", function() {
    document.getElementById("step3").classList.remove("hidden");
    document.getElementById("step3").scrollIntoView({behavior:"smooth"});
});

// Step 3: Upload
document.getElementById("uploadBtn").addEventListener("click", function() {
    const pfId = document.getElementById("pfPatientId").value;
    if (!pfId || !FORM_ID) { alert("Missing patient or form ID."); return; }

    this.disabled = true;
    this.innerHTML = \'<i class="bi bi-hourglass-split"></i> Uploading...\';

    // For now send without PDF data (simple mode)
    fetch(BASE + "/api/pf_push.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({form_id: FORM_ID, pf_patient_id: pfId, pdf_data: ""})
    })
    .then(r => r.json())
    .then(res => {
        const el = document.getElementById("uploadStatus");
        el.classList.remove("hidden");
        if (res.success) {
            el.innerHTML = \'<div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl"><i class="bi bi-check-circle-fill text-xl"></i><div><div class="font-bold">Uploaded successfully!</div><div class="text-sm">Document is now in the patient chart.</div></div></div>\';
            document.getElementById("uploadBtn").className = "inline-flex items-center gap-2 px-8 py-3.5 bg-slate-400 text-white font-bold rounded-xl text-base cursor-not-allowed";
        } else {
            el.innerHTML = `<div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl"><i class="bi bi-exclamation-circle-fill text-xl"></i><div class="text-sm">${res.error || "Upload failed."}</div></div>`;
            document.getElementById("uploadBtn").disabled = false;
            document.getElementById("uploadBtn").innerHTML = \'<i class="bi bi-cloud-upload-fill text-xl"></i> Retry Upload\';
        }
    })
    .catch(() => {
        document.getElementById("uploadStatus").innerHTML =
            \'<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">Network error.</div>\';
        document.getElementById("uploadBtn").disabled = false;
    });
});
</script>';
?>

<?php include __DIR__ . '/includes/footer.php'; ?>
