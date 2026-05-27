#!/usr/bin/env python3
"""
patch_send_credentials.py
Adds "Send Account Info" email button to admin/users.php.
"""
BASE = '/var/www/paperlessmd'

def read(p):
    return open(p, encoding='utf-8').read()

def write(p, c):
    open(p, 'w', encoding='utf-8').write(c)

path = BASE + '/admin/users.php'
c = read(path)

# ── 1. Add mailer include + send_credentials POST handler ───────────────────
old_requires = """require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();
requireAdmin();

$pageTitle = 'Manage Staff';
$activeNav = '';
$msg       = $_GET['msg'] ?? '';

$staff = $pdo->query("SELECT * FROM staff ORDER BY role DESC, full_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    verifyCsrf();
    $uid = (int)($_POST['user_id'] ?? 0);
    $pdo->prepare("UPDATE staff SET active = NOT active WHERE id = ? AND id != ?")->execute([$uid, $_SESSION['user_id']]);
    header('Location: ' . BASE_URL . '/admin/users.php?msg=updated');
    exit;
}"""

new_requires = """require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
requireLogin();
requireAdmin();

$pageTitle = 'Manage Staff';
$activeNav = '';
$msg       = $_GET['msg'] ?? '';

$staff = $pdo->query("SELECT * FROM staff ORDER BY role DESC, full_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $pdo->prepare("UPDATE staff SET active = NOT active WHERE id = ? AND id != ?")->execute([$uid, $_SESSION['user_id']]);
        header('Location: ' . BASE_URL . '/admin/users.php?msg=updated');
        exit;
    }

    if ($action === 'send_credentials') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $row  = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
        $row->execute([$uid]);
        $u    = $row->fetch();

        if (!$u || empty($u['email'])) {
            header('Location: ' . BASE_URL . '/admin/users.php?msg=no_email');
            exit;
        }

        $sigTitle  = !empty($u['sig_title']) ? $u['sig_title'] : 'Medical Assistant';
        $roleLbl   = ucfirst($u['role']);
        $loginUrl  = BASE_URL . '/index.php';
        $appName   = defined('APP_NAME') ? APP_NAME : 'PaperlessMD';

        $html = '
<p>Hi <strong>' . htmlspecialchars($u['full_name'], ENT_QUOTES) . '</strong>,</p>
<p>Here is a summary of your <strong>' . $appName . '</strong> account. Keep this information safe — your username is required to log in.</p>
<dl class="meta">
  <dt>Full Name</dt>      <dd>' . htmlspecialchars($u['full_name'],  ENT_QUOTES) . '</dd>
  <dt>Username</dt>       <dd><strong>' . htmlspecialchars($u['username'],  ENT_QUOTES) . '</strong></dd>
  <dt>Role</dt>           <dd>' . $roleLbl . '</dd>
  <dt>Signature Title</dt><dd>' . htmlspecialchars($sigTitle, ENT_QUOTES) . '</dd>
  <dt>Status</dt>         <dd>' . ($u['active'] ? 'Active' : 'Inactive') . '</dd>
</dl>
<p style="margin-bottom:6px;">Log in here:</p>
<a href="' . $loginUrl . '" class="btn">Log in to ' . $appName . '</a>
<p style="font-size:12px;color:#94a3b8;margin-top:18px;">If you need to reset your password, contact your administrator. Do not share your credentials with anyone.</p>';

        $sent = sendMail($u['email'], 'Your ' . $appName . ' Account Information', $html);
        header('Location: ' . BASE_URL . '/admin/users.php?msg=' . ($sent ? 'sent' : 'mail_fail'));
        exit;
    }
}"""

assert old_requires in c, "FAIL: original requires/POST block not found"
c = c.replace(old_requires, new_requires, 1)
print("✓ POST handler + mailer include added")

# ── 2. Add flash messages for sent / no_email / mail_fail ───────────────────
old_flash = """<?php if ($msg === 'updated'): ?>
<div class="mb-5 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm">
    <i class="bi bi-check-circle-fill flex-shrink-0"></i> Staff updated successfully.
</div>
<?php endif; ?>"""

new_flash = """<?php if ($msg === 'updated'): ?>
<div class="mb-5 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm">
    <i class="bi bi-check-circle-fill flex-shrink-0"></i> Staff updated successfully.
</div>
<?php elseif ($msg === 'sent'): ?>
<div class="mb-5 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm">
    <i class="bi bi-envelope-check-fill flex-shrink-0"></i> Account information email sent successfully.
</div>
<?php elseif ($msg === 'no_email'): ?>
<div class="mb-5 flex items-center gap-3 bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-xl text-sm">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i> This staff member has no email address on file. Add one in Edit Staff first.
</div>
<?php elseif ($msg === 'mail_fail'): ?>
<div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
    <i class="bi bi-x-circle-fill flex-shrink-0"></i> Email could not be sent. Check SMTP settings.
</div>
<?php endif; ?>"""

