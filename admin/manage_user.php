<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();
requireAdmin();

$id      = (int)($_GET['id'] ?? 0);
$isEdit  = $id > 0;
$user    = null;
$error   = '';
$vals    = ['username' => '', 'full_name' => '', 'role' => 'ma'];
$pageTitle = $isEdit ? 'Edit Staff Member' : 'Add Staff Member';
$activeNav = '';

if ($isEdit) {
    $s = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
    $s->execute([$id]);
    $user = $s->fetch();
    if (!$user) { header('Location: ' . BASE_URL . '/admin/users.php'); exit; }
    $vals = ['username' => $user['username'], 'full_name' => $user['full_name'], 'role' => $user['role']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $vals = [
        'username'  => trim($_POST['username']  ?? ''),
        'full_name' => trim($_POST['full_name'] ?? ''),
        'role'      => in_array($_POST['role'] ?? '', ['admin','ma','billing','scheduler']) ? $_POST['role'] : 'ma',
    ];
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!$vals['username'] || !$vals['full_name']) {
        $error = 'Username and full name are required.';
    } elseif (!$isEdit && !$password) {
        $error = 'Password is required for new staff.';
    } elseif ($password && $password !== $password2) {
        $error = 'Passwords do not match.';
    } elseif ($password && strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        if ($isEdit) {
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE staff SET username=?, full_name=?, role=?, password_hash=? WHERE id=?")
                    ->execute([$vals['username'], $vals['full_name'], $vals['role'], $hash, $id]);
            } else {
                $pdo->prepare("UPDATE staff SET username=?, full_name=?, role=? WHERE id=?")
                    ->execute([$vals['username'], $vals['full_name'], $vals['role'], $id]);
            }
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO staff (username, full_name, role, password_hash, active) VALUES (?,?,?,?,1)")
                ->execute([$vals['username'], $vals['full_name'], $vals['role'], $hash]);
        }
        header('Location: ' . BASE_URL . '/admin/users.php?msg=updated');
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6">
    <a href="<?= BASE_URL ?>/admin/users.php" class="hover:text-blue-600 font-medium">Manage Staff</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold"><?= $isEdit ? 'Edit ' . h($user['full_name']) : 'Add Staff Member' ?></span>
</nav>

<div class="max-w-lg">
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="bg-gradient-to-r from-indigo-600 to-indigo-500 px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-xl">
            <i class="bi bi-<?= $isEdit ? 'pencil-fill' : 'person-plus-fill' ?> text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-white font-bold text-lg"><?= $isEdit ? 'Edit Staff Member' : 'Add Staff Member' ?></h2>
            <?php if ($isEdit): ?>
            <p class="text-indigo-200 text-sm"><?= h($user['full_name']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="p-6">
        <?php if ($error): ?>
        <div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
            <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i> <?= h($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Full Name <span class="text-red-400">*</span>
                </label>
                <input type="text" name="full_name" value="<?= h($vals['full_name']) ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition focus:bg-white"
                       placeholder="Dr. Jane Smith" required autofocus>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Username <span class="text-red-400">*</span>
                </label>
                <input type="text" name="username" value="<?= h($vals['username']) ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition focus:bg-white font-mono"
                       placeholder="jsmith" autocomplete="off" required>
            </div>

                <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Role</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 p-3.5 border border-slate-200 rounded-xl cursor-pointer transition-colors has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                        <input type="radio" name="role" value="ma" <?= $vals['role'] === 'ma' ? 'checked' : '' ?>
                               class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-400">
                        <div>
                            <div class="text-sm font-semibold text-slate-700">Medical Assistant</div>
                            <div class="text-xs text-slate-500">Clinical access</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3.5 border border-slate-200 rounded-xl cursor-pointer transition-colors has-[:checked]:border-violet-400 has-[:checked]:bg-violet-50">
                        <input type="radio" name="role" value="scheduler" <?= $vals['role'] === 'scheduler' ? 'checked' : '' ?>
                               class="w-4 h-4 text-violet-600 border-slate-300 focus:ring-violet-400">
                        <div>
                            <div class="text-sm font-semibold text-slate-700">Scheduler</div>
                            <div class="text-xs text-slate-500">Schedule management only</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3.5 border border-slate-200 rounded-xl cursor-pointer transition-colors has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50">
                        <input type="radio" name="role" value="billing" <?= $vals['role'] === 'billing' ? 'checked' : '' ?>
                               class="w-4 h-4 text-amber-600 border-slate-300 focus:ring-amber-400">
                        <div>
                            <div class="text-sm font-semibold text-slate-700">Billing</div>
                            <div class="text-xs text-slate-500">Forms &amp; ICD-10 only</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3.5 border border-slate-200 rounded-xl cursor-pointer transition-colors has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                        <input type="radio" name="role" value="admin" <?= $vals['role'] === 'admin' ? 'checked' : '' ?>
                               class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-400">
                        <div>
                            <div class="text-sm font-semibold text-slate-700">Admin</div>
                            <div class="text-xs text-slate-500">Full access</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Password -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Password
                    <?php if ($isEdit): ?>
                    <span class="ml-1 text-xs text-slate-400 font-normal">(leave blank to keep current)</span>
                    <?php else: ?>
                    <span class="text-red-400">*</span>
                    <?php endif; ?>
                </label>
                <input type="password" name="password"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition focus:bg-white"
                       placeholder="Min. 6 characters" autocomplete="new-password"
                       <?= !$isEdit ? 'required' : '' ?>>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Confirm Password</label>
                <input type="password" name="password2"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition focus:bg-white"
                       placeholder="Repeat password" autocomplete="new-password">
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <button type="submit"
                        class="flex-1 sm:flex-none flex items-center justify-center gap-2
                               bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-bold
                               px-8 py-3 rounded-xl transition-all shadow-sm hover:shadow-md">
                    <i class="bi bi-check-circle-fill"></i> <?= $isEdit ? 'Save Changes' : 'Create Account' ?>
                </button>
                <a href="<?= BASE_URL ?>/admin/users.php"
                   class="flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold
                          text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
