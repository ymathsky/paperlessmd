<?php
/**
 * includes/wiz_nav.php
 * Drop this at the bottom of any wizard form (inside the card, after last .wiz-step).
 * Expects: $accentClass (e.g. 'bg-red-700 hover:bg-red-800'), $cancelUrl
 */
$accentClass ??= 'bg-blue-600 hover:bg-blue-700';
$cancelUrl   ??= BASE_URL . '/patients.php';
?>
<div id="wiz-nav" class="no-select">
    <!-- Back -->
    <button type="button" id="wiz-back"
            class="hidden flex items-center gap-2 px-6 py-3 bg-white border border-slate-200
                   hover:bg-slate-50 text-slate-600 font-semibold rounded-xl transition-colors text-sm">
        <i class="bi bi-arrow-left"></i> Back
    </button>

    <div class="flex items-center gap-3 ml-auto">
        <a href="<?= $cancelUrl ?>"
           class="flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-semibold
                  text-slate-500 hover:text-slate-700 transition-colors">
            Cancel
        </a>

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
            <i class="bi bi-check2-circle text-xl"></i> Submit &amp; Save
        </button>
    </div>
</div>
