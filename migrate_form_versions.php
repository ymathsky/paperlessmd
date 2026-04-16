<?php
/**
 * Migration: Create form_versions table
 *
 * form_versions stores a snapshot of a form_submission's data BEFORE it is
 * amended by an admin. This allows full audit of every version of a document.
 *
 * Each time an admin edits form_data on an existing signed/uploaded submission,
 * the current form_data is copied here first (version N), then the main row is
 * updated. The main row always holds the current (latest) content.
 *
 * Run once at: https://docs.md-officesupport.com/migrate_form_versions.php
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

$results = [];

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS form_versions (
            id              BIGINT AUTO_INCREMENT PRIMARY KEY,
            submission_id   INT NOT NULL COMMENT 'form_submissions.id this snapshot belongs to',
            version_num     SMALLINT NOT NULL DEFAULT 1 COMMENT 'Sequence: 1 = first amendment, 2 = second, etc.',
            form_data       JSON NOT NULL COMMENT 'Snapshot of form_data before amendment',
            amended_by      INT NULL COMMENT 'staff.id of the admin who made the amendment',
            amended_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            amendment_note  TEXT NULL COMMENT 'Optional reason for amendment',
            INDEX idx_submission (submission_id),
            INDEX idx_amended_at (amended_at),
            FOREIGN KEY (submission_id) REFERENCES form_submissions(id) ON DELETE CASCADE,
            FOREIGN KEY (amended_by)    REFERENCES staff(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $results[] = ['ok' => true, 'msg' => 'form_versions table created (or already exists).'];
} catch (PDOException $e) {
    $results[] = ['ok' => false, 'msg' => 'Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')];
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Migrate — Form Versions</title>
<style>body{font-family:sans-serif;max-width:640px;margin:40px auto;padding:0 16px}
.ok{color:#059669;background:#d1fae5;padding:10px 16px;border-radius:8px;margin-bottom:8px}
.err{color:#dc2626;background:#fee2e2;padding:10px 16px;border-radius:8px;margin-bottom:8px}
</style></head><body>
<h2>Form Versions Migration</h2>
<?php foreach ($results as $r): ?>
    <p class="<?= $r['ok'] ? 'ok' : 'err' ?>"><?= $r['msg'] ?></p>
<?php endforeach; ?>
<p><a href="<?= BASE_URL ?>/dashboard.php">← Back to Dashboard</a></p>
</body></html>
