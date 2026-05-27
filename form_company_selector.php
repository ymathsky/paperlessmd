<?php
/**
 * Form Company Selector
 * Include this at the top of any form's content area.
 * Renders two styled radio buttons for BWC / VMP selection.
 * Company is saved in form_data['company'] and used by print templates.
 * JS updates .co-name-display / .co-name-uc-display / .co-name-abb-display spans on change.
 */
?>
<!-- ── Practice / Company Selector ─────────────────────────────────────── -->
<div class="mb-5 p-4 bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl border border-slate-200">
    <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-1.5">
        <i class="bi bi-building-fill text-slate-400"></i> Select Practice
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
        <label class="flex items-center gap-3 p-3.5 border-2 rounded-xl cursor-pointer transition-all
                      border-blue-300 bg-blue-50 shadow-sm
                      has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:shadow
                      [&:not(:has(:checked))]:border-slate-200 [&:not(:has(:checked))]:bg-white">
            <input type="radio" name="company" value="Beyond Wound Care Inc."
                   class="w-4 h-4 text-blue-600 border-slate-300 focus:ring-blue-500 flex-shrink-0"
                   checked>
            <div class="leading-tight min-w-0">
                <div class="font-semibold text-sm text-slate-800">Beyond Wound Care Inc.</div>
                <div class="text-xs text-slate-500 truncate">1340 Remington Rd, STE P &bull; 847-873-8693</div>
            </div>
        </label>
        <label class="flex items-center gap-3 p-3.5 border-2 rounded-xl cursor-pointer transition-all
                      border-slate-200 bg-white
                      has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50 has-[:checked]:shadow">
            <input type="radio" name="company" value="Visiting Medical Physician Inc."
                   class="w-4 h-4 text-teal-600 border-slate-300 focus:ring-teal-500 flex-shrink-0">
            <div class="leading-tight min-w-0">
                <div class="font-semibold text-sm text-slate-800">Visiting Medical Physician Inc.</div>
                <div class="text-xs text-slate-500 truncate">1340 Remington Rd, Suite M &bull; 847.252.1858</div>
            </div>
        </label>
    </div>
</div>
<script>
(function () {
    function syncCompany(val) {
        var uc  = val.toUpperCase();
        var abb = (val === 'Visiting Medical Physician Inc.') ? 'VMP' : 'BWC';
        document.querySelectorAll('.co-name-display').forEach(function (el) { el.textContent = val; });
        document.querySelectorAll('.co-name-uc-display').forEach(function (el) { el.textContent = uc; });
        document.querySelectorAll('.co-name-abb-display').forEach(function (el) { el.textContent = abb; });
    }
    document.addEventListener('change', function (e) {
        if (e.target && e.target.name === 'company') syncCompany(e.target.value);
    });
    // Sync on load (handles draft restore which sets value without firing 'change')
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            var checked = document.querySelector('[name="company"]:checked');
            if (checked) syncCompany(checked.value);
        }, 0);
    });
}());
</script>
