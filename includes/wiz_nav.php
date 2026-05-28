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

        <?php if ($endVisitId): ?>
        <script>
        function wizEndVisit(visitId, btn) {
            var timeOutInput = document.querySelector('input[name="time_out"]');
            if (timeOutInput) {
                var now = new Date();
                var hh  = String(now.getHours()).padStart(2, '0');
                var mm  = String(now.getMinutes()).padStart(2, '0');
                timeOutInput.value = hh + ':' + mm;
                timeOutInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (!confirm('Mark this visit as completed?\n\nTime Out recorded: ' + (timeOutInput ? timeOutInput.value : 'N/A'))) return;
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Ending…';
            var csrf = (typeof CSRF !== 'undefined') ? CSRF : (window._pdCsrf || '');
            var base = (typeof BASE !== 'undefined') ? BASE : (window._pdBase || '');
            fetch(base + '/api/schedule_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf: csrf, id: visitId, action: 'status', status: 'completed' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    window.location.href = base + '/schedule.php';
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-stop-circle-fill"></i><span>End Visit</span>';
                    alert('Error: ' + (data.error || 'Could not end visit.'));
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-stop-circle-fill"></i><span>End Visit</span>';
                alert('Network error. Please try again.');
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            var bubble   = document.getElementById('endVisitBubble');
            var submitBtn = document.getElementById('submitBtn');
            if (!bubble || !submitBtn) return;
            function syncBubble() {
                bubble.classList.toggle('hidden', submitBtn.classList.contains('hidden'));
            }
            syncBubble();
            new MutationObserver(syncBubble).observe(submitBtn, { attributes: true, attributeFilter: ['class'] });
        });
        </script>
        <?php endif; ?>
    </div>
</div>

<?php if ($endVisitId): ?>
<!-- End Visit floating bubble — bottom-left, mirrors AI bubble on bottom-right -->
<div id="endVisitBubble"
     class="hidden fixed bottom-6 left-6 z-50 no-print">
    <button type="button"
            class="flex items-center gap-2.5 pl-4 pr-5 py-3.5 rounded-2xl text-white font-bold
                   shadow-xl hover:shadow-2xl transition-all duration-200 hover:scale-105 active:scale-95 text-sm"
            style="background:linear-gradient(135deg,#e11d48,#be123c)"
            onclick="wizEndVisit(<?= (int)$endVisitId ?>, this)"
            title="End Visit">
        <i class="bi bi-stop-circle-fill text-base"></i>
        <span>End Visit</span>
    </button>
</div>
<?php endif; ?>
