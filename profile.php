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
        } elseif ($action === 'update_email') {
            $email = trim($_POST['email'] ?? '');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif (strlen($email) > 200) {
                $error = 'Email address is too long.';
            } else {
                $pdo->prepare("UPDATE staff SET email = ? WHERE id = ?")
                    ->execute([$email ?: null, (int)$_SESSION['user_id']]);
                $success = 'Email address updated.';
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
        } elseif ($action === 'toggle_dark_mode') {
            $newVal = ($_POST['dark_mode'] ?? '0') === '1' ? 1 : 0;
            $pdo->prepare("UPDATE staff SET dark_mode = ? WHERE id = ?")
                ->execute([$newVal, (int)$_SESSION['user_id']]);
            $_SESSION['dark_mode'] = (bool)$newVal;
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'dark_mode' => $newVal]);
            exit;
        } elseif ($action === 'update_push_pref') {
            $key = trim($_POST['pref_key'] ?? '');
            $val = ($_POST['pref_value'] ?? '1') === '1';
            $validKeys = ['new_message','form_signed','form_countersigned','schedule_assigned','daily_route','account_locked'];
            header('Content-Type: application/json');
            if (!in_array($key, $validKeys, true)) {
                echo json_encode(['error' => 'Invalid preference key']);
                exit;
            }
            $s2 = $pdo->prepare('SELECT push_prefs FROM staff WHERE id = ? LIMIT 1');
            $s2->execute([(int)$_SESSION['user_id']]);
            $prefs = json_decode((string)($s2->fetchColumn() ?: '{}'), true) ?? [];
            $prefs[$key] = $val;
            $pdo->prepare('UPDATE staff SET push_prefs = ? WHERE id = ?')
                ->execute([json_encode($prefs), (int)$_SESSION['user_id']]);
            echo json_encode(['ok' => true]);
            exit;
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

// Push notification preferences
$pushPrefs = json_decode((string)($user['push_prefs'] ?? '{}'), true) ?? [];
$allPushTypes = [
    'new_message'        => ['label' => 'New messages',                   'desc' => 'Alert when you receive a new message',                    'icon' => 'bi-chat-dots-fill',          'ibg' => 'bg-blue-500',    'roles' => null],
    'form_signed'        => ['label' => 'Form submitted for signature',   'desc' => 'A patient form is ready for your signature',             'icon' => 'bi-pen-fill',                'ibg' => 'bg-violet-500',  'roles' => ['admin','provider','pcc']],
    'form_countersigned' => ['label' => 'Form countersigned by provider', 'desc' => 'A provider countersigned a form you submitted',           'icon' => 'bi-check2-all',              'ibg' => 'bg-emerald-500', 'roles' => ['admin','ma','pcc']],
    'schedule_assigned'  => ['label' => 'New visit assigned to me',       'desc' => 'A patient visit has been scheduled for you',             'icon' => 'bi-calendar2-check-fill',    'ibg' => 'bg-amber-500',   'roles' => ['admin','ma','scheduler','pcc']],
    'daily_route'        => ['label' => 'Daily morning route summary',    'desc' => 'Morning overview of your visits for the day',            'icon' => 'bi-map-fill',                'ibg' => 'bg-cyan-600',    'roles' => ['ma']],
    'account_locked'     => ['label' => 'Account lockout alerts',         'desc' => 'A staff account was locked out of the system',           'icon' => 'bi-shield-exclamation-fill', 'ibg' => 'bg-red-500',     'roles' => ['admin']],
];
$myPushTypes = array_filter($allPushTypes, function($cfg) use ($user) {
    return $cfg['roles'] === null || in_array($user['role'], $cfg['roles'], true);
});

$roleColors = [
    'admin'   => ['grad' => 'from-indigo-600 via-violet-600 to-purple-700',  'light' => 'from-indigo-400 to-violet-400',  'badge' => 'bg-indigo-100 text-indigo-700',   'label' => 'Administrator'],
    'ma'      => ['grad' => 'from-sky-600 via-blue-600 to-indigo-700',       'light' => 'from-sky-400 to-blue-500',        'badge' => 'bg-sky-100 text-sky-700',         'label' => 'Medical Assistant'],
    'billing' => ['grad' => 'from-amber-500 via-orange-500 to-rose-600',     'light' => 'from-amber-400 to-orange-400',    'badge' => 'bg-amber-100 text-amber-700',     'label' => 'Billing'],
];
$rc = $roleColors[$user['role']] ?? $roleColors['ma'];

$initials  = strtoupper(mb_substr($user['full_name'], 0, 2));
$hasAvatar = !empty($user['avatar_url']);
$csrfTok   = csrfToken();

include __DIR__ . '/includes/header.php';
?>

<style>
@keyframes avatar-pulse { 0%,100%{box-shadow:0 0 0 0 rgba(99,102,241,.35)} 50%{box-shadow:0 0 0 12px rgba(99,102,241,0)} }
.avatar-ring-anim { animation: avatar-pulse 2.5s ease-in-out infinite; }
.profile-hero { background: linear-gradient(135deg,#1e1b4b 0%,#312e81 30%,#4f46e5 65%,#7c3aed 100%); }
.stat-card:hover .stat-num { transform: scale(1.08); }
.stat-num { transition: transform .2s; display: inline-block; }
.fade-in { animation: fadeInUp .4s ease both; }
@keyframes fadeInUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
/* Tab nav: icon-only on small screens */
#profileTabNav { scrollbar-width:none; }
#profileTabNav::-webkit-scrollbar { display:none; }
.tab-label { display:none; }
@media (min-width: 480px) { .tab-label { display:inline; } }
</style>

<?php if ($success): ?>
<div class="mb-4 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-2xl text-sm font-medium fade-in">
    <i class="bi bi-check-circle-fill flex-shrink-0 text-lg"></i> <?= h($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-4 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl text-sm font-medium fade-in">
    <i class="bi bi-exclamation-circle-fill flex-shrink-0 text-lg"></i> <?= h($error) ?>
</div>
<?php endif; ?>

<!-- HERO -->
<div class="profile-hero rounded-3xl overflow-hidden shadow-2xl mb-8 relative">
    <div class="absolute top-0 right-0 w-80 h-80 rounded-full opacity-10" style="background:radial-gradient(circle,#a78bfa,transparent 70%);transform:translate(30%,-30%)"></div>
    <div class="absolute bottom-0 left-0 w-64 h-64 rounded-full opacity-10" style="background:radial-gradient(circle,#60a5fa,transparent 70%);transform:translate(-30%,30%)"></div>

    <div class="relative px-8 pt-10 pb-0 flex flex-col items-center text-center">
        <!-- Avatar -->
        <div class="relative mb-4 group cursor-pointer" id="avatarUploadZone" title="Click to change photo">
            <div class="w-28 h-28 rounded-full overflow-hidden ring-4 ring-white shadow-2xl avatar-ring-anim flex items-center justify-center select-none" id="avatarCircle">
                <?php if ($hasAvatar): ?>
                <img src="<?= h($user['avatar_url']) ?>?v=<?= time() ?>" alt="Profile photo" id="avatarImg" class="w-full h-full object-cover">
                <?php else: ?>
                <div id="avatarInitials" class="w-full h-full bg-gradient-to-br <?= $rc['light'] ?> flex items-center justify-center text-white text-3xl font-black tracking-tight"><?= $initials ?></div>
                <?php endif; ?>
            </div>
            <div class="absolute inset-0 rounded-full bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-1 pointer-events-none">
                <i class="bi bi-camera-fill text-white text-xl"></i>
                <span class="text-white text-xs font-bold">Change</span>
            </div>
            <input type="file" id="avatarInput" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden">
        </div>
        <div id="avatarStatus" class="hidden mb-2 px-4 py-1.5 rounded-full text-xs font-bold backdrop-blur-sm"></div>
        <h1 class="text-2xl sm:text-3xl font-black text-white mb-1 tracking-tight drop-shadow"><?= h($user['full_name']) ?></h1>
        <p class="text-indigo-200 text-sm mb-3 font-mono">@<?= h($user['username']) ?></p>
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/15 backdrop-blur-sm border border-white/20 text-white text-sm font-semibold mb-6">
            <i class="bi bi-shield-fill-check text-white/80"></i> <?= $rc['label'] ?>
        </div>
        <!-- Stats strip -->
        <div class="w-full max-w-lg grid grid-cols-3 gap-px bg-white/10 rounded-t-2xl overflow-hidden">
            <div class="stat-card bg-white/10 backdrop-blur-sm py-4 px-2 text-center">
                <p class="text-2xl font-black text-white stat-num"><?= $formCount ?></p>
                <p class="text-white/60 text-xs font-semibold mt-0.5">Forms</p>
            </div>
            <div class="stat-card bg-white/10 backdrop-blur-sm py-4 px-2 text-center">
                <p class="text-2xl font-black text-white stat-num"><?= $visitsDone ?></p>
                <p class="text-white/60 text-xs font-semibold mt-0.5">Completed</p>
            </div>
            <div class="stat-card bg-white/10 backdrop-blur-sm py-4 px-2 text-center">
                <p class="text-2xl font-black text-white stat-num"><?= $visitsTotal ?></p>
                <p class="text-white/60 text-xs font-semibold mt-0.5">Total Visits</p>
            </div>
        </div>
    </div>
</div>

<!-- ── Profile Tab Navigation ───────────────────────────────────────────── -->
<div id="profileTabNav"
     style="display:flex;gap:4px;padding:5px;background:#fff;border-radius:16px;
            border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.06);
            margin-bottom:24px;overflow-x:auto;-webkit-overflow-scrolling:touch;">
    <button class="prof-tab" data-tab="account"
            style="flex:1;min-width:80px;display:flex;align-items:center;justify-content:center;
                   gap:6px;padding:10px 14px;border-radius:11px;font-size:13px;font-weight:600;
                   cursor:pointer;border:none;white-space:nowrap;transition:all 0.2s;">
        <i class="bi bi-person-fill"></i> <span class="tab-label">Account</span>
    </button>
    <button class="prof-tab" data-tab="signatures"
            style="flex:1;min-width:80px;display:flex;align-items:center;justify-content:center;
                   gap:6px;padding:10px 14px;border-radius:11px;font-size:13px;font-weight:600;
                   cursor:pointer;border:none;white-space:nowrap;transition:all 0.2s;">
        <i class="bi bi-pen-fill"></i> <span class="tab-label">Signatures</span>
    </button>
    <button class="prof-tab" data-tab="notifications"
            style="flex:1;min-width:80px;display:flex;align-items:center;justify-content:center;
                   gap:6px;padding:10px 14px;border-radius:11px;font-size:13px;font-weight:600;
                   cursor:pointer;border:none;white-space:nowrap;transition:all 0.2s;">
        <i class="bi bi-bell-fill"></i> <span class="tab-label">Notifications</span>
    </button>
    <button class="prof-tab" data-tab="preferences"
            style="flex:1;min-width:80px;display:flex;align-items:center;justify-content:center;
                   gap:6px;padding:10px 14px;border-radius:11px;font-size:13px;font-weight:600;
                   cursor:pointer;border:none;white-space:nowrap;transition:all 0.2s;">
        <i class="bi bi-sliders"></i> <span class="tab-label">Preferences</span>
    </button>
</div>

<!-- ── Tab: Account ──────────────────────────────────────────────────────── -->
<div id="tab-account" class="prof-panel">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left: Account info -->
        <div class="lg:col-span-1 space-y-5">

            <!-- Account Details -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-indigo-50 grid place-items-center shrink-0">
                        <i class="bi bi-person-vcard-fill text-indigo-500"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-700">Account Details</h4>
                </div>
                <div class="p-5 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-slate-50 rounded-xl grid place-items-center shrink-0 border border-slate-100"><i class="bi bi-person-fill text-slate-400"></i></div>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-400 font-medium">Full Name</p>
                            <p class="font-semibold text-slate-800 text-sm truncate"><?= h($user['full_name']) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-slate-50 rounded-xl grid place-items-center shrink-0 border border-slate-100"><i class="bi bi-at text-slate-400"></i></div>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-400 font-medium">Username</p>
                            <p class="font-semibold text-slate-800 text-sm font-mono truncate"><?= h($user['username']) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-slate-50 rounded-xl grid place-items-center shrink-0 border border-slate-100"><i class="bi bi-envelope text-slate-400"></i></div>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-400 font-medium">Email</p>
                            <p class="font-semibold text-sm truncate <?= $user['email'] ? 'text-slate-800' : 'text-slate-400' ?>"><?= $user['email'] ? h($user['email']) : 'Not set' ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-slate-50 rounded-xl grid place-items-center shrink-0 border border-slate-100"><i class="bi bi-calendar3 text-slate-400"></i></div>
                        <div>
                            <p class="text-xs text-slate-400 font-medium">Member Since</p>
                            <p class="font-semibold text-slate-800 text-sm"><?= date('M j, Y', strtotime($user['created_at'])) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 <?= $user['active'] ? 'bg-emerald-50' : 'bg-red-50' ?> rounded-xl grid place-items-center shrink-0 border <?= $user['active'] ? 'border-emerald-100' : 'border-red-100' ?>">
                            <i class="bi bi-circle-fill text-xs <?= $user['active'] ? 'text-emerald-500' : 'text-red-400' ?>"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 font-medium">Account Status</p>
                            <p class="font-semibold text-sm <?= $user['active'] ? 'text-emerald-700' : 'text-red-600' ?>"><?= $user['active'] ? 'Active' : 'Inactive' ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Remove avatar button -->
            <div id="removeAvatarWrap" class="<?= $hasAvatar ? '' : 'hidden' ?>">
                <button id="removeAvatarBtn"
                        class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-white border border-red-200 hover:bg-red-50
                               text-red-600 font-semibold rounded-2xl text-sm transition-all shadow-sm">
                    <i class="bi bi-person-x-fill"></i> Remove Profile Photo
                </button>
            </div>

        </div>

        <!-- Right: Edit forms -->
        <div class="lg:col-span-2 space-y-5">

            <!-- Update name -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-500 px-6 py-4 flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-xl shrink-0"><i class="bi bi-person-fill text-white text-lg"></i></div>
                    <div>
                        <h3 class="text-white font-bold">Update Display Name</h3>
                        <p class="text-blue-100 text-xs">Changes how your name appears across the app</p>
                    </div>
                </div>
                <div class="p-6">
                    <form method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $csrfTok ?>">
                        <input type="hidden" name="action" value="update_name">
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Full Name <span class="text-red-400">*</span></label>
                            <input type="text" name="full_name" value="<?= h($user['full_name']) ?>" maxlength="100"
                                   class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition focus:bg-white"
                                   placeholder="Your full name" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Username</label>
                            <input type="text" value="<?= h($user['username']) ?>" disabled
                                   class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-100 text-slate-400 font-mono cursor-not-allowed">
                            <p class="mt-1.5 text-xs text-slate-400">Username can only be changed by an administrator.</p>
                        </div>
                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm">
                            <i class="bi bi-check-lg"></i> Save Name
                        </button>
                    </form>
                </div>
            </div>

            <!-- Update email -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="bg-gradient-to-r from-emerald-600 to-teal-500 px-6 py-4 flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-xl shrink-0"><i class="bi bi-envelope-fill text-white text-lg"></i></div>
                    <div>
                        <h3 class="text-white font-bold">Email Address</h3>
                        <p class="text-emerald-100 text-xs">Used for system notifications</p>
                    </div>
                </div>
                <div class="p-6">
                    <form method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $csrfTok ?>">
                        <input type="hidden" name="action" value="update_email">
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email Address</label>
                            <input type="email" name="email" value="<?= h($user['email'] ?? '') ?>" maxlength="200"
                                   class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition focus:bg-white"
                                   placeholder="you@example.com">
                            <p class="mt-1.5 text-xs text-slate-400">Leave blank to remove your email address.</p>
                        </div>
                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm">
                            <i class="bi bi-check-lg"></i> Save Email
                        </button>
                    </form>
                </div>
            </div>

            <!-- Change password -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="bg-gradient-to-r from-violet-600 to-purple-600 px-6 py-4 flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-xl shrink-0"><i class="bi bi-key-fill text-white text-lg"></i></div>
                    <div>
                        <h3 class="text-white font-bold">Change Password</h3>
                        <p class="text-violet-200 text-xs">Minimum 8 characters</p>
                    </div>
                </div>
                <div class="p-6">
                    <form method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $csrfTok ?>">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Current Password <span class="text-red-400">*</span></label>
                            <input type="password" name="current_password" autocomplete="current-password"
                                   class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition focus:bg-white"
                                   placeholder="Your current password" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">New Password <span class="text-red-400">*</span></label>
                            <input type="password" name="new_password" id="newPw" autocomplete="new-password"
                                   class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition focus:bg-white"
                                   placeholder="At least 8 characters" required minlength="8">
                            <div class="mt-2 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div id="strengthBar" class="h-full rounded-full transition-all duration-300 w-0 bg-red-400"></div>
                            </div>
                            <p id="strengthLabel" class="text-xs text-slate-400 mt-1"></p>
                        </div>
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Confirm New Password <span class="text-red-400">*</span></label>
                            <input type="password" name="confirm_password" id="confirmPw" autocomplete="new-password"
                                   class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition focus:bg-white"
                                   placeholder="Repeat new password" required>
                            <p id="matchMsg" class="text-xs mt-1 hidden"></p>
                        </div>
                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-violet-600 hover:bg-violet-700 active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm">
                            <i class="bi bi-key-fill"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ── Tab: Signatures ───────────────────────────────────────────────────── -->
<div id="tab-signatures" class="prof-panel hidden">
    <div class="max-w-3xl mx-auto space-y-5">

        <!-- Saved Signature -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden" id="savedSigSection">
            <div class="bg-gradient-to-r from-emerald-600 to-teal-500 px-6 py-4 flex items-center gap-3">
                <div class="bg-white/20 p-2 rounded-xl shrink-0"><i class="bi bi-pen-fill text-white text-lg"></i></div>
                <div>
                    <h3 class="text-white font-bold">Pre-Saved Signature</h3>
                    <p class="text-emerald-100 text-xs">Draw once — auto-fills your signature on every form</p>
                </div>
            </div>

            <div class="p-6">
                <div id="savedSigMsg" class="hidden mb-4 text-sm font-semibold"></div>

                <?php if (!empty($user['saved_signature'])): ?>
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

                <div class="flex gap-1 p-1 bg-slate-100 rounded-xl mb-4 w-fit">
                    <button type="button" id="tabDraw" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all bg-white text-slate-800 shadow-sm">
                        <i class="bi bi-pen"></i> Draw
                    </button>
                    <button type="button" id="tabUpload" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all text-slate-500 hover:text-slate-700">
                        <i class="bi bi-upload"></i> Upload
                    </button>
                </div>

                <div id="panelDraw">
                    <div class="relative border-2 border-dashed border-slate-300 rounded-2xl bg-white overflow-hidden focus-within:border-emerald-400 transition-colors" style="touch-action:none;" id="savedSigWrapper">
                        <canvas id="savedSigCanvas" style="display:block;width:100%;height:140px;touch-action:none;cursor:crosshair;"></canvas>
                        <div id="savedSigPlaceholder" class="absolute inset-0 flex items-center justify-center text-slate-300 pointer-events-none select-none italic text-sm">Draw your signature here</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-4">
                        <button id="saveSigBtn" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm">
                            <i class="bi bi-floppy-fill"></i> Save Signature
                        </button>
                        <button id="clearSigPadBtn" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold rounded-xl text-sm transition-all">
                            <i class="bi bi-eraser"></i> Clear Pad
                        </button>
                    </div>
                </div>

                <div id="panelUpload" class="hidden">
                    <div id="uploadDropZone" class="border-2 border-dashed border-slate-300 rounded-2xl bg-slate-50 hover:bg-emerald-50 hover:border-emerald-400 transition-colors cursor-pointer flex flex-col items-center justify-center gap-3 py-8 px-4 text-center">
                        <div class="w-12 h-12 bg-slate-100 rounded-2xl grid place-items-center"><i class="bi bi-image text-slate-400 text-2xl"></i></div>
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Drop an image here, or <span class="text-emerald-600 underline">browse</span></p>
                            <p class="text-xs text-slate-400 mt-0.5">PNG, JPG or GIF — white/transparent background recommended</p>
                        </div>
                        <input type="file" id="sigUploadInput" accept="image/png,image/jpeg,image/gif" class="hidden">
                    </div>
                    <div id="uploadPreviewWrapper" class="hidden mt-4">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Preview</p>
                        <div class="border border-slate-200 rounded-xl bg-white p-3 inline-block">
                            <img id="uploadPreviewImg" src="" alt="Signature preview" class="max-h-20 max-w-xs object-contain">
                        </div>
                        <p id="uploadFileName" class="text-xs text-slate-400 mt-1"></p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-4">
                        <button id="saveUploadBtn" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <i class="bi bi-floppy-fill"></i> Save Uploaded Signature
                        </button>
                        <button id="clearUploadBtn" class="hidden inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold rounded-xl text-sm transition-all">
                            <i class="bi bi-x-circle"></i> Clear
                        </button>
                    </div>
                </div>

                <?php if (!empty($user['saved_signature'])): ?>
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <button id="deleteSavedSigBtn" class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 font-semibold rounded-xl text-sm transition-all">
                        <i class="bi bi-trash3"></i> Remove Saved Signature
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (in_array($user['role'], ['provider', 'admin'], true)): ?>
        <!-- Provider RX Signature -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden" id="providerSigSection">
            <div class="bg-gradient-to-r from-violet-600 to-purple-600 px-6 py-4 flex items-center gap-3">
                <div class="bg-white/20 p-2 rounded-xl shrink-0"><i class="bi bi-prescription2 text-white text-lg"></i></div>
                <div>
                    <h3 class="text-white font-bold">Provider RX Signature</h3>
                    <p class="text-violet-200 text-xs">Auto-signs printed prescriptions — draw once, done</p>
                </div>
            </div>
            <div class="p-6">
                <div id="provSigMsg" class="hidden mb-4 text-sm font-semibold"></div>

                <?php if (!empty($user['saved_provider_signature'])): ?>
                <div id="provSigPreview" class="mb-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Current Provider Signature</p>
                    <div class="border border-slate-200 rounded-xl bg-slate-50 p-3 inline-block">
                        <img src="<?= h($user['saved_provider_signature']) ?>" alt="Provider signature" class="max-h-16 max-w-xs object-contain">
                    </div>
                    <?php if (!empty($user['saved_provider_npi'])): ?>
                    <p class="text-xs text-slate-500 mt-1 font-mono">NPI: <?= h($user['saved_provider_npi']) ?></p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div id="provSigPreview" class="hidden"></div>
                <?php endif; ?>

                <p class="text-sm text-slate-600 mb-4">This signature appears automatically at the bottom of printed prescriptions when your name is selected as prescriber.</p>

                <!-- NPI -->
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">NPI Number <span class="text-slate-400 font-normal text-xs">(10 digits, optional)</span></label>
                    <input type="text" id="provNpiInput" maxlength="10" inputmode="numeric" pattern="\d{10}"
                           value="<?= h($user['saved_provider_npi'] ?? '') ?>"
                           placeholder="1234567890"
                           class="w-full sm:w-48 px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition focus:bg-white font-mono">
                </div>

                <!-- Draw / Upload tabs -->
                <div class="flex gap-1 p-1 bg-slate-100 rounded-xl mb-4 w-fit">
                    <button type="button" id="provTabDraw" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all bg-white text-slate-800 shadow-sm">
                        <i class="bi bi-pen"></i> Draw
                    </button>
                    <button type="button" id="provTabUpload" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all text-slate-500 hover:text-slate-700">
                        <i class="bi bi-upload"></i> Upload
                    </button>
                </div>

                <div id="provPanelDraw">
                    <div class="relative border-2 border-dashed border-slate-300 rounded-2xl bg-white overflow-hidden focus-within:border-violet-400 transition-colors" style="touch-action:none;" id="provSigWrapper">
                        <canvas id="provSigCanvas" style="display:block;width:100%;height:140px;touch-action:none;cursor:crosshair;"></canvas>
                        <div id="provSigPlaceholder" class="absolute inset-0 flex items-center justify-center text-slate-300 pointer-events-none select-none italic text-sm">Draw your provider signature here</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-4">
                        <button id="provSaveSigBtn" class="inline-flex items-center gap-2 px-5 py-2.5 bg-violet-600 hover:bg-violet-700 active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm">
                            <i class="bi bi-floppy-fill"></i> Save Provider Signature
                        </button>
                        <button id="provClearPadBtn" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold rounded-xl text-sm transition-all">
                            <i class="bi bi-eraser"></i> Clear Pad
                        </button>
                    </div>
                </div>

                <div id="provPanelUpload" class="hidden">
                    <div id="provUploadDropZone" class="border-2 border-dashed border-slate-300 rounded-2xl bg-slate-50 hover:bg-violet-50 hover:border-violet-400 transition-colors cursor-pointer flex flex-col items-center justify-center gap-3 py-8 px-4 text-center">
                        <div class="w-12 h-12 bg-slate-100 rounded-2xl grid place-items-center"><i class="bi bi-image text-slate-400 text-2xl"></i></div>
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Drop an image here, or <span class="text-violet-600 underline">browse</span></p>
                            <p class="text-xs text-slate-400 mt-0.5">PNG, JPG — transparent or white background recommended</p>
                        </div>
                        <input type="file" id="provUploadInput" accept="image/png,image/jpeg" class="hidden">
                    </div>
                    <div id="provUploadPreviewWrap" class="hidden mt-4">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Preview</p>
                        <div class="border border-slate-200 rounded-xl bg-white p-3 inline-block">
                            <img id="provUploadPreviewImg" src="" alt="Signature preview" class="max-h-20 max-w-xs object-contain">
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-4">
                        <button id="provSaveUploadBtn" class="inline-flex items-center gap-2 px-5 py-2.5 bg-violet-600 hover:bg-violet-700 active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <i class="bi bi-floppy-fill"></i> Save Uploaded Signature
                        </button>
                        <button id="provClearUploadBtn" class="hidden inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold rounded-xl text-sm transition-all">
                            <i class="bi bi-x-circle"></i> Clear
                        </button>
                    </div>
                </div>

                <?php if (!empty($user['saved_provider_signature'])): ?>
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <button id="deleteProvSigBtn" class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 font-semibold rounded-xl text-sm transition-all">
                        <i class="bi bi-trash3"></i> Remove Provider Signature
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- ── Tab: Notifications ───────────────────────────────────────────────── -->
<div id="tab-notifications" class="prof-panel hidden">
    <div class="max-w-3xl mx-auto">

        <!-- Push Notification Preferences -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <!-- Card header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-sky-500 flex items-center justify-center shrink-0">
                    <i class="bi bi-bell-fill text-white text-base"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800">Push Notifications</h3>
                    <p class="text-xs text-slate-500">Manage which alerts you receive on this device</p>
                </div>
            </div>
            <!-- Preferences list -->
            <div id="pushPrefsList">
                <?php if (empty($myPushTypes)): ?>
                <p class="text-sm text-slate-400 italic px-6 py-4">No notification types available for your role.</p>
                <?php else: ?>
                <div class="divide-y divide-slate-100">
                <?php foreach ($myPushTypes as $prefKey => $cfg):
                    $enabled = ($pushPrefs[$prefKey] ?? true) !== false;
                ?>
                <div class="flex items-center gap-4 px-6 py-4">
                    <div class="w-10 h-10 rounded-xl <?= h($cfg['ibg']) ?> flex items-center justify-center shrink-0">
                        <i class="bi <?= h($cfg['icon']) ?> text-white text-sm"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-700 leading-tight"><?= h($cfg['label']) ?></p>
                        <p class="text-xs text-slate-500 mt-0.5"><?= h($cfg['desc']) ?></p>
                    </div>
                    <button type="button"
                            data-pref-key="<?= h($prefKey) ?>"
                            onclick="togglePushPref(this,'<?= h($prefKey) ?>')"
                            class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 <?= $enabled ? 'bg-sky-500' : 'bg-slate-400' ?>"
                            role="switch"
                            aria-checked="<?= $enabled ? 'true' : 'false' ?>"
                            aria-label="Toggle <?= h($cfg['label']) ?>">
                        <span class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out <?= $enabled ? 'translate-x-5' : 'translate-x-0' ?>"></span>
                    </button>
                </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="px-6 py-3 border-t border-slate-100">
                    <p class="text-xs text-slate-400">
                        <i class="bi bi-info-circle mr-1"></i>
                        Push notifications require browser permission. If you haven't been prompted yet, <a href="#" class="text-sky-600 underline" onclick="requestPushPermission();return false;">enable them now</a>.
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ── Tab: Preferences ─────────────────────────────────────────────────── -->
<div id="tab-preferences" class="prof-panel hidden">
    <div class="max-w-3xl mx-auto space-y-5">

        <!-- Appearance -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-slate-50 grid place-items-center shrink-0">
                    <i class="bi bi-moon-stars-fill text-slate-400"></i>
                </div>
                <h4 class="text-sm font-bold text-slate-700">Appearance</h4>
            </div>
            <div class="p-5">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-700">Dark Mode</p>
                        <p class="text-xs text-slate-400 mt-0.5">Easier on the eyes at night</p>
                    </div>
                    <button id="darkModeToggle"
                            type="button"
                            onclick="toggleDarkMode(this)"
                            class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 <?= !empty($user['dark_mode']) ? 'bg-blue-600' : 'bg-slate-200' ?>"
                            role="switch"
                            aria-checked="<?= !empty($user['dark_mode']) ? 'true' : 'false' ?>"
                            aria-label="Toggle dark mode">
                        <span class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out <?= !empty($user['dark_mode']) ? 'translate-x-5' : 'translate-x-0' ?>"></span>
                    </button>
                </div>
            </div>
        </div>

        <?php if (isAdmin()): ?>
        <a href="<?= BASE_URL ?>/admin/users.php"
           class="flex items-center justify-between gap-3 px-5 py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl transition-colors shadow-sm group">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-white/20 rounded-xl grid place-items-center"><i class="bi bi-people-fill"></i></div>
                <div>
                    <p class="font-bold text-sm">Manage Staff</p>
                    <p class="text-indigo-200 text-xs">Add, edit, or deactivate accounts</p>
                </div>
            </div>
            <i class="bi bi-arrow-right group-hover:translate-x-1 transition-transform"></i>
        </a>
        <?php endif; ?>

    </div>
</div>

<script>
(function () {
    var tabs   = document.querySelectorAll('.prof-tab');
    var panels = document.querySelectorAll('.prof-panel');
    var LS_KEY = 'pd_profile_tab';

    function switchTab(tabId) {
        tabs.forEach(function (t) {
            var active = t.dataset.tab === tabId;
            t.style.background = active ? '#0f172a' : 'transparent';
            t.style.color      = active ? '#fff' : '#64748b';
            t.style.fontWeight = active ? '700' : '500';
        });
        panels.forEach(function (p) {
            if (p.id === 'tab-' + tabId) {
                p.classList.remove('hidden');
            } else {
                p.classList.add('hidden');
            }
        });
        localStorage.setItem(LS_KEY, tabId);
    }

    tabs.forEach(function (t) {
        t.addEventListener('click', function () { switchTab(t.dataset.tab); });
    });

    // Honour URL hash (e.g. profile.php#notifications)
    var hash  = (location.hash || '').replace('#', '');
    var saved = localStorage.getItem(LS_KEY) || 'account';
    switchTab(hash && document.getElementById('tab-' + hash) ? hash : saved);

    // Dark-mode border on tab nav
    var nav = document.getElementById('profileTabNav');
    if (nav && document.documentElement.classList.contains('dark')) {
        nav.style.background   = '#1e293b';
        nav.style.borderColor  = '#334155';
    }
}());
</script>

<script>
async function togglePushPref(btn, key) {
    const isOn  = btn.getAttribute('aria-checked') === 'true';
    const newVal = isOn ? 0 : 1;

    // Instant UI feedback
    btn.setAttribute('aria-checked', newVal ? 'true' : 'false');
    btn.classList.toggle('bg-sky-500',  !!newVal);
    btn.classList.toggle('bg-slate-400', !newVal);
    const knob = btn.querySelector('span');
    if (knob) {
        knob.classList.toggle('translate-x-5', !!newVal);
        knob.classList.toggle('translate-x-0', !newVal);
    }

    try {
        const fd = new FormData();
        fd.append('action',      'update_push_pref');
        fd.append('pref_key',    key);
        fd.append('pref_value',  newVal);
        fd.append('csrf_token',  '<?= $csrfTok ?>');
        const res  = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error('Server error');
        pdToast(newVal ? 'Notifications enabled' : 'Notifications disabled', 'success', 2000);
    } catch (e) {
        // Rollback UI
        btn.setAttribute('aria-checked', isOn ? 'true' : 'false');
        btn.classList.toggle('bg-sky-500',  isOn);
        btn.classList.toggle('bg-slate-400', !isOn);
        const knob2 = btn.querySelector('span');
        if (knob2) {
            knob2.classList.toggle('translate-x-5', isOn);
            knob2.classList.toggle('translate-x-0', !isOn);
        }
        pdToast('Failed to save preference', 'error');
    }
}

function requestPushPermission() {
    if (!('Notification' in window)) {
        pdToast('Push notifications are not supported in this browser', 'error');
        return;
    }
    Notification.requestPermission().then(function(perm) {
        if (perm !== 'granted') {
            pdToast('Permission denied — check your browser settings', 'error', 4000);
            return;
        }
        // Permission granted — now ensure we have an active push subscription
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            pdToast('Notifications enabled!', 'success');
            return;
        }
        function b64uToUint8(b64) {
            var pad = '='.repeat((4 - b64.length % 4) % 4);
            var raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
            var arr = new Uint8Array(raw.length);
            for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
            return arr;
        }
        navigator.serviceWorker.ready.then(function(reg) {
            reg.pushManager.getSubscription().then(function(existing) {
                if (existing) {
                    // Already subscribed — re-sync to server in case DB is stale
                    fetch('<?= BASE_URL ?>/api/push_subscribe.php', {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body:    JSON.stringify({ csrf: window._pdCsrf, action: 'subscribe',
                                                  endpoint: existing.toJSON().endpoint,
                                                  keys:     existing.toJSON().keys }),
                    }).catch(function(){});
                    pdToast('Notifications enabled!', 'success');
                    return;
                }
                // No subscription yet — fetch VAPID key and subscribe
                fetch('<?= BASE_URL ?>/api/push_subscribe.php?vapid')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.publicKey) { pdToast('Notifications enabled!', 'success'); return; }
                        reg.pushManager.subscribe({
                            userVisibleOnly:      true,
                            applicationServerKey: b64uToUint8(data.publicKey),
                        }).then(function(sub) {
                            fetch('<?= BASE_URL ?>/api/push_subscribe.php', {
                                method:  'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body:    JSON.stringify({ csrf: window._pdCsrf, action: 'subscribe',
                                                          endpoint: sub.toJSON().endpoint,
                                                          keys:     sub.toJSON().keys }),
                            }).catch(function(){});
                            pdToast('Notifications enabled!', 'success');
                        }).catch(function(e) {
                            console.warn('[Push] subscribe failed:', e);
                            pdToast('Notifications enabled!', 'success');
                        });
                    }).catch(function() { pdToast('Notifications enabled!', 'success'); });
            });
        });
    });
}
</script>
<script>
(function(){
    var newPw=document.getElementById('newPw'),confirmPw=document.getElementById('confirmPw'),
        bar=document.getElementById('strengthBar'),label=document.getElementById('strengthLabel'),
        matchMsg=document.getElementById('matchMsg');
    function calcStrength(pw){var s=0;if(pw.length>=8)s++;if(pw.length>=12)s++;if(/[A-Z]/.test(pw))s++;if(/[0-9]/.test(pw))s++;if(/[^A-Za-z0-9]/.test(pw))s++;return s;}
    newPw&&newPw.addEventListener('input',function(){
        var pw=newPw.value;if(!pw){bar.style.width='0';label.textContent='';return;}
        var s=calcStrength(pw),map=[
            {w:'20%',cls:'bg-red-400',txt:'Very weak',col:'text-red-500'},
            {w:'40%',cls:'bg-orange-400',txt:'Weak',col:'text-orange-500'},
            {w:'60%',cls:'bg-yellow-400',txt:'Fair',col:'text-yellow-600'},
            {w:'80%',cls:'bg-lime-400',txt:'Good',col:'text-lime-600'},
            {w:'100%',cls:'bg-emerald-500',txt:'Strong',col:'text-emerald-600'}],m=map[Math.min(s,4)];
        bar.className='h-full rounded-full transition-all duration-300 '+m.cls;bar.style.width=m.w;
        label.className='text-xs mt-1 '+m.col;label.textContent=m.txt;
    });
    function checkMatch(){var a=newPw?newPw.value:'',b=confirmPw?confirmPw.value:'';
        if(!b){matchMsg.classList.add('hidden');return;}matchMsg.classList.remove('hidden');
        if(a===b){matchMsg.className='text-xs mt-1 text-emerald-600 font-semibold';matchMsg.textContent='✓ Passwords match';}
        else{matchMsg.className='text-xs mt-1 text-red-500 font-semibold';matchMsg.textContent='✗ Passwords do not match';}}
    newPw&&newPw.addEventListener('input',checkMatch);confirmPw&&confirmPw.addEventListener('input',checkMatch);
})();
</script>

