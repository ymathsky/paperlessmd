#!/usr/bin/env bash
# =============================================================================
# PaperlessMD — Deploy Script
# Run on the server to pull latest code from GitHub and apply migrations.
#
# Usage (manual):
#   sudo -u www-data bash /usr/local/bin/paperlessmd-deploy
#
# Called automatically by the webhook handler on every git push.
# =============================================================================
set -euo pipefail

APP_DIR="/var/www/paperlessmd"
LOG="/var/log/paperlessmd-deploy.log"
PHP="$(which php8.2 2>/dev/null || which php)"

echo "" >> "$LOG"
echo "===== Deploy started: $(date '+%Y-%m-%d %H:%M:%S') =====" >> "$LOG"

# ── 1. Pull latest code ───────────────────────────────────────────────────────
cd "$APP_DIR"
git fetch origin main 2>> "$LOG"
git reset --hard origin/main 2>> "$LOG"
echo "Git pull: OK" >> "$LOG"

# ── 2. Fix permissions ────────────────────────────────────────────────────────
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
mkdir -p "${APP_DIR}/uploads/photos"
chown -R www-data:www-data "${APP_DIR}/uploads"
chmod -R 775 "${APP_DIR}/uploads"
# config.local.php must NOT be world-readable
[ -f "${APP_DIR}/includes/config.local.php" ] && chmod 640 "${APP_DIR}/includes/config.local.php"

# ── 3. Run all migrate_*.php files (idempotent) ───────────────────────────────
MIGRATION_ORDER=(
    migrate_settings.php
    migrate_audit.php
    migrate_patient_status.php
    migrate_patient_photos.php
    migrate_assigned_ma.php
    migrate_schedule.php
    migrate_visit_type.php
    migrate_visit_notes.php
    migrate_meds.php
    migrate_messages.php
    migrate_soap.php
    migrate_diagnoses.php
    migrate_wounds.php
    migrate_care_notes.php
    migrate_pf.php
    migrate_saved_signatures.php
    migrate_sig_cols.php
    migrate_ma_locations.php
    migrate_mobile_tokens.php
    migrate_last_active.php
    migrate_login_lockout.php
    migrate_form_versions.php
    migrate_admin_notes.php
    migrate_billing_role.php
    migrate_scheduler_role.php
    migrate_staff_email.php
    migrate_provider_role.php
    migrate_pcc_role.php
    migrate_schedule_provider.php
    migrate_form_submissions_provider.php
    migrate_patient_extras.php
)

echo "Running migrations..." >> "$LOG"
for MIG in "${MIGRATION_ORDER[@]}"; do
    MIGPATH="${APP_DIR}/${MIG}"
    if [ -f "$MIGPATH" ]; then
        OUT=$("$PHP" -r "
            \$_SERVER['SERVER_NAME'] = 'localhost';
            \$_SESSION = ['user_id'=>1,'role'=>'admin','full_name'=>'Deploy'];
            require '${MIGPATH}';
        " 2>&1 || true)
        echo "  [OK] ${MIG}" >> "$LOG"
    fi
done

# ── 4. Reload Apache (graceful — no downtime) ─────────────────────────────────
if command -v apache2ctl &>/dev/null; then
    apache2ctl graceful 2>> "$LOG" && echo "Apache: reloaded" >> "$LOG"
fi

echo "===== Deploy finished: $(date '+%Y-%m-%d %H:%M:%S') =====" >> "$LOG"
echo "Deploy OK — $(date '+%Y-%m-%d %H:%M:%S')"
