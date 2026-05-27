#!/usr/bin/env python3
"""
move_sig_title.py
Moves sig_title setting from profile.php to admin/manage_user.php (admin-only).
"""
import subprocess, sys, re

BASE = '/var/www/paperlessmd'

def read(p):
    return open(p, encoding='utf-8').read()

def write(p, c):
    open(p, 'w', encoding='utf-8').write(c)

# ── 1. admin/manage_user.php ────────────────────────────────────────────────
print("1. Patching admin/manage_user.php...")
path = BASE + '/admin/manage_user.php'
c = read(path)

# 1a. Add sig_title to isEdit $vals
old = "    $vals = ['username' => $user['username'], 'full_name' => $user['full_name'], 'email' => $user['email'] ?? '', 'role' => $user['role']];"
new = "    $vals = ['username' => $user['username'], 'full_name' => $user['full_name'], 'email' => $user['email'] ?? '', 'role' => $user['role'], 'sig_title' => $user['sig_title'] ?? ''];"
assert old in c, "FAIL: isEdit vals line not found"
c = c.replace(old, new, 1)
print("  ✓ isEdit vals updated")

# 1b. Add sig_title to POST $vals
old = "        'role'      => in_array($_POST['role'] ?? '', ['admin','ma','billing','scheduler','provider','pcc']) ? $_POST['role'] : 'ma',\n    ];"
new = "        'role'      => in_array($_POST['role'] ?? '', ['admin','ma','billing','scheduler','provider','pcc']) ? $_POST['role'] : 'ma',\n        'sig_title' => trim($_POST['sig_title'] ?? ''),\n    ];"
assert old in c, "FAIL: POST vals role line not found"
c = c.replace(old, new, 1)
print("  ✓ POST vals updated")

# 1c. UPDATE with password
old = '''            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE staff SET username=?, full_name=?, email=?, role=?, password_hash=? WHERE id=?")
                    ->execute([$vals['username'], $vals['full_name'], $vals['email'] ?: null, $vals['role'], $hash, $id]);
            } else {
                $pdo->prepare("UPDATE staff SET username=?, full_name=?, email=?, role=? WHERE id=?")
                    ->execute([$vals['username'], $vals['full_name'], $vals['email'] ?: null, $vals['role'], $id]);
            }'''
new = '''            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE staff SET username=?, full_name=?, email=?, role=?, sig_title=?, password_hash=? WHERE id=?")
                    ->execute([$vals['username'], $vals['full_name'], $vals['email'] ?: null, $vals['role'], $vals['sig_title'] ?: null, $hash, $id]);
            } else {
                $pdo->prepare("UPDATE staff SET username=?, full_name=?, email=?, role=?, sig_title=? WHERE id=?")
                    ->execute([$vals['username'], $vals['full_name'], $vals['email'] ?: null, $vals['role'], $vals['sig_title'] ?: null, $id]);
            }'''
assert old in c, "FAIL: UPDATE with password block not found"
c = c.replace(old, new, 1)
print("  ✓ UPDATE queries updated")

# 1d. INSERT
old = '''            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO staff (username, full_name, email, role, password_hash, active) VALUES (?,?,?,?,?,1)")
                ->execute([$vals['username'], $vals['full_name'], $vals['email'] ?: null, $vals['role'], $hash]);'''
new = '''            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO staff (username, full_name, email, role, sig_title, password_hash, active) VALUES (?,?,?,?,?,?,1)")
                ->execute([$vals['username'], $vals['full_name'], $vals['email'] ?: null, $vals['role'], $vals['sig_title'] ?: null, $hash]);'''
assert old in c, "FAIL: INSERT block not found"
c = c.replace(old, new, 1)
print("  ✓ INSERT query updated")

# 1e. Add UI field: a select before the Password section
SIG_TITLE_FIELD = '''
            <!-- Signature Title -->
            <div class="mb-5">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5 flex items-center gap-2">
                    <i class="bi bi-tag-fill text-violet-400 text-sm"></i> Form Signature Title
                    <span class="text-xs font-normal text-slate-400">(label shown on signature block)</span>
                </label>
                <?php
                $__stPresets = ['Medical Assistant','Provider','Physician','Physician Assistant','Nurse Practitioner','LPN','Clinical Staff','Administrator'];
                $__stIsCustom = !empty($vals['sig_title']) && !in_array($vals['sig_title'], $__stPresets, true);
                ?>
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1">
                        <select name="sig_title" id="sigTitleSel"
                                class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                       focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:bg-white transition">
                            <option value="Medical Assistant"<?= (empty($vals['sig_title']) || $vals['sig_title'] === 'Medical Assistant') ? ' selected' : '' ?>>Medical Assistant</option>
                            <option value="Provider"<?= $vals['sig_title'] === 'Provider' ? ' selected' : '' ?>>Provider</option>
                            <option value="Physician"<?= $vals['sig_title'] === 'Physician' ? ' selected' : '' ?>>Physician</option>
                            <option value="Physician Assistant"<?= $vals['sig_title'] === 'Physician Assistant' ? ' selected' : '' ?>>Physician Assistant</option>
                            <option value="Nurse Practitioner"<?= $vals['sig_title'] === 'Nurse Practitioner' ? ' selected' : '' ?>>Nurse Practitioner</option>
                            <option value="LPN"<?= $vals['sig_title'] === 'LPN' ? ' selected' : '' ?>>LPN</option>
                            <option value="Clinical Staff"<?= $vals['sig_title'] === 'Clinical Staff' ? ' selected' : '' ?>>Clinical Staff</option>
                            <option value="Administrator"<?= $vals['sig_title'] === 'Administrator' ? ' selected' : '' ?>>Administrator</option>
                            <option value="__custom__"<?= $__stIsCustom ? ' selected' : '' ?>>Custom…</option>
                        </select>
                    </div>
                    <div id="sigTitleCustomWrap" class="flex-1" style="display:<?= $__stIsCustom ? '' : 'none' ?>;">
                        <input type="text" name="sig_title_custom" id="sigTitleCustom" maxlength="100"
                               value="<?= h($__stIsCustom ? $vals['sig_title'] : '') ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:bg-white transition"
                               placeholder="e.g. Wound Care Specialist">
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-1.5 ml-1">Forms will display "<span id="sigTitlePreview"><?= h($vals['sig_title'] ?: 'Medical Assistant') ?></span> Signature" on the staff signing block.</p>
            </div>

'''

