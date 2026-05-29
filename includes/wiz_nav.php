<?php
/**
 * includes/wiz_nav.php
 * Drop this at the bottom of any wizard form (inside the card, after last .wiz-step).
 * Expects: $accentClass (e.g. 'bg-red-700 hover:bg-red-800'), $cancelUrl
 * Optional: $endVisitId (int) — renders an End Visit button on the last step
 */
$accentClass ??= 'bg-blue-600 hover:bg-blue-700';
$cancelUrl   ??= BASE_URL . '/patients.php';
$endVisitId  ??= 0;
?>
<div id="wiz-nav" class="no-select">
    <!-- Back -->
    <button type="button" id="wiz-back"
            class="hidden flex items-center gap-2 px-6 py-3 bg-white border border-slate-200
                   hover:bg-slate-50 text-slate-600 font-semibold rounded-xl transition-colors text-sm">
        <i class="bi bi-arrow-left"></i> Back
    </button>

    <div class="flex items-center gap-3 ml-auto">
        <!-- Next (hidden on last step) -->
        <button type="button" id="wiz-next"
                class="flex items-center gap-2 px-7 py-3 <?= $accentClass ?>
                       text-white font-bold rounded-xl transition-all shadow-md hover:shadow-lg text-sm active:scale-95">
            Next <i class="bi bi-arrow-right"></i>
        </button>

        <!-- Submit (only visible on last step, hidden by JS) -->
        <button type="button" id="submitBtn"
                class="hidden flex items-center justify-center gap-2 px-8 py-3 <?= $accentClass ?>
                       text-white font-bold rounded-xl transition-all shadow-md hover:shadow-lg text-base active:scale-95 flex-1 sm:flex-none">
            <i class="bi bi-stop-circle-fill text-xl"></i> End Visit
        </button>

    </div>
</div>

