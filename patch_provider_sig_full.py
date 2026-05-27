import subprocess, re

# ── 1. Extend api/save_signature.php ─────────────────────────────────────────
path = '/var/www/paperlessmd/api/save_signature.php'
with open(path) as f:
    src = f.read()

new_actions = """
} elseif ($action === 'save_provider') {
    $sig   = $body['signature']      ?? '';
    $name  = trim($body['prov_name'] ?? '');
    $npi   = trim($body['prov_npi']  ?? '');
    if (!$sig || !preg_match('/^data:image\\/png;base64,[A-Za-z0-9+\\/=]+$/', $sig)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid signature format']);
        exit;
    }
    $pdo->prepare("UPDATE staff SET saved_provider_signature = ?, saved_provider_name = ?, saved_provider_npi = ? WHERE id = ?")
        ->execute([$sig, $name ?: null, $npi ?: null, (int)$_SESSION['user_id']]);
    echo json_encode(['ok' => true, 'msg' => 'Provider signature saved']);

} elseif ($action === 'clear_provider') {
    $pdo->prepare("UPDATE staff SET saved_provider_signature = NULL, saved_provider_name = NULL, saved_provider_npi = NULL WHERE id = ?")
        ->execute([(int)$_SESSION['user_id']]);
    echo json_encode(['ok' => true, 'msg' => 'Provider signature cleared']);

} else {"""

src = src.replace('} else {\n    http_response_code(400);\n    echo json_encode([\'ok\' => false, \'error\' => \'Unknown action\']);\n}', new_actions + '\n    http_response_code(400);\n    echo json_encode([\'ok\' => false, \'error\' => \'Unknown action\']);\n}')
with open(path, 'w') as f:
    f.write(src)

r = subprocess.run(['php', '-l', path], capture_output=True, text=True)
print('save_signature.php:', r.stdout.strip())


# ── 2. Add Provider Sig section to profile.php ───────────────────────────────
path = '/var/www/paperlessmd/profile.php'
with open(path) as f:
    src = f.read()

