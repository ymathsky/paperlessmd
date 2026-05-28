<?php
/**
 * includes/rx_pad_panel.php
 *
 * Floating RX Pad panel — write & print prescriptions, company VMP or BWC.
 * Saved meds sync to patient_medications (auto-fills future CS forms).
 *
 * Requires in calling page:
 *   $patient_id  (int)
 *   $patient     (array with first_name, last_name, dob)
 *   $pdo         (PDO)
 */

$_rxCsrf        = csrfToken();
$_rxPatientName = htmlspecialchars(trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')), ENT_QUOTES, 'UTF-8');
$_rxPatientDob  = htmlspecialchars($patient['dob'] ?? '', ENT_QUOTES, 'UTF-8');

// Auto-fill prescriber: today's schedule → patient assigned_provider → logged-in provider → blank
$_rxProvider = '';
try {
    // 1. Today's schedule for this patient
    $__rxSp = $pdo->prepare("SELECT provider_name FROM `schedule` WHERE patient_id = ? AND visit_date = CURDATE() AND COALESCE(provider_name,'') != '' ORDER BY id DESC LIMIT 1");
    $__rxSp->execute([$patient_id]);
    $_rxProvider = (string)($__rxSp->fetchColumn() ?: '');

    // 2. Fallback: patient's assigned_provider from patients table
    if (!$_rxProvider) {
        $__rxPat = $pdo->prepare("SELECT assigned_provider FROM patients WHERE id = ? AND COALESCE(assigned_provider,'') != '' LIMIT 1");
        $__rxPat->execute([$patient_id]);
        $_rxProvider = (string)($__rxPat->fetchColumn() ?: '');
    }

    // 3. Fallback: logged-in user if they are a provider
    if (!$_rxProvider && ($_SESSION['role'] ?? '') === 'provider') {
        $_rxProvider = $_SESSION['full_name'] ?? '';
    }

    // No further fallback — leave blank so staff selects the correct provider
} catch (PDOException $e) {}

// Provider names for datalist
$_rxProviders = [];
try {
    $_rxProviders = $pdo->query("SELECT full_name FROM staff WHERE role='provider' AND active=1 ORDER BY full_name")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

// Fetch current active medications for the Medications tab
$_rxActiveMeds = [];
try {
    $__rxMs = $pdo->prepare("SELECT id, med_name, med_frequency FROM patient_medications WHERE patient_id = ? AND status = 'active' ORDER BY sort_order ASC, added_at ASC");
    $__rxMs->execute([$patient_id]);
    $_rxActiveMeds = $__rxMs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Fetch diagnoses for the Diagnoses tab
$_rxDiagnoses = [];
try {
    $__rxDx = $pdo->prepare("SELECT icd_code, icd_desc, notes FROM patient_diagnoses WHERE patient_id = ? ORDER BY added_at DESC");
    $__rxDx->execute([$patient_id]);
    $_rxDiagnoses = $__rxDx->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Company presets
$_rxCompanies = [
    'bwc' => [
        'name'    => 'Beyond Wound Care Inc.',
        'address' => '1340 Remington RD, Ste P',
        'city'    => 'Schaumburg, IL 60173',
        'phone'   => '847.873.8693',
        'fax'     => '847.873.8486',
        'email'   => 'Support@beyondwoundcare.com',
    ],
    'vmp' => [
        'name'    => 'Visiting Medical Physician Inc.',
        'address' => '1340 Remington RD, Suite M',
        'city'    => 'Schaumburg, IL 60173',
        'phone'   => '847.252.1858',
        'fax'     => '',
        'email'   => 'care@visitingmedicalphysician.com',
    ],
];
$_rxCompJson = json_encode($_rxCompanies);
?>

<!-- ═══════════════ RX PAD: Floating Button ═══════════════════════════════ -->
<button id="rxPadFloatBtn"
        title="Write Prescription (RX)"
        onclick="rxPadOpen()"
        class="fixed right-5 w-14 h-14 flex items-center justify-center
               rounded-full shadow-lg transition-transform hover:scale-110 active:scale-95
               no-print"
        style="bottom:248px;z-index:7900;background:linear-gradient(135deg,#5b21b6,#7c3aed);box-shadow:0 4px 16px rgba(124,58,237,.5);">
    <i class="bi bi-prescription2 text-white text-2xl"></i>
</button>

<!-- ═══════════════ RX PAD: Overlay ═════════════════════════════════════ -->
<div id="rxPadOverlay"
     class="fixed inset-0 hidden no-print"
     style="background:rgba(15,10,40,.55);backdrop-filter:blur(2px);z-index:8000;"
     onclick="rxPadClose()"></div>

<!-- ═══════════════ RX PAD: Panel ═══════════════════════════════════════ -->
<style>
@media(min-width:768px){
    #rxPadPanel{
        left:50% !important;right:auto !important;
        width:min(680px,92vw);margin-left:calc(min(680px,92vw)/-2);
        border-radius:18px !important;bottom:1.5rem !important;
    }
}
</style>
<div id="rxPadPanel"
     class="fixed bottom-0 no-print"
     style="left:0;right:0;z-index:8001;overflow:hidden;
            max-height:92vh;display:flex;flex-direction:column;
            border-radius:22px 22px 0 0;box-shadow:0 -8px 40px rgba(0,0,0,.22);
            transform:translateY(calc(100% + 2rem));transition:transform .35s cubic-bezier(.4,0,.2,1);background:#fff;">

    <!-- Gradient Header (drag handle + title) -->
    <div class="flex-shrink-0" style="background:linear-gradient(135deg,#4c1d95 0%,#6d28d9 45%,#8b5cf6 100%);">
        <!-- Handle -->
        <div class="flex justify-center pt-2.5 pb-0">
            <div style="width:36px;height:4px;border-radius:99px;background:rgba(255,255,255,.35);"></div>
        </div>
        <!-- Title row -->
        <div class="flex items-center justify-between px-5 pb-4 pt-2">
            <div class="flex items-center gap-3">
                <div style="width:38px;height:38px;border-radius:12px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-prescription2 text-white" style="font-size:18px;"></i>
                </div>
                <div>
                    <div style="color:#fff;font-weight:700;font-size:16px;line-height:1.2;">Write Prescription</div>
                    <div style="color:#c4b5fd;font-size:12px;font-weight:500;margin-top:2px;"><?= $_rxPatientName ?></div>
                </div>
            </div>
            <button onclick="rxPadClose()"
                    style="width:32px;height:32px;border-radius:50%;border:none;background:rgba(255,255,255,.18);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;"
                    onmouseover="this.style.background='rgba(255,255,255,.28)'"
                    onmouseout="this.style.background='rgba(255,255,255,.18)'">
                <i class="bi bi-x-lg" style="font-size:13px;"></i>
            </button>
        </div>
    </div>

    <!-- Tab Bar -->
    <div class="flex-shrink-0" style="display:flex;background:#f5f3ff;border-bottom:1.5px solid #ede9fe;">
        <button type="button" id="rxTabBtnWrite" onclick="rxSwitchTab('write')"
                style="flex:1;padding:10px 8px;border:none;background:transparent;cursor:pointer;font-size:13px;font-weight:700;color:#6d28d9;border-bottom:3px solid #7c3aed;transition:all .15s;">
            <i class="bi bi-prescription2" style="margin-right:4px;"></i>Write RX
        </button>
        <button type="button" id="rxTabBtnMeds" onclick="rxSwitchTab('meds')"
                style="flex:1;padding:10px 8px;border:none;background:transparent;cursor:pointer;font-size:13px;font-weight:600;color:#94a3b8;border-bottom:3px solid transparent;transition:all .15s;">
            <i class="bi bi-capsule-pill" style="margin-right:4px;"></i>Medications
            <span id="rxMedsBadge" style="display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;border-radius:99px;background:#a78bfa;color:#fff;font-size:10px;font-weight:700;padding:0 4px;margin-left:4px;"><?= count($_rxActiveMeds) ?></span>
        </button>
        <button type="button" id="rxTabBtnDx" onclick="rxSwitchTab('dx')"
                style="flex:1;padding:10px 8px;border:none;background:transparent;cursor:pointer;font-size:13px;font-weight:600;color:#94a3b8;border-bottom:3px solid transparent;transition:all .15s;">
            <i class="bi bi-clipboard2-pulse" style="margin-right:4px;"></i>Diagnoses
            <span id="rxDxBadge" style="display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;border-radius:99px;background:#a78bfa;color:#fff;font-size:10px;font-weight:700;padding:0 4px;margin-left:4px;"><?= count($_rxDiagnoses) ?></span>
        </button>
    </div>

    <div id="rxStatusBar"
         class="flex-shrink-0"
         style="display:none;margin:10px 16px 0;padding:9px 12px;border-radius:10px;font-size:12px;font-weight:700;"></div>

    <!-- Scrollable Body: Write RX -->
    <div id="rxBodyWrite" class="overflow-y-auto flex-1" style="padding:16px;display:flex;flex-direction:column;gap:14px;">

        <!-- ① Prescribing Entity -->
        <div style="background:#f5f3ff;border:1.5px solid #ede9fe;border-radius:14px;padding:14px;">
            <p style="font-size:10px;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.08em;margin:0 0 10px;">
                <i class="bi bi-building-fill" style="margin-right:4px;"></i>Prescribing Entity
            </p>
            <div style="display:flex;gap:8px;margin-bottom:10px;">
                <button type="button" id="rxCoBwc" onclick="rxSelectCompany('bwc')"
                        style="flex:1;padding:9px 12px;border-radius:10px;font-size:13px;font-weight:700;border:none;cursor:pointer;transition:all .2s;background:#7c3aed;color:#fff;box-shadow:0 2px 10px rgba(124,58,237,.4);">
                    <i class="bi bi-hospital-fill" style="margin-right:4px;"></i>BWC
                </button>
                <button type="button" id="rxCoVmp" onclick="rxSelectCompany('vmp')"
                        style="flex:1;padding:9px 12px;border-radius:10px;font-size:13px;font-weight:700;border:1.5px solid #ddd8fe;cursor:pointer;transition:all .2s;background:#fff;color:#6d28d9;">
                    <i class="bi bi-heart-pulse-fill" style="margin-right:4px;"></i>VMP
                </button>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                <i class="bi bi-geo-alt-fill" style="color:#a78bfa;font-size:11px;"></i>
                <span id="rxCoName" style="font-size:12px;font-weight:600;color:#6d28d9;">Beyond Wound Care Inc.</span>
            </div>
        </div>

        <!-- ② Date & Prescriber -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div>
                <label style="display:flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#64748b;margin-bottom:6px;">
                    <i class="bi bi-calendar3" style="color:#a78bfa;"></i> Date
                </label>
                <input id="rxDate" type="date" value="<?= date('Y-m-d') ?>"
                       style="width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:13px;color:#1e293b;background:#fff;box-sizing:border-box;outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#7c3aed';this.style.boxShadow='0 0 0 3px rgba(124,58,237,.12)'"
                       onblur="this.style.borderColor='#e2e8f0';this.style.boxShadow='none'">
            </div>
            <div>
                <label style="display:flex;align-items:center;justify-content:space-between;gap:5px;font-size:11px;font-weight:700;color:#64748b;margin-bottom:6px;">
                    <span style="display:flex;align-items:center;gap:5px;"><i class="bi bi-person-badge" style="color:#a78bfa;"></i> Prescriber</span>
                    <span id="rxSigStatusBadge" style="display:none;align-items:center;gap:4px;font-size:10px;font-weight:600;padding:2px 7px;border-radius:99px;background:#f1f5f9;color:#64748b;transition:all .2s;"></span>
                </label>
                <input id="rxPrescriber" type="text"
                       value="<?= htmlspecialchars($_rxProvider, ENT_QUOTES, 'UTF-8') ?>"
                       list="rxPrescriberList" autocomplete="off" placeholder="Select or type…"
                       style="width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:13px;color:#1e293b;background:#fff;box-sizing:border-box;outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#7c3aed';this.style.boxShadow='0 0 0 3px rgba(124,58,237,.12)'"
                       onblur="this.style.borderColor='#e2e8f0';this.style.boxShadow='none'"
                       oninput="rxLoadProviderSig(this.value)"
                       onchange="rxLoadProviderSig(this.value)">
                <datalist id="rxPrescriberList">
                    <?php foreach ($_rxProviders as $_rxPn): ?>
                    <option value="<?= htmlspecialchars($_rxPn, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
        </div>

        <!-- ③ Medications -->
        <div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <label style="display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.07em;">
                    <i class="bi bi-capsule-pill" style="color:#a78bfa;font-size:13px;"></i> Medications
                </label>
                <div style="display:flex;gap:6px;align-items:center;">
                    <button type="button" id="rxImportPfBtn" onclick="rxImportPfClick()"
                            style="display:inline-flex;align-items:center;gap:5px;padding:5px 10px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:8px;font-size:11px;font-weight:700;color:#15803d;cursor:pointer;transition:all .15s;"
                            onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='#f0fdf4'"
                            title="Upload a Practice Fusion patient summary PDF to auto-fill medications">
                        <i class="bi bi-file-earmark-arrow-up" style="font-size:12px;"></i> Import from PF
                    </button>
                    <button type="button" id="rxUseChartMedsBtn" onclick="rxUseChartMeds()"
                            style="display:inline-flex;align-items:center;gap:5px;padding:5px 10px;background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:8px;font-size:11px;font-weight:700;color:#1d4ed8;cursor:pointer;transition:all .15s;"
                            onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'"
                            title="Copy active chart medications into this RX">
                        <i class="bi bi-arrow-down-square" style="font-size:12px;"></i> Use Active Meds
                    </button>
                    <input type="file" id="rxPfFileInput" accept="application/pdf,.pdf" style="display:none;">
                    <button type="button" onclick="rxAddRow()"
                            style="display:flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;border:1.5px solid #ddd8fe;background:#faf8ff;color:#6d28d9;font-size:12px;font-weight:700;cursor:pointer;transition:all .15s;"
                            onmouseover="this.style.background='#ede9fe'"
                            onmouseout="this.style.background='#faf8ff'">
                        <i class="bi bi-plus-circle-fill" style="color:#7c3aed;"></i> Add Medication
                    </button>
                </div>
            </div>
            <div id="rxPfStatus" style="display:none;margin-bottom:8px;padding:8px 12px;border-radius:10px;font-size:12px;font-weight:600;"></div>
            <div id="rxMedRows" style="display:flex;flex-direction:column;gap:8px;"></div>
        </div>

        <!-- Handwritten medication note -->
        <?php
        $hwFieldName   = 'rx_med_handwriting';
        $hwFieldId     = 'rxMedHandwritingData';
        $hwLabel       = 'Handwrite Medications (stylus / draw)';
        $hwPlaceholder = 'Write medication names, doses &amp; frequencies with your stylus or finger&hellip;';
        $hwExisting    = '';
        include __DIR__ . '/handwriting_pad.php';
        ?>

        <!-- ④ Notes -->
        <div>
            <label style="display:flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#64748b;margin-bottom:6px;">
                <i class="bi bi-chat-square-text" style="color:#a78bfa;"></i> Notes / Instructions
            </label>
            <textarea id="rxNotes" rows="2" placeholder="Special instructions, substitution notes…"
                      style="width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:13px;color:#1e293b;background:#fff;box-sizing:border-box;outline:none;resize:none;font-family:inherit;transition:border-color .15s;"
                      onfocus="this.style.borderColor='#7c3aed';this.style.boxShadow='0 0 0 3px rgba(124,58,237,.12)'"
                      onblur="this.style.borderColor='#e2e8f0';this.style.boxShadow='none'"></textarea>
        </div>

    </div><!-- /rxBodyWrite -->

    <!-- Scrollable Body: Medications Tab -->
    <div id="rxBodyMeds" class="overflow-y-auto flex-1" style="padding:16px;display:none;flex-direction:column;">
        <div id="rxMedsListWrap">
            <?php if (empty($_rxActiveMeds)): ?>
            <div style="text-align:center;padding:32px 16px;color:#94a3b8;">
                <i class="bi bi-capsule" style="font-size:36px;opacity:.4;display:block;margin-bottom:10px;"></i>
                <p style="font-size:13px;margin:0;">No active medications on record</p>
            </div>
            <?php else: ?>
            <?php foreach ($_rxActiveMeds as $_rxMed): ?>
            <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;background:#f8f7ff;border:1.5px solid #ede9fe;border-radius:12px;margin-bottom:8px;">
                <div style="width:32px;height:32px;border-radius:8px;background:#7c3aed;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;">
                    <i class="bi bi-capsule-pill" style="color:#fff;font-size:14px;"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:13px;font-weight:700;color:#1e293b;line-height:1.3;"><?= htmlspecialchars($_rxMed['med_name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if (!empty($_rxMed['med_frequency'])): ?>
                    <div style="font-size:12px;color:#64748b;margin-top:2px;"><?= htmlspecialchars($_rxMed['med_frequency'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>
                <button type="button"
                        onclick="rxAddFromChart('<?= htmlspecialchars($_rxMed['med_name'], ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars($_rxMed['med_frequency'] ?? '', ENT_QUOTES, 'UTF-8') ?>')"
                        style="flex-shrink:0;display:inline-flex;align-items:center;gap:4px;padding:5px 9px;background:#eef2ff;border:1.5px solid #c7d2fe;border-radius:8px;font-size:11px;font-weight:700;color:#4338ca;cursor:pointer;transition:all .15s;"
                        onmouseover="this.style.background='#e0e7ff'" onmouseout="this.style.background='#eef2ff'">
                    <i class="bi bi-plus-circle"></i> Add
                </button>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <p style="font-size:11px;color:#94a3b8;text-align:center;margin-top:12px;flex-shrink:0;">
            <i class="bi bi-info-circle" style="margin-right:3px;"></i>Prescriptions saved to chart sync here automatically
        </p>
    </div><!-- /rxBodyMeds -->

    <!-- Scrollable Body: Diagnoses Tab -->
    <div id="rxBodyDx" class="overflow-y-auto flex-1" style="padding:16px;display:none;flex-direction:column;">
        <div id="rxDxListWrap">
            <?php if (empty($_rxDiagnoses)): ?>
            <div style="text-align:center;padding:32px 16px;color:#94a3b8;">
                <i class="bi bi-clipboard2-pulse" style="font-size:36px;opacity:.4;display:block;margin-bottom:10px;"></i>
                <p style="font-size:13px;margin:0;">No diagnoses on record</p>
            </div>
            <?php else: ?>
            <?php foreach ($_rxDiagnoses as $_rxDxItem): ?>
            <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;background:#f8f7ff;border:1.5px solid #ede9fe;border-radius:12px;margin-bottom:8px;">
                <div style="flex-shrink:0;padding:4px 8px;background:#7c3aed;border-radius:7px;margin-top:2px;">
                    <span style="font-size:11px;font-weight:700;color:#fff;font-family:monospace;"><?= htmlspecialchars($_rxDxItem['icd_code'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:13px;font-weight:700;color:#1e293b;line-height:1.3;"><?= htmlspecialchars($_rxDxItem['icd_desc'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if (!empty($_rxDxItem['notes'])): ?>
                    <div style="font-size:12px;color:#64748b;margin-top:2px;"><?= htmlspecialchars($_rxDxItem['notes'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <p style="font-size:11px;color:#94a3b8;text-align:center;margin-top:12px;flex-shrink:0;">
            <i class="bi bi-info-circle" style="margin-right:3px;"></i>ICD-10 diagnoses from the patient chart
        </p>
    </div><!-- /rxBodyDx -->

    <!-- Footer -->
    <div class="flex-shrink-0" style="padding:12px 16px;border-top:1px solid #f1f5f9;display:flex;gap:10px;background:#fafbff;">
        <button type="button" onclick="rxSave()" id="rxSaveBtn"
                style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;border-radius:12px;border:none;font-size:14px;font-weight:700;color:#fff;cursor:pointer;transition:all .2s;background:linear-gradient(135deg,#5b21b6,#7c3aed);box-shadow:0 4px 14px rgba(124,58,237,.4);">
            <i class="bi bi-floppy2-fill"></i> Save to Chart
        </button>
        <button type="button" onclick="rxPrint()"
                style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;transition:all .2s;background:#fff;border:2px solid #7c3aed;color:#6d28d9;"
                onmouseover="this.style.background='#f5f3ff'"
                onmouseout="this.style.background='#fff'">
            <i class="bi bi-printer-fill"></i> Print RX
        </button>
    </div>

</div><!-- /rxPadPanel -->


<!-- ═══════════════ PRINT LAYOUT (hidden, shown only on print) ═══════════ -->
<div id="rxPrintLayout" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:-9999;overflow:auto;background:#fff;color:#000;color-scheme:light;">
    <style>
        @media print {
            /* Force white canvas */
            html, body { background: white !important; color: black !important; margin: 0 !important; padding: 0 !important; }
            /* Strip all backgrounds so dark app theme doesn't bleed through */
            * { background: transparent !important; box-shadow: none !important; }
            /* Hide all page content — only when RX pad is the thing being printed */
            body.rx-printing * { visibility: hidden !important; }
            /* Show only the print layout — absolute so content paginates naturally without blank first page */
            body.rx-printing #rxPrintLayout {
                visibility: visible !important;
                display: block !important;
                position: absolute !important;
                top: 0 !important; left: 0 !important;
                width: 100% !important;
                height: auto !important;
                overflow: visible !important;
                background: white !important;
            }
            body.rx-printing #rxPrintLayout * { visibility: visible !important; }
            #rxPrintLayout .rxpl-page { background: white !important; }
            /* When NOT printing the RX pad, keep the layout hidden */
            body:not(.rx-printing) #rxPrintLayout { display: none !important; }
            #rxPadPanel, #rxPadOverlay { display: none !important; visibility: hidden !important; }
            .rxpl-page {
                width: 5.5in;
                height: auto;
                margin: 0 auto;
                font-family: Arial, sans-serif;
                padding: 0.5in;
                box-sizing: border-box;
                border: none;
            }
        }
        .rxpl-page {
            width: 5.5in;
            min-height: 8in;
            margin: 0 auto;
            font-family: Arial, sans-serif;
            padding: 0.5in;
            box-sizing: border-box;
            border: 1px solid #ccc;
        }
        /* Force light theme on the entire print layout */
        #rxPrintLayout { background: #fff !important; color: #000 !important; color-scheme: light !important; }
        #rxPrintLayout * { color-scheme: light !important; }
        @media print {
            #rxPrintLayout { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
        .rxpl-page {
            background: #fff !important;
            color: #000 !important;
        }
        .rxpl-header { border-bottom: 2px solid #6d28d9; padding-bottom: 10px; margin-bottom: 14px; }
        .rxpl-co-name { font-size: 16pt; font-weight: bold; color: #6d28d9 !important; }
        .rxpl-co-sub  { font-size: 9pt; color: #555 !important; }
        .rxpl-patient-row { display: flex; gap: 16px; margin-bottom: 10px; font-size: 10pt; }
        .rxpl-label   { font-size: 8pt; text-transform: uppercase; color: #999 !important; display: block; margin-bottom: 2px; }
        .rxpl-val     { font-size: 10pt; font-weight: 600; color: #000 !important; border-bottom: 1px solid #ccc; min-width: 120px; padding-bottom: 2px; }
        .rxpl-rx-mark { font-size: 28pt; font-weight: bold; color: #6d28d9 !important; line-height: 1; margin: 12px 0 6px; }
        .rxpl-med-row { border-bottom: 1px dashed #ddd; padding: 8px 0; }
        .rxpl-med-name { font-size: 12pt; font-weight: bold; color: #000 !important; }
        .rxpl-med-sig  { font-size: 10pt; color: #333 !important; margin-top: 2px; }
        .rxpl-med-qty  { font-size: 9pt; color: #555 !important; margin-top: 2px; }
        .rxpl-dx-section { margin-top: 14px; padding-top: 10px; border-top: 1px solid #e5e7eb; }
        .rxpl-dx-title  { font-size: 8pt; text-transform: uppercase; color: #6d28d9 !important; font-weight: 700; letter-spacing: .06em; margin-bottom: 6px; }
        .rxpl-dx-row    { display: flex; gap: 8px; margin-bottom: 4px; font-size: 9pt; }
        .rxpl-dx-code   { font-family: monospace; font-weight: 700; color: #6d28d9 !important; white-space: nowrap; flex-shrink: 0; }
        .rxpl-dx-desc   { color: #333 !important; }
        .rxpl-notes    { font-size: 9pt; color: #444 !important; margin-top: 12px; padding-top: 8px; border-top: 1px solid #ddd; }
        .rxpl-footer   { margin-top: 24px; padding-top: 12px; border-top: 2px solid #6d28d9; display: flex; justify-content: space-between; font-size: 9pt; }
        .rxpl-sig-line { border-bottom: 1px solid #000; width: 200px; margin-top: 24px; }
    </style>

    <div class="rxpl-page">
        <div class="rxpl-header">
            <div class="rxpl-co-name" id="rxplCoName"></div>
            <div class="rxpl-co-sub" id="rxplCoAddr"></div>
            <div class="rxpl-co-sub" id="rxplCoPhone"></div>
        </div>

        <div class="rxpl-patient-row">
            <div>
                <span class="rxpl-label">Patient</span>
                <span class="rxpl-val" id="rxplPatient"></span>
            </div>
            <div>
                <span class="rxpl-label">Date of Birth</span>
                <span class="rxpl-val" id="rxplDob"></span>
            </div>
            <div>
                <span class="rxpl-label">Date</span>
                <span class="rxpl-val" id="rxplDate"></span>
            </div>
        </div>

        <div class="rxpl-rx-mark">&#8478;</div>

        <div id="rxplMeds"></div>

        <div id="rxplHandwritingWrap" class="rxpl-notes" style="display:none;">
            <strong>Handwritten Medications:</strong>
            <div style="margin-top:8px;">
                <img id="rxplHandwritingImg" src="" alt="Handwritten medications"
                     style="display:block;max-width:100%;border:1px solid #ddd;border-radius:8px;background:#fff;">
            </div>
        </div>

        <?php if (!empty($_rxDiagnoses)): ?>
        <div class="rxpl-dx-section">
            <div class="rxpl-dx-title">Diagnoses (ICD-10)</div>
            <?php foreach ($_rxDiagnoses as $_rxPrintDx): ?>
            <div class="rxpl-dx-row">
                <span class="rxpl-dx-code"><?= htmlspecialchars($_rxPrintDx['icd_code'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="rxpl-dx-desc"><?= htmlspecialchars($_rxPrintDx['icd_desc'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div id="rxplNotesWrap" class="rxpl-notes">
            <strong>Instructions:</strong> <span id="rxplNotes"></span>
        </div>

        <div class="rxpl-footer">
            <div>
                <div id="rxplPrescriber" style="font-size:10pt;font-weight:600;margin-bottom:2px;"></div>
                <img id="rxplSigImg" src="" alt="Provider Signature"
                     style="display:none;max-height:52px;max-width:200px;margin-top:4px;object-fit:contain;">
                <div id="rxplSigLine" class="rxpl-sig-line"></div>
                <div style="font-size:8pt;margin-top:4px;color:#555;">Prescriber Signature &amp; Date</div>
            </div>
            <div style="text-align:right;font-size:8pt;color:#888;">
                <div>Substitution Permitted</div>
                <div style="margin-top:6px;">&#9744; Dispense as Written</div>
            </div>
        </div>
    </div>
</div><!-- /rxPrintLayout -->


<!-- ═══════════════ RX PAD: JavaScript ══════════════════════════════════ -->
<script>
(function () {
    var RX_PATIENT_ID = <?= (int)$patient_id ?>;
    var RX_CSRF       = <?= json_encode($_rxCsrf) ?>;
    var RX_COMPANIES  = <?= $_rxCompJson ?>;
    var rxCompany     = 'bwc';
    var rxRowIdx      = 0;
    var _rxProvSig    = '';
    var _rxSigTimer   = null;

    function rxShowStatus(msg, type) {
        var bar = document.getElementById('rxStatusBar');
        if (!bar) return;
        bar.textContent = msg;
        bar.style.display = 'block';
        if (type === 'ok') {
            bar.style.background = '#ecfdf3';
            bar.style.border = '1px solid #bbf7d0';
            bar.style.color = '#166534';
        } else if (type === 'err') {
            bar.style.background = '#fef2f2';
            bar.style.border = '1px solid #fecaca';
            bar.style.color = '#b91c1c';
        } else {
            bar.style.background = '#eff6ff';
            bar.style.border = '1px solid #bfdbfe';
            bar.style.color = '#1d4ed8';
        }
    }

    function rxHideStatus(delayMs) {
        var bar = document.getElementById('rxStatusBar');
        if (!bar) return;
        if (!delayMs) { bar.style.display = 'none'; return; }
        setTimeout(function () { bar.style.display = 'none'; }, delayMs);
    }

    /* ── auto-load provider signature by name ───────────────────── */
    window.rxLoadProviderSig = function (name) {
        clearTimeout(_rxSigTimer);
        _rxSigTimer = setTimeout(function () {
            name = (name || '').trim();
            if (name.length < 2) { _rxProvSig = ''; rxUpdateSigBadge(false); return; }
            fetch('<?= BASE_URL ?>/api/provider_sig.php?name=' + encodeURIComponent(name))
                .then(function (r) { return r.ok ? r.json() : { sig: null }; })
                .then(function (d) {
                    _rxProvSig = d.sig || '';
                    rxUpdateSigBadge(!!_rxProvSig);
                })
                .catch(function () { _rxProvSig = ''; });
        }, 400);
    };

    function rxUpdateSigBadge(hasSig) {
        var badge = document.getElementById('rxSigStatusBadge');
        if (!badge) return;
        if (hasSig) {
            badge.style.display = 'flex';
            badge.textContent = '\u2713 Auto-sign on';
            badge.style.background = '#d1fae5';
            badge.style.color = '#065f46';
        } else {
            badge.style.display = 'flex';
            badge.textContent = 'No saved signature';
            badge.style.background = '#f1f5f9';
            badge.style.color = '#64748b';
        }
    }

    /* ── open / close ──────────────────────────────────────────────── */
    window.rxPadOpen = function () {
        document.getElementById('rxPadOverlay').classList.remove('hidden');
        document.getElementById('rxPadPanel').style.transform = 'translateY(0)';
        rxHideStatus();
        // Add at least one row if none exist
        if (document.getElementById('rxMedRows').children.length === 0) rxAddRow();
        // Auto-load provider signature for pre-filled prescriber
        var presEl = document.getElementById('rxPrescriber');
        if (presEl && presEl.value.trim()) rxLoadProviderSig(presEl.value);
    };

    window.rxPadClose = function () {
        document.getElementById('rxPadPanel').style.transform = 'translateY(calc(100% + 2rem))';
        document.getElementById('rxPadOverlay').classList.add('hidden');
    };

    /* ── company selector ──────────────────────────────────────────── */
    window.rxSelectCompany = function (slug) {
        rxCompany = slug;
        var bwcBtn = document.getElementById('rxCoBwc');
        var vmpBtn = document.getElementById('rxCoVmp');
        var activeS  = 'flex:1;padding:9px 12px;border-radius:10px;font-size:13px;font-weight:700;border:none;cursor:pointer;transition:all .2s;background:#7c3aed;color:#fff;box-shadow:0 2px 10px rgba(124,58,237,.4);';
        var inactS   = 'flex:1;padding:9px 12px;border-radius:10px;font-size:13px;font-weight:700;border:1.5px solid #ddd8fe;cursor:pointer;transition:all .2s;background:#fff;color:#6d28d9;box-shadow:none;';
        bwcBtn.setAttribute('style', slug === 'bwc' ? activeS : inactS);
        vmpBtn.setAttribute('style', slug === 'vmp' ? activeS : inactS);
        var co = RX_COMPANIES[slug] || {};
        document.getElementById('rxCoName').textContent = co.name || '';
    };

    /* ── add / remove med rows ─────────────────────────────────────── */
    window.rxAddRow = function (prefill) {
        var idx    = rxRowIdx++;
        var rowNum = document.querySelectorAll('.rxMedRow').length + 1;
        var wrap   = document.createElement('div');
        wrap.className   = 'rxMedRow';
        wrap.dataset.idx = idx;
        wrap.style.cssText = 'background:#faf8ff;border:1.5px solid #ede9fe;border-radius:14px;padding:12px;';
        var iStyle = 'width:100%;border:1.5px solid #ddd8fe;border-radius:10px;padding:9px 12px;font-size:13px;color:#1e293b;background:#fff;box-sizing:border-box;outline:none;font-family:inherit;transition:border-color .15s;';
        var iFocus = "this.style.borderColor='#7c3aed';this.style.boxShadow='0 0 0 3px rgba(124,58,237,.12)'";
        var iBlur  = "this.style.borderColor='#ddd8fe';this.style.boxShadow='none'";
        wrap.innerHTML =
            '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">' +
                '<span class="rx-row-num" style="display:inline-flex;align-items:center;justify-content:center;' +
                    'width:22px;height:22px;border-radius:50%;background:#7c3aed;color:#fff;font-size:11px;font-weight:700;flex-shrink:0;">' +
                    rowNum +
                '</span>' +
                '<button type="button" onclick="rxRemoveRow(this)" title="Remove medication"' +
                    ' style="width:26px;height:26px;display:flex;align-items:center;justify-content:center;border-radius:50%;border:none;background:transparent;cursor:pointer;color:#94a3b8;"' +
                    ' onmouseover="this.style.background=\'#fee2e2\';this.style.color=\'#ef4444\'"' +
                    ' onmouseout="this.style.background=\'transparent\';this.style.color=\'#94a3b8\'">' +
                    '<i class="bi bi-trash3" style="font-size:12px;"></i>' +
                '</button>' +
            '</div>' +
            '<input type="text" name="rx_name_' + idx + '" placeholder="Drug name & dose" autocomplete="off"' +
            ' style="' + iStyle + 'margin-bottom:6px;"' +
            ' onfocus="' + iFocus + '" onblur="' + iBlur + '">' +
            '<input type="text" name="rx_freq_' + idx + '" placeholder="Sig / Frequency (e.g. Take 1 tablet twice daily)" autocomplete="off"' +
            ' style="' + iStyle + 'margin-bottom:6px;"' +
            ' onfocus="' + iFocus + '" onblur="' + iBlur + '">' +
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">' +
                '<div>' +
                    '<div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Qty</div>' +
                    '<input type="text" name="rx_qty_' + idx + '" placeholder="e.g. 30" autocomplete="off" maxlength="8"' +
                    ' style="' + iStyle + '"' +
                    ' onfocus="' + iFocus + '" onblur="' + iBlur + '">' +
                '</div>' +
                '<div>' +
                    '<div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Refills</div>' +
                    '<input type="text" name="rx_ref_' + idx + '" placeholder="0" autocomplete="off" maxlength="4"' +
                    ' style="' + iStyle + '"' +
                    ' onfocus="' + iFocus + '" onblur="' + iBlur + '">' +
                '</div>' +
            '</div>';
        document.getElementById('rxMedRows').appendChild(wrap);
        var nameEl = wrap.querySelector('[name="rx_name_' + idx + '"]');
        var freqEl = wrap.querySelector('[name="rx_freq_' + idx + '"]');
        var qtyEl  = wrap.querySelector('[name="rx_qty_' + idx + '"]');
        var refEl  = wrap.querySelector('[name="rx_ref_' + idx + '"]');
        if (prefill && typeof prefill === 'object') {
            if (nameEl) nameEl.value = prefill.name || '';
            if (freqEl) freqEl.value = prefill.frequency || '';
            if (qtyEl)  qtyEl.value  = prefill.qty || '';
            if (refEl)  refEl.value  = prefill.refills || '';
        }
        if (nameEl) nameEl.focus();
    };

    window.rxAddFromChart = function (name, freq) {
        rxSwitchTab('write');
        rxAddRow({name: name || '', frequency: freq || ''});
        rxShowStatus('Medication added to Write RX.', 'ok');
        rxHideStatus(1800);
    };

    window.rxUseChartMeds = function () {
        var btn = document.getElementById('rxUseChartMedsBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split" style="font-size:12px;"></i> Loading...';
        }
        fetch('<?= BASE_URL ?>/api/meds.php?action=list&patient_id=' + RX_PATIENT_ID)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok || !data.meds || !data.meds.length) {
                    rxShowStatus('No active medications found in chart.', 'err');
                    rxHideStatus(2400);
                    return;
                }
                var rowsEl = document.getElementById('rxMedRows');
                rowsEl.innerHTML = '';
                rxRowIdx = 0;
                data.meds.forEach(function (m) {
                    rxAddRow({
                        name: m.med_name || '',
                        frequency: m.med_frequency || '',
                        qty: '',
                        refills: ''
                    });
                });
                rxShowStatus(data.meds.length + ' medication' + (data.meds.length !== 1 ? 's' : '') + ' loaded from chart.', 'ok');
                rxHideStatus(2200);
            })
            .catch(function () {
                rxShowStatus('Could not load chart medications.', 'err');
                rxHideStatus(2400);
            })
            .finally(function () {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-down-square" style="font-size:12px;"></i> Use Active Meds';
                }
            });
    };

    window.rxRemoveRow = function (btn) {
        var row = btn.closest('.rxMedRow');
        if (row) { row.remove(); rxRenumberRows(); }
    };

    function rxRenumberRows() {
        document.querySelectorAll('.rxMedRow .rx-row-num').forEach(function (badge, i) {
            badge.textContent = i + 1;
        });
    }

    /* ── collect rows ──────────────────────────────────────────────── */
    function rxCollectMeds() {
        var meds = [];
        document.querySelectorAll('.rxMedRow').forEach(function (row) {
            var idx = row.dataset.idx;
            var name = (row.querySelector('[name="rx_name_' + idx + '"]') || {}).value || '';
            var freq = (row.querySelector('[name="rx_freq_' + idx + '"]') || {}).value || '';
            var qty  = (row.querySelector('[name="rx_qty_'  + idx + '"]') || {}).value || '';
            var ref  = (row.querySelector('[name="rx_ref_'  + idx + '"]') || {}).value || '';
            if (name.trim()) {
                meds.push({ name: name.trim(), dosage: '', frequency: freq.trim(), qty: qty.trim(), refills: ref.trim() });
            }
        });
        return meds;
    }

    /* ── save to chart ─────────────────────────────────────────────── */
    window.rxSave = function () {
        var meds = rxCollectMeds();
        if (!meds.length) {
            rxShowStatus('Please enter at least one medication before saving.', 'err');
            return;
        }
        var prescriber = (document.getElementById('rxPrescriber').value || '').trim();
        if (!prescriber) {
            rxShowStatus('Prescriber is required before saving.', 'err');
            document.getElementById('rxPrescriber').focus();
            return;
        }
        var rxDate = document.getElementById('rxDate').value || '';
        if (!rxDate) {
            rxShowStatus('Date is required before saving.', 'err');
            document.getElementById('rxDate').focus();
            return;
        }
        var btn = document.getElementById('rxSaveBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';
        rxShowStatus('Saving prescription to chart...', 'info');

        fetch('<?= BASE_URL ?>/api/save_rx.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf:       RX_CSRF,
                patient_id: RX_PATIENT_ID,
                company:    rxCompany,
                prescriber: prescriber,
                date:       rxDate,
                notes:      (document.getElementById('rxNotes').value || '').trim(),
                meds:       meds,
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.ok) {
                btn.innerHTML = '<i class="bi bi-check2-circle"></i> Saved!';
                btn.style.background = 'linear-gradient(135deg,#15803d,#16a34a)';
                rxShowStatus('Prescription saved successfully.', 'ok');
                setTimeout(function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-floppy2-fill"></i> Save to Chart';
                    btn.style.background = 'linear-gradient(135deg,#5b21b6,#7c3aed)';
                    rxSwitchTab('meds'); // Switch to meds tab to show the updated list
                    rxHideStatus(1200);
                }, 1600);
            } else {
                rxShowStatus('Error saving: ' + (data.error || 'Unknown error'), 'err');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-floppy2-fill"></i> Save to Chart';
            }
        })
        .catch(function () {
            rxShowStatus('Network error while saving. Please try again.', 'err');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-floppy2-fill"></i> Save to Chart';
        });
    };

    /* ── print RX ──────────────────────────────────────────────────── */
    function rxDoPrint(meds) {
        var co   = RX_COMPANIES[rxCompany] || {};
        var date = document.getElementById('rxDate').value || '';
        var dParts = date.split('-');
        var dDisplay = dParts.length === 3
            ? (parseInt(dParts[1], 10) + '/' + parseInt(dParts[2], 10) + '/' + dParts[0])
            : date;

        // Populate print layout
        document.getElementById('rxplCoName').textContent  = co.name    || '';
        document.getElementById('rxplCoAddr').textContent  = (co.address || '') + (co.city ? ', ' + co.city : '');
        var phoneLine = co.phone || '';
        if (co.fax) phoneLine += '  |  Fax: ' + co.fax;
        if (co.email) phoneLine += (phoneLine ? '  |  ' : '') + co.email;
        document.getElementById('rxplCoPhone').textContent = phoneLine;
        document.getElementById('rxplPatient').textContent    = <?= json_encode(html_entity_decode($_rxPatientName, ENT_QUOTES, 'UTF-8')) ?>;
        var rawDob = '<?= $_rxPatientDob ?>';
        var dobParts = rawDob.split('-');
        document.getElementById('rxplDob').textContent = dobParts.length === 3
            ? (parseInt(dobParts[1], 10) + '/' + parseInt(dobParts[2], 10) + '/' + dobParts[0])
            : rawDob;
        document.getElementById('rxplDate').textContent       = dDisplay;
        document.getElementById('rxplPrescriber').textContent = document.getElementById('rxPrescriber').value || '';

        // Provider signature
        var sigImg  = document.getElementById('rxplSigImg');
        var sigLine = document.getElementById('rxplSigLine');
        if (_rxProvSig) {
            sigImg.src = _rxProvSig;
            sigImg.style.display = 'block';
            sigLine.style.display = 'none';
        } else {
            sigImg.src = '';
            sigImg.style.display = 'none';
            sigLine.style.display = '';
        }

        // Medications
        var medsHtml = '';
        meds.forEach(function (m, i) {
            medsHtml +=
                '<div class="rxpl-med-row">' +
                    '<div class="rxpl-med-name">' + (i + 1) + '. ' + escHtml(m.name) + '</div>' +
                    (m.frequency ? '<div class="rxpl-med-sig">Sig: ' + escHtml(m.frequency) + '</div>' : '') +
                    (m.qty ? '<div class="rxpl-med-qty">Qty: ' + escHtml(m.qty) +
                        (m.refills ? '  &bull;  Refills: ' + escHtml(m.refills) : '') + '</div>' : '') +
                '</div>';
        });
        document.getElementById('rxplMeds').innerHTML = medsHtml;

        var hwInput = document.getElementById('rxMedHandwritingData');
        var hwWrap  = document.getElementById('rxplHandwritingWrap');
        var hwImg   = document.getElementById('rxplHandwritingImg');
        var hwValue = hwInput ? (hwInput.value || '').trim() : '';
        if (hwValue) {
            hwImg.src = hwValue;
            hwWrap.style.display = '';
        } else {
            hwImg.src = '';
            hwWrap.style.display = 'none';
        }

        var notes = (document.getElementById('rxNotes').value || '').trim();
        document.getElementById('rxplNotesWrap').style.display = notes ? '' : 'none';
        document.getElementById('rxplNotes').textContent = notes;

        // Show print layout, trigger print, then restore
        var printEl  = document.getElementById('rxPrintLayout');
        var panelEl  = document.getElementById('rxPadPanel');
        var overlayEl = document.getElementById('rxPadOverlay');
        printEl.style.zIndex = '99999';
        printEl.style.display = 'block';
        // Slide panel out of the way so it doesn't obscure print preview
        if (panelEl)  panelEl.style.transform  = 'translateY(calc(100% + 2rem))';
        if (overlayEl) overlayEl.style.display  = 'none';
        document.body.classList.add('rx-printing');
        var _rxCleanup = function () {
            printEl.style.display = 'none';
            printEl.style.zIndex = '-9999';
            // Restore panel
            if (panelEl)  panelEl.style.transform  = 'translateY(0)';
            if (overlayEl) overlayEl.style.display = '';
            document.body.classList.remove('rx-printing');
            clearTimeout(_rxFallback);
        };
        var _rxFallback = setTimeout(_rxCleanup, 60000);
        window.addEventListener('afterprint', _rxCleanup, { once: true });
        window.print();
    }

    window.rxPrint = function () {
        var meds     = rxCollectMeds();
        var presName = (document.getElementById('rxPrescriber').value || '').trim();

        function doPrintWithMeds(medsArr) {
            // Ensure provider signature is fetched before printing
            if (presName && !_rxProvSig) {
                clearTimeout(_rxSigTimer);
                fetch('<?= BASE_URL ?>/api/provider_sig.php?name=' + encodeURIComponent(presName))
                    .then(function (r) { return r.ok ? r.json() : { sig: null }; })
                    .then(function (d) { _rxProvSig = d.sig || ''; rxUpdateSigBadge(!!_rxProvSig); rxDoPrint(medsArr); })
                    .catch(function () { rxDoPrint(medsArr); });
            } else {
                rxDoPrint(medsArr);
            }
        }

        if (meds.length) {
            doPrintWithMeds(meds);
            return;
        }
        // No meds entered in Write RX tab — fall back to chart medications
        fetch('<?= BASE_URL ?>/api/meds.php?action=list&patient_id=' + RX_PATIENT_ID)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok && data.meds && data.meds.length) {
                    var chartMeds = data.meds.map(function (m) {
                        return { name: m.med_name || '', frequency: m.med_frequency || '', qty: '', refills: '' };
                    });
                    rxShowStatus('Printing using active chart medications.', 'info');
                    rxHideStatus(1800);
                    doPrintWithMeds(chartMeds);
                } else {
                    rxShowStatus('No medications found. Add at least one medication before printing.', 'err');
                }
            })
            .catch(function () {
                rxShowStatus('Could not load chart medications. Please enter them manually.', 'err');
            });
    };

    /* ── tab switching ───────────────────────────────────────────── */
    window.rxSwitchTab = function (tab) {
        var writeBody = document.getElementById('rxBodyWrite');
        var medsBody  = document.getElementById('rxBodyMeds');
        var dxBody    = document.getElementById('rxBodyDx');
        var writeBtn  = document.getElementById('rxTabBtnWrite');
        var medsBtn   = document.getElementById('rxTabBtnMeds');
        var dxBtn     = document.getElementById('rxTabBtnDx');
        // Hide all, reset all
        [writeBody, medsBody, dxBody].forEach(function(b) { if (b) b.style.display = 'none'; });
        [writeBtn, medsBtn, dxBtn].forEach(function(b) { if (b) { b.style.color = '#94a3b8'; b.style.borderBottomColor = 'transparent'; b.style.fontWeight = '600'; } });
        if (tab === 'meds') {
            medsBody.style.display = 'flex';
            medsBtn.style.color = '#6d28d9'; medsBtn.style.borderBottomColor = '#7c3aed'; medsBtn.style.fontWeight = '700';
            rxRefreshMedsList();
        } else if (tab === 'dx') {
            dxBody.style.display = 'flex';
            dxBtn.style.color = '#6d28d9'; dxBtn.style.borderBottomColor = '#7c3aed'; dxBtn.style.fontWeight = '700';
        } else {
            writeBody.style.display = 'flex';
            writeBtn.style.color = '#6d28d9'; writeBtn.style.borderBottomColor = '#7c3aed'; writeBtn.style.fontWeight = '700';
        }
    };

    /* ── refresh medications list via AJAX ───────────────────────── */
    function rxRefreshMedsList() {
        var wrap = document.getElementById('rxMedsListWrap');
        if (!wrap) return;
        wrap.innerHTML = '<p style="text-align:center;color:#94a3b8;padding:20px;font-size:13px;"><i class="bi bi-hourglass-split"></i> Loading\u2026</p>';
        fetch('<?= BASE_URL ?>/api/meds.php?action=list&patient_id=' + RX_PATIENT_ID)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var badge = document.getElementById('rxMedsBadge');
                if (!data.ok || !data.meds || !data.meds.length) {
                    wrap.innerHTML = '<div style="text-align:center;padding:32px 16px;color:#94a3b8;"><i class="bi bi-capsule" style="font-size:36px;opacity:.4;display:block;margin-bottom:10px;"></i><p style="font-size:13px;margin:0;">No active medications on record</p></div>';
                    if (badge) badge.textContent = '0';
                    return;
                }
                if (badge) badge.textContent = data.meds.length;
                wrap.innerHTML = data.meds.map(function (m) {
                    return '<div style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;background:#f8f7ff;border:1.5px solid #ede9fe;border-radius:12px;margin-bottom:8px;">'
                        + '<div style="width:32px;height:32px;border-radius:8px;background:#7c3aed;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;"><i class="bi bi-capsule-pill" style="color:#fff;font-size:14px;"></i></div>'
                        + '<div style="flex:1;min-width:0;"><div style="font-size:13px;font-weight:700;color:#1e293b;line-height:1.3;">' + escHtml(m.med_name) + '</div>'
                        + (m.med_frequency ? '<div style="font-size:12px;color:#64748b;margin-top:2px;">' + escHtml(m.med_frequency) + '</div>' : '')
                        + '</div></div>';
                }).join('');
            })
            .catch(function () {
                wrap.innerHTML = '<p style="color:#ef4444;font-size:13px;text-align:center;padding:16px;">Could not load medications</p>';
            });
    }

    function escHtml(str) {
        return (str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ── Practice Fusion PDF import ─────────────────────────────────── */
    window.rxImportPfClick = function () {
        var fi = document.getElementById('rxPfFileInput');
        if (fi) fi.click();
    };

    (function () {
        var fi     = document.getElementById('rxPfFileInput');
        var btn    = document.getElementById('rxImportPfBtn');
        var status = document.getElementById('rxPfStatus');

        function showStatus(msg, type) {
            status.textContent = msg;
            status.style.display = 'block';
            if (type === 'ok') {
                status.style.background = '#f0fdf4'; status.style.color = '#15803d'; status.style.border = '1px solid #bbf7d0';
            } else if (type === 'err') {
                status.style.background = '#fef2f2'; status.style.color = '#b91c1c'; status.style.border = '1px solid #fecaca';
            } else {
                status.style.background = '#f8fafc'; status.style.color = '#475569'; status.style.border = '1px solid #e2e8f0';
            }
        }

        fi && fi.addEventListener('change', function () {
            var file = this.files && this.files[0];
            if (!file) return;
            this.value = ''; // reset so same file can be re-selected

            if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
                showStatus('Please select a PDF file.', 'err');
                return;
            }
            if (file.size > 20 * 1024 * 1024) {
                showStatus('File too large (max 20 MB).', 'err');
                return;
            }

            // Update button to loading state
            var origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split" style="font-size:12px;"></i> Parsing\u2026';
            showStatus('\u23f3 Extracting medications from PDF\u2026', 'info');

            var fd = new FormData();
            fd.append('csrf', RX_CSRF);
            fd.append('patient_id', RX_PATIENT_ID);
            fd.append('pdf', file);

            fetch('<?= BASE_URL ?>/api/import_pf_pdf.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    if (!data.ok) {
                        showStatus('\u2717 ' + (data.error || 'Import failed'), 'err');
                        return;
                    }
                    var meds = data.meds || [];
                    if (!meds.length) {
                        showStatus('\u26a0 No medications found in PDF. Try a different export or add them manually.', 'err');
                        return;
                    }

                    // Clear existing rows and populate with parsed meds
                    var rowsEl = document.getElementById('rxMedRows');
                    rowsEl.innerHTML = '';
                    rxRowIdx = 0;

                    meds.forEach(function (m) {
                        rxAddRow();
                        var idx = rxRowIdx; // rxAddRow increments, last used index
                        // Find the last added row
                        var rows = rowsEl.querySelectorAll('.rxMedRow');
                        var row  = rows[rows.length - 1];
                        if (!row) return;
                        var ri = row.dataset.idx;
                        var nameEl = row.querySelector('[name="rx_name_' + ri + '"]');
                        var freqEl = row.querySelector('[name="rx_freq_' + ri + '"]');
                        var qtyEl  = row.querySelector('[name="rx_qty_'  + ri + '"]');
                        var refEl  = row.querySelector('[name="rx_ref_'  + ri + '"]');
                        if (nameEl) nameEl.value = m.name      || '';
                        if (freqEl) freqEl.value = m.frequency || '';
                        if (qtyEl)  qtyEl.value  = m.qty       || '';
                        if (refEl)  refEl.value  = m.refills   || '';
                    });

                    showStatus('\u2713 ' + meds.length + ' medication' + (meds.length !== 1 ? 's' : '') + ' imported from Practice Fusion PDF.', 'ok');
                    rxShowStatus(meds.length + ' medication' + (meds.length !== 1 ? 's were' : ' was') + ' imported from PF PDF.', 'ok');
                    rxHideStatus(2200);
                    setTimeout(function () { status.style.display = 'none'; }, 6000);
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    showStatus('\u2717 Network error \u2014 please try again.', 'err');
                    rxShowStatus('PF import failed due to network error.', 'err');
                });
        });
    })();

    /* ── keyboard shortcut (Escape to close) ──────────────────────── */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') rxPadClose();
    });

})();
</script>
