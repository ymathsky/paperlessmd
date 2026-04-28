<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$pageTitle = 'My Profile';
$activeNav = 'profile';

// Load current user fresh from DB
$stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_name') {
            $full_name = trim($_POST['full_name'] ?? '');
            if (!$full_name) {
                $error = 'Full name is required.';
            } elseif (strlen($full_name) > 100) {
                $error = 'Full name must be 100 characters or fewer.';
            } else {
                $pdo->prepare("UPDATE staff SET full_name = ? WHERE id = ?")
                    ->execute([$full_name, (int)$_SESSION['user_id']]);
                $_SESSION['full_name'] = $full_name;
                $success = 'Display name updated.';
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
            }

        } elseif ($action === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password']     ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (!password_verify($current, $user['password_hash'])) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($new) < 8) {
                $error = 'New password must be at least 8 characters.';
            } elseif ($new !== $confirm) {
                $error = 'New passwords do not match.';
            } else {
                $pdo->prepare("UPDATE staff SET password_hash = ? WHERE id = ?")
                    ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$_SESSION['user_id']]);
                $success = 'Password changed successfully.';
            }
        }
    }
}

// Activity stats
$fStmt = $pdo->prepare("SELECT COUNT(*) FROM form_submissions WHERE ma_id = ?");
$fStmt->execute([(int)$_SESSION['user_id']]);
$formCount = (int)$fStmt->fetchColumn();

$vStmt = $pdo->prepare("SELECT COUNT(*) FROM `schedule` WHERE ma_id = ? AND status = 'completed'");
$vStmt->execute([(int)$_SESSION['user_id']]);
$visitsDone = (int)$vStmt->fetchColumn();

$vTotalStmt = $pdo->prepare("SELECT COUNT(*) FROM `schedule` WHERE ma_id = ?");
$vTotalStmt->execute([(int)$_SESSION['user_id']]);
$visitsTotal = (int)$vTotalStmt->fetchColumn();

$roleColors = [
    'admin'   => ['grad' => 'from-indigo-500 to-indigo-700',   'badge' => 'bg-indigo-100 text-indigo-700',   'label' => 'Administrator'],
    'ma'      => ['grad' => 'from-slate-500 to-slate-700',     'badge' => 'bg-slate-100 text-slate-700',     'label' => 'Medical Assistant'],
    'billing' => ['grad' => 'from-amber-400 to-amber-600',     'badge' => 'bg-amber-100 text-amber-700',     'label' => 'Billing'],
];
$rc = $roleColors[$user['role']] ?? $roleColors['ma'];

include __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-800">My Profile</h2>
        <p class="text-slate-500 text-sm mt-0.5">Manage your account information and password</p>
    </div>
</div>