provider_html = """
        <?php if (isAdmin()): ?>
        <!-- ── Saved Provider Signature ── -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mt-6" id="savedProvSigSection">
            <div class="bg-gradient-to-r from-violet-600 to-purple-500 px-6 py-4 flex items-center gap-3">
                <div class="bg-white/20 p-2 rounded-xl shrink-0">
                    <i class="bi bi-person-badge-fill text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-white font-bold">Saved Provider / Physician Signature</h3>
                    <p class="text-violet-100 text-xs">Draw or upload once — auto-fills the provider signature on all forms</p>
                </div>
            </div>
            <div class="p-6">
                <div id="provSigMsg" class="hidden mb-4 text-sm font-semibold"></div>

                <!-- Name + NPI row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Provider Name (Print)</label>
                        <input type="text" id="savedProvName" placeholder="Dr. Full Name"
                               value="<?= h($user['saved_provider_name'] ?? '') ?>"
                               class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-violet-400 bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Provider NPI</label>
                        <input type="text" id="savedProvNpi" placeholder="10-digit NPI"
                               value="<?= h($user['saved_provider_npi'] ?? '') ?>"
                               maxlength="10" pattern="[0-9]{10}"
                               class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-violet-400 bg-white">
                    </div>
                </div>

                <?php if (!empty($user['saved_provider_signature'])): ?>
                <div id="savedProvSigPreview" class="mb-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Current Saved Provider Signature</p>
                    <div class="border border-slate-200 rounded-xl bg-slate-50 p-3 inline-block">
                        <img src="<?= h($user['saved_provider_signature']) ?>" alt="Saved provider signature" class="max-h-16 max-w-xs object-contain">
                    </div>
                </div>
                <?php else: ?>
                <div id="savedProvSigPreview" class="hidden"></div>
                <?php endif; ?>

                <p class="text-sm text-slate-600 mb-4">This signature auto-fills the <strong>Provider / Physician Signature</strong> panel when countersigning forms. You can still clear and re-sign on any individual form.</p>

                <!-- Tab switcher -->
                <div class="flex gap-1 p-1 bg-slate-100 rounded-xl mb-4 w-fit">
                    <button type="button" id="provTabDraw"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all bg-white text-slate-800 shadow-sm">
                        <i class="bi bi-pen"></i> Draw
                    </button>
                    <button type="button" id="provTabUpload"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all text-slate-500 hover:text-slate-700">
                        <i class="bi bi-upload"></i> Upload
                    </button>
                </div>

                <!-- Draw panel -->
                <div id="provPanelDraw">
                    <div class="relative border-2 border-dashed border-slate-300 rounded-2xl bg-white overflow-hidden focus-within:border-violet-400 transition-colors" style="touch-action:none;" id="savedProvSigWrapper">
                        <canvas id="savedProvSigCanvas" style="display:block;width:100%;height:140px;touch-action:none;cursor:crosshair;"></canvas>
                        <div id="savedProvSigPlaceholder" class="absolute inset-0 flex items-center justify-center text-slate-300 pointer-events-none select-none italic text-sm">
                            Provider sign here
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-4">
                        <button id="saveProvSigBtn"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-violet-600 hover:bg-violet-700
                                       active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm">
                            <i class="bi bi-floppy-fill"></i> Save Provider Signature
                        </button>
                        <button id="clearProvSigPadBtn"
                                class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200
                                       text-slate-600 font-semibold rounded-xl text-sm transition-all">
                            <i class="bi bi-eraser"></i> Clear Pad
                        </button>
                    </div>
                </div>

                <!-- Upload panel -->
                <div id="provPanelUpload" class="hidden">
                    <div id="provUploadDropZone"
                         class="border-2 border-dashed border-slate-300 rounded-2xl bg-slate-50 hover:bg-violet-50
                                hover:border-violet-400 transition-colors cursor-pointer flex flex-col items-center
                                justify-center gap-3 py-8 px-4 text-center">
                        <div class="w-12 h-12 bg-slate-100 rounded-2xl grid place-items-center">
                            <i class="bi bi-image text-slate-400 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Drop an image here, or <span class="text-violet-600 underline">browse</span></p>
                            <p class="text-xs text-slate-400 mt-0.5">PNG, JPG or GIF — white/transparent background recommended</p>
                        </div>
                        <input type="file" id="provSigUploadInput" accept="image/png,image/jpeg,image/gif" class="hidden">
                    </div>
                    <div id="provUploadPreviewWrapper" class="hidden mt-4">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Preview</p>
                        <div class="border border-slate-200 rounded-xl bg-white p-3 inline-block">
                            <img id="provUploadPreviewImg" src="" alt="Provider signature preview" class="max-h-20 max-w-xs object-contain">
                        </div>
                        <p id="provUploadFileName" class="text-xs text-slate-400 mt-1"></p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-4">
                        <button id="saveProvUploadBtn"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-violet-600 hover:bg-violet-700
                                       active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm
                                       disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <i class="bi bi-floppy-fill"></i> Save Uploaded Signature
                        </button>
                        <button id="clearProvUploadBtn"
                                class="hidden inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200
                                       text-slate-600 font-semibold rounded-xl text-sm transition-all">
                            <i class="bi bi-x-circle"></i> Clear
                        </button>
                    </div>
                </div>

                <?php if (!empty($user['saved_provider_signature'])): ?>
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <button id="deleteProvSavedSigBtn"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-50 hover:bg-red-100
                                   text-red-600 font-semibold rounded-xl text-sm transition-all">
                        <i class="bi bi-trash3"></i> Remove Saved Provider Signature
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>"""

