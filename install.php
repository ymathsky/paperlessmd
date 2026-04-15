<?php
require_once __DIR__ . '/includes/config.php';

$msg = $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminUser  = trim($_POST['admin_username'] ?? 'admin');
    $adminPass  = $_POST['admin_password'] ?? '';
    $adminName  = trim($_POST['admin_name'] ?? 'Administrator');
    $dbPassover = $_POST['db_pass'] ?? '';

    if (strlen($adminPass) < 8) {
        $msg = 'Admin password must be at least 8 characters.';
        $msgType = 'error';
    } else {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, $dbPassover ?: DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");
            $pdo->exec("CREATE TABLE IF NOT EXISTS staff (
                id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL, full_name VARCHAR(100) NOT NULL,
                role ENUM('admin','ma','billing') NOT NULL DEFAULT 'ma', active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS patients (
                id INT AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL,
                dob DATE, email VARCHAR(150), phone VARCHAR(20), address TEXT, insurance VARCHAR(100), pcp VARCHAR(100),
                pf_patient_id VARCHAR(100) NULL, created_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES staff(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS form_submissions (
                id INT AUTO_INCREMENT PRIMARY KEY, patient_id INT NOT NULL, form_type VARCHAR(50) NOT NULL,
                form_data JSON NOT NULL, patient_signature MEDIUMTEXT, poa_name VARCHAR(100), poa_relationship VARCHAR(50),
                poa_signature MEDIUMTEXT, signed_at TIMESTAMP NULL, ma_id INT,
                status ENUM('draft','signed','uploaded') DEFAULT 'draft',
                pf_uploaded_at TIMESTAMP NULL, pf_uploaded_by INT, pf_patient_id VARCHAR(100) NULL, pf_doc_id VARCHAR(100) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (patient_id) REFERENCES patients(id), FOREIGN KEY (ma_id) REFERENCES staff(id),
                FOREIGN KEY (pf_uploaded_by) REFERENCES staff(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS wound_photos (
                id INT AUTO_INCREMENT PRIMARY KEY, patient_id INT NOT NULL, filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255), description TEXT, wound_location VARCHAR(100), uploaded_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (patient_id) REFERENCES patients(id), FOREIGN KEY (uploaded_by) REFERENCES staff(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS schedule (
                id INT AUTO_INCREMENT PRIMARY KEY,
                visit_date DATE NOT NULL,
                ma_id INT NOT NULL,
                patient_id INT NOT NULL,
                visit_time TIME NULL,
                visit_order SMALLINT NOT NULL DEFAULT 0,
                status ENUM('pending','en_route','completed','missed') NOT NULL DEFAULT 'pending',
                notes TEXT NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (ma_id) REFERENCES staff(id),
                FOREIGN KEY (patient_id) REFERENCES patients(id),
                FOREIGN KEY (created_by) REFERENCES staff(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                username VARCHAR(100) NULL,
                user_role VARCHAR(20) NULL,
                action VARCHAR(50) NOT NULL,
                target_type VARCHAR(50) NULL,
                target_id INT NULL,
                target_label VARCHAR(255) NULL,
                ip_address VARCHAR(45) NULL,
                details TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_target (target_type, target_id),
                INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS wound_measurements (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                patient_id  INT NOT NULL,
                visit_id    INT NULL,
                measured_at DATE NOT NULL,
                wound_site  VARCHAR(150) NOT NULL DEFAULT 'Unspecified',
                length_cm   DECIMAL(5,1) NOT NULL DEFAULT 0.0,
                width_cm    DECIMAL(5,1) NOT NULL DEFAULT 0.0,
                depth_cm    DECIMAL(5,1) NOT NULL DEFAULT 0.0,
                notes       TEXT NULL,
                recorded_by INT NULL,
                created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (patient_id) REFERENCES patients(id),
                FOREIGN KEY (recorded_by) REFERENCES staff(id),
                INDEX idx_patient_date (patient_id, measured_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO staff (username,password_hash,full_name,role) VALUES (?,?,?,'admin')
                                   ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),full_name=VALUES(full_name),role='admin'");
            $stmt->execute([$adminUser, $hash, $adminName]);
            $msg = 'success'; $msgType = 'success';
        } catch (PDOException $e) {
            $msg = $e->getMessage(); $msgType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="font-sans min-h-screen bg-slate-50 flex items-start justify-center p-6 pt-12">
<div class="w-full max-w-md">
    <div class="text-center mb-8">
        <div class="inline-flex w-16 h-16 bg-blue-600 rounded-2xl items-center justify-center mb-4 shadow-lg">
            <i class="bi bi-clipboard2-heart-fill text-white text-2xl"></i>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-800"><?= APP_NAME ?></h1>
        <p class="text-slate-500 text-sm mt-1">First-time setup</p>
    </div>

    <?php if ($msgType === 'success'): ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 text-sm text-emerald-800 mb-6">
        <div class="flex items-center gap-2 font-bold mb-2"><i class="bi bi-check-circle-fill text-lg"></i> Installation Complete!</div>
        <p><a href="<?= BASE_URL ?>/index.php" class="font-semibold underline">Click here to sign in</a></p>
        <p class="mt-2 text-emerald-700 font-medium">⚠️ Delete or rename <code>install.php</code> now.</p>
    </div>
    <?php elseif ($msg && $msgType === 'error'): ?>
    <div class="bg-red-50 border border-red-200 rounded-2xl p-4 text-sm text-red-700 mb-6 flex items-start gap-2">
        <i class="bi bi-exclamation-circle text-lg shrink-0 mt-0.5"></i> <?= h($msg) ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8">
        <form method="POST" novalidate>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Database Connection</p>
            <div class="mb-5">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">MySQL Password
                    <span class="text-slate-400 font-normal">(blank = XAMPP default)</span>
                </label>
                <input type="password" name="db_pass"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-slate-50"
                       placeholder="Leave blank for default XAMPP">
            </div>

            <div class="border-t border-slate-100 my-6"></div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Admin Account</p>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Username</label>
                <input type="text" name="admin_username" value="admin" required
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-slate-50">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Full Name</label>
                <input type="text" name="admin_name" value="Administrator" required
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-slate-50">
            </div>
            <div class="mb-8">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Password <span class="text-red-500">*</span>
                    <span class="text-slate-400 font-normal">(min. 8 characters)</span>
                </label>
                <input type="password" name="admin_password" required minlength="8"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-slate-50"
                       placeholder="Strong password">
            </div>

            <button type="submit"
                    class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800
                           text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-blue-500/25
                           active:scale-[0.98] flex items-center justify-center gap-2">
                <i class="bi bi-database-fill-gear text-lg"></i> Run Installation
            </button>
        </form>
    </div>
</div>
</body>
</html>