<?php if ($success): ?>
<div class="mb-5 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm font-medium">
    <i class="bi bi-check-circle-fill flex-shrink-0"></i> <?= h($success) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm font-medium">
    <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i> <?= h($error) ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ── Left column: profile card + stats ── -->
    <div class="lg:col-span-1 space-y-5">

        <!-- Profile card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="h-20 bg-gradient-to-br <?= $rc['grad'] ?>"></div>
            <div class="px-6 pb-6">
                <div class="flex items-end gap-4 -mt-8 mb-4">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br <?= $rc['grad'] ?> ring-4 ring-white
                                grid place-items-center text-white text-xl font-extrabold shadow-lg shrink-0">
                        <?= strtoupper(mb_substr($user['full_name'], 0, 2)) ?>
                    </div>
                </div>
                <h3 class="text-lg font-extrabold text-slate-800 leading-tight"><?= h($user['full_name']) ?></h3>
                <p class="text-slate-500 text-sm mb-3">@<?= h($user['username']) ?></p>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?= $rc['badge'] ?>">
                    <?= $rc['label'] ?>
                </span>
            </div>
        </div>

        <!-- Info -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 space-y-4">
            <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wide">Account Details</h4>
            <div class="space-y-3 text-sm">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-slate-100 rounded-lg grid place-items-center shrink-0">
                        <i class="bi bi-person-fill text-slate-500"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">Full Name</p>
                        <p class="font-semibold text-slate-800"><?= h($user['full_name']) ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-slate-100 rounded-lg grid place-items-center shrink-0">
                        <i class="bi bi-at text-slate-500"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">Username</p>
                        <p class="font-semibold text-slate-800 font-mono"><?= h($user['username']) ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-slate-100 rounded-lg grid place-items-center shrink-0">
                        <i class="bi bi-shield-fill text-slate-500"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">Role</p>
                        <p class="font-semibold text-slate-800"><?= $rc['label'] ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-slate-100 rounded-lg grid place-items-center shrink-0">
                        <i class="bi bi-calendar3 text-slate-500"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">Member Since</p>
                        <p class="font-semibold text-slate-800"><?= date('M j, Y', strtotime($user['created_at'])) ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 <?= $user['active'] ? 'bg-emerald-50' : 'bg-red-50' ?> rounded-lg grid place-items-center shrink-0">
                        <i class="bi bi-circle-fill text-xs <?= $user['active'] ? 'text-emerald-500' : 'text-red-400' ?>"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">Status</p>
                        <p class="font-semibold <?= $user['active'] ? 'text-emerald-700' : 'text-red-600' ?>">
                            <?= $user['active'] ? 'Active' : 'Inactive' ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity stats -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-4">Activity</h4>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-indigo-50 rounded-xl p-3 text-center">
                    <p class="text-2xl font-extrabold text-indigo-700"><?= $formCount ?></p>
                    <p class="text-xs text-indigo-500 font-semibold mt-0.5">Forms Submitted</p>
                </div>
                <div class="bg-emerald-50 rounded-xl p-3 text-center">
                    <p class="text-2xl font-extrabold text-emerald-700"><?= $visitsDone ?></p>
                    <p class="text-xs text-emerald-500 font-semibold mt-0.5">Visits Completed</p>
                </div>
                <div class="col-span-2 bg-slate-50 rounded-xl p-3 text-center">
                    <p class="text-2xl font-extrabold text-slate-700"><?= $visitsTotal ?></p>
                    <p class="text-xs text-slate-400 font-semibold mt-0.5">Total Visits Assigned</p>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Right column: edit forms ── -->
    <div class="lg:col-span-2 space-y-5">

        <!-- Update display name -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-500 px-6 py-4 flex items-center gap-3">
                <div class="bg-white/20 p-2 rounded-xl shrink-0">
                    <i class="bi bi-person-fill text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-white font-bold">Update Display Name</h3>
                    <p class="text-blue-200 text-xs">Changes how your name appears across the app</p>
                </div>
            </div>
            <div class="p-6">
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="update_name">
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Full Name <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="full_name"
                               value="<?= h($user['full_name']) ?>"
                               maxlength="100"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                      transition focus:bg-white"
                               placeholder="Your full name" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Username</label>
                        <input type="text" value="<?= h($user['username']) ?>" disabled
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-100
                                      text-slate-400 font-mono cursor-not-allowed">
                        <p class="mt-1.5 text-xs text-slate-400">Username can only be changed by an administrator.</p>
                    </div>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700
                                   active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm">
                        <i class="bi bi-check-lg"></i> Save Name
                    </button>
                </form>
            </div>
        </div>

        <!-- Change password -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="bg-gradient-to-r from-violet-600 to-violet-500 px-6 py-4 flex items-center gap-3">
                <div class="bg-white/20 p-2 rounded-xl shrink-0">
                    <i class="bi bi-key-fill text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-white font-bold">Change Password</h3>
                    <p class="text-violet-200 text-xs">Minimum 8 characters</p>
                </div>
            </div>
            <div class="p-6">
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Current Password <span class="text-red-400">*</span>
                        </label>
                        <input type="password" name="current_password" autocomplete="current-password"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent
                                      transition focus:bg-white"
                               placeholder="Your current password" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                            New Password <span class="text-red-400">*</span>
                        </label>
                        <input type="password" name="new_password" id="newPw" autocomplete="new-password"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent
                                      transition focus:bg-white"
                               placeholder="At least 8 characters" required minlength="8">
                        <!-- Strength bar -->
                        <div class="mt-2 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div id="strengthBar" class="h-full rounded-full transition-all duration-300 w-0 bg-red-400"></div>
                        </div>
                        <p id="strengthLabel" class="text-xs text-slate-400 mt-1"></p>
                    </div>
                    <div class="mb-5">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Confirm New Password <span class="text-red-400">*</span>
                        </label>
                        <input type="password" name="confirm_password" id="confirmPw" autocomplete="new-password"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent
                                      transition focus:bg-white"
                               placeholder="Repeat new password" required>
                        <p id="matchMsg" class="text-xs mt-1 hidden"></p>
                    </div>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-violet-600 hover:bg-violet-700
                                   active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm">
                        <i class="bi bi-key-fill"></i> Update Password
                    </button>
                </form>
            </div>
        </div>

        <?php if (isAdmin()): ?>
        <!-- Admin: quick link to manage all staff -->
        <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-5 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-100 rounded-xl grid place-items-center shrink-0">
                    <i class="bi bi-people-fill text-indigo-600"></i>
                </div>
                <div>
                    <p class="font-semibold text-slate-800 text-sm">Manage All Staff</p>
                    <p class="text-xs text-slate-500">Add, edit, or deactivate staff accounts</p>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/admin/users.php"
               class="shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700
                      text-white text-sm font-semibold rounded-xl transition-colors">
                <i class="bi bi-gear-fill"></i> Manage Staff
            </a>
        </div>
        <?php endif; ?>

        <!-- ── Saved Signature ── -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden" id="savedSigSection">
            <div class="bg-gradient-to-r from-emerald-600 to-teal-500 px-6 py-4 flex items-center gap-3">
                <div class="bg-white/20 p-2 rounded-xl shrink-0">
                    <i class="bi bi-pen-fill text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-white font-bold">Pre-Saved Signature</h3>
                    <p class="text-emerald-100 text-xs">Draw once — auto-fills your signature on every form</p>
                </div>
            </div>
            <div class="p-6">
                <div id="savedSigMsg" class="hidden mb-4 text-sm font-semibold"></div>

                <?php if (!empty($user['saved_signature'])): ?>
                <!-- Currently saved signature preview -->
                <div id="savedSigPreview" class="mb-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Current Saved Signature</p>
                    <div class="border border-slate-200 rounded-xl bg-slate-50 p-3 inline-block">
                        <img src="<?= h($user['saved_signature']) ?>" alt="Saved signature" class="max-h-16 max-w-xs object-contain">
                    </div>
                    <?php if ($user['saved_sig_updated_at']): ?>
                    <p class="text-xs text-slate-400 mt-1">Saved <?= date('M j, Y', strtotime($user['saved_sig_updated_at'])) ?></p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div id="savedSigPreview" class="hidden"></div>
                <?php endif; ?>

                <p class="text-sm text-slate-600 mb-4">Your signature will be automatically applied to the <strong>MA signature</strong> field on every form — you can still clear and re-sign on any individual form if needed.</p>

                <!-- Tab switcher: Draw / Upload -->
                <div class="flex gap-1 p-1 bg-slate-100 rounded-xl mb-4 w-fit">
                    <button type="button" id="tabDraw"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all
                                   bg-white text-slate-800 shadow-sm">
                        <i class="bi bi-pen"></i> Draw
                    </button>
                    <button type="button" id="tabUpload"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all
                                   text-slate-500 hover:text-slate-700">
                        <i class="bi bi-upload"></i> Upload
                    </button>
                </div>

                <!-- Draw panel -->
                <div id="panelDraw">
                    <div class="relative border-2 border-dashed border-slate-300 rounded-2xl bg-white overflow-hidden focus-within:border-emerald-400 transition-colors" style="touch-action:none;" id="savedSigWrapper">
                        <canvas id="savedSigCanvas" style="display:block;width:100%;height:140px;touch-action:none;cursor:crosshair;"></canvas>
                        <div id="savedSigPlaceholder" class="absolute inset-0 flex items-center justify-center text-slate-300 pointer-events-none select-none italic text-sm">
                            Draw your signature here
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-4">
                        <button id="saveSigBtn"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700
                                       active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="bi bi-floppy-fill"></i> Save Signature
                        </button>
                        <button id="clearSigPadBtn"
                                class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200
                                       text-slate-600 font-semibold rounded-xl text-sm transition-all">
                            <i class="bi bi-eraser"></i> Clear Pad
                        </button>
                    </div>
                </div>

                <!-- Upload panel -->
                <div id="panelUpload" class="hidden">
                    <!-- Drop zone -->
                    <div id="uploadDropZone"
                         class="border-2 border-dashed border-slate-300 rounded-2xl bg-slate-50 hover:bg-emerald-50
                                hover:border-emerald-400 transition-colors cursor-pointer flex flex-col items-center
                                justify-center gap-3 py-8 px-4 text-center">
                        <div class="w-12 h-12 bg-slate-100 rounded-2xl grid place-items-center">
                            <i class="bi bi-image text-slate-400 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Drop an image here, or <span class="text-emerald-600 underline">browse</span></p>
                            <p class="text-xs text-slate-400 mt-0.5">PNG, JPG or GIF — white/transparent background recommended</p>
                        </div>
                        <input type="file" id="sigUploadInput" accept="image/png,image/jpeg,image/gif"
                               class="hidden">
                    </div>
                    <!-- Preview after file chosen -->
                    <div id="uploadPreviewWrapper" class="hidden mt-4">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Preview</p>
                        <div class="border border-slate-200 rounded-xl bg-white p-3 inline-block">
                            <img id="uploadPreviewImg" src="" alt="Signature preview" class="max-h-20 max-w-xs object-contain">
                        </div>
                        <p id="uploadFileName" class="text-xs text-slate-400 mt-1"></p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-4">
                        <button id="saveUploadBtn"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700
                                       active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm
                                       disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <i class="bi bi-floppy-fill"></i> Save Uploaded Signature
                        </button>
                        <button id="clearUploadBtn"
                                class="hidden inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200
                                       text-slate-600 font-semibold rounded-xl text-sm transition-all">
                            <i class="bi bi-x-circle"></i> Clear
                        </button>
                    </div>
                </div>

                <!-- Delete button (shown regardless of tab) -->
                <?php if (!empty($user['saved_signature'])): ?>
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <button id="deleteSavedSigBtn"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-50 hover:bg-red-100
                                   text-red-600 font-semibold rounded-xl text-sm transition-all">
                        <i class="bi bi-trash3"></i> Remove Saved Signature
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /lg:col-span-2 -->