provider_js = """
<script>
// ── Provider Saved Signature ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    var canvas      = document.getElementById('savedProvSigCanvas');
    var wrapper     = document.getElementById('savedProvSigWrapper');
    var placeholder = document.getElementById('savedProvSigPlaceholder');
    var msgEl       = document.getElementById('provSigMsg');
    var previewEl   = document.getElementById('savedProvSigPreview');
    var deleteBtn   = document.getElementById('deleteProvSavedSigBtn');

    var tabDraw   = document.getElementById('provTabDraw');
    var tabUpload = document.getElementById('provTabUpload');
    var panelDraw = document.getElementById('provPanelDraw');
    var panelUpload = document.getElementById('provPanelUpload');

    if (!canvas) return; // not admin, section not rendered

    function activateTab(tab) {
        var isDraw = (tab === 'draw');
        tabDraw.className   = 'inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all ' +
            (isDraw ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700');
        tabUpload.className = 'inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all ' +
            (!isDraw ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700');
        panelDraw.classList.toggle('hidden', !isDraw);
        panelUpload.classList.toggle('hidden', isDraw);
    }
    tabDraw   && tabDraw.addEventListener('click',   function () { activateTab('draw'); });
    tabUpload && tabUpload.addEventListener('click', function () { activateTab('upload'); });

    var pad = null;
    if (canvas && typeof SignaturePad !== 'undefined') {
        function resizeCanvas() {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            var w = wrapper.getBoundingClientRect().width || wrapper.offsetWidth;
            if (!w) return;
            canvas.width  = w * ratio;
            canvas.height = 140 * ratio;
            canvas.style.width  = w + 'px';
            canvas.style.height = '140px';
            canvas.getContext('2d').scale(ratio, ratio);
            if (pad) pad.clear();
        }
        pad = new SignaturePad(canvas, { penColor: 'rgb(88,28,135)', minWidth: 1.5, maxWidth: 3 });
        pad.addEventListener('beginStroke', function () { if (placeholder) placeholder.style.display = 'none'; });
        (function tryInit(n) {
            var w = wrapper.getBoundingClientRect().width || wrapper.offsetWidth;
            if (!w && n < 30) { requestAnimationFrame(function () { tryInit(n + 1); }); return; }
            resizeCanvas();
        })(0);
        window.addEventListener('resize', resizeCanvas);
    }

    document.getElementById('clearProvSigPadBtn').addEventListener('click', function () {
        if (pad) pad.clear();
        if (placeholder) placeholder.style.display = '';
    });

    // Upload
    var dropZone       = document.getElementById('provUploadDropZone');
    var fileInput      = document.getElementById('provSigUploadInput');
    var previewWrapper = document.getElementById('provUploadPreviewWrapper');
    var previewImg     = document.getElementById('provUploadPreviewImg');
    var fileNameEl     = document.getElementById('provUploadFileName');
    var saveUploadBtn  = document.getElementById('saveProvUploadBtn');
    var clearUploadBtn = document.getElementById('clearProvUploadBtn');
    var _uploadDataURL = null;

    function handleFile(file) {
        if (!file || !file.type.startsWith('image/')) { showMsg('Please select a PNG, JPG, or GIF image.', 'err'); return; }
        if (file.size > 2 * 1024 * 1024) { showMsg('Image must be under 2 MB.', 'err'); return; }
        var reader = new FileReader();
        reader.onload = function (e) {
            var img = new Image();
            img.onload = function () {
                var cvs = document.createElement('canvas');
                var maxW = 600, maxH = 200;
                var scale = Math.min(1, maxW / img.naturalWidth, maxH / img.naturalHeight);
                cvs.width  = Math.round(img.naturalWidth  * scale);
                cvs.height = Math.round(img.naturalHeight * scale);
                var ctx = cvs.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, cvs.width, cvs.height);
                ctx.drawImage(img, 0, 0, cvs.width, cvs.height);
                _uploadDataURL = cvs.toDataURL('image/png');
                previewImg.src = _uploadDataURL;
                fileNameEl.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
                previewWrapper.classList.remove('hidden');
                saveUploadBtn.disabled = false;
                clearUploadBtn.classList.remove('hidden');
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
    dropZone && dropZone.addEventListener('click', function () { fileInput && fileInput.click(); });
    fileInput && fileInput.addEventListener('change', function () { if (this.files[0]) handleFile(this.files[0]); });
    dropZone && dropZone.addEventListener('dragover',  function (e) { e.preventDefault(); dropZone.classList.add('border-violet-400','bg-violet-50'); });
    dropZone && dropZone.addEventListener('dragleave', function ()  { dropZone.classList.remove('border-violet-400','bg-violet-50'); });
    dropZone && dropZone.addEventListener('drop', function (e) {
        e.preventDefault(); dropZone.classList.remove('border-violet-400','bg-violet-50');
        var f = e.dataTransfer.files && e.dataTransfer.files[0]; if (f) handleFile(f);
    });
    clearUploadBtn && clearUploadBtn.addEventListener('click', function () {
        _uploadDataURL = null;
        previewWrapper.classList.add('hidden');
        saveUploadBtn.disabled = true;
        clearUploadBtn.classList.add('hidden');
        if (fileInput) fileInput.value = '';
    });

    function showMsg(text, type) {
        msgEl.textContent = text;
        msgEl.className = 'mb-4 text-sm font-semibold ' + (type === 'ok' ? 'text-violet-600' : 'text-red-500');
        msgEl.classList.remove('hidden');
        setTimeout(function () { msgEl.classList.add('hidden'); }, 5000);
    }

    function updatePreview(dataURL) {
        var img = previewEl.querySelector('img');
        if (img) { img.src = dataURL; }
        else {
            previewEl.innerHTML = '<p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Current Saved Provider Signature</p>' +
                '<div class="border border-slate-200 rounded-xl bg-slate-50 p-3 inline-block">' +
                '<img src="' + dataURL + '" alt="Saved provider signature" class="max-h-16 max-w-xs object-contain"></div>';
            previewEl.classList.remove('hidden');
        }
        if (!deleteBtn) location.reload();
    }

    function postProvSig(dataURL, btn, originalLabel) {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving\u2026';
        fetch('<?= BASE_URL ?>/api/save_signature.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf:      '<?= csrfToken() ?>',
                action:    'save_provider',
                signature: dataURL,
                prov_name: (document.getElementById('savedProvName') || {}).value || '',
                prov_npi:  (document.getElementById('savedProvNpi')  || {}).value || '',
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            btn.disabled = false; btn.innerHTML = originalLabel;
            if (j.ok) { showMsg('\u2713 Provider signature saved.', 'ok'); updatePreview(dataURL); }
            else { showMsg('Error: ' + (j.error || 'Unknown error'), 'err'); }
        })
        .catch(function () { btn.disabled = false; btn.innerHTML = originalLabel; showMsg('Network error.', 'err'); });
    }

    document.getElementById('saveProvSigBtn').addEventListener('click', function () {
        if (!pad || pad.isEmpty()) { showMsg('Please draw your signature first.', 'err'); return; }
        postProvSig(pad.toDataURL('image/png'), this, '<i class="bi bi-floppy-fill"></i> Save Provider Signature');
    });
    saveUploadBtn && saveUploadBtn.addEventListener('click', function () {
        if (!_uploadDataURL) { showMsg('Please choose an image first.', 'err'); return; }
        postProvSig(_uploadDataURL, this, '<i class="bi bi-floppy-fill"></i> Save Uploaded Signature');
    });
    deleteBtn && deleteBtn.addEventListener('click', function () {
        if (!confirm('Remove saved provider signature?')) return;
        deleteBtn.disabled = true;
        fetch('<?= BASE_URL ?>/api/save_signature.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf: '<?= csrfToken() ?>', action: 'clear_provider' })
        })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (j.ok) location.reload(); else { deleteBtn.disabled = false; showMsg('Error: ' + j.error, 'err'); } });
    });
});
</script>
"""

