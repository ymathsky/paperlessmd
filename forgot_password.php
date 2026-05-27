<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';

// Already logged in → dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$msg   = '';
$error = '';
$sent  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');

    if ($username === '') {
        $error = 'Please enter your username.';
    } else {
        $stmt = $pdo->prepare("SELECT id, email, full_name FROM staff WHERE username = ? AND active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Always show "check your email" to prevent username enumeration
        if ($user && !empty($user['email'])) {
            // Generate a secure token (64 hex chars = 32 random bytes)
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $pdo->prepare("UPDATE staff SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?")
                ->execute([$token, $expires, (int)$user['id']]);

            $resetLink = BASE_URL . '/reset_password.php?token=' . $token;

            $html = '
            <div style="font-family:Inter,system-ui,sans-serif;max-width:540px;margin:auto;padding:32px 24px;background:#f8fafc;border-radius:16px;">
                <div style="text-align:center;margin-bottom:28px;">
                    <div style="display:inline-block;background:linear-gradient(135deg,#1e3a8a,#1d4ed8);border-radius:16px;padding:18px 22px;margin-bottom:12px;">
                        <span style="font-size:2rem;">🔒</span>
                    </div>
                    <h1 style="font-size:22px;font-weight:800;color:#1e293b;margin:0;">' . h(APP_NAME) . '</h1>
                    <p style="color:#64748b;margin:4px 0 0;">Password Reset Request</p>
                </div>
                <div style="background:#fff;border-radius:12px;padding:28px;border:1px solid #e2e8f0;">
                    <p style="color:#1e293b;font-size:15px;margin-top:0;">Hi <strong>' . h($user['full_name']) . '</strong>,</p>
                    <p style="color:#475569;font-size:14px;line-height:1.6;">
                        We received a request to reset your password for your <strong>' . h(APP_NAME) . '</strong> account.
                        Click the button below to choose a new password. This link expires in <strong>1 hour</strong>.
                    </p>
                    <div style="text-align:center;margin:28px 0;">
                        <a href="' . $resetLink . '"
                           style="background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;text-decoration:none;
                                  font-weight:700;font-size:15px;padding:14px 36px;border-radius:12px;display:inline-block;">
                            Reset My Password
                        </a>
                    </div>
                    <p style="color:#94a3b8;font-size:12px;border-top:1px solid #f1f5f9;padding-top:16px;margin-bottom:0;">
                        If you did not request a password reset, you can safely ignore this email — your password will not change.<br><br>
                        Or copy this link: <a href="' . $resetLink . '" style="color:#2563eb;">' . $resetLink . '</a>
                    </p>
                </div>
                <p style="text-align:center;color:#94a3b8;font-size:11px;margin-top:20px;">
                    ' . h(PRACTICE_NAME) . ' &mdash; ' . h(APP_NAME) . '
                </p>
            </div>';

            sendMail($user['email'], 'Password Reset — ' . APP_NAME, $html);
        }

        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — <?= APP_NAME ?></title>
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
                <i class="bi bi-key-fill text-white" style="font-size:2.2rem"></i>
            </div>
            <h1 class="text-white font-extrabold text-3xl tracking-tight"><?= APP_NAME ?></h1>
            <p class="text-blue-200 mt-1 text-sm">Password Recovery</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-3xl shadow-[0_32px_64px_-12px_rgba(0,0,0,.4)] p-8">

            <?php if ($sent): ?>
            <div class="flex flex-col items-center text-center py-2">
                <div class="w-16 h-16 bg-emerald-100 rounded-2xl grid place-items-center mb-4">
                    <i class="bi bi-envelope-check-fill text-emerald-600 text-3xl"></i>
                </div>
                <h2 class="font-bold text-slate-800 text-lg mb-2">Check your email</h2>
                <p class="text-slate-500 text-sm leading-relaxed mb-6">
                    If that username has an email address on file, we've sent a password reset link.
                    The link expires in <strong>1 hour</strong>.
                </p>
                <a href="<?= BASE_URL ?>/index.php"
                   class="w-full text-center bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800
                          text-white font-bold py-3.5 rounded-2xl transition-all shadow-lg shadow-blue-500/30">
                    Back to Sign In
                </a>
            </div>

            <?php else: ?>

            <h2 class="font-bold text-slate-800 text-xl mb-1">Forgot your password?</h2>
            <p class="text-slate-500 text-sm mb-6">Enter your username and we'll send a reset link to the email on your account.</p>

            <?php if ($error): ?>
            <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3.5 rounded-2xl text-sm mb-5">
                <i class="bi bi-exclamation-circle text-lg shrink-0 mt-0.5"></i>
                <span><?= h($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="mb-6">
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

                <button type="submit"
                        class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800
                               text-white font-bold py-4 rounded-2xl transition-all duration-200
                               shadow-lg shadow-blue-500/30 hover:shadow-xl hover:shadow-blue-600/40
                               active:scale-[0.98] flex items-center justify-center gap-2 text-base">
                    <i class="bi bi-envelope-fill"></i>
                    Send Reset Link
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
</body>
</html>
