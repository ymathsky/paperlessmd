<?php
/**
 * includes/handwriting_pad.php
 * Reusable freehand handwriting pad (tablet stylus / finger / mouse).
 * Uses SignaturePad.js (already loaded in footer.php).
 *
 * Set before including:
 *   $hwFieldName  - hidden input name  (default: 'med_handwriting')
 *   $hwFieldId    - hidden input id    (default: 'medHandwritingData')
 *   $hwLabel      - toggle button text (default: 'Handwrite Medications')
 *   $hwPlaceholder- canvas hint text   (optional)
 *   $hwExisting   - pre-filled base64  (optional)
 */
$hwFieldName   = $hwFieldName   ?? 'med_handwriting';
$hwFieldId     = $hwFieldId     ?? 'medHandwritingData';
$hwLabel       = $hwLabel       ?? 'Handwrite Medications';
$hwPlaceholder = $hwPlaceholder ?? 'Write medication names, doses &amp; frequencies here&hellip;';
$hwExisting    = $hwExisting    ?? '';
$hwUid         = 'hw_' . substr(md5($hwFieldId . microtime()), 0, 8);
?>
<div class="hw-pad-wrap mt-4" data-uid="<?= $hwUid ?>" data-field-id="<?= h($hwFieldId) ?>">
    <input type="hidden" name="<?= h($hwFieldName) ?>" id="<?= h($hwFieldId) ?>"
           value="<?= h($hwExisting) ?>">

    <!-- Toggle + preview row -->
    <div class="flex flex-wrap items-center gap-3">
        <button type="button"
                class="hw-toggle inline-flex items-center gap-2 px-4 py-2
                       bg-indigo-50 hover:bg-indigo-100 border border-indigo-200
                       text-indigo-700 font-semibold text-sm rounded-xl transition-all no-print">
            <i class="bi bi-pencil-square"></i> <?= h($hwLabel) ?>
        </button>
        <span class="hw-preview-wrap hidden items-center gap-2">
            <img class="hw-thumb" src="<?= $hwExisting ? h($hwExisting) : '' ?>"
                 alt="Handwritten medications"
                 style="height:44px;max-width:320px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;<?= $hwExisting ? '' : 'display:none;' ?>">
            <button type="button"
                    class="hw-remove-thumb text-slate-400 hover:text-red-500 transition-colors text-xs no-print">
                <i class="bi bi-x-circle"></i> Remove
            </button>
        </span>
    </div>

    <!-- Drawing panel (hidden until toggled) -->
    <div class="hw-panel hidden mt-3 border-2 border-indigo-200 rounded-2xl overflow-hidden bg-white no-print">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-3 px-4 py-3 bg-indigo-50 border-b border-indigo-200">
            <i class="bi bi-pencil-square text-indigo-600 text-base"></i>
            <span class="text-sm font-bold text-indigo-700 flex-1">Draw with stylus or finger</span>
            <!-- Pen size dots -->
            <div class="flex items-center gap-2" title="Pen size">
                <button type="button" class="hw-pen w-5 h-5 rounded-full bg-slate-800 border-2 border-transparent
                        hover:border-indigo-500 transition-all hw-pen--active" data-min="0.8" data-max="1.5" title="Fine"></button>
                <button type="button" class="hw-pen w-6 h-6 rounded-full bg-slate-800 border-2 border-transparent
                        hover:border-indigo-500 transition-all" data-min="1.5" data-max="3" title="Medium"></button>
                <button type="button" class="hw-pen w-7 h-7 rounded-full bg-slate-800 border-2 border-transparent
                        hover:border-indigo-500 transition-all" data-min="3" data-max="6" title="Thick"></button>
            </div>
            <button type="button"
                    class="hw-undo ml-1 px-3 py-1.5 text-xs bg-white border border-slate-200
                           text-slate-600 rounded-lg hover:bg-slate-50 transition-all"
                    title="Undo last stroke">
                <i class="bi bi-arrow-counterclockwise"></i> Undo
            </button>
        </div>

        <!-- Canvas -->
        <div class="hw-canvas-wrap relative" style="touch-action:none;">
            <canvas class="hw-canvas block"
                    style="width:100%;height:220px;cursor:crosshair;touch-action:none;display:block;"></canvas>
            <div class="hw-placeholder absolute inset-0 flex items-center justify-center
                        text-slate-300 pointer-events-none select-none italic text-sm text-center px-6">
                <?= $hwPlaceholder ?>
            </div>
        </div>

        <!-- Footer buttons -->
        <div class="flex flex-wrap items-center gap-3 px-4 py-3 bg-slate-50 border-t border-slate-200">
            <button type="button"
                    class="hw-done px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white
                           text-sm font-bold rounded-xl transition-all shadow-sm">
                <i class="bi bi-check2-circle"></i> Save Drawing
            </button>
            <button type="button"
                    class="hw-clear px-4 py-2 bg-white border border-slate-200 text-slate-600
                           hover:bg-slate-100 text-sm font-semibold rounded-xl transition-all">
                <i class="bi bi-eraser"></i> Clear
            </button>
            <button type="button"
                    class="hw-cancel ml-auto px-4 py-2 text-slate-400 hover:text-slate-600
                           text-sm transition-all">
                Cancel
            </button>
        </div>
    </div>
</div>
