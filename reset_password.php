<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Already logged in → dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$token = trim($_GET['token'] ?? '');
$error = '';
$done  = false;

// Validate token immediately (before form render)
$user = null;
if ($token !== '') {
    $stmt = $pdo->prepare("
        SELECT id, full_name, username, password_reset_token, password_reset_expires
        FROM staff
        WHERE password_reset_token = ? AND active = 1
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if ($row && !empty($row['password_reset_expires'])
        && strtotime($row['password_reset_expires']) > time()
        && hash_equals($row['password_reset_token'], $token)) {
        $user = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postToken = trim($_POST['token'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Re-validate token from POST
    if ($postToken !== '') {
        $stmt = $pdo->prepare("
            SELECT id, full_name, username, password_reset_token, password_reset_expires
            FROM staff
            WHERE password_reset_token = ? AND active = 1
            LIMIT 1
        ");
        $stmt->execute([$postToken]);
        $row = $stmt->fetch();
        if ($row && !empty($row['password_reset_expires'])
            && strtotime($row['password_reset_expires']) > time()
            && hash_equals($row['password_reset_token'], $postToken)) {
            $user = $row;
        }
    }

    if (!$user) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // Update password and clear reset token
        $pdo->prepare("UPDATE staff SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL,
                        failed_logins = 0, locked_until = NULL WHERE id = ?")
            ->execute([$hash, (int)$user['id']]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','system-ui','sans-serif'] } } } }</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="font-sans min-h-screen flex items-center justify-center p-4 relative overflow-hidden"
      style="background: linear-gradient(135deg, #172554 0%, #1e3a8a 40%, #1d4ed8 100%)">

    <div class="absolute -top-32 -right-32 w-96 h-96 bg-blue-400/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-cyan-400/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-[400px] relative z-10">

        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex w-20 h-20 bg-white/15 backdrop-blur-sm rounded-3xl items-center justify-center mb-5 shadow-2xl ring-1 ring-white/20">
                <i class="bi bi-shield-lock-fill text-white" style="font-size:2.2rem"></i>
            </div>
            <h1 class="text-white font-extrabold text-3xl tracking-tight"><?= APP_NAME ?></h1>
            <p class="text-blue-200 mt-1 text-sm">Reset Your Password</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-3xl shadow-[0_32px_64px_-12px_rgba(0,0,0,.4)] p-8">

            <?php if ($done): ?>
            <!-- Success state -->
            <div class="flex flex-col items-center text-center py-2">
                <div class="w-16 h-16 bg-emerald-100 rounded-2xl grid place-items-center mb-4">
                    <i class="bi bi-check-circle-fill text-emerald-600 text-3xl"></i>
                </div>
                <h2 class="font-bold text-slate-800 text-lg mb-2">Password updated!</h2>
                <p class="text-slate-500 text-sm leading-relaxed mb-6">
                    Your password has been changed successfully. You can now sign in with your new password.
                </p>
                <a href="<?= BASE_URL ?>/index.php"
                   class="w-full text-center bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800
                          text-white font-bold py-3.5 rounded-2xl transition-all shadow-lg shadow-blue-500/30">
                    Sign In Now
                </a>
            </div>

            <?php elseif (!$user && !$_POST): ?>
            <!-- Invalid / expired token -->
            <div class="flex flex-col items-center text-center py-2">
                <div class="w-16 h-16 bg-red-100 rounded-2xl grid place-items-center mb-4">
                    <i class="bi bi-x-circle-fill text-red-500 text-3xl"></i>
                </div>
                <h2 class="font-bold text-slate-800 text-lg mb-2">Link expired or invalid</h2>
                <p class="text-slate-500 text-sm leading-relaxed mb-6">
                    This password reset link is no longer valid. Links expire after 1 hour.
                    Please request a new one.
                </p>
                <a href="<?= BASE_URL ?>/forgot_password.php"
                   class="w-full text-center bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800
                          text-white font-bold py-3.5 rounded-2xl transition-all shadow-lg shadow-blue-500/30">
                    Request New Link
                </a>
            </div>

            <?php else: ?>
            <!-- Reset form -->
            <h2 class="font-bold text-slate-800 text-xl mb-1">Choose a new password</h2>
            <p class="text-slate-500 text-sm mb-6">
                Hi <strong><?= h($user['full_name']) ?></strong> — enter your new password below.
            </p>

            <?php if ($error): ?>
            <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3.5 rounded-2xl text-sm mb-5">
                <i class="bi bi-exclamation-circle text-lg shrink-0 mt-0.5"></i>
                <span><?= h($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="token" value="<?= h($token ?: ($_POST['token'] ?? '')) ?>">

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">New Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 pointer-events-none">
                            <i class="bi bi-lock text-lg"></i>
                        </span>
                        <input type="password" name="password" id="pwd1"
                               class="w-full pl-11 pr-12 py-3.5 border border-slate-200 rounded-2xl text-sm bg-slate-50
                                      focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                      transition placeholder-slate-300"
                               placeholder="Min. 6 characters"
                               autocomplete="new-password" required autofocus>
                        <button type="button" onclick="togglePwd('pwd1','eye1')"
                                class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-slate-600">
                            <i class="bi bi-eye text-lg" id="eye1"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-7">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Confirm New Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 pointer-events-none">
                            <i class="bi bi-lock-fill text-lg"></i>
                        </span>
                        <input type="password" name="password2" id="pwd2"
                               class="w-full pl-11 pr-12 py-3.5 border border-slate-200 rounded-2xl text-sm bg-slate-50
                                      focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                      transition placeholder-slate-300"
                               placeholder="Repeat new password"
                               autocomplete="new-password" required>
                        <button type="button" onclick="togglePwd('pwd2','eye2')"
                                class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-slate-600">
                            <i class="bi bi-eye text-lg" id="eye2"></i>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800
                               text-white font-bold py-4 rounded-2xl transition-all duration-200
                               shadow-lg shadow-blue-500/30 hover:shadow-xl hover:shadow-blue-600/40
                               active:scale-[0.98] flex items-center justify-center gap-2 text-base">
                    <i class="bi bi-check-circle-fill"></i>
                    Set New Password
                </button>
            </form>

            <?php endif; ?>

            <div class="mt-6 pt-5 border-t border-slate-100 text-center">
                <a href="<?= BASE_URL ?>/index.php"
                   class="text-sm text-blue-600 hover:text-blue-800 font-semibold flex items-center justify-center gap-1.5">
                    <i class="bi bi-arrow-left"></i> Back to Sign In
                </a>
            </div>
        </div>

        <p class="text-center text-blue-300/80 text-xs mt-6 flex items-center justify-center gap-2">
            <i class="bi bi-shield-lock-fill"></i>
            Secure access &mdash; Do not share your credentials
        </p>
    </div>

    <script>
    function togglePwd(fieldId, eyeId) {
        var f = document.getElementById(fieldId);
        var e = document.getElementById(eyeId);
        var show = f.type === 'password';
        f.type = show ? 'text' : 'password';
        e.className = show ? 'bi bi-eye-slash text-lg' : 'bi bi-eye text-lg';
    }
    </script>
</body>
</html>