# Insert provider HTML section before </div><!-- /lg:col-span-2 -->
anchor = '\n    </div><!-- /lg:col-span-2 -->'
if anchor in src:
    src = src.replace(anchor, provider_html + anchor, 1)
    print('OK: inserted provider sig HTML section')
else:
    print('NOT FOUND: HTML anchor')

# Insert provider JS just before </script> at the very end (before include footer)
# Find the last </script> before the footer include
anchor_js = '\n</script>'
last_idx = src.rfind(anchor_js)
if last_idx != -1:
    src = src[:last_idx + len(anchor_js)] + '\n' + provider_js + src[last_idx + len(anchor_js):]
    print('OK: inserted provider sig JS')
else:
    print('NOT FOUND: JS anchor')

with open(path, 'w') as f:
    f.write(src)
r = subprocess.run(['php', '-l', path], capture_output=True, text=True)
print('profile.php:', r.stdout.strip())


# ── 3. Auto-fill in view_document.php ────────────────────────────────────────
path = '/var/www/paperlessmd/view_document.php'
with open(path) as f:
    src = f.read()

# Find where the doc is loaded (requireLogin) and add provider sig load after
load_snippet = """
// Load saved provider signature for current user
$_provSavedSig  = '';
$_provSavedName = '';
$_provSavedNpi  = '';
if (isset($_SESSION['user_id'])) {
    $__ps = $pdo->prepare("SELECT saved_provider_signature, saved_provider_name, saved_provider_npi FROM staff WHERE id = ? LIMIT 1");
    $__ps->execute([(int)$_SESSION['user_id']]);
    $__pr = $__ps->fetch(PDO::FETCH_ASSOC) ?: [];
    $_provSavedSig  = (string)($__pr['saved_provider_signature'] ?? '');
    $_provSavedName = (string)($__pr['saved_provider_name']      ?? '');
    $_provSavedNpi  = (string)($__pr['saved_provider_npi']       ?? '');
}
"""

