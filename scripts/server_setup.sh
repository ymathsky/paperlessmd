#!/usr/bin/env bash
# =============================================================================
# PaperlessMD — DigitalOcean Server Setup
# Run ONCE as root on a fresh Ubuntu 22.04 / 24.04 Droplet.
#
# Usage:
#   chmod +x server_setup.sh
#   sudo bash server_setup.sh
# =============================================================================
set -euo pipefail

APP_DIR="/var/www/paperlessmd"
REPO="https://github.com/ymathsky/paperlessmd.git"
PHP_VER="8.2"
DB_NAME="paperlessmd"
DB_USER="pduser"
# Change this password before running!
DB_PASS="ChangeMe_Strong_Password_123!"

echo "=============================="
echo " PaperlessMD Server Setup"
echo "=============================="

# ── 1. System updates ─────────────────────────────────────────────────────────
apt-get update -y && apt-get upgrade -y

# ── 2. Install Apache, PHP, MySQL ────────────────────────────────────────────
apt-get install -y \
    apache2 \
    mysql-server \
    php${PHP_VER} \
    php${PHP_VER}-mysql \
    php${PHP_VER}-mbstring \
    php${PHP_VER}-gd \
    php${PHP_VER}-curl \
    php${PHP_VER}-zip \
    php${PHP_VER}-xml \
    php${PHP_VER}-fileinfo \
    php${PHP_VER}-opcache \
    libapache2-mod-php${PHP_VER} \
    git \
    certbot \
    python3-certbot-apache \
    unzip \
    curl

# ── 3. Apache modules ─────────────────────────────────────────────────────────
a2enmod rewrite headers expires deflate php${PHP_VER}

# ── 4. PHP tuning ─────────────────────────────────────────────────────────────
PHP_INI="/etc/php/${PHP_VER}/apache2/php.ini"
sed -i 's/^upload_max_filesize.*/upload_max_filesize = 50M/'      "$PHP_INI"
sed -i 's/^post_max_size.*/post_max_size = 52M/'                  "$PHP_INI"
sed -i 's/^memory_limit.*/memory_limit = 512M/'                   "$PHP_INI"
sed -i 's/^max_execution_time.*/max_execution_time = 120/'        "$PHP_INI"

# ── 5. MySQL: create DB + user ────────────────────────────────────────────────
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';

-- Raise packet size limit for base64 images stored in MEDIUMTEXT
SET GLOBAL max_allowed_packet = 67108864;
FLUSH PRIVILEGES;
SQL

# Persist max_allowed_packet in MySQL config
cat >> /etc/mysql/mysql.conf.d/mysqld.cnf <<MYCNF

[mysqld]
max_allowed_packet = 64M
MYCNF
systemctl restart mysql

# ── 6. Clone repo ─────────────────────────────────────────────────────────────
if [ -d "$APP_DIR/.git" ]; then
    echo "Repo already cloned — skipping git clone."
else
    git clone "$REPO" "$APP_DIR"
fi

# ── 7. Permissions ────────────────────────────────────────────────────────────
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
mkdir -p "${APP_DIR}/uploads/photos"
chown -R www-data:www-data "${APP_DIR}/uploads"
chmod -R 775 "${APP_DIR}/uploads"

# ── 8. Create config.local.php with production values ────────────────────────
cat > "${APP_DIR}/includes/config.local.php" <<PHP
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', '${DB_NAME}');
define('DB_USER', '${DB_USER}');
define('DB_PASS', '${DB_PASS}');
define('BASE_URL', '');

define('MAIL_HOST',      '');
define('MAIL_USER',      '');
define('MAIL_PASS',      '');
define('MAIL_PORT',      587);
define('MAIL_FROM',      '');
define('MAIL_FROM_NAME', 'PaperlessMD');

define('APP_NAME',         'PaperlessMD');
define('PRACTICE_NAME',    'Beyond Wound Care Inc.');
define('PRACTICE_ADDRESS', '1340 Remington RD, STE P, Schaumburg, IL 60173');
define('PRACTICE_PHONE',   '847-873-8693');
define('PRACTICE_FAX',     '847-873-8486');
define('PRACTICE_EMAIL',   'Support@beyondwoundcare.com');
define('UPLOAD_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR);
define('SESSION_TIMEOUT', 7200);
define('GEMINI_API_KEY',  '');

if (!function_exists('h')) {
    function h(string \$val): string {
        return htmlspecialchars(\$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}
PHP
chown www-data:www-data "${APP_DIR}/includes/config.local.php"
chmod 640 "${APP_DIR}/includes/config.local.php"

# ── 9. Apache virtual host ────────────────────────────────────────────────────
cat > /etc/apache2/sites-available/paperlessmd.conf <<VHOST
<VirtualHost *:80>
    ServerName YOUR_DOMAIN_OR_IP
    DocumentRoot ${APP_DIR}
    DirectoryIndex index.php

    <Directory ${APP_DIR}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Block direct access to sensitive files
    <FilesMatch "(^migrate_|^seed_|^install|^_patch|^_tmp|config\.local|config\.keys)">
        Require all denied
    </FilesMatch>

    ErrorLog \${APACHE_LOG_DIR}/paperlessmd_error.log
    CustomLog \${APACHE_LOG_DIR}/paperlessmd_access.log combined
</VirtualHost>
VHOST

a2dissite 000-default.conf 2>/dev/null || true
a2ensite paperlessmd.conf
systemctl reload apache2

# ── 10. Deploy webhook (auto-deploy on git push) ──────────────────────────────
cp "${APP_DIR}/scripts/deploy.sh" /usr/local/bin/paperlessmd-deploy
chmod +x /usr/local/bin/paperlessmd-deploy
chown www-data:www-data /usr/local/bin/paperlessmd-deploy

# ── 11. Run all migrations ────────────────────────────────────────────────────
echo "Running migrations..."
bash /usr/local/bin/paperlessmd-deploy

echo ""
echo "=============================="
echo " Setup complete!"
echo "=============================="
echo " App:      http://YOUR_DOMAIN_OR_IP"
echo " DB name:  ${DB_NAME}"
echo " DB user:  ${DB_USER}"
echo " DB pass:  ${DB_PASS}"
echo ""
echo " Next steps:"
echo "  1. Edit /etc/apache2/sites-available/paperlessmd.conf"
echo "     and replace YOUR_DOMAIN_OR_IP with your actual domain"
echo "  2. Run: certbot --apache -d yourdomain.com   (free SSL)"
echo "  3. Edit ${APP_DIR}/includes/config.local.php"
echo "     and set your MAIL_* and GEMINI_API_KEY values"
echo "  4. Visit http://YOUR_DOMAIN_OR_IP/install.php to create admin user"
echo "=============================="