assert old_flash in c, "FAIL: flash block not found"
c = c.replace(old_flash, new_flash, 1)
print("✓ Flash messages added")

# ── 3. Add email address column header ──────────────────────────────────────
old_thead = """                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Role</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                    <th class="px-4 py-3.5"></th>"""

new_thead = """                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Role</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide hidden sm:table-cell">Email</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                    <th class="px-4 py-3.5"></th>"""

assert old_thead in c, "FAIL: thead not found"
c = c.replace(old_thead, new_thead, 1)
print("✓ Email column header added")

# ── 4. Add email column cell in each row ────────────────────────────────────
old_role_cell = """                    <td class="px-4 py-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                                     <?php
                                     $roleCls = ['admin'=>'bg-indigo-100 text-indigo-700','billing'=>'bg-amber-100 text-amber-700','scheduler'=>'bg-violet-100 text-violet-700','provider'=>'bg-teal-100 text-teal-700'];
                                     echo $roleCls[$u['role']] ?? 'bg-slate-100 text-slate-600';
                                     ?>">
                            <?= ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-4">"""

new_role_cell = """                    <td class="px-4 py-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                                     <?php
                                     $roleCls = ['admin'=>'bg-indigo-100 text-indigo-700','billing'=>'bg-amber-100 text-amber-700','scheduler'=>'bg-violet-100 text-violet-700','provider'=>'bg-teal-100 text-teal-700'];
                                     echo $roleCls[$u['role']] ?? 'bg-slate-100 text-slate-600';
                                     ?>">
                            <?= ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-4 hidden sm:table-cell">
                        <?php if (!empty($u['email'])): ?>
                        <span class="text-xs text-slate-500"><?= h($u['email']) ?></span>
                        <?php else: ?>
                        <span class="text-xs text-slate-300 italic">none</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-4">"""

assert old_role_cell in c, "FAIL: role cell not found"
c = c.replace(old_role_cell, new_role_cell, 1)
print("✓ Email column cell added")

# ── 5. Add "Send Account Info" button to actions column ─────────────────────
old_actions = """                        <div class="flex items-center gap-2">
                            <a href="<?= BASE_URL ?>/admin/manage_user.php?id=<?= $u['id'] ?>"
                               class="text-blue-600 hover:text-blue-800 font-semibold text-xs bg-blue-50 hover:bg-blue-100 px-3.5 py-2 rounded-xl transition-colors">
                                Edit
                            </a>
                            <?php if (!$isSelf): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token"  value="<?= csrfToken() ?>">
                                <input type="hidden" name="action"      value="toggle">
                                <input type="hidden" name="user_id"     value="<?= $u['id'] ?>">
                                <button type="submit"
                                        class="text-xs font-semibold px-3.5 py-2 rounded-xl transition-colors
                                               <?= $isActive ? 'text-red-600 bg-red-50 hover:bg-red-100' : 'text-emerald-600 bg-emerald-50 hover:bg-emerald-100' ?>">
                                    <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>"""

new_actions = """                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="<?= BASE_URL ?>/admin/manage_user.php?id=<?= $u['id'] ?>"
                               class="text-blue-600 hover:text-blue-800 font-semibold text-xs bg-blue-50 hover:bg-blue-100 px-3.5 py-2 rounded-xl transition-colors">
                                Edit
                            </a>
                            <?php if (!empty($u['email'])): ?>
                            <form method="POST" class="inline"
                                  onsubmit="return confirm('Send account info to <?= h(addslashes($u['full_name'])) ?> at <?= h($u['email']) ?>?')">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="action"     value="send_credentials">
                                <input type="hidden" name="user_id"    value="<?= $u['id'] ?>">
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 text-xs font-semibold px-3.5 py-2 rounded-xl transition-colors
                                               text-violet-600 bg-violet-50 hover:bg-violet-100"
                                        title="Email account info to <?= h($u['email']) ?>">
                                    <i class="bi bi-envelope-arrow-up-fill"></i> Send Info
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-3.5 py-2 rounded-xl
                                         text-slate-300 bg-slate-50 cursor-not-allowed"
                                  title="No email address on file — edit this staff member to add one">
                                <i class="bi bi-envelope-x"></i> Send Info
                            </span>
                            <?php endif; ?>
                            <?php if (!$isSelf): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="csrf_token"  value="<?= csrfToken() ?>">
                                <input type="hidden" name="action"      value="toggle">
                                <input type="hidden" name="user_id"     value="<?= $u['id'] ?>">
                                <button type="submit"
                                        class="text-xs font-semibold px-3.5 py-2 rounded-xl transition-colors
                                               <?= $isActive ? 'text-red-600 bg-red-50 hover:bg-red-100' : 'text-emerald-600 bg-emerald-50 hover:bg-emerald-100' ?>">
                                    <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>"""

assert old_actions in c, "FAIL: actions column not found"
c = c.replace(old_actions, new_actions, 1)
print("✓ Send Account Info button added")

write(path, c)
print("✓ admin/users.php saved")
print("\n✅ Done!")