# Inject after requireLogin(); requireNotBillingApi alternative — after the line that fetches $doc
anchor = 'require_once __DIR__ . \'/includes/auth.php\';\nrequire_once __DIR__ . \'/includes/db.php\';\nrequireLogin();'
if anchor in src:
    src = src.replace(anchor, anchor + '\n' + load_snippet, 1)
    print('OK: injected provider sig load')
else:
    print('NOT FOUND: auth anchor in view_document.php')

# Now update the provPanel HTML to show saved sig banner + pre-fill name/NPI
# Replace the <div class="mb-4"> provName section with the auto-fill aware version
old_prov_panel_inner = '''        <div class="mb-4">
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Provider Name</label>
            <input type="text" id="provName" placeholder="Dr. Full Name"
                   class="w-full max-w-sm px-4 py-2.5 border border-slate-200 rounded-xl text-sm
                          focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent bg-white">
        </div>
        <div class="relative border-2 border-dashed border-slate-300 rounded-xl bg-slate-50 overflow-hidden" style="touch-action:none;">
            <canvas id="provCanvas" class="w-full block" style="height:140px;"></canvas>
            <div id="provPlaceholder" class="absolute inset-0 flex flex-col items-center justify-center text-slate-300 pointer-events-none select-none">
                <i class="bi bi-pencil-square text-4xl mb-1"></i>
                <span class="text-sm font-medium">Provider sign here</span>
            </div>
        </div>
        <div class="flex items-center gap-3 mt-4 flex-wrap">
            <button id="provClearBtn"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-slate-600
                           bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                <i class="bi bi-eraser-fill"></i> Clear
            </button>
            <button id="provSaveBtn"
                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-bold text-white
                           bg-violet-600 hover:bg-violet-700 rounded-xl transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="bi bi-check-circle-fill"></i> Save Provider Signature
            </button>
            <span id="provMsg" class="text-sm font-semibold hidden"></span>
        </div>'''