<script>
// Password strength indicator
const newPw    = document.getElementById('newPw');
const confirmPw = document.getElementById('confirmPw');
const bar      = document.getElementById('strengthBar');
const label    = document.getElementById('strengthLabel');
const matchMsg = document.getElementById('matchMsg');

function calcStrength(pw) {
    let score = 0;
    if (pw.length >= 8)  score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    return score;
}

newPw.addEventListener('input', () => {
    const pw = newPw.value;
    if (!pw) { bar.style.width = '0'; label.textContent = ''; return; }
    const s = calcStrength(pw);
    const map = [
        { w: '20%',  cls: 'bg-red-400',    txt: 'Very weak',  color: 'text-red-500'   },
        { w: '40%',  cls: 'bg-orange-400', txt: 'Weak',       color: 'text-orange-500'},
        { w: '60%',  cls: 'bg-yellow-400', txt: 'Fair',       color: 'text-yellow-600'},
        { w: '80%',  cls: 'bg-lime-400',   txt: 'Good',       color: 'text-lime-600'  },
        { w: '100%', cls: 'bg-emerald-500',txt: 'Strong',     color: 'text-emerald-600'},
    ];
    const m = map[Math.min(s, 4)];
    bar.className = 'h-full rounded-full transition-all duration-300 ' + m.cls;
    bar.style.width = m.w;
    label.className = 'text-xs mt-1 ' + m.color;
    label.textContent = m.txt;
});

