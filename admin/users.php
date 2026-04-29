<?php
require_once __DIR__ . '/../includes/auth.php';
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
}

include __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-800">Manage Staff</h2>
        <p class="text-slate-500 text-sm mt-0.5"><?= count($staff) ?> staff member<?= count($staff) !== 1 ? 's' : '' ?></p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/admin/roles.php"
           class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold
                  px-4 py-2.5 rounded-xl transition-all text-sm">
            <i class="bi bi-person-badge-fill"></i> Roles &amp; Permissions
        </a>
        <a href="<?= BASE_URL ?>/admin/manage_user.php"
           class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold
                  px-5 py-2.5 rounded-xl transition-all shadow-sm hover:shadow-md active:scale-95 text-sm">
            <i class="bi bi-person-plus-fill"></i> Add Staff Member
        </a>
    </div>
</div>

<?php if ($msg === 'updated'): ?>
<div class="mb-5 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm">
    <i class="bi bi-check-circle-fill flex-shrink-0"></i> Staff updated successfully.
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <?php if (empty($staff)): ?>
    <div class="flex flex-col items-center justify-center py-20 text-slate-400">
        <i class="bi bi-people text-5xl mb-3 opacity-30"></i>
        <p class="text-sm">No staff members yet.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 text-left border-b border-slate-100">
                    <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Staff Member</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Username</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Role</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                    <th class="px-4 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($staff as $u):
                    $isSelf    = $u['id'] == $_SESSION['user_id'];
                    $isActive  = (bool)$u['active'];
                ?>
                <tr class="hover:bg-slate-50/70 transition-colors <?= !$isActive ? 'opacity-60' : '' ?>">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br
                                        <?= $u['role'] === 'admin' ? 'from-indigo-500 to-indigo-700' : ($u['role'] === 'billing' ? 'from-amber-400 to-amber-600' : 'from-slate-400 to-slate-600') ?>
                                        grid place-items-center text-white text-xs font-bold flex-shrink-0">
                                <?= strtoupper(substr($u['full_name'],0,2)) ?>
                            </div>
                            <div>
                                <div class="font-semibold text-slate-800">
                                    <?= h($u['full_name']) ?>
                                    <?php if ($isSelf): ?>
                                    <span class="ml-2 text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold">You</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-slate-500 font-mono text-xs"><?= h($u['username']) ?></td>
                    <td class="px-4 py-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                                     <?php
                                     $roleCls = ['admin'=>'bg-indigo-100 text-indigo-700','billing'=>'bg-amber-100 text-amber-700','scheduler'=>'bg-violet-100 text-violet-700'];
                                     echo $roleCls[$u['role']] ?? 'bg-slate-100 text-slate-600';
                                     ?>">
                            <?= ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-4">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold
                                     <?= $isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600' ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $isActive ? 'bg-emerald-500' : 'bg-red-400' ?>"></span>
                            <?= $isActive ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex items-center gap-2">
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
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