new_prov_panel_inner = '''        <!-- Name + NPI row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Provider Name (Print)</label>
                <input type="text" id="provName" placeholder="Dr. Full Name"
                       value="<?= h($_provSavedName) ?>"
                       class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm
                              focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent bg-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Provider NPI</label>
                <input type="text" id="provNpi" placeholder="10-digit NPI"
                       value="<?= h($_provSavedNpi) ?>"
                       maxlength="10"
                       class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm
                              focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent bg-white">
            </div>
        </div>
        <?php if ($_provSavedSig): ?>
        <div id="provSavedBanner" class="flex items-center gap-3 bg-violet-50 border border-violet-200 text-violet-700 px-4 py-2.5 rounded-xl text-sm mb-3">
            <i class="bi bi-lightning-charge-fill shrink-0"></i>
            <span class="flex-1">Using your <strong>saved provider signature</strong>. <a href="<?= BASE_URL ?>/profile.php#savedProvSigSection" class="underline hover:text-violet-900 font-semibold" target="_blank">Update in Profile</a></span>
            <button type="button" id="useManualProvSig" class="text-xs font-semibold bg-violet-100 hover:bg-violet-200 px-3 py-1 rounded-lg transition-colors">Sign manually</button>
        </div>
        <div id="provSigPadArea" class="hidden">
        <?php else: ?>
        <div id="provSigPadArea">
        <?php endif; ?>
            <div class="relative border-2 border-dashed border-slate-300 rounded-xl bg-slate-50 overflow-hidden" style="touch-action:none;">
                <canvas id="provCanvas" class="w-full block" style="height:140px;"></canvas>
                <div id="provPlaceholder" class="absolute inset-0 flex flex-col items-center justify-center text-slate-300 pointer-events-none select-none">
                    <i class="bi bi-pencil-square text-4xl mb-1"></i>
                    <span class="text-sm font-medium">Provider sign here</span>
                </div>
            </div>
        </div>
        <?php if ($_provSavedSig): ?>
        <script>window._provSavedSignature = <?= json_encode($_provSavedSig) ?>;</script>
        <?php endif; ?>
        <div class="flex items-center gap-3 mt-4 flex-wrap">
            <button id="provClearBtn"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-slate-600
                           bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                <i class="bi bi-eraser-fill"></i> Clear
            </button>
            <button id="provSaveBtn"
                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-bold text-white
                           bg-violet-600 hover:bg-violet-700 rounded-xl transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="bi bi-check-circle-fill"></i> Save Provider Signature
            </button>
            <span id="provMsg" class="text-sm font-semibold hidden"></span>
        </div>'''

if old_prov_panel_inner in src:
    src = src.replace(old_prov_panel_inner, new_prov_panel_inner, 1)
    print('OK: updated provider panel HTML')
else:
    print('NOT FOUND: provider panel inner HTML')

# Now update the JS to handle saved sig auto-fill and "Sign manually" button
# Also pass provNpi in the API call
old_js_init = "    var provPad = new SignaturePad(provCanvas, { penColor: '#3b0764', minWidth: 1.5, maxWidth: 3 });"
new_js_init = """    var provPad = new SignaturePad(provCanvas, { penColor: '#3b0764', minWidth: 1.5, maxWidth: 3 });

    // ── Saved provider signature auto-fill ───────────────────────────
    var provSavedBanner  = document.getElementById('provSavedBanner');
    var provSigPadArea   = document.getElementById('provSigPadArea');
    var useManualBtn     = document.getElementById('useManualProvSig');
    if (typeof window._provSavedSignature !== 'undefined') {
        // Pre-load saved sig into pad so it submits even without manual signing
        var img = new Image();
        img.onload = function () {
            resizeProvCanvas();
            provPad.fromDataURL(window._provSavedSignature, { width: provCanvas.width, height: provCanvas.height });
            document.getElementById('provPlaceholder').style.display = 'none';
        };
        img.src = window._provSavedSignature;
    }
    useManualBtn && useManualBtn.addEventListener('click', function () {
        provSavedBanner.classList.add('hidden');
        provSigPadArea.classList.remove('hidden');
        provPad.clear();
        document.getElementById('provPlaceholder').style.display = '';
    });"""

if old_js_init in src:
    src = src.replace(old_js_init, new_js_init, 1)
    print('OK: updated provider JS auto-fill')
else:
    print('NOT FOUND: provider JS init line')

# Also add provNpi to the sign_provider API call
old_api_call = "                    provider_name: document.getElementById('provName').value.trim(),"
new_api_call = """                    provider_name: document.getElementById('provName').value.trim(),
                    provider_npi:  (document.getElementById('provNpi') || {value:''}).value.trim(),"""

cnt = src.count(old_api_call)
print(f'API call occurrences: {cnt}')
if cnt > 0:
    src = src.replace(old_api_call, new_api_call)
    print('OK: added provNpi to API call(s)')

with open(path, 'w') as f:
    f.write(src)
r = subprocess.run(['php', '-l', path], capture_output=True, text=True)
print('view_document.php:', r.stdout.strip())
print('Done.')
