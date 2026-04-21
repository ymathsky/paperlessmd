<?php
/**
 * Print Template: Informed Consent for Wound Care Treatment (wound_care_consent)
 * Matches the Beyond Wound Care Inc. paper form exactly.
 */
$ptName  = h($patient['first_name'] . ' ' . $patient['last_name']);
$ptDob   = $patient['dob'] ? date('m/d/Y', strtotime($patient['dob'])) : '';
$sigDate = date('m/d/Y', strtotime($f['created_at']));
$fd      = is_string($f['form_data']) ? (json_decode($f['form_data'], true) ?? []) : ($f['form_data'] ?? []);
$maName  = h($fd['ma_name'] ?? ($f['ma_name'] ?? ''));

function wcc_checked(array $fd, string $key): string {
    return !empty($fd[$key]) ? '&#10003;' : '&#9744;';
}
?>
<style>
  @page { size: 8in 13in; margin: 0.4in 0.5in; }
</style>
<div class="bwc-form">

    <!-- ── Practice Header ─────────────────────────────────────── -->
    <div class="bwc-header">
        <img src="<?= BASE_URL ?>/assets/img/logo.png" class="bwc-header-logo" alt="Beyond Wound Care Inc.">
        <div class="bwc-header-text">
            <p class="bwc-practice-name">Beyond Wound Care Inc.</p>
            <p>1340 Remington RD, Ste P &nbsp; Schaumburg, IL 60173</p>
            <p>Phone: 847.873.8693 &nbsp;&nbsp; Fax: 847.873.8486</p>
            <p>Email: Support@beyondwoundcare.com</p>
        </div>
    </div>

    <!-- ── Form Title ──────────────────────────────────────────── -->
    <p class="bwc-form-title" style="text-align:center;">INFORMED CONSENT FOR WOUND CARE TREATMENT</p>

    <!-- ── Patient / DOB line ─────────────────────────────────── -->
    <div class="bwc-patient-line" style="margin:10pt 0 8pt;">
        <strong>Patient Name:</strong>
        <span class="bwc-fill"><?= $ptName ?></span>
        &nbsp;&nbsp;&nbsp;
        <strong>DOB:</strong>
        <span class="bwc-fill-sm"><?= $ptDob ?></span>
    </div>

    <!-- ── Intro ──────────────────────────────────────────────── -->
    <div style="font-size:9.5pt;line-height:1.55;margin-bottom:8pt;">
        <p style="margin:0 0 5pt;">Patient/caregiver hereby voluntarily consents to wound care treatment by the provider (MD/NP) of <strong>BEYOND WOUND CARE INC.</strong> Patient/Caregiver understands that this consent form will be valid and remain in effect as long as the patient remains active and receives services and treatments at BEYOND WOUND CARE INC. A new consent form will be obtained when a patient is discharged and returns for services and treatments. <strong>Patient has the right to give or refuse consent to any proposed service or treatment.</strong></p>
    </div>

    <!-- ── Numbered Sections ───────────────────────────────────── -->
    <ol style="font-size:9.5pt;line-height:1.5;margin:0;padding-left:16pt;space-y:0;">

        <li style="margin-bottom:6pt;">
            <strong>General Description of Wound Care Treatment:</strong> Patient acknowledges that physician/NP has explained their treatment for wound care, which can include, but not be limited to: debridement, dressing changes, skin grafts, off-loading devices, physical examinations and treatment, diagnostic procedures, laboratory work (such as wound care cultures), request x-rays, other imaging studies and administration of medications prescribed by a physician and or NP. Patient acknowledges that the physician/NP has given them the opportunity to ask any questions related to the services or treatments being provided and that the physician/NP answered all questions.
        </li>

        <li style="margin-bottom:6pt;">
            <strong>Benefits of Wound Care Treatment:</strong> Patient acknowledges that physician/NP has explained the benefits of wound care treatment, which include enhanced wound healing and reduced risks of amputation and infection.
        </li>

        <li style="margin-bottom:6pt;">
            <strong>Risks and Side Effects of Wound Care Treatment:</strong> Patient acknowledges that physician/NP has explained that wound care treatment may cause side effects and risks including, but not limited to: infection, pain and inflammation, bleeding, allergic reaction to topical and injected local anesthetics or skin prep solutions, removal of healthy tissue, delayed healing or failure to heal, possible scarring and possible damage to: blood vessels, surrounding tissues, and nerves.
        </li>

        <li style="margin-bottom:6pt;">
            <strong>Likelihood of achieving goals:</strong> Patient acknowledges that physician/NP has explained the proposed treatment plan that they are more than likely to have optimized treatment outcomes; however, any service or treatment carry the risk of unsuccessful results, complications and injuries, from both known and unforeseen causes.
        </li>

        <li style="margin-bottom:6pt;">
            <strong>General Description of Wound Debridement:</strong> Patient acknowledges that physician/NP has explained that wound debridement means the removal of unhealthy tissue from a wound to promote healing. During the course of treatment, multiple wound debridement's may be necessary.
        </li>

        <li style="margin-bottom:6pt;">
            <strong>Risks/Side Effects of Wound Debridement:</strong> Patient acknowledges the physician/NP has explained the risks and/or complications of wound debridement include, but are not limited to: potential scarring, possible allergic reactions to topical and injected local anesthetics or skin prep solutions, excessive bleeding, removal of healthy tissue, infection, ongoing pain and inflammation, and failure to heal. Patient specifically acknowledges that physician/NP has explained that bleeding after debridement may cause rapid deterioration of an already compromised patient.
        </li>

        <li style="margin-bottom:6pt;">
            <strong>Patient Identification and Wound Images:</strong> Patient/caregiver understands and consents that images (digital, film, etc.) may be taken by BWC of patient's wounds with their surrounding anatomic features. The purpose of these images is to monitor the progress of wound treatment and ensure continuity of care. Patient/caregiver further agrees that their referring physician/NP may receive communications, including these images, regarding patient's treatment plan and results. The images are considered protected health information and will be handled in accordance with federal laws regarding the privacy, security and confidentiality of such information. Patient understands that BEYOND WOUND CARE INC. will retain ownership rights to these images, but the patient will be allowed access to view or obtain copies according to state and Federal law. Patient understands that these images will be stored in a secure manner that will protect privacy and that they will be kept for the time period required by law. Patient waives any and all rights to royalties or other compensation for these images. Images that identify the patient will only be released and/or used outside BEYOND WOUND CARE INC. (BWC) upon written authorization from the patient or patient's legal representative.
        </li>

        <li style="margin-bottom:6pt;">
            <strong>Use and Disclosure of Protected Health Information (PHI):</strong> Patient consents to BWC use of PHI, results of patient's medical history and physical examination and wound images obtained during the course of patient's wound care treatment and stored in BEYOND WOUND CARE INC. wound database for purposes of education, and quality assessment. Patient's PHI may be disclosed by BEYOND WOUND CARE INC. to its affiliated companies, and third parties who have executed a Business Associate Agreement. Disclosure of patient's PHI shall be in compliance with the privacy regulations of the Health Insurance Portability and Accountability Act of 1996 (HIPAA). Patient/caregiver specifically authorizes use and disclosure of patient's PHI by BEYOND WOUND CARE INC., its affiliates, and business associates for purposes related to treatment, payment and health care operations. If patient wishes to request a restriction to how his/her PHI may be used or disclosed, patient may send a written request for restriction to BEYOND WOUND CARE INC.
        </li>

        <li style="margin-bottom:6pt;">
            <strong>Financial Responsibility:</strong> Patient/caregiver understands that regardless of his or her assigned insurance benefits, patient is responsible for any amount not covered by insurance. Patient authorizes medical information to be released to any payor and their respective agent to determine benefits or the benefits payable for related services.
        </li>

    </ol>

    <!-- ── Closing statement ──────────────────────────────────── -->
    <div style="font-size:9.5pt;line-height:1.55;margin:10pt 0 8pt;">
        <p style="margin:0 0 5pt;">The patient/caregiver or POA hereby acknowledges that he or she has read and agrees to the contents of sections 1 through 9 of these documents. Patient agrees that his or her medical condition has been explained to him or her by the physician/NP. Patient agrees that the risks, benefits and alternatives of all care, treatment and services that patient will undergo while a patient at BWC have been discussed with patient/caregiver by physician/NP. Patient understands the nature of his or her medical condition, the risks, alternatives and benefits of treatment, and the consequences of failure to seek or delay treatment for any conditions. Patient has read this document, or had it read to him/her and understands the contents herein. The patient has had the opportunity to ask questions of the physician and has received answers to all of his or her questions.</p>
        <p style="margin:0;">By signing below, patient consents to the care, treatment and services described in this document and orally by the physician, consents to the creation of images to record his or her wounds and consents to the transfer of health information protected by HIPAA. The Physician has explained to the patient (or his or her legal representative), the nature of the treatment, reasonable alternatives, benefits, risks, side effects, likelihood of achieving patient's goals, complications and consequences which are/or may be associated with the treatment or procedure(s).</p>
    </div>

    <!-- ── Signature Block ────────────────────────────────────── -->
    <div class="bwc-sigs" style="page-break-inside:avoid;">
        <table style="width:100%;border-collapse:collapse;font-size:10pt;">
            <tr>
                <td style="width:65%;padding-right:16pt;vertical-align:bottom;">
                    <div style="position:relative;min-height:36pt;border-bottom:1px solid #000;">
                        <?php if (!empty($f['patient_signature'])): ?>
                        <img src="<?= h($f['patient_signature']) ?>" class="bwc-sig-img" alt="Patient Signature">
                        <?php elseif (!empty($f['poa_name'])): ?>
                        <span style="font-size:9pt;color:#555;"><?= h($f['poa_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="bwc-sig-label">
                        Patient Signature or Authorized Representative
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
            <?php if ($maName): ?>
            <tr>
                <td colspan="2" style="padding-top:10pt;vertical-align:bottom;">
                    <div style="min-height:24pt;border-bottom:1px solid #000;padding-bottom:2pt;">
                        <span style="font-size:10pt;"><?= $maName ?></span>
                    </div>
                    <div class="bwc-sig-label">Witness / Staff Name</div>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

</div><!-- /.bwc-form -->
