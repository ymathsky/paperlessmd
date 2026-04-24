<?php
/**
 * Practice Header Partial
 * Outputs the correct logo + company info based on $patient['company'].
 * Available variables when included:
 *   $patient        – patient row from DB
 *   $practiceFormTitle (optional) – e.g. "CONSENT FORM", "Cognitive Wellness Exam"
 */
$_isVmp = (($patient['company'] ?? '') === 'Visiting Medical Physician Inc.');
?>
<div class="bwc-header">
<?php if ($_isVmp): ?>
    <img src="<?= BASE_URL ?>/assets/img/vmp_logo.png" class="bwc-header-logo" alt="Visiting Medical Physician Inc.">
    <div class="bwc-header-text">
        <p class="bwc-practice-name" style="color:#14936d;">Visiting Medical Physician Inc.</p>
        <p>1340 Remington RD, Suite M &nbsp; Schaumburg, IL 60173</p>
        <p>Phone: 847.252.1858</p>
        <p>Email: care@visitingmedicalphysician.com</p>
        <?php if (!empty($practiceFormTitle)): ?>
        <p class="bwc-form-title"><?= htmlspecialchars($practiceFormTitle, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <img src="<?= BASE_URL ?>/assets/img/logo.png" class="bwc-header-logo" alt="Beyond Wound Care Inc.">
    <div class="bwc-header-text">
        <p class="bwc-practice-name">Beyond Wound Care Inc.</p>
        <p>1340 Remington RD, STE P &nbsp; Schaumburg, IL 60173</p>
        <p>Phone: 847-873-8693 &nbsp;&nbsp; Fax: 847-873-8486</p>
        <p>Support@beyondwoundcare.com</p>
        <?php if (!empty($practiceFormTitle)): ?>
        <p class="bwc-form-title"><?= htmlspecialchars($practiceFormTitle, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>
