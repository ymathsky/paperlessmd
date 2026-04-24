<?php
/**
 * Print Template: Remote Patient Monitoring (RPM) Consent Form
 */
$ptName  = h($patient['first_name'] . ' ' . $patient['last_name']);
$sigDate = date('m/d/Y', strtotime($f['created_at']));
$fd      = is_string($f['form_data']) ? (json_decode($f['form_data'], true) ?? []) : ($f['form_data'] ?? []);
if (!empty($fd['patient_name'])) $ptName  = h($fd['patient_name']);
$rpmSerial = h($fd['rpm_serial'] ?? '');
?>
<style>
  @page { size: 8in 13in; margin: 0.4in 0.5in; }
</style>
<div class="bwc-form">

    <!-- Header -->
    <?php $practiceFormTitle = ''; include __DIR__ . '/../practice_header.php'; ?>

    <!-- Form Title -->
    <p class="bwc-form-title" style="text-align:center;margin:8pt 0 14pt;">REMOTE PATIENT MONITORING (RPM) CONSENT FORM</p>

    <!-- Intro -->
    <p style="font-size:9.5pt;line-height:1.55;margin-bottom:10pt;text-align:justify;">
        I understand and agree to participate in the <strong>Remote Patient Monitoring (RPM) Program</strong>. I acknowledge the following:
    </p>

    <!-- Sections -->
    <div style="font-size:9.5pt;line-height:1.5;">

        <p style="font-weight:bold;margin:0 0 3pt;"><u>Purpose of RPM Services</u></p>
        <ul style="margin:0 0 9pt;padding-left:16pt;">
            <li style="margin-bottom:2pt;">RPM services allow my healthcare provider to monitor my blood pressure remotely.</li>
            <li style="margin-bottom:2pt;">The goal of this program is to improve my health outcomes and detect potential health concerns early.</li>
            <li style="margin-bottom:2pt;">My healthcare provider will review my transmitted readings and contact me if necessary.</li>
        </ul>

        <p style="font-weight:bold;margin:0 0 3pt;"><u>Equipment Use</u></p>
        <ul style="margin:0 0 9pt;padding-left:16pt;">
            <li style="margin-bottom:2pt;">I agree to use the RPM device as instructed.</li>
            <li style="margin-bottom:2pt;">I understand that I am responsible for properly caring for the equipment provided to me.</li>
            <li style="margin-bottom:2pt;">I will notify the healthcare team if the device is damaged, lost, or not working properly.</li>
            <li style="margin-bottom:2pt;">I agree to return the device if I discontinue participation in the program.</li>
            <li style="margin-bottom:2pt;">I understand that I am the only person who should be using the RPM.</li>
        </ul>

        <p style="font-weight:bold;margin:0 0 3pt;"><u>Patient Responsibilities</u></p>
        <ul style="margin:0 0 9pt;padding-left:16pt;">
            <li style="margin-bottom:2pt;">I agree to take readings as instructed by my healthcare provider.</li>
            <li style="margin-bottom:2pt;">I agree to follow instructions regarding device use.</li>
            <li style="margin-bottom:2pt;">I understand that RPM services do not replace emergency medical care.</li>
            <li style="margin-bottom:2pt;">In case of emergency, I will call <strong>911</strong> or seek immediate medical attention.</li>
        </ul>

        <p style="font-weight:bold;margin:0 0 3pt;"><u>Communication</u></p>
        <ul style="margin:0 0 9pt;padding-left:16pt;">
            <li style="margin-bottom:2pt;">I authorize the healthcare team to contact me regarding my readings.</li>
            <li style="margin-bottom:2pt;">I understand that I will be contacted every 30 days via phone to review and discuss my results.</li>
        </ul>

        <p style="font-weight:bold;margin:0 0 3pt;"><u>Privacy and Confidentiality</u></p>
        <ul style="margin:0 0 9pt;padding-left:16pt;">
            <li style="margin-bottom:2pt;">My health information will be transmitted electronically and stored securely in accordance with applicable privacy laws (including HIPAA).</li>
            <li style="margin-bottom:2pt;">I understand that reasonable safeguards are used to protect my information.</li>
        </ul>

        <p style="font-weight:bold;margin:0 0 3pt;"><u>Voluntary Participation</u></p>
        <ul style="margin:0 0 9pt;padding-left:16pt;">
            <li style="margin-bottom:2pt;">Participation in RPM services is voluntary.</li>
            <li style="margin-bottom:2pt;">I may withdraw from the program at any time by notifying my healthcare provider.</li>
            <li style="margin-bottom:2pt;">I understand that discontinuing RPM services will not affect my access to other medical care.</li>
        </ul>

    </div>

    <!-- Closing paragraph -->
    <p style="font-size:9.5pt;line-height:1.55;margin:4pt 0 10pt;text-align:justify;">
        I&nbsp;<span style="display:inline-block;min-width:180pt;border-bottom:1px solid #000;vertical-align:bottom;padding-bottom:1pt;"><?= $ptName ?></span>,
        have read (or had read to me) this consent form. I understand the information provided and have had the opportunity to ask questions. I voluntarily agree to participate in the Remote Patient Monitoring (RPM) Program.
    </p>

    <!-- RPM Serial # -->
    <p style="font-size:9.5pt;margin:0 0 14pt;">
        I acknowledged that I received RPM with serial #:&nbsp;
        <span style="display:inline-block;min-width:200pt;border-bottom:1px solid #000;vertical-align:bottom;padding-bottom:1pt;"><?= $rpmSerial ?></span>.
    </p>

    <!-- Signature -->
    <div class="bwc-sigs" style="margin-top:10pt;page-break-inside:avoid;">
        <table style="width:100%;border-collapse:collapse;font-size:10pt;">
            <tr>
                <td style="width:65%;padding-right:20pt;vertical-align:bottom;">
                    <div style="position:relative;min-height:36pt;border-bottom:1px solid #000;">
                        <?php if (!empty($f['patient_signature'])): ?>
                        <img src="<?= h($f['patient_signature']) ?>" class="bwc-sig-img" alt="Patient Signature">
                        <?php elseif (!empty($f['poa_name'])): ?>
                        <span style="font-size:9pt;color:#555;"><?= h($f['poa_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="bwc-sig-label">
                        Signature of Patient or Authorized Person
                        <?php if (!empty($f['poa_name'])): ?>
                        <br><span style="font-size:8pt;color:#666;">POA: <?= h($f['poa_name']) ?><?= !empty($f['poa_relationship']) ? ' (' . h($f['poa_relationship']) . ')' : '' ?></span>
                        <?php endif; ?>
                    </div>
                </td>
                <td style="width:35%;vertical-align:bottom;">
                    <div style="min-height:36pt;border-bottom:1px solid #000;padding-bottom:2pt;">
                        <span style="font-size:10pt;"><?= $sigDate ?></span>
                    </div>
                    <div class="bwc-sig-label">Date</div>
                </td>
            </tr>
        </table>
    </div>

</div><!-- /.bwc-form -->
