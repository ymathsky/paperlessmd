#!/usr/bin/env python3
"""
patch_sig_title.py
Adds per-user signature title (sig_title) feature to PaperlessMD.
Run on the server: python3 /tmp/patch_sig_title.py
"""
import subprocess, sys

BASE = '/var/www/paperlessmd'

def read(p):
    return open(p, encoding='utf-8').read()

def write(p, c):
    open(p, 'w', encoding='utf-8').write(c)

def mysql(sql):
    r = subprocess.run(
        ['mysql', '-u', 'pduser', '-pYm@thsky12101992', 'paperlessmd', '-e', sql],
        capture_output=True, text=True
    )
    return r.stdout + r.stderr

# ── 1. ALTER TABLE ──────────────────────────────────────────────────────────
print("1. Adding sig_title column to staff table...")
out = mysql("ALTER TABLE staff ADD COLUMN sig_title VARCHAR(100) NULL DEFAULT NULL AFTER saved_sig_updated_at")
if 'ERROR' in out and 'Duplicate column' not in out:
    print("  ✗ MySQL error:", out)
    sys.exit(1)
print("  ✓ Column added (or already exists)")

# ── 2. sig_block.php ────────────────────────────────────────────────────────
print("\n2. Patching includes/sig_block.php...")
path = BASE + '/includes/sig_block.php'
c = read(path)

old_load = """<?php
// Load the logged-in MA's saved signature (if any) for auto-fill
$_maSavedSig = '';
if (isset($pdo) && isset($_SESSION['user_id'])) {
    $__ss = $pdo->prepare("SELECT saved_signature FROM staff WHERE id = ? LIMIT 1");
    $__ss->execute([(int)$_SESSION['user_id']]);
    $_maSavedSig = (string)($__ss->fetchColumn() ?: '');
}"""

new_load = """<?php
// Load the logged-in user's saved signature and signature title for auto-fill
$_maSavedSig = '';
$_sigTitle   = 'Medical Assistant';
if (isset($pdo) && isset($_SESSION['user_id'])) {
    $__ss = $pdo->prepare("SELECT saved_signature, sig_title FROM staff WHERE id = ? LIMIT 1");
    $__ss->execute([(int)$_SESSION['user_id']]);
    $__row = $__ss->fetch(PDO::FETCH_ASSOC) ?: [];
    $_maSavedSig = (string)($__row['saved_signature'] ?? '');
    if (!empty(trim($__row['sig_title'] ?? ''))) {
        $_sigTitle = trim($__row['sig_title']);
    }
}"""

if old_load in c:
    c = c.replace(old_load, new_load)
    print("  ✓ load block updated")
else:
    print("  ✗ FAILED: load block not found — check sig_block.php manually")
    sys.exit(1)

old_heading = '<span class="text-white font-semibold text-sm">Medical Assistant Signature</span>'
new_heading = '<span class="text-white font-semibold text-sm"><?= h($_sigTitle) ?> Signature</span>'
if old_heading in c:
    c = c.replace(old_heading, new_heading)
    print("  ✓ heading updated")
else:
    print("  ✗ FAILED: heading not found")
    sys.exit(1)

old_label = """            <label class="block text-sm font-semibold text-slate-700 mb-2">MA sign below
                <span class="text-slate-400 font-normal text-xs ml-1">(staff member completing this form)</span>
            </label>"""
new_label = """            <label class="block text-sm font-semibold text-slate-700 mb-2"><?= h($_sigTitle) ?> sign below
                <span class="text-slate-400 font-normal text-xs ml-1">(staff member completing this form)</span>
            </label>"""
if old_label in c:
    c = c.replace(old_label, new_label)
    print("  ✓ sign-below label updated")
else:
    print("  ✗ FAILED: sign-below label not found")
    sys.exit(1)

old_placeholder = '                <div class="sig-placeholder">MA sign here</div>'
new_placeholder = '                <div class="sig-placeholder"><?= h($_sigTitle) ?> sign here</div>'
if old_placeholder in c:
    c = c.replace(old_placeholder, new_placeholder)
    print("  ✓ canvas placeholder updated")
else:
    print("  ✗ FAILED: canvas placeholder not found")
    sys.exit(1)

write(path, c)
print("  ✓ sig_block.php saved")