old_pw_section = '            <!-- Password section -->'
assert old_pw_section in c, "FAIL: password section comment not found"
c = c.replace(old_pw_section, SIG_TITLE_FIELD + '            <!-- Password section -->', 1)
print("  ✓ sig_title UI field inserted")

# 1f. Add JS for sig_title custom toggle (before closing </script> of pmToggle)
SIG_TITLE_JS = """
// Signature title custom toggle
(function () {
    var sel     = document.getElementById('sigTitleSel');
    var wrap    = document.getElementById('sigTitleCustomWrap');
    var custom  = document.getElementById('sigTitleCustom');
    var preview = document.getElementById('sigTitlePreview');
    if (!sel) return;

    function syncTitle() {
        var val = sel.value === '__custom__' ? (custom ? custom.value.trim() : '') : sel.value;
        if (preview) preview.textContent = val || 'Medical Assistant';
        wrap.style.display = sel.value === '__custom__' ? '' : 'none';
        // Relay custom value into sig_title select so the POST sends it
        if (sel.value !== '__custom__' && custom) custom.value = '';
    }
    sel.addEventListener('change', syncTitle);
    custom && custom.addEventListener('input', syncTitle);

    // On submit: if custom chosen, copy value into a hidden field
    var form = sel.closest('form');
    if (form) {
        form.addEventListener('submit', function () {
            if (sel.value === '__custom__') {
                sel.name = '_sig_title_ignored';  // disable select
                var hid = document.createElement('input');
                hid.type  = 'hidden';
                hid.name  = 'sig_title';
                hid.value = custom ? custom.value.trim() : '';
                form.appendChild(hid);
            }
        });
    }
})();
"""

old_script_end = "</script>\n\n<?php include __DIR__ . '/../includes/footer.php'; ?>"
assert old_script_end in c, "FAIL: closing script/footer not found"
c = c.replace(old_script_end, SIG_TITLE_JS + "</script>\n\n<?php include __DIR__ . '/../includes/footer.php'; ?>", 1)
print("  ✓ sig_title JS added")

write(path, c)
print("  ✓ manage_user.php saved")

# ── 2. profile.php — remove sig_title card + JS ──────────────────────────────
print("\n2. Patching profile.php (removing sig_title card)...")
path = BASE + '/profile.php'
c = read(path)

# Remove the sig_title card block (from the comment to the closing </div>)
# The card was inserted right before "    </div><!-- /lg:col-span-2 -->"
# Match from the card's opening comment to (but not including) the col-span-2 close
card_start_marker = '\n        <!-- ── Signature Title ── -->'
col_close_marker  = '\n    </div><!-- /lg:col-span-2 -->'

idx_start = c.find(card_start_marker)
idx_end   = c.find(col_close_marker)
assert idx_start != -1, "FAIL: sig_title card start not found in profile.php"
assert idx_end   != -1, "FAIL: col-span-2 close not found in profile.php"
assert idx_start < idx_end, "FAIL: card start is after col close"
c = c[:idx_start] + c[idx_end:]
print("  ✓ sig_title card removed from profile.php")

# Remove the sig_title JS block
js_start_marker = '\n<script>\n/* ── Signature Title Setting ── */'
js_end_marker   = '\n</script>\n\n<?php include __DIR__ . \'/includes/footer.php\'; ?>'

idx_js_start = c.find(js_start_marker)
idx_footer   = c.find(js_end_marker)
assert idx_js_start != -1, "FAIL: sig_title JS start not found"
assert idx_footer   != -1, "FAIL: footer include not found"
# Remove from js_start up to (but not including) the footer include line
c = c[:idx_js_start] + '\n\n<?php include __DIR__ . \'/includes/footer.php\'; ?>'
print("  ✓ sig_title JS removed from profile.php")

write(path, c)
print("  ✓ profile.php saved")

# ── 3. api/save_signature.php — remove save_title action ────────────────────
print("\n3. Patching api/save_signature.php (removing save_title action)...")
path = BASE + '/api/save_signature.php'
c = read(path)

old_save_title = """\n} elseif ($action === 'save_title') {
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

} else {"""

new_save_title = "\n} else {"
assert old_save_title in c, "FAIL: save_title action not found in save_signature.php"
c = c.replace(old_save_title, new_save_title, 1)
print("  ✓ save_title action removed")

write(path, c)
print("  ✓ api/save_signature.php saved")

print("\n✅ All done!")
print("   - admin/manage_user.php: sig_title field added (admin-only via requireAdmin)")
print("   - profile.php: sig_title card + JS removed")
print("   - api/save_signature.php: save_title action removed")
