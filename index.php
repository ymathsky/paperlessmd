<?php
require_once __DIR__ . '/includes/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error   = '';
$locked  = false;
$timeout = ($_GET['msg'] ?? '') === 'timeout';

// ── Lockout constants ────────────────────────────────────────────────────────
const LOCKOUT_MAX_ATTEMPTS = 5;
const LOCKOUT_MINUTES      = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/audit.php';

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        // Fetch regardless of active flag so we can report lockout state
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            $nowTs       = time();
            $lockedUntil = $user['locked_until'] ? strtotime($user['locked_until']) : 0;

            if ($lockedUntil > $nowTs) {
                // ── Account is currently locked ───────────────────────────
                $minsLeft = (int)ceil(($lockedUntil - $nowTs) / 60);
                $error    = 'Account locked — too many failed attempts. '
                          . 'Try again in ' . $minsLeft . ' minute' . ($minsLeft !== 1 ? 's' : '') . '.';
                $locked   = true;
                auditLog($pdo, 'login_fail', 'user', (int)$user['id'],
                         $user['username'], 'account_locked');

            } elseif (!$user['active']) {
                // ── Inactive account — generic message to avoid disclosure ─
                $error = 'Invalid username or password.';
                auditLog($pdo, 'login_fail', null, null, null,
                         'attempted_username=' . $username . ' reason=inactive');

            } elseif (password_verify($password, $user['password_hash'])) {
                // ── Successful login — reset lockout state ────────────────
                $pdo->prepare("UPDATE staff SET failed_attempts = 0, locked_until = NULL WHERE id = ?")
                    ->execute([(int)$user['id']]);

                session_regenerate_id(true);
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['username']    = $user['username'];
                $_SESSION['full_name']   = $user['full_name'];
                $_SESSION['role']        = $user['role'];
                $_SESSION['last_active'] = time();
                auditLog($pdo, 'login', 'user', (int)$user['id'], $user['username']);
                header('Location: ' . BASE_URL . '/dashboard.php');
                exit;

            } else {
                // ── Wrong password — increment counter ────────────────────
                $newAttempts = (int)($user['failed_attempts'] ?? 0) + 1;
                $lockUntil   = null;
                $justLocked  = false;

                if ($newAttempts >= LOCKOUT_MAX_ATTEMPTS) {
                    $lockUntil  = date('Y-m-d H:i:s', $nowTs + LOCKOUT_MINUTES * 60);
                    $justLocked = true;
                }

                $pdo->prepare("UPDATE staff SET failed_attempts = ?, locked_until = ? WHERE id = ?")
                    ->execute([$newAttempts, $lockUntil, (int)$user['id']]);

                auditLog($pdo, 'login_fail', 'user', (int)$user['id'],
                         $user['username'],
                         'attempt=' . $newAttempts . ($justLocked ? ' locked=1' : ''));

                if ($justLocked) {
                    // Notify admin by email
                    $adminEmail = PRACTICE_EMAIL;
                    $subject    = '[' . APP_NAME . '] Account locked — ' . $user['full_name'];
                    $body       = "A staff account has been automatically locked after "
                                . LOCKOUT_MAX_ATTEMPTS . " consecutive failed login attempts.\n\n"
                                . "Account  : " . $user['full_name'] . " (" . $user['username'] . ")\n"
                                . "Role     : " . $user['role'] . "\n"
                                . "Locked   : " . date('Y-m-d H:i:s T') . "\n"
                                . "Unlocks  : " . $lockUntil . "\n"
                                . "IP addr  : " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n"
                                . "User-agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . "\n\n"
                                . "— " . APP_NAME . " security system";
                    $headers    = "From: noreply@md-officesupport.com\r\n"
                                . "X-Mailer: " . APP_NAME;
                    @mail($adminEmail, $subject, $body, $headers);

                    $error  = 'Account locked for ' . LOCKOUT_MINUTES . ' minutes due to too many failed attempts. '
                            . 'The administrator has been notified.';
                    $locked = true;
                } else {
                    $remaining = LOCKOUT_MAX_ATTEMPTS - $newAttempts;
                    $error     = 'Invalid username or password. '
                               . $remaining . ' attempt' . ($remaining !== 1 ? 's' : '')
                               . ' remaining before lockout.';
                }
            }
        } else {
            // Username not found — generic message
            $error = 'Invalid username or password.';
            auditLog($pdo, 'login_fail', null, null, null,
                     'attempted_username=' . $username . ' reason=not_found');
        }
    } else {
        $error = 'Please enter your username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','system-ui','sans-serif'] } } } }</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="font-sans min-h-screen flex items-center justify-center p-4 relative overflow-hidden"
      style="background: linear-gradient(135deg, #172554 0%, #1e3a8a 40%, #1d4ed8 100%)">

    <!-- BG blobs -->
    <div class="absolute -top-32 -right-32 w-96 h-96 bg-blue-400/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-cyan-400/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute top-1/2 left-1/4 w-64 h-64 bg-indigo-500/5 rounded-full blur-2xl pointer-events-none"></div>

    <div class="w-full max-w-[400px] relative z-10">

        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex w-20 h-20 bg-white/15 backdrop-blur-sm rounded-3xl items-center justify-center mb-5 shadow-2xl ring-1 ring-white/20">
                <i class="bi bi-clipboard2-heart-fill text-white" style="font-size:2.2rem"></i>
            </div>
            <h1 class="text-white font-extrabold text-3xl tracking-tight"><?= APP_NAME ?></h1>
            <p class="text-blue-200 mt-1 text-sm"><?= h(PRACTICE_NAME) ?></p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-3xl shadow-[0_32px_64px_-12px_rgba(0,0,0,.4)] p-8">

            <?php if ($timeout): ?>
            <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3.5 rounded-2xl text-sm mb-6">
                <i class="bi bi-clock-history text-lg shrink-0 mt-0.5"></i>
                <span>Your session expired due to inactivity. Please sign in again.</span>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <?php if ($locked): ?>
            <div class="flex items-start gap-3 bg-amber-50 border border-amber-300 text-amber-800 px-4 py-3.5 rounded-2xl text-sm mb-6">
                <i class="bi bi-shield-lock-fill text-lg shrink-0 mt-0.5 text-amber-500"></i>
                <span><?= h($error) ?></span>
            </div>
            <?php else: ?>
            <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3.5 rounded-2xl text-sm mb-6">
                <i class="bi bi-exclamation-circle text-lg shrink-0 mt-0.5"></i>
                <span><?= h($error) ?></span>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <form method="POST" novalidate>
                <!-- Username -->
                <div class="mb-5">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Username</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 pointer-events-none">
                            <i class="bi bi-person text-lg"></i>
                        </span>
                        <input type="text" name="username"
                               class="w-full pl-11 pr-4 py-3.5 border border-slate-200 rounded-2xl text-sm bg-slate-50
                                      focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                      transition placeholder-slate-300"
                               placeholder="Enter your username"
                               autocomplete="username" required autofocus>
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-8">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 pointer-events-none">
                            <i class="bi bi-lock text-lg"></i>
                        </span>
                        <input type="password" name="password" id="pwdField"
                               class="w-full pl-11 pr-12 py-3.5 border border-slate-200 rounded-2xl text-sm bg-slate-50
                                      focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                      transition placeholder-slate-300"
                               placeholder="Enter your password"
                               autocomplete="current-password" required>
                        <button type="button" id="pwdToggle"
                                class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400 hover:text-slate-600">
                            <i class="bi bi-eye text-lg" id="pwdEyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800
                               text-white font-bold py-4 rounded-2xl transition-all duration-200
                               shadow-lg shadow-blue-500/30 hover:shadow-xl hover:shadow-blue-600/40
                               active:scale-[0.98] flex items-center justify-center gap-2 text-base">
                    Sign In
                    <i class="bi bi-arrow-right-circle-fill text-lg"></i>
                </button>
            </form>
        </div>

        <p class="text-center text-blue-300/80 text-xs mt-6 flex items-center justify-center gap-2">
            <i class="bi bi-shield-lock-fill"></i>
            Secure access &mdash; Do not share your credentials
        </p>
    </div>

    <script>
    var pwd = document.getElementById('pwdField');
    var eye = document.getElementById('pwdEyeIcon');
    document.getElementById('pwdToggle').addEventListener('click', function() {
        var show = pwd.type === 'password';
        pwd.type = show ? 'text' : 'password';
        eye.className = show ? 'bi bi-eye-slash text-lg' : 'bi bi-eye text-lg';
    });
    </script>
</body>
</html>