# ── 3. api/save_signature.php ────────────────────────────────────────────────
print("\n3. Patching api/save_signature.php...")
path = BASE + '/api/save_signature.php'
c = read(path)

old_else = """} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}"""

new_else = """} elseif ($action === 'save_title') {
    $title = trim($body['title'] ?? '');
    if (!$title) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Title is required']);
        exit;
    }
    if (strlen($title) > 100) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Title must be 100 characters or fewer']);
        exit;
    }
    $pdo->prepare("UPDATE staff SET sig_title = ? WHERE id = ?")
        ->execute([$title, (int)$_SESSION['user_id']]);
    echo json_encode(['ok' => true, 'msg' => 'Signature title saved']);

} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}"""

if old_else in c:
    c = c.replace(old_else, new_else)
    print("  ✓ save_title action added")
else:
    print("  ✗ FAILED: else block not found in save_signature.php")
    sys.exit(1)

write(path, c)
print("  ✓ api/save_signature.php saved")

# ── 4. profile.php ──────────────────────────────────────────────────────────
print("\n4. Patching profile.php...")
path = BASE + '/profile.php'
c = read(path)

PRESETS = "['Medical Assistant','Provider','Physician','Physician Assistant','Nurse Practitioner','LPN','Clinical Staff','Administrator']"

def opt(val):
    """Render a <option> tag with PHP selected logic"""
    return f'<option value="{val}"<?= ($user[\'sig_title\'] ?? \'\') === \'{val}\' ? \' selected\' : \'\' ?>>{val}</option>'

SIG_TITLE_CARD = """
        <!-- ── Signature Title ── -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden" id="sigTitleSection">
            <div class="bg-gradient-to-r from-violet-600 to-violet-500 px-6 py-4 flex items-center gap-3">
                <div class="bg-white/20 p-2 rounded-xl shrink-0">
                    <i class="bi bi-tag-fill text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-white font-bold">Form Signature Title</h3>
                    <p class="text-violet-100 text-xs">Controls the label shown on your signature block on patient forms</p>
                </div>
            </div>
            <div class="p-6">
                <div id="sigTitleMsg" class="hidden mb-4 text-sm font-semibold"></div>
                <p class="text-sm text-slate-600 mb-4">
                    Choose how you appear on the <strong>signature block</strong> of patient forms.
                    For example, select <em>Provider</em> so forms show <strong>Provider Signature</strong> instead of <em>Medical Assistant Signature</em>.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
                    <div class="flex-1">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Signature Title</label>
                        <select id="sigTitleSelect"
                                class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white">
                            <option value="Medical Assistant"<?= empty($user['sig_title']) || ($user['sig_title'] ?? '') === 'Medical Assistant' ? ' selected' : '' ?>>Medical Assistant</option>
                            <option value="Provider"<?= ($user['sig_title'] ?? '') === 'Provider' ? ' selected' : '' ?>>Provider</option>
                            <option value="Physician"<?= ($user['sig_title'] ?? '') === 'Physician' ? ' selected' : '' ?>>Physician</option>
                            <option value="Physician Assistant"<?= ($user['sig_title'] ?? '') === 'Physician Assistant' ? ' selected' : '' ?>>Physician Assistant</option>
                            <option value="Nurse Practitioner"<?= ($user['sig_title'] ?? '') === 'Nurse Practitioner' ? ' selected' : '' ?>>Nurse Practitioner</option>
                            <option value="LPN"<?= ($user['sig_title'] ?? '') === 'LPN' ? ' selected' : '' ?>>LPN</option>
                            <option value="Clinical Staff"<?= ($user['sig_title'] ?? '') === 'Clinical Staff' ? ' selected' : '' ?>>Clinical Staff</option>
                            <option value="Administrator"<?= ($user['sig_title'] ?? '') === 'Administrator' ? ' selected' : '' ?>>Administrator</option>
                            <?php
                            $__presets = ['Medical Assistant','Provider','Physician','Physician Assistant','Nurse Practitioner','LPN','Clinical Staff','Administrator'];
                            $__isCustom = !empty($user['sig_title']) && !in_array($user['sig_title'], $__presets, true);
                            ?>
                            <option value="custom"<?= $__isCustom ? ' selected' : '' ?>>Custom…</option>
                        </select>
                    </div>
                    <div class="flex-1" id="sigTitleCustomWrap" style="display:none;">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Custom Title</label>
                        <input type="text" id="sigTitleCustom" maxlength="100"
                               class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 bg-white"
                               placeholder="e.g. Wound Care Specialist"
                               value="<?= h($__isCustom ? ($user['sig_title'] ?? '') : '') ?>">
                    </div>
                    <button id="saveSigTitleBtn"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-violet-600 hover:bg-violet-700
                                   active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm whitespace-nowrap">
                        <i class="bi bi-floppy-fill"></i> Save Title
                    </button>
                </div>
                <?php if (!empty($user['sig_title'])): ?>
                <p class="text-xs text-slate-400 mt-3">
                    <i class="bi bi-info-circle"></i>
                    Current: <strong><?= h($user['sig_title']) ?></strong> — forms will show "<strong><?= h($user['sig_title']) ?> Signature</strong>"
                </p>
                <?php else: ?>
                <p class="text-xs text-slate-400 mt-3">
                    <i class="bi bi-info-circle"></i>
                    Default: <strong>Medical Assistant Signature</strong>
                </p>
                <?php endif; ?>
            </div>
        </div>
"""