function checkMatch() {
    const a = newPw.value, b = confirmPw.value;
    if (!b) { matchMsg.classList.add('hidden'); return; }
    matchMsg.classList.remove('hidden');
    if (a === b) {
        matchMsg.className = 'text-xs mt-1 text-emerald-600 font-semibold';
        matchMsg.textContent = '✓ Passwords match';
    } else {
        matchMsg.className = 'text-xs mt-1 text-red-500 font-semibold';
        matchMsg.textContent = '✗ Passwords do not match';
    }
}

newPw.addEventListener('input', checkMatch);
confirmPw.addEventListener('input', checkMatch);
</script>

<script>
/* ── Profile: Saved Signature Pad + Upload ── */
(function () {
    var canvas      = document.getElementById('savedSigCanvas');
    var wrapper     = document.getElementById('savedSigWrapper');
    var placeholder = document.getElementById('savedSigPlaceholder');
    var saveBtn     = document.getElementById('saveSigBtn');
    var clearBtn    = document.getElementById('clearSigPadBtn');
    var deleteBtn   = document.getElementById('deleteSavedSigBtn');
    var msgEl       = document.getElementById('savedSigMsg');
    var previewEl   = document.getElementById('savedSigPreview');

    // ── Tab switcher ──────────────────────────────────────────────────
    var tabDraw   = document.getElementById('tabDraw');
    var tabUpload = document.getElementById('tabUpload');
    var panelDraw = document.getElementById('panelDraw');
    var panelUpload = document.getElementById('panelUpload');

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

    // ── Draw pad ──────────────────────────────────────────────────────
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
            pad.clear();
        }

        pad = new SignaturePad(canvas, { penColor: 'rgb(15,23,42)', minWidth: 1.5, maxWidth: 3 });
        pad.addEventListener('beginStroke', function () { placeholder.style.display = 'none'; });

        (function tryInit(n) {
            var w = wrapper.getBoundingClientRect().width || wrapper.offsetWidth;
            if (!w && n < 30) { requestAnimationFrame(function () { tryInit(n + 1); }); return; }
            resizeCanvas();
        })(0);
        window.addEventListener('resize', function () { resizeCanvas(); });
    }

    clearBtn && clearBtn.addEventListener('click', function () {
        if (pad) { pad.clear(); }
        if (placeholder) placeholder.style.display = '';
    });

    // ── Upload panel ──────────────────────────────────────────────────
    var dropZone       = document.getElementById('uploadDropZone');
    var fileInput      = document.getElementById('sigUploadInput');
    var previewWrapper = document.getElementById('uploadPreviewWrapper');
    var previewImg     = document.getElementById('uploadPreviewImg');
    var fileNameEl     = document.getElementById('uploadFileName');
    var saveUploadBtn  = document.getElementById('saveUploadBtn');
    var clearUploadBtn = document.getElementById('clearUploadBtn');
    var _uploadDataURL = null;

    function handleFile(file) {
        if (!file || !file.type.startsWith('image/')) {
            showMsg('Please select a PNG, JPG, or GIF image.', 'err'); return;
        }
        if (file.size > 2 * 1024 * 1024) {
            showMsg('Image must be under 2 MB.', 'err'); return;
        }
        var reader = new FileReader();
        reader.onload = function (e) {
            // Convert to PNG via canvas for consistent base64 format
            var img = new Image();
            img.onload = function () {
                var cvs = document.createElement('canvas');
                // Scale down if very large, keep aspect ratio, max 600×200
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

    dropZone && dropZone.addEventListener('dragover',  function (e) { e.preventDefault(); dropZone.classList.add('border-emerald-400', 'bg-emerald-50'); });
    dropZone && dropZone.addEventListener('dragleave', function ()  { dropZone.classList.remove('border-emerald-400', 'bg-emerald-50'); });
    dropZone && dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZone.classList.remove('border-emerald-400', 'bg-emerald-50');
        var f = e.dataTransfer.files && e.dataTransfer.files[0];
        if (f) handleFile(f);
    });

    clearUploadBtn && clearUploadBtn.addEventListener('click', function () {
        _uploadDataURL = null;
        previewWrapper.classList.add('hidden');
        saveUploadBtn.disabled = true;
        clearUploadBtn.classList.add('hidden');
        if (fileInput) fileInput.value = '';
    });

    // ── Shared helpers ────────────────────────────────────────────────
    function showMsg(text, type) {
        msgEl.textContent = text;
        msgEl.className = 'mb-4 text-sm font-semibold ' + (type === 'ok' ? 'text-emerald-600' : 'text-red-500');
        msgEl.classList.remove('hidden');
        setTimeout(function () { msgEl.classList.add('hidden'); }, 5000);
    }

    function updatePreview(dataURL) {
        var img = previewEl.querySelector('img');
        if (img) {
            img.src = dataURL;
        } else {
            previewEl.innerHTML = '<p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Current Saved Signature</p>' +
                '<div class="border border-slate-200 rounded-xl bg-slate-50 p-3 inline-block">' +
                '<img src="' + dataURL + '" alt="Saved signature" class="max-h-16 max-w-xs object-contain"></div>';
            previewEl.classList.remove('hidden');
        }
        if (!deleteBtn) location.reload();
    }

    function postSig(dataURL, btn, originalLabel) {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';
        fetch('<?= BASE_URL ?>/api/save_signature.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf: '<?= csrfToken() ?>', action: 'save', signature: dataURL })
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            btn.disabled = false;
            btn.innerHTML = originalLabel;
            if (j.ok) {
                showMsg('✓ Signature saved — forms will auto-fill from now on.', 'ok');
                updatePreview(dataURL);
            } else {
                showMsg('Error: ' + (j.error || 'Unknown error'), 'err');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = originalLabel;
            showMsg('Network error — please try again.', 'err');
        });
    }

    // ── Save (draw) ───────────────────────────────────────────────────
    saveBtn && saveBtn.addEventListener('click', function () {
        if (!pad || pad.isEmpty()) { showMsg('Please draw your signature first.', 'err'); return; }
        postSig(pad.toDataURL('image/png'), saveBtn, '<i class="bi bi-floppy-fill"></i> Save Signature');
    });

    // ── Save (upload) ─────────────────────────────────────────────────
    saveUploadBtn && saveUploadBtn.addEventListener('click', function () {
        if (!_uploadDataURL) { showMsg('Please choose an image first.', 'err'); return; }
        postSig(_uploadDataURL, saveUploadBtn, '<i class="bi bi-floppy-fill"></i> Save Uploaded Signature');
    });

    // ── Delete ────────────────────────────────────────────────────────
    deleteBtn && deleteBtn.addEventListener('click', function () {
        if (!confirm('Remove your saved signature? Forms will require manual signing again.')) return;
        deleteBtn.disabled = true;
        fetch('<?= BASE_URL ?>/api/save_signature.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf: '<?= csrfToken() ?>', action: 'clear' })
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (j.ok) { location.reload(); }
            else { deleteBtn.disabled = false; showMsg('Error: ' + j.error, 'err'); }
        });
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