<script>
(function(){
    var zone=document.getElementById('avatarUploadZone'),input=document.getElementById('avatarInput'),
        circle=document.getElementById('avatarCircle'),statusEl=document.getElementById('avatarStatus'),
        removeWrap=document.getElementById('removeAvatarWrap'),removeBtn=document.getElementById('removeAvatarBtn'),
        csrf='<?= csrfToken() ?>';
    function setStatus(msg,ok){statusEl.textContent=msg;statusEl.className='mb-2 px-4 py-1.5 rounded-full text-xs font-bold backdrop-blur-sm '+(ok?'bg-emerald-500/80 text-white':'bg-red-500/80 text-white');statusEl.classList.remove('hidden');setTimeout(function(){statusEl.classList.add('hidden');},3500);}
    function setCircleSpinner(){circle.innerHTML='<div class="w-full h-full bg-slate-200 animate-pulse flex items-center justify-center"><i class="bi bi-arrow-repeat text-slate-400 text-2xl" style="animation:spin 1s linear infinite"></i></div>';}
    function setCirclePhoto(url){circle.innerHTML='<img src="'+url+'?v='+Date.now()+'" alt="Profile photo" class="w-full h-full object-cover">';removeWrap&&removeWrap.classList.remove('hidden');}
    zone&&zone.addEventListener('click',function(){input&&input.click();});
    input&&input.addEventListener('change',function(){
        var file=this.files&&this.files[0];if(!file)return;
        if(file.size>5*1024*1024){setStatus('Image must be under 5 MB',false);return;}
        var fd=new FormData();fd.append('csrf',csrf);fd.append('action','upload');fd.append('avatar',file);
        setCircleSpinner();
        fetch('<?= BASE_URL ?>/api/upload_avatar.php',{method:'POST',body:fd})
            .then(function(r){return r.json();})
            .then(function(j){if(j.ok){setCirclePhoto(j.url);setStatus('Photo updated!',true);}else{setStatus(j.error||'Upload failed',false);location.reload();}})
            .catch(function(){setStatus('Network error',false);location.reload();});
        this.value='';
    });
    removeBtn&&removeBtn.addEventListener('click',function(){
        if(!confirm('Remove your profile photo?'))return;
        var fd=new FormData();fd.append('csrf',csrf);fd.append('action','delete');
        fetch('<?= BASE_URL ?>/api/upload_avatar.php',{method:'POST',body:fd})
            .then(function(r){return r.json();})
            .then(function(j){if(j.ok){location.reload();}else{setStatus(j.error||'Delete failed',false);}})
            .catch(function(){setStatus('Network error',false);});
    });
})();
</script>