<?php if ($endVisitId > 0): ?>
<!-- ── End Visit Confirmation Modal ─────────────────────────────────────── -->
<style>@media print{#endVisitModal{display:none!important}}</style>
<div id="endVisitModal"
     style="display:none;position:fixed;inset:0;z-index:9999;
            align-items:flex-end;justify-content:center;">
    <!-- Backdrop -->
    <div onclick="endVisitModalClose()"
         style="position:absolute;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);"></div>
    <!-- Card -->
    <div id="endVisitModalCard"
         style="position:relative;background:#fff;border-radius:1.25rem 1.25rem 0 0;
                width:100%;max-width:26rem;
                box-shadow:0 -8px 48px rgba(0,0,0,.22);
                padding:1.5rem;
                padding-bottom:calc(1.5rem + env(safe-area-inset-bottom));
                display:flex;flex-direction:column;gap:1.25rem;
                transform:translateY(100%);transition:transform .3s cubic-bezier(.32,.72,0,1);">

        <!-- Header -->
        <div style="display:flex;align-items:center;gap:.75rem;">
            <div style="width:2.5rem;height:2.5rem;border-radius:.75rem;background:#fee2e2;
                        display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-stop-circle-fill" style="color:#dc2626;font-size:1.125rem;line-height:1;"></i>
            </div>
            <div style="min-width:0;">
                <p style="font-weight:700;color:#1e293b;font-size:1rem;margin:0;line-height:1.2;">End Visit</p>
                <p style="font-size:.75rem;color:#94a3b8;margin:.2rem 0 0;line-height:1.3;">All data will be saved and the visit closed.</p>
            </div>
            <button type="button" onclick="endVisitModalClose()"
                    style="margin-left:auto;flex-shrink:0;width:2rem;height:2rem;border:none;background:none;cursor:pointer;
                           color:#94a3b8;border-radius:.5rem;display:flex;align-items:center;justify-content:center;
                           transition:background .15s,color .15s;"
                    onmouseover="this.style.background='#f1f5f9';this.style.color='#334155'"
                    onmouseout="this.style.background='none';this.style.color='#94a3b8'">
                <i class="bi bi-x-lg" style="font-size:.9rem;line-height:1;"></i>
            </button>
        </div>

        <!-- Time Out (read-only display) -->
        <div>
            <label style="display:block;font-size:.7rem;font-weight:700;color:#475569;
                          margin-bottom:.375rem;text-transform:uppercase;letter-spacing:.06em;">Time Out</label>
            <div style="display:flex;align-items:center;gap:.5rem;padding:.75rem 1rem;
                        border:1px solid #e2e8f0;background:#f8fafc;border-radius:.75rem;
                        font-size:.875rem;color:#334155;">
                <i class="bi bi-clock" style="color:#94a3b8;flex-shrink:0;"></i>
                <span id="evTimeDisplay" style="font-weight:600;"></span>
                <span style="color:#94a3b8;font-size:.75rem;margin-left:auto;">auto-stamped</span>
            </div>
        </div>

        <!-- Follow-Up In -->
        <div>
            <label style="display:block;font-size:.7rem;font-weight:700;color:#475569;
                          margin-bottom:.375rem;text-transform:uppercase;letter-spacing:.06em;">
                Follow-Up In
                <span style="font-weight:400;text-transform:none;color:#94a3b8;letter-spacing:0;">(optional)</span>
            </label>
            <div style="display:flex;gap:.5rem;">
                <input type="number" id="evFuWeeks" min="1" placeholder="e.g. 2"
                       style="flex:1;padding:.75rem 1rem;border:1px solid #e2e8f0;border-radius:.75rem;
                              font-size:.875rem;background:#fff;outline:none;
                              transition:border-color .15s,box-shadow .15s;"
                       onfocus="this.style.borderColor='#f87171';this.style.boxShadow='0 0 0 3px rgba(239,68,68,.15)'"
                       onblur="this.style.borderColor='#e2e8f0';this.style.boxShadow='none'">
                <select id="evFuUnit"
                        style="padding:.75rem .875rem;border:1px solid #e2e8f0;border-radius:.75rem;
                               font-size:.875rem;background:#fff;outline:none;cursor:pointer;
                               transition:border-color .15s,box-shadow .15s;"
                        onfocus="this.style.borderColor='#f87171';this.style.boxShadow='0 0 0 3px rgba(239,68,68,.15)'"
                        onblur="this.style.borderColor='#e2e8f0';this.style.boxShadow='none'">
                    <option value="weeks">Weeks</option>
                    <option value="days">Days</option>
                </select>
            </div>
        </div>

        <!-- Actions -->
        <div style="display:flex;gap:.75rem;padding-top:.125rem;">
            <button type="button" id="evConfirmBtn" onclick="endVisitConfirm()"
                    style="flex:1;padding:.75rem;background:#dc2626;color:#fff;font-weight:700;
                           font-size:.875rem;border:none;border-radius:.75rem;cursor:pointer;
                           box-shadow:0 1px 4px rgba(0,0,0,.14);
                           transition:background .15s,transform .1s;
                           display:flex;align-items:center;justify-content:center;gap:.35rem;"
                    onmouseover="this.style.background='#b91c1c'"
                    onmouseout="this.style.background='#dc2626'"
                    onmousedown="this.style.transform='scale(.97)'"
                    onmouseup="this.style.transform='scale(1)'">
                <i class="bi bi-stop-circle-fill"></i> Confirm End Visit
            </button>
            <button type="button" onclick="endVisitModalClose()"
                    style="padding:.75rem 1.25rem;background:#f1f5f9;color:#475569;font-weight:600;
                           font-size:.875rem;border:none;border-radius:.75rem;cursor:pointer;
                           transition:background .15s;"
                    onmouseover="this.style.background='#e2e8f0'"
                    onmouseout="this.style.background='#f1f5f9'">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var _confirmed = false;

    function currentTzHHMM() {
        var tz = (window._pdTimezone && window._pdTimezone !== '') ? window._pdTimezone : undefined;
        try {
            var parts = new Intl.DateTimeFormat('en-US', {
                timeZone: tz, hour: '2-digit', minute: '2-digit', hour12: false
            }).formatToParts(new Date());
            var h = '', m = '';
            parts.forEach(function (p) {
                if (p.type === 'hour')   h = p.value;
                if (p.type === 'minute') m = p.value;
            });
            if (h === '24') h = '00'; // Intl midnight edge-case
            return h.padStart(2, '0') + ':' + m.padStart(2, '0');
        } catch (e) {
            var now = new Date();
            return String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        }
    }

    window.endVisitModalClose = function () {
        var card  = document.getElementById('endVisitModalCard');
        var modal = document.getElementById('endVisitModal');
        card.style.transform = 'translateY(100%)';
        setTimeout(function () { modal.style.display = 'none'; }, 280);
    };

    function openEndVisitModal() {
        var modal = document.getElementById('endVisitModal');
        var card  = document.getElementById('endVisitModalCard');

        // Stamp current time in the app timezone
        var timeStr = currentTzHHMM(); // HH:MM (24h) for DB storage
        var timeOut = document.querySelector('input[name="time_out"]');
        if (timeOut) timeOut.value = timeStr;

        // Display in 12-hour format
        var tz = (window._pdTimezone && window._pdTimezone !== '') ? window._pdTimezone : undefined;
        var dispStr = timeStr;
        try {
            dispStr = new Intl.DateTimeFormat('en-US', {
                timeZone: tz, hour: 'numeric', minute: '2-digit', hour12: true
            }).format(new Date());
        } catch (e) {}
        var disp = document.getElementById('evTimeDisplay');
        if (disp) disp.textContent = dispStr;

        // Pre-fill F/U from hidden inputs if present (vital_cs etc.)
        var fuW = document.getElementById('fuWeeksHidden');
        var fuU = document.getElementById('fuUnitHidden');
        var evW = document.getElementById('evFuWeeks');
        var evU = document.getElementById('evFuUnit');
        if (evW && fuW && fuW.value) evW.value = fuW.value;
        if (evU && fuU) evU.value = fuU.value || 'weeks';

        modal.style.display = 'flex';
        card.style.transform = 'translateY(100%)';
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                card.style.transform = 'translateY(0)';
            });
        });
    }

    window.endVisitConfirm = function () {
        var btn = document.getElementById('evConfirmBtn');
        var fuW = document.getElementById('fuWeeksHidden');
        var fuU = document.getElementById('fuUnitHidden');
        var evW = document.getElementById('evFuWeeks');
        var evU = document.getElementById('evFuUnit');
        if (fuW && evW) fuW.value = evW.value;
        if (fuU && evU) fuU.value = evU.value;

        endVisitModalClose();
        _confirmed = true;
        btn.disabled = true;
        setTimeout(function () {
            var sb = document.getElementById('submitBtn');
            if (sb) sb.click();
        }, 320);
    };

    window._pdEndVisitGate = function () {
        if (_confirmed) return true;
        openEndVisitModal();
        return false;
    };

    window._pdValidateExtra = function () {
        return window._pdEndVisitGate();
    };

    // Teleport to <body> to escape overflow:hidden / stacking-context constraints.
    (function () {
        function doTeleport() {
            var m = document.getElementById('endVisitModal');
            if (m && m.parentElement !== document.body) document.body.appendChild(m);
        }
        if (document.body) { doTeleport(); }
        else { document.addEventListener('DOMContentLoaded', doTeleport); }
    })();
})();
</script>
<?php endif; ?>