old_col_close = "    </div><!-- /lg:col-span-2 -->"
if old_col_close in c:
    c = c.replace(old_col_close, SIG_TITLE_CARD + old_col_close, 1)
    print("  ✓ sig_title card inserted")
else:
    print("  ✗ FAILED: col-span-2 close tag not found")
    sys.exit(1)

SIG_TITLE_JS = """<script>
/* ── Signature Title Setting ── */
(function () {
    var select  = document.getElementById('sigTitleSelect');
    var custom  = document.getElementById('sigTitleCustom');
    var wrap    = document.getElementById('sigTitleCustomWrap');
    var saveBtn = document.getElementById('saveSigTitleBtn');
    var msgEl   = document.getElementById('sigTitleMsg');

    function showCustom(v) {
        if (wrap) wrap.style.display = (v === 'custom') ? '' : 'none';
    }
    if (select) {
        showCustom(select.value);
        select.addEventListener('change', function () { showCustom(this.value); });
    }

    function showMsg(text, type) {
        if (!msgEl) return;
        msgEl.textContent = text;
        msgEl.className = 'mb-4 text-sm font-semibold ' + (type === 'ok' ? 'text-violet-600' : 'text-red-500');
        msgEl.classList.remove('hidden');
        setTimeout(function () { msgEl.classList.add('hidden'); }, 6000);
    }

    saveBtn && saveBtn.addEventListener('click', function () {
        if (!select) return;
        var title = select.value === 'custom'
            ? (custom ? custom.value.trim() : '')
            : select.value;
        if (!title) { showMsg('Please enter a custom title.', 'err'); return; }
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving\u2026';
        fetch('<?= BASE_URL ?>/api/save_signature.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf: '<?= csrfToken() ?>', action: 'save_title', title: title })
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-floppy-fill"></i> Save Title';
            if (j.ok) {
                showMsg('\u2713 Saved \u2014 forms will now show \u201c' + title + ' Signature\u201d.', 'ok');
                // Update the info text below
                var info = document.querySelector('#sigTitleSection .text-xs.text-slate-400');
                if (info) info.innerHTML = '<i class="bi bi-info-circle"></i> Current: <strong>' + title + '</strong> \u2014 forms will show \u201c<strong>' + title + ' Signature</strong>\u201d';
            } else {
                showMsg('Error: ' + (j.error || 'Unknown error'), 'err');
            }
        })
        .catch(function () {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-floppy-fill"></i> Save Title';
            showMsg('Network error \u2014 please try again.', 'err');
        });
    });
})();
</script>

"""

old_footer = "<?php include __DIR__ . '/includes/footer.php'; ?>"
if old_footer in c:
    c = c.replace(old_footer, SIG_TITLE_JS + old_footer, 1)
    print("  ✓ sig_title JS inserted")
else:
    print("  ✗ FAILED: footer include not found")
    sys.exit(1)

write(path, c)
print("  ✓ profile.php saved")

# ── Done ─────────────────────────────────────────────────────────────────────
print("\n✅ All patches applied successfully!")
print("   - staff.sig_title column added")
print("   - sig_block.php: uses sig_title for signature block label")
print("   - api/save_signature.php: save_title action added")
print("   - profile.php: Signature Title card + JS added")