<script>
document.addEventListener('DOMContentLoaded',function(){
    var canvas=document.getElementById('savedSigCanvas'),wrapper=document.getElementById('savedSigWrapper'),
        placeholder=document.getElementById('savedSigPlaceholder'),saveBtn=document.getElementById('saveSigBtn'),
        clearBtn=document.getElementById('clearSigPadBtn'),deleteBtn=document.getElementById('deleteSavedSigBtn'),
        msgEl=document.getElementById('savedSigMsg'),previewEl=document.getElementById('savedSigPreview'),
        tabDraw=document.getElementById('tabDraw'),tabUpload=document.getElementById('tabUpload'),
        panelDraw=document.getElementById('panelDraw'),panelUpload=document.getElementById('panelUpload');

    function activateTab(tab){var isDraw=(tab==='draw');
        tabDraw.className='inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all '+(isDraw?'bg-white text-slate-800 shadow-sm':'text-slate-500 hover:text-slate-700');
        tabUpload.className='inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all '+(!isDraw?'bg-white text-slate-800 shadow-sm':'text-slate-500 hover:text-slate-700');
        panelDraw.classList.toggle('hidden',!isDraw);panelUpload.classList.toggle('hidden',isDraw);}
    tabDraw&&tabDraw.addEventListener('click',function(){activateTab('draw');});
    tabUpload&&tabUpload.addEventListener('click',function(){activateTab('upload');});

    var pad=null;
    if(canvas&&typeof SignaturePad!=='undefined'){
        function resizeCanvas(){var ratio=Math.max(window.devicePixelRatio||1,1),w=wrapper.getBoundingClientRect().width||wrapper.offsetWidth;if(!w)return;canvas.width=w*ratio;canvas.height=140*ratio;canvas.style.width=w+'px';canvas.style.height='140px';canvas.getContext('2d').scale(ratio,ratio);pad.clear();}
        pad=new SignaturePad(canvas,{penColor:'rgb(15,23,42)',minWidth:1.5,maxWidth:3});
        pad.addEventListener('beginStroke',function(){placeholder.style.display='none';});
        (function tryInit(n){var w=wrapper.getBoundingClientRect().width||wrapper.offsetWidth;if(!w&&n<30){requestAnimationFrame(function(){tryInit(n+1);});return;}resizeCanvas();})(0);
        window.addEventListener('resize',function(){resizeCanvas();});
    }
    clearBtn&&clearBtn.addEventListener('click',function(){if(pad)pad.clear();if(placeholder)placeholder.style.display='';});

    var dropZone=document.getElementById('uploadDropZone'),fileInput=document.getElementById('sigUploadInput'),
        previewWrapper=document.getElementById('uploadPreviewWrapper'),previewImg=document.getElementById('uploadPreviewImg'),
        fileNameEl=document.getElementById('uploadFileName'),saveUploadBtn=document.getElementById('saveUploadBtn'),
        clearUploadBtn=document.getElementById('clearUploadBtn'),_uploadDataURL=null;

    function handleFile(file){
        if(!file||!file.type.startsWith('image/')){showMsg('Please select a PNG, JPG, or GIF image.','err');return;}
        if(file.size>2*1024*1024){showMsg('Image must be under 2 MB.','err');return;}
        var reader=new FileReader();
        reader.onload=function(e){var img=new Image();img.onload=function(){var cvs=document.createElement('canvas'),maxW=600,maxH=200,scale=Math.min(1,maxW/img.naturalWidth,maxH/img.naturalHeight);cvs.width=Math.round(img.naturalWidth*scale);cvs.height=Math.round(img.naturalHeight*scale);var ctx=cvs.getContext('2d');ctx.fillStyle='#ffffff';ctx.fillRect(0,0,cvs.width,cvs.height);ctx.drawImage(img,0,0,cvs.width,cvs.height);_uploadDataURL=cvs.toDataURL('image/png');previewImg.src=_uploadDataURL;fileNameEl.textContent=file.name+' ('+Math.round(file.size/1024)+' KB)';previewWrapper.classList.remove('hidden');saveUploadBtn.disabled=false;clearUploadBtn.classList.remove('hidden');};img.src=e.target.result;};
        reader.readAsDataURL(file);
    }
    dropZone&&dropZone.addEventListener('click',function(){fileInput&&fileInput.click();});
    fileInput&&fileInput.addEventListener('change',function(){if(this.files[0])handleFile(this.files[0]);});
    dropZone&&dropZone.addEventListener('dragover',function(e){e.preventDefault();dropZone.classList.add('border-emerald-400','bg-emerald-50');});
    dropZone&&dropZone.addEventListener('dragleave',function(){dropZone.classList.remove('border-emerald-400','bg-emerald-50');});
    dropZone&&dropZone.addEventListener('drop',function(e){e.preventDefault();dropZone.classList.remove('border-emerald-400','bg-emerald-50');var f=e.dataTransfer.files&&e.dataTransfer.files[0];if(f)handleFile(f);});
    clearUploadBtn&&clearUploadBtn.addEventListener('click',function(){_uploadDataURL=null;previewWrapper.classList.add('hidden');saveUploadBtn.disabled=true;clearUploadBtn.classList.add('hidden');if(fileInput)fileInput.value='';});

    function showMsg(text,type){msgEl.textContent=text;msgEl.className='mb-4 text-sm font-semibold '+(type==='ok'?'text-emerald-600':'text-red-500');msgEl.classList.remove('hidden');setTimeout(function(){msgEl.classList.add('hidden');},5000);}

    function updatePreview(dataURL){var img=previewEl.querySelector('img');if(img){img.src=dataURL;}else{previewEl.innerHTML='<p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Current Saved Signature</p><div class="border border-slate-200 rounded-xl bg-slate-50 p-3 inline-block"><img src="'+dataURL+'" alt="Saved signature" class="max-h-16 max-w-xs object-contain"></div>';previewEl.classList.remove('hidden');}if(!deleteBtn)location.reload();}

    function postSig(dataURL,btn,originalLabel){btn.disabled=true;btn.innerHTML='<i class="bi bi-hourglass-split"></i> Saving\u2026';
        fetch('<?= BASE_URL ?>/api/save_signature.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:'<?= csrfToken() ?>',action:'save',signature:dataURL})})
        .then(function(r){return r.json();}).then(function(j){btn.disabled=false;btn.innerHTML=originalLabel;if(j.ok){showMsg('\u2713 Signature saved \u2014 forms will auto-fill from now on.','ok');updatePreview(dataURL);}else{showMsg('Error: '+(j.error||'Unknown error'),'err');}})
        .catch(function(){btn.disabled=false;btn.innerHTML=originalLabel;showMsg('Network error \u2014 please try again.','err');});}

    saveBtn&&saveBtn.addEventListener('click',function(){if(!pad||pad.isEmpty()){showMsg('Please draw your signature first.','err');return;}postSig(pad.toDataURL('image/png'),saveBtn,'<i class="bi bi-floppy-fill"></i> Save Signature');});
    saveUploadBtn&&saveUploadBtn.addEventListener('click',function(){if(!_uploadDataURL){showMsg('Please choose an image first.','err');return;}postSig(_uploadDataURL,saveUploadBtn,'<i class="bi bi-floppy-fill"></i> Save Uploaded Signature');});

    deleteBtn&&deleteBtn.addEventListener('click',function(){
        if(!confirm('Remove your saved signature? Forms will require manual signing again.'))return;
        deleteBtn.disabled=true;
        fetch('<?= BASE_URL ?>/api/save_signature.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:'<?= csrfToken() ?>',action:'clear'})})
        .then(function(r){return r.json();}).then(function(j){if(j.ok){location.reload();}else{deleteBtn.disabled=false;showMsg('Error: '+j.error,'err');}});
    });
});
</script>

<?php if (in_array($user['role'], ['provider', 'admin'], true)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var pCanvas   = document.getElementById('provSigCanvas'),
        pWrapper  = document.getElementById('provSigWrapper'),
        pPHolder  = document.getElementById('provSigPlaceholder'),
        pSaveBtn  = document.getElementById('provSaveSigBtn'),
        pClearBtn = document.getElementById('provClearPadBtn'),
        pDelBtn   = document.getElementById('deleteProvSigBtn'),
        pMsgEl    = document.getElementById('provSigMsg'),
        pPreview  = document.getElementById('provSigPreview'),
        pNpiInput = document.getElementById('provNpiInput'),
        pTabDraw  = document.getElementById('provTabDraw'),
        pTabUp    = document.getElementById('provTabUpload'),
        pPanDraw  = document.getElementById('provPanelDraw'),
        pPanUp    = document.getElementById('provPanelUpload');

    function pActivateTab(tab) {
        var isDraw = (tab === 'draw');
        pTabDraw.className = 'inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all ' + (isDraw ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700');
        pTabUp.className   = 'inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all ' + (!isDraw ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700');
        pPanDraw.classList.toggle('hidden', !isDraw);
        pPanUp.classList.toggle('hidden', isDraw);
    }
    pTabDraw && pTabDraw.addEventListener('click', function () { pActivateTab('draw'); });
    pTabUp   && pTabUp.addEventListener('click',   function () { pActivateTab('upload'); });

    var pPad = null;
    if (pCanvas && typeof SignaturePad !== 'undefined') {
        function pResize() {
            var ratio = Math.max(window.devicePixelRatio || 1, 1),
                w = pWrapper.getBoundingClientRect().width || pWrapper.offsetWidth;
            if (!w) return;
            pCanvas.width = w * ratio; pCanvas.height = 140 * ratio;
            pCanvas.style.width = w + 'px'; pCanvas.style.height = '140px';
            pCanvas.getContext('2d').scale(ratio, ratio);
            pPad.clear();
        }
        pPad = new SignaturePad(pCanvas, { penColor: 'rgb(15,23,42)', minWidth: 1.5, maxWidth: 3 });
        pPad.addEventListener('beginStroke', function () { if (pPHolder) pPHolder.style.display = 'none'; });
        (function tryInit(n) { var w = pWrapper.getBoundingClientRect().width || pWrapper.offsetWidth; if (!w && n < 30) { requestAnimationFrame(function () { tryInit(n + 1); }); return; } pResize(); })(0);
        window.addEventListener('resize', pResize);
    }
    pClearBtn && pClearBtn.addEventListener('click', function () { if (pPad) pPad.clear(); if (pPHolder) pPHolder.style.display = ''; });

    // Upload panel
    var pDropZone = document.getElementById('provUploadDropZone'),
        pFileIn   = document.getElementById('provUploadInput'),
        pPrevWrap = document.getElementById('provUploadPreviewWrap'),
        pPrevImg  = document.getElementById('provUploadPreviewImg'),
        pSaveUpBtn= document.getElementById('provSaveUploadBtn'),
        pClrUpBtn = document.getElementById('provClearUploadBtn'),
        _pUpData  = null;

    function pHandleFile(file) {
        if (!file || !file.type.startsWith('image/')) { pShowMsg('Please select a PNG or JPG image.', 'err'); return; }
        if (file.size > 2 * 1024 * 1024) { pShowMsg('Image must be under 2 MB.', 'err'); return; }
        var reader = new FileReader();
        reader.onload = function (e) {
            var img = new Image();
            img.onload = function () {
                var cvs = document.createElement('canvas'), maxW = 600, maxH = 200,
                    scale = Math.min(1, maxW / img.naturalWidth, maxH / img.naturalHeight);
                cvs.width = Math.round(img.naturalWidth * scale);
                cvs.height = Math.round(img.naturalHeight * scale);
                var ctx = cvs.getContext('2d');
                ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, cvs.width, cvs.height);
                ctx.drawImage(img, 0, 0, cvs.width, cvs.height);
                _pUpData = cvs.toDataURL('image/png');
                pPrevImg.src = _pUpData;
                pPrevWrap.classList.remove('hidden');
                pSaveUpBtn.disabled = false;
                pClrUpBtn.classList.remove('hidden');
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
    pDropZone && pDropZone.addEventListener('click', function () { pFileIn && pFileIn.click(); });
    pFileIn   && pFileIn.addEventListener('change', function () { if (this.files[0]) pHandleFile(this.files[0]); });
    pDropZone && pDropZone.addEventListener('dragover',  function (e) { e.preventDefault(); pDropZone.classList.add('border-violet-400', 'bg-violet-50'); });
    pDropZone && pDropZone.addEventListener('dragleave', function ()  { pDropZone.classList.remove('border-violet-400', 'bg-violet-50'); });
    pDropZone && pDropZone.addEventListener('drop', function (e) {
        e.preventDefault(); pDropZone.classList.remove('border-violet-400', 'bg-violet-50');
        var f = e.dataTransfer.files && e.dataTransfer.files[0]; if (f) pHandleFile(f);
    });
    pClrUpBtn && pClrUpBtn.addEventListener('click', function () {
        _pUpData = null; pPrevWrap.classList.add('hidden');
        pSaveUpBtn.disabled = true; pClrUpBtn.classList.add('hidden');
        if (pFileIn) pFileIn.value = '';
    });

    function pShowMsg(text, type) {
        pMsgEl.textContent = text;
        pMsgEl.className = 'mb-4 text-sm font-semibold ' + (type === 'ok' ? 'text-emerald-600' : 'text-red-500');
        pMsgEl.classList.remove('hidden');
        setTimeout(function () { pMsgEl.classList.add('hidden'); }, 5000);
    }

    function pUpdatePreview(dataURL, npi) {
        var img = pPreview.querySelector('img');
        if (img) {
            img.src = dataURL;
        } else {
            var npiHtml = npi ? '<p class="text-xs text-slate-500 mt-1 font-mono">NPI: ' + npi + '</p>' : '';
            pPreview.innerHTML = '<p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Current Provider Signature</p><div class="border border-slate-200 rounded-xl bg-slate-50 p-3 inline-block"><img src="' + dataURL + '" alt="Provider signature" class="max-h-16 max-w-xs object-contain"></div>' + npiHtml;
            pPreview.classList.remove('hidden');
        }
        if (!pDelBtn) location.reload();
    }

    function pPostSig(dataURL, btn, originalLabel) {
        var npi = (pNpiInput ? pNpiInput.value.trim() : '');
        if (npi && !/^\d{10}$/.test(npi)) { pShowMsg('NPI must be exactly 10 digits.', 'err'); return; }
        btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving\u2026';
        fetch('<?= BASE_URL ?>/api/save_provider_sig.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf: '<?= csrfToken() ?>', action: 'save', signature: dataURL, npi: npi })
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            btn.disabled = false; btn.innerHTML = originalLabel;
            if (j.ok) {
                pShowMsg('\u2713 Provider signature saved \u2014 will auto-sign RX prints.', 'ok');
                pUpdatePreview(dataURL, npi);
            } else {
                pShowMsg('Error: ' + (j.error || 'Unknown error'), 'err');
            }
        })
        .catch(function () { btn.disabled = false; btn.innerHTML = originalLabel; pShowMsg('Network error \u2014 please try again.', 'err'); });
    }

    pSaveBtn   && pSaveBtn.addEventListener('click', function () {
        if (!pPad || pPad.isEmpty()) { pShowMsg('Please draw your signature first.', 'err'); return; }
        pPostSig(pPad.toDataURL('image/png'), pSaveBtn, '<i class="bi bi-floppy-fill"></i> Save Provider Signature');
    });
    pSaveUpBtn && pSaveUpBtn.addEventListener('click', function () {
        if (!_pUpData) { pShowMsg('Please choose an image first.', 'err'); return; }
        pPostSig(_pUpData, pSaveUpBtn, '<i class="bi bi-floppy-fill"></i> Save Uploaded Signature');
    });

    pDelBtn && pDelBtn.addEventListener('click', function () {
        if (!confirm('Remove your provider signature? RX prints will show a blank signature line.')) return;
        pDelBtn.disabled = true;
        fetch('<?= BASE_URL ?>/api/save_provider_sig.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf: '<?= csrfToken() ?>', action: 'clear' })
        })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (j.ok) { location.reload(); } else { pDelBtn.disabled = false; pShowMsg('Error: ' + j.error, 'err'); } });
    });
});
</script>
<?php endif; ?>

<script>
async function toggleDarkMode(btn) {
    const isOn  = btn.getAttribute('aria-checked') === 'true';
    const newVal = isOn ? 0 : 1;

    // Instant UI feedback
    const html = document.documentElement;
    html.classList.toggle('dark', !!newVal);
    btn.setAttribute('aria-checked', newVal ? 'true' : 'false');
    btn.classList.toggle('bg-blue-600', !!newVal);
    btn.classList.toggle('bg-slate-200', !newVal);
    const knob = btn.querySelector('span');
    if (knob) {
        knob.classList.toggle('translate-x-5', !!newVal);
        knob.classList.toggle('translate-x-0', !newVal);
    }

    // Persist to server
    try {
        const fd = new FormData();
        fd.append('action', 'toggle_dark_mode');
        fd.append('dark_mode', newVal);
        fd.append('csrf_token', '<?= $csrfTok ?>');
        const res  = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error('Server error');
        pdToast(newVal ? 'Dark mode enabled' : 'Dark mode disabled', 'success', 2500);
    } catch (e) {
        // Rollback UI
        html.classList.toggle('dark', isOn);
        btn.setAttribute('aria-checked', isOn ? 'true' : 'false');
        btn.classList.toggle('bg-blue-600', isOn);
        btn.classList.toggle('bg-slate-200', !isOn);
        const knob2 = btn.querySelector('span');
        if (knob2) {
            knob2.classList.toggle('translate-x-5', isOn);
            knob2.classList.toggle('translate-x-0', !isOn);
        }
        pdToast('Failed to save preference', 'error');
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
