</div><!-- /container -->
</div><!-- /pt-20 -->

<footer class="bg-white border-t border-slate-100 py-4 text-center text-xs text-slate-400 no-print">
    <?= APP_NAME ?> &copy; <?= date('Y') ?> &mdash; HIPAA-Conscious Paperless Document System
</footer>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>window._pdBase = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>/assets/js/autosave.js" defer></script>
<script src="<?= BASE_URL ?>/assets/js/offline.js" defer></script>
<script>
(function () {
    // User dropdown
    var btn  = document.getElementById('uBtn');
    var drop = document.getElementById('uDrop');
    if (btn && drop) {
        btn.addEventListener('click', function (e) { e.stopPropagation(); drop.classList.toggle('hidden'); });
        document.addEventListener('click', function () { drop.classList.add('hidden'); });
    }
    // Mobile menu
    var mBtn  = document.getElementById('mBtn');
    var mMenu = document.getElementById('mMenu');
    if (mBtn && mMenu) {
        mBtn.addEventListener('click', function () { mMenu.classList.toggle('hidden'); });
    }
})();
</script>
<?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>
