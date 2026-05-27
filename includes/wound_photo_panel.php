<?php
/**
 * Reusable Wound Photo Panel
 * Requires: $patient_id (int) to be set in the including file.
 * Outputs: floating button, slide-up panel, camera modal, JS, CSS.
 * Also renders the Quick Notes panel (requires visit_id in URL).
 */
$_wcsrf = csrfToken();

// ── Last wound photo + measurement for this patient ───────────────────────────
$_wpLastSaved = null;
try {
    $__s = $pdo->prepare(
        'SELECT wp.id AS photo_id, wp.filename, wp.wound_location, wp.created_at,
                wm.area_cm2, wm.length_cm, wm.width_cm, wm.ruler_detected,
                wm.annotated_photo_path
         FROM wound_photos wp
         LEFT JOIN wound_measurements wm ON wm.photo_id = wp.id
         WHERE wp.patient_id = ?
         ORDER BY wp.created_at DESC LIMIT 1'
    );
    $__s->execute([$patient_id]);
    $__row = $__s->fetch(PDO::FETCH_ASSOC);
    if ($__row) {
        $_wpLastSaved = [
            'photo_url'      => BASE_URL . '/uploads/photos/' . $__row['filename'],
            'annotated_url'  => $__row['annotated_photo_path'],
            'area_cm2'       => $__row['area_cm2'],
            'length_cm'      => $__row['length_cm'],
            'width_cm'       => $__row['width_cm'],
            'ruler_detected' => !empty($__row['ruler_detected']),
            'wound_location' => $__row['wound_location'],
            'taken_at'       => $__row['created_at'] ? substr($__row['created_at'], 0, 10) : null,
        ];
    }
} catch (Exception $e) { /* non-fatal */ }

// ── Last 5 wound photos for thumbnail strip ───────────────────────────────────
$_wpRecentPhotos = [];
try {
    $__r = $pdo->prepare(
        'SELECT wp.id, wp.filename, wp.wound_location, wp.created_at,
                wm.area_cm2, wm.length_cm, wm.width_cm, wm.ruler_detected,
                wm.annotated_photo_path
         FROM wound_photos wp
         LEFT JOIN wound_measurements wm ON wm.photo_id = wp.id
         WHERE wp.patient_id = ?
         ORDER BY wp.created_at DESC LIMIT 5'
    );
    $__r->execute([$patient_id]);
    foreach ($__r->fetchAll(PDO::FETCH_ASSOC) as $__pr) {
        $_wpRecentPhotos[] = [
            'photo_url'      => BASE_URL . '/uploads/photos/' . $__pr['filename'],
            'annotated_url'  => $__pr['annotated_photo_path'],
            'area_cm2'       => $__pr['area_cm2'],
            'length_cm'      => $__pr['length_cm'],
            'width_cm'       => $__pr['width_cm'],
            'ruler_detected' => !empty($__pr['ruler_detected']),
            'wound_location' => $__pr['wound_location'],
            'taken_at'       => $__pr['created_at'] ? substr($__pr['created_at'], 0, 10) : null,
        ];
    }
} catch (Exception $e) { /* non-fatal */ }

$_wpAllPhotos = [];
try {
    $__a = $pdo->prepare(
        'SELECT wp.id, wp.filename, wp.wound_location, wp.created_at
         FROM wound_photos wp
         WHERE wp.patient_id = ?
         ORDER BY wp.created_at DESC, wp.id DESC'
    );
    $__a->execute([$patient_id]);
    foreach ($__a->fetchAll(PDO::FETCH_ASSOC) as $__pa) {
        $_wpAllPhotos[] = [
            'photo_url'      => BASE_URL . '/uploads/photos/' . $__pa['filename'],
            'wound_location' => $__pa['wound_location'],
            'taken_at'       => $__pa['created_at'] ? substr($__pa['created_at'], 0, 10) : null,
        ];
    }
} catch (Exception $e) { /* non-fatal */ }

// Quick Notes — load existing note if visit_id is present
$_qnVisitId  = (int)($_GET['visit_id'] ?? 0);
$_qnNoteText = '';
if ($_qnVisitId > 0) {
    $__qnStmt = $pdo->prepare("SELECT visit_notes FROM `schedule` WHERE id = ?");
    $__qnStmt->execute([$_qnVisitId]);
    $_qnNoteText = (string)($__qnStmt->fetchColumn() ?: '');
}
?>

<!-- ── Quick Notes floating trigger (above camera) ──────────────── -->
<button id="qnFloatBtn"
        onclick="qnOpenPanel()"
        title="Quick visit note"
        class="fixed right-5 w-14 h-14 flex items-center justify-center
               <?= $_qnNoteText ? 'bg-amber-500 hover:bg-amber-600' : 'bg-amber-400 hover:bg-amber-500' ?>
               active:scale-95 text-white rounded-full shadow-xl transition-all duration-200 no-print"
        style="bottom:172px;z-index:7900;">
    <i class="bi bi-sticky-fill text-2xl"></i>
    <?php if ($_qnNoteText): ?>
    <span class="absolute -top-1 -right-1 w-4 h-4 bg-emerald-500 rounded-full border-2 border-white"></span>
    <?php endif; ?>
</button>

<!-- Quick Notes overlay -->
<div id="qnOverlay"
     class="fixed inset-0 hidden no-print"
     style="z-index:7950;background:rgba(0,0,0,0.45);pointer-events:none;"></div>

<!-- Quick Notes slide-up panel -->
<div id="qnPanel"
     class="fixed bottom-0 bg-white rounded-t-2xl shadow-2xl no-print"
     style="left:50%;width:min(100vw,520px);transform:translateX(-50%) translateY(100%);
            transition:transform 0.3s ease-out;z-index:7960;display:flex;flex-direction:column;max-height:75dvh;">
    <div class="bg-white rounded-t-2xl px-5 pt-3 pb-3 border-b border-slate-100 flex-shrink-0">
        <div class="mx-auto w-8 h-1 bg-slate-300 rounded-full mb-2"></div>
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-800"><i class="bi bi-sticky-fill text-amber-500 mr-1.5"></i>Quick Visit Note</h3>
                <p class="text-xs text-slate-400">
                    <?= $_qnVisitId > 0 ? 'Saved to this visit — visible to staff &amp; provider' : 'Open from a scheduled visit to save notes' ?>
                </p>
            </div>
            <button onclick="qnClosePanel()"
                    class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100
                           hover:bg-slate-200 text-slate-500 transition-colors">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
    </div>
    <div class="px-5 py-4 flex flex-col gap-3 flex-1">
        <textarea id="qnTextarea"
                  rows="6"
                  class="w-full px-3 py-2.5 border border-amber-200 rounded-xl text-sm bg-amber-50
                         focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent resize-none"
                  placeholder="<?= $_qnVisitId > 0 ? 'e.g. wound improved, patient reports pain 3/10, BP elevated…' : 'No scheduled visit linked — note cannot be saved.' ?>"
                  ><?= htmlspecialchars($_qnNoteText) ?></textarea>
        <div class="flex items-center gap-3">
            <button type="button" onclick="qnSaveNote()"
                    class="px-5 py-2 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white text-sm font-bold rounded-xl transition-all shadow-sm">
                <i class="bi bi-floppy-fill mr-1.5"></i> Save Note
            </button>
            <button type="button" id="qnDictBtn" onclick="qnToggleDictation()"
                    title="Dictate note"
                    class="w-10 h-10 flex items-center justify-center rounded-full bg-slate-100
                           hover:bg-rose-100 hover:text-rose-600 text-slate-500 transition-all shadow-sm"
                    style="flex-shrink:0;">
                <i class="bi bi-mic-fill text-base"></i>
            </button>
            <span id="qnSavedMsg" class="hidden text-sm text-emerald-600 font-semibold">
                <i class="bi bi-check-circle-fill mr-0.5"></i> Saved!
            </span>
        </div>
        <?php if ($_qnVisitId > 0): ?>
        <?php endif; ?>
    </div>
</div>

<!-- ── Wound Photo Panel ───────────────────────────────────────── -->

<!-- Floating camera trigger -->
<button id="wpFloatBtn"
        onclick="wpOpenPanel()"
        title="Add wound photo"
        class="fixed bottom-24 right-5 w-14 h-14 flex items-center justify-center
               bg-violet-600 hover:bg-violet-700 active:scale-95 text-white rounded-full shadow-xl
               transition-all duration-200 no-print"
        style="z-index:8000;">
    <i class="bi bi-camera-fill text-2xl"></i>
    <span id="wpBadge"
          class="absolute -top-1 -right-1 hidden min-w-[20px] h-5 px-1 bg-emerald-500 text-white
                 text-[10px] font-bold rounded-full flex items-center justify-center leading-none"></span>
</button>

<!-- Overlay (purely visual — pointer-events:none so it never blocks the panel) -->
<div id="wpOverlay"
     class="fixed inset-0 hidden no-print"
     style="z-index:8100;background:rgba(0,0,0,0.45);pointer-events:none;"></div>

<!-- Slide-up panel -->
<div id="wpPanel"
     class="fixed bottom-0 bg-white rounded-t-2xl shadow-2xl no-print"
     style="left:50%;width:min(100vw,520px);transform:translateX(-50%) translateY(100%);
            transition:transform 0.3s ease-out;z-index:8200;display:flex;flex-direction:column;max-height:88dvh;">

    <!-- Handle + header -->
    <div class="bg-white rounded-t-2xl px-5 pt-3 pb-3 border-b border-slate-100 flex-shrink-0">
        <div class="mx-auto w-8 h-1 bg-slate-300 rounded-full mb-2"></div>
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-800"><i class="bi bi-camera-fill text-violet-600 mr-1.5"></i>Add Wound Photo</h3>
                <p class="text-xs text-slate-400">Saved directly to this patient's chart</p>
            </div>
            <button onclick="wpClosePanel()"
                    class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100
                           hover:bg-slate-200 text-slate-500 transition-colors">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
    </div>

    <!-- Scrollable body -->
    <div class="px-5 py-4 space-y-4" style="overflow-y:auto;flex:1 1 auto;">

        <?php if (!empty($_wpRecentPhotos)): ?>
        <!-- Recent wound photo thumbnails -->
        <div id="wpRecentStrip">
            <p class="text-xs font-semibold text-slate-500 mb-2">
                <i class="bi bi-clock-history text-violet-500 mr-1"></i>Recent Photos
            </p>
            <div class="flex gap-2 pb-1" style="overflow-x:auto;scrollbar-width:none;">
                <?php foreach ($_wpRecentPhotos as $_ri => $_rp): ?>
                <button type="button"
                        onclick="wpLoadRecent(<?= $_ri ?>)"
                        class="wp-recent-thumb flex-shrink-0 relative rounded-xl overflow-hidden
                               border-2 border-slate-200 hover:border-violet-500 transition-colors"
                        style="width:76px;height:76px;">
                    <img src="<?= htmlspecialchars($_rp['photo_url']) ?>"
                         alt="Wound photo <?= $_ri + 1 ?>"
                         class="w-full h-full object-cover">
                                        <?php if ($_ri === 0): ?>
                    <span class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-emerald-400 rounded-full
                                 border border-white"></span>
                    <?php endif; ?>
                    <?php if ($_rp['taken_at']): ?>
                    <span class="absolute top-1.5 left-1.5 text-[7px] font-bold text-white
                                 bg-black/50 rounded px-1"><?= substr($_rp['taken_at'], 5) ?></span>
                    <?php endif; ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="flex gap-2 rounded-2xl bg-slate-100 p-1">
            <button type="button" id="wpTabCaptureBtn" onclick="wpSetPanelTab('capture')"
                    class="flex-1 px-3 py-2 rounded-xl text-sm font-semibold transition-colors bg-white text-violet-700 shadow-sm">
                <i class="bi bi-camera-fill mr-1"></i> Capture
            </button>
            <button type="button" id="wpTabGalleryBtn" onclick="wpSetPanelTab('gallery')"
                    class="flex-1 px-3 py-2 rounded-xl text-sm font-semibold transition-colors text-slate-600 hover:text-slate-900">
                <i class="bi bi-grid-3x3-gap-fill mr-1"></i> All Photos
            </button>
        </div>

        <div id="wpCapturePane">

        <!-- Source buttons -->
        <div class="grid grid-cols-2 gap-2">
            <button type="button" id="wpBtnCamera"
                    onclick="wpOpenCamera()"
                    class="flex items-center justify-center gap-2 py-2.5 rounded-xl border-2
                           border-violet-600 bg-violet-50 text-violet-700 font-semibold text-sm
                           hover:bg-violet-100 active:scale-95 transition-all">
                <i class="bi bi-camera-fill text-base"></i> Take Photo
            </button>
            <label for="wpFileGallery"
                   class="flex items-center justify-center gap-2 py-2.5 rounded-xl border-2
                          border-slate-200 bg-slate-50 text-slate-600 font-semibold text-sm
                          cursor-pointer hover:border-violet-400 hover:bg-violet-50 hover:text-violet-700
                          active:scale-95 transition-all select-none">
                <i class="bi bi-images text-base"></i> From Gallery
            </label>
        </div>

        <!-- Preview -->
        <div id="wpPreviewWrap" class="hidden">
            <div class="relative rounded-2xl overflow-hidden border border-slate-200 bg-slate-100
                        flex items-center justify-center" style="min-height:140px;">
                <img id="wpPreviewImg" src="" alt="Preview"
                     class="max-h-56 max-w-full object-contain rounded-2xl">
                <button type="button" onclick="wpClearFile()"
                        class="absolute top-2 right-2 w-8 h-8 flex items-center justify-center
                               bg-black/50 text-white rounded-full hover:bg-black/70 transition-colors text-sm">
                    <i class="bi bi-x-lg"></i>
                </button>
                <span id="wpPreviewBadge"
                      class="absolute top-2 left-2 bg-emerald-500 text-white text-[10px] font-bold
                             px-2 py-0.5 rounded-full shadow"></span>
            </div>
        </div>

        <!-- Wound location -->
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Wound Location</label>
            <div class="grid grid-cols-3 gap-2 mb-2" id="wpLocBtns">
                <?php foreach (['Left Foot','Right Foot','Left Leg','Right Leg','Sacrum','Other'] as $_loc): ?>
                <button type="button"
                        class="wp-loc-btn px-2 py-2 rounded-xl border border-slate-200 bg-slate-50
                               text-slate-600 text-xs font-medium hover:border-violet-400 hover:bg-violet-50
                               hover:text-violet-700 transition-all"
                        style="cursor:pointer;">
                    <?= htmlspecialchars($_loc) ?>
                </button>
                <?php endforeach; ?>
            </div>
            <input id="wpLocation" type="text" placeholder="Or type location…"
                   class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white
                          focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent">
        </div>

        <!-- Note -->
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-semibold text-slate-600">Note <span class="text-slate-400 font-normal">(optional)</span></label>
                <button type="button" id="wpDictBtn" onclick="wpToggleDictation()"
                        title="Dictate note"
                        class="w-8 h-8 flex items-center justify-center rounded-full
                               bg-slate-100 hover:bg-rose-100 hover:text-rose-600
                               text-slate-500 transition-all">
                    <i class="bi bi-mic-fill text-sm"></i>
                </button>
            </div>
            <textarea id="wpNote" rows="2" placeholder="e.g. wound size 3×2 cm, improving…"
                      class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white resize-none
                             focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent"></textarea>
            <p id="wpDictStatus" class="hidden text-[10px] text-rose-500 mt-1 flex items-center gap-1">
                <i class="bi bi-record-circle-fill animate-pulse"></i> Listening…
            </p>
        </div>

        <!-- Status bar -->
        <div id="wpStatus" class="hidden text-sm rounded-xl px-4 py-3 text-center font-medium"></div>

        <!-- Submit -->
        <button type="button" id="wpSubmitBtn"
                onclick="wpSubmit()"
                class="w-full py-3.5 bg-violet-600 hover:bg-violet-700 active:scale-95 text-white font-bold
                       rounded-2xl text-sm transition-all shadow-sm flex items-center justify-center gap-2">
            <i class="bi bi-cloud-upload-fill"></i> Save Photo to Chart
        </button>

        <!-- Saved photos this session -->
        <div id="wpSavedWrap" class="hidden">
            <p class="text-xs font-semibold text-slate-500 mb-2"><i class="bi bi-check-circle-fill text-emerald-500 mr-1"></i>Photos added this visit</p>
            <div id="wpSavedGrid" class="grid grid-cols-4 gap-2"></div>
        </div>
        </div>

        <div id="wpGalleryPane" class="hidden space-y-3">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <div>
                        <p class="text-sm font-bold text-slate-800">All Wound Photos</p>
                        <p class="text-xs text-slate-500">Browse every photo saved for this patient.</p>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-white text-slate-500 border border-slate-200">
                        <?= count($_wpAllPhotos) ?> total
                    </span>
                </div>
                <div id="wpGalleryPreview" class="hidden rounded-2xl overflow-hidden border border-slate-200 bg-white">
                    <img id="wpGalleryPreviewImg" src="" alt="All wound photo preview" class="w-full max-h-72 object-contain bg-slate-100">
                    <div class="px-3 py-2 text-xs text-slate-500 flex items-center justify-between gap-3">
                        <span id="wpGalleryPreviewMeta" class="truncate"></span>
                        <span id="wpGalleryPreviewDate" class="font-semibold text-slate-600 whitespace-nowrap"></span>
                    </div>
                </div>
            </div>
            <?php if (!empty($_wpAllPhotos)): ?>
            <div class="grid grid-cols-3 gap-2 max-h-[42dvh] overflow-y-auto pr-1">
                <?php foreach ($_wpAllPhotos as $_ai => $_ap): ?>
                <button type="button" onclick="wpShowGalleryPhoto(<?= $_ai ?>)"
                        class="relative rounded-xl overflow-hidden border border-slate-200 bg-slate-100 aspect-square hover:border-violet-400 transition-colors">
                    <img src="<?= htmlspecialchars($_ap['photo_url']) ?>" alt="Patient wound photo <?= $_ai + 1 ?>" class="w-full h-full object-cover">
                    <?php if ($_ap['taken_at']): ?>
                    <span class="absolute top-1.5 left-1.5 text-[7px] font-bold text-white bg-black/50 rounded px-1">
                        <?= substr($_ap['taken_at'], 5) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($_ap['wound_location'])): ?>
                    <span class="absolute bottom-0 left-0 right-0 text-[8px] font-bold text-white text-center bg-black/55 px-1 py-0.5 truncate">
                        <?= htmlspecialchars($_ap['wound_location']) ?>
                    </span>
                    <?php endif; ?>
                </button>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">
                No wound photos have been saved for this patient yet.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Gallery file input — outside all panels to avoid hit-test interference -->
<input type="file" id="wpFileGallery" accept="image/*"
       style="position:fixed;top:-9999px;left:-9999px;width:0;height:0;opacity:0;pointer-events:none;">

<!-- Full-screen camera modal -->
<div id="wpCameraModal"
     class="fixed inset-0 bg-black hidden no-print"
     style="z-index:9999;touch-action:none;">
    <video id="wpCameraVideo" autoplay playsinline
           class="w-full h-full object-cover"></video>
    <canvas id="wpCameraCanvas"
            class="hidden w-full h-full object-cover"></canvas>

    <!-- Top bar -->
    <div class="absolute top-0 left-0 right-0 flex items-center justify-between px-4 py-3
                bg-gradient-to-b from-black/60 to-transparent">
        <span class="text-white font-semibold text-sm"><i class="bi bi-camera-fill mr-1.5"></i>Take Wound Photo</span>
        <div class="flex gap-2">
            <button id="wpCamSwitchBtn"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/20 hover:bg-white/30
                           text-white text-xs font-semibold transition-colors">
                <i class="bi bi-arrow-repeat"></i> Flip
            </button>
            <button id="wpFlashBtn"
                    class="hidden flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/20 hover:bg-white/30
                           text-white text-xs font-semibold transition-colors">
                <i class="bi bi-lightning-fill"></i>
            </button>
            <button id="wpCamCloseBtn"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/20 hover:bg-white/30
                           text-white text-xs font-semibold transition-colors">
                <i class="bi bi-x-lg"></i> Close
            </button>
        </div>
    </div>

    <!-- Zoom controls -->
    <div id="wpZoomWrap"
         class="hidden absolute flex items-center justify-center"
         style="bottom:100px;left:50%;transform:translateX(-50%);width:min(80vw,280px);z-index:1;">
        <div class="flex items-center gap-2 px-3 py-2 rounded-2xl w-full" style="background:rgba(0,0,0,0.5);">
            <button id="wpZoomOut"
                    class="w-8 h-8 flex items-center justify-center text-white text-xl font-bold
                           rounded-full active:scale-90 transition-transform"
                    style="background:rgba(255,255,255,0.2);">&#8722;</button>
            <input id="wpZoomRange" type="range" min="1" max="5" step="0.1" value="1"
                   class="flex-1" style="accent-color:#a78bfa;">
            <button id="wpZoomIn"
                    class="w-8 h-8 flex items-center justify-center text-white text-xl font-bold
                           rounded-full active:scale-90 transition-transform"
                    style="background:rgba(255,255,255,0.2);">&#43;</button>
            <span id="wpZoomLabel"
                  class="text-white font-bold text-xs"
                  style="min-width:28px;text-align:center;">1&times;</span>
        </div>
    </div>

    <!-- Bottom controls -->
    <div class="absolute bottom-0 left-0 right-0 flex items-center justify-center gap-6 pb-10 pt-6
                bg-gradient-to-t from-black/60 to-transparent">
        <button id="wpCamSnapBtn"
                class="w-16 h-16 rounded-full bg-white shadow-lg flex items-center justify-center
                       active:scale-90 transition-transform border-4 border-violet-400">
            <div class="w-12 h-12 rounded-full bg-violet-600"></div>
        </button>
        <button id="wpCamRetakeBtn"
                class="hidden px-5 py-2.5 rounded-xl bg-white/20 hover:bg-white/30 text-white
                       font-semibold text-sm transition-colors">
            <i class="bi bi-arrow-counterclockwise mr-1"></i> Retake
        </button>
        <button id="wpCamUseBtn"
                class="hidden px-5 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white
                       font-bold text-sm transition-colors shadow-lg">
            <i class="bi bi-check-lg mr-1"></i> Use Photo
        </button>
    </div>
</div>

<script>
(function () {
    var patientId = <?= (int)$patient_id ?>;
    var csrfToken = <?= json_encode($_wcsrf) ?>;
    var uploadUrl  = <?= json_encode(BASE_URL . '/api/upload_photo.php') ?>;
    var updateUrl  = <?= json_encode(BASE_URL . '/api/update_wound_photo.php') ?>;
    var savedCount = 0;
    var currentBlob = null;
    var currentFile = null;
    var lastPhotoId = 0;
    var _wpGalleryActive = false;
    var _wpGalleryTimer  = null;

    var _wpLastSaved     = <?= json_encode($_wpLastSaved) ?>;
    var _wpRecentPhotos  = <?= json_encode($_wpRecentPhotos) ?>;
    var _wpAllPhotos     = <?= json_encode($_wpAllPhotos) ?>;
    var _wpRestoredOnce  = false;
    var _wpGalleryIndex  = -1;

    // Element refs (early so all closures can use them)
    var panel    = document.getElementById('wpPanel');
    var camModal = document.getElementById('wpCameraModal');

    // ── Panel open / close ────────────────────────────────────────
    window.wpOpenPanel = function () {
        panel.style.transform = 'translateX(-50%) translateY(0)';
        document.getElementById('wpOverlay').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        wpSetPanelTab('capture');
        // On first open after page load, restore last saved photo + measurements
        if (!_wpRestoredOnce && _wpLastSaved && !currentBlob && !currentFile) {
            _wpRestoredOnce = true;
            _wpRestoreLastPhoto(_wpLastSaved);
        }
        setTimeout(function () {
            document.addEventListener('click', wpOutsideHandler);
        }, 50);
    };

    function _wpRestoreLastPhoto(d) {
        // Show the original photo in the preview area
        var previewImg   = document.getElementById('wpPreviewImg');
        var previewBadge = document.getElementById('wpPreviewBadge');
        previewImg.src           = d.photo_url;
        previewBadge.textContent = d.taken_at ? ('Saved ' + d.taken_at) : 'Last photo';
        document.getElementById('wpPreviewWrap').classList.remove('hidden');
        // Pre-fill wound location
        if (d.wound_location) {
            var locEl = document.getElementById('wpLocation');
            if (locEl) locEl.value = d.wound_location;
            document.querySelectorAll('.wp-loc-btn').forEach(function(btn) {
                if (btn.textContent.trim() === d.wound_location) {
                    btn.classList.add('border-violet-500', 'bg-violet-100', 'text-violet-800');
                }
            });
        }
        // Show measurement card if data exists
        if (d.area_cm2 != null || d.annotated_url) {
            var card    = document.getElementById('wpMeasureCard');
            var spinner = document.getElementById('wpMeasureSpinner');
            var result  = document.getElementById('wpMeasureResult');
            var errDiv  = document.getElementById('wpMeasureError');
            card.classList.remove('hidden');
            spinner.classList.add('hidden');
            errDiv.classList.add('hidden');
            result.classList.remove('hidden');
            document.getElementById('wpMeasureAnnotatedImg').src = d.annotated_url || d.photo_url;
            document.getElementById('wpMArea').textContent   = d.area_cm2  != null ? d.area_cm2  : '\u2014';
            document.getElementById('wpMLength').textContent = d.length_cm != null ? d.length_cm : '\u2014';
            document.getElementById('wpMWidth').textContent  = d.width_cm  != null ? d.width_cm  : '\u2014';
            var badge = document.getElementById('wpMeasureAccuracyBadge');
            badge.textContent  = d.ruler_detected ? '\u2713 Saved measurement' : '\u2713 Saved (no ruler)';
            badge.style.cssText = 'background:rgba(100,116,139,.85);color:#fff;padding:3px 8px;border-radius:99px;';
            document.getElementById('wpMeasureWarn').classList.add('hidden');
            document.getElementById('wpTissueRow').classList.add('hidden');
        }
    }

    window.wpLoadRecent = function (idx) {
        var d = _wpRecentPhotos[idx];
        if (!d) return;
        _wpRestoredOnce = true;
        _wpRestoreLastPhoto(d);
        // Highlight selected thumbnail
        document.querySelectorAll('.wp-recent-thumb').forEach(function (el, i) {
            el.style.borderColor = (i === idx) ? '#7c3aed' : '';
        });
        // Scroll preview into view
        var pw = document.getElementById('wpPreviewWrap');
        if (pw) setTimeout(function () { pw.scrollIntoView({block:'nearest',behavior:'smooth'}); }, 80);
    };

    window.wpSetPanelTab = function (tab) {
        var capturePane = document.getElementById('wpCapturePane');
        var galleryPane  = document.getElementById('wpGalleryPane');
        var captureBtn   = document.getElementById('wpTabCaptureBtn');
        var galleryBtn   = document.getElementById('wpTabGalleryBtn');
        if (!capturePane || !galleryPane || !captureBtn || !galleryBtn) return;

        var captureActive = tab !== 'gallery';
        capturePane.classList.toggle('hidden', !captureActive);
        galleryPane.classList.toggle('hidden', captureActive);

        captureBtn.classList.toggle('bg-white', captureActive);
        captureBtn.classList.toggle('text-violet-700', captureActive);
        captureBtn.classList.toggle('shadow-sm', captureActive);
        captureBtn.classList.toggle('text-slate-600', !captureActive);
        captureBtn.classList.toggle('hover:text-slate-900', !captureActive);

        galleryBtn.classList.toggle('bg-white', !captureActive);
        galleryBtn.classList.toggle('text-violet-700', !captureActive);
        galleryBtn.classList.toggle('shadow-sm', !captureActive);
        galleryBtn.classList.toggle('text-slate-600', captureActive);
        galleryBtn.classList.toggle('hover:text-slate-900', captureActive);

        if (!captureActive && _wpGalleryIndex < 0 && _wpAllPhotos.length) {
            wpShowGalleryPhoto(0);
        }
    };

    window.wpShowGalleryPhoto = function (idx) {
        var d = _wpAllPhotos[idx];
        if (!d) return;
        _wpGalleryIndex = idx;
        var wrap = document.getElementById('wpGalleryPreview');
        var img  = document.getElementById('wpGalleryPreviewImg');
        var meta = document.getElementById('wpGalleryPreviewMeta');
        var date = document.getElementById('wpGalleryPreviewDate');
        if (!wrap || !img || !meta || !date) return;
        img.src = d.photo_url;
        meta.textContent = d.wound_location ? d.wound_location : 'Unspecified location';
        date.textContent = d.taken_at ? ('Saved ' + d.taken_at) : 'Saved photo';
        wrap.classList.remove('hidden');
    };

    window.wpClosePanel = function () {
        panel.style.transform = 'translateX(-50%) translateY(100%)';
        document.getElementById('wpOverlay').classList.add('hidden');
        document.body.style.overflow = '';
        document.removeEventListener('click', wpOutsideHandler);
    };
    function wpOutsideHandler(e) {
        // Never close when gallery picker or camera is active
        if (_wpGalleryActive) return;
        if (!camModal.classList.contains('hidden')) return;
        var floatBtn = document.getElementById('wpFloatBtn');
        if (panel.contains(e.target) ||
            floatBtn.contains(e.target) ||
            camModal.contains(e.target)) { return; }
        wpClosePanel();
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { wpCloseCamera(); wpClosePanel(); }
    });

    // ── Gallery ───────────────────────────────────────────────────
    // Suspend the outside-click handler while the OS file picker is open
    var galleryLabel = panel.querySelector('label[for="wpFileGallery"]');
    if (galleryLabel) {
        galleryLabel.addEventListener('click', function () {
            _wpGalleryActive = true;
            document.removeEventListener('click', wpOutsideHandler);
            clearTimeout(_wpGalleryTimer);
            // Safety reset if user cancels picker (no change event fires)
            _wpGalleryTimer = setTimeout(function () {
                _wpGalleryActive = false;
                document.addEventListener('click', wpOutsideHandler);
            }, 60000);
        });
    }
    document.getElementById('wpFileGallery').addEventListener('change', function () {
        _wpGalleryActive = false;
        clearTimeout(_wpGalleryTimer);
        // Re-register outside handler after a short delay so the picker-close
        // click event (fired by iOS/Android on dialog dismiss) does not close the panel
        setTimeout(function () {
            document.addEventListener('click', wpOutsideHandler);
        }, 400);
        if (!this.files || !this.files[0]) return;
        currentFile = this.files[0];
        currentBlob = null;
        var reader = new FileReader();
        reader.onload = function (ev) { wpShowPreview(ev.target.result, 'Gallery'); wpAutoUpload(); };
        reader.readAsDataURL(currentFile);
    });

    function wpShowPreview(src, label) {
        document.getElementById('wpPreviewImg').src = src;
        document.getElementById('wpPreviewBadge').textContent = '✓ ' + label;
        document.getElementById('wpPreviewWrap').classList.remove('hidden');
    }
    window.wpClearFile = function () {
        currentBlob = null; currentFile = null; lastPhotoId = 0;
        document.getElementById('wpPreviewWrap').classList.add('hidden');
        document.getElementById('wpPreviewImg').src = '';
        document.getElementById('wpFileGallery').value = '';
    };

    // ── Camera modal ──────────────────────────────────────────────
    var camVideo  = document.getElementById('wpCameraVideo');
    var camCanvas = document.getElementById('wpCameraCanvas');
    var snapBtn   = document.getElementById('wpCamSnapBtn');
    var retakeBtn = document.getElementById('wpCamRetakeBtn');
    var useBtn    = document.getElementById('wpCamUseBtn');
    var closeBtn  = document.getElementById('wpCamCloseBtn');
    var switchBtn = document.getElementById('wpCamSwitchBtn');
    var camStream  = null;
    var facingMode  = 'environment';
    var snapBlob    = null;
    var wpCamZoom   = 1;
    var wpHwZoom    = false;
    var wpZoomMin   = 1;
    var wpZoomMax   = 5;
    var wpTorchOn   = false;
    var wpHwTorch   = false;

    window.wpOpenCamera = function () {
        document.removeEventListener('click', wpOutsideHandler);
        snapBlob = null;
        camCanvas.classList.add('hidden');
        camVideo.classList.remove('hidden');
        retakeBtn.classList.add('hidden');
        useBtn.classList.add('hidden');
        snapBtn.classList.remove('hidden');
        camModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        wpResetCameraControls();
        wpStartStream();
    };
    function wpStartStream() {
        if (camStream) camStream.getTracks().forEach(function (t) { t.stop(); });
        navigator.mediaDevices.getUserMedia({
            video: { facingMode: facingMode, width: { ideal: 4096 }, height: { ideal: 4096 } },
            audio: false
        }).then(function (s) {
            camStream = s;
            camVideo.srcObject = s;
            camVideo.play();
            wpSetupCameraControls(s);
        }).catch(function (err) {
            wpCloseCamera();
            alert('Camera not available: ' + err.message);
        });
    }
    function wpCloseCamera() {
        if (wpTorchOn && camStream) {
            var _vt = camStream.getVideoTracks()[0];
            if (_vt) try { _vt.applyConstraints({advanced: [{torch: false}]}); } catch (e) {}
        }
        if (camStream) { camStream.getTracks().forEach(function (t) { t.stop(); }); camStream = null; }
        wpResetCameraControls();
        camModal.classList.add('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(function () {
            document.addEventListener('click', wpOutsideHandler);
        }, 100);
    }

    snapBtn.addEventListener('click', function () {
        var vw = camVideo.videoWidth  || 1280;
        var vh = camVideo.videoHeight || 720;
        camCanvas.width  = vw;
        camCanvas.height = vh;
        var ctx = camCanvas.getContext('2d');
        if (!wpHwZoom && wpCamZoom > 1) {
            // Digital zoom: centre-crop the frame to simulate zoom
            var cropW = vw / wpCamZoom;
            var cropH = vh / wpCamZoom;
            ctx.drawImage(camVideo, (vw - cropW) / 2, (vh - cropH) / 2, cropW, cropH, 0, 0, vw, vh);
        } else {
            ctx.drawImage(camVideo, 0, 0, vw, vh);
        }
        camVideo.classList.add('hidden');
        camCanvas.classList.remove('hidden');
        snapBtn.classList.add('hidden');
        retakeBtn.classList.remove('hidden');
        useBtn.classList.remove('hidden');
        document.getElementById('wpZoomWrap').classList.add('hidden');
        if (camStream) camStream.getTracks().forEach(function (t) { t.enabled = false; });
        camCanvas.toBlob(function (b) { snapBlob = b; }, 'image/jpeg', 0.92);
    });
    retakeBtn.addEventListener('click', function () {
        snapBlob = null;
        camCanvas.classList.add('hidden');
        camVideo.classList.remove('hidden');
        retakeBtn.classList.add('hidden');
        useBtn.classList.add('hidden');
        snapBtn.classList.remove('hidden');
        if (camStream) camStream.getTracks().forEach(function (t) { t.enabled = true; });
        // Restore digital-zoom CSS and show zoom bar
        if (!wpHwZoom && wpCamZoom > 1) {
            camVideo.style.transform = 'scale(' + wpCamZoom + ')';
            camVideo.style.transformOrigin = 'center center';
        }
        document.getElementById('wpZoomWrap').classList.remove('hidden');
    });
    useBtn.addEventListener('click', function () {
        if (!snapBlob) return;
        currentBlob = snapBlob;
        currentFile = null;
        wpShowPreview(camCanvas.toDataURL('image/jpeg', 0.92), 'Camera');
        wpCloseCamera();
        wpAutoUpload();
    });
    closeBtn.addEventListener('click', wpCloseCamera);
    switchBtn.addEventListener('click', function () {
        facingMode = facingMode === 'environment' ? 'user' : 'environment';
        wpResetCameraControls();
        wpStartStream();
    });

    // ── Camera controls: zoom + torch ─────────────────────────────
    function wpResetCameraControls() {
        wpCamZoom = 1; wpTorchOn = false; wpHwZoom = false; wpHwTorch = false;
        camVideo.style.transform = '';
        var zw = document.getElementById('wpZoomWrap');
        if (zw) zw.classList.add('hidden');
        var zr = document.getElementById('wpZoomRange');
        if (zr) zr.value = 1;
        var zl = document.getElementById('wpZoomLabel');
        if (zl) zl.innerHTML = '1&times;';
        var fb = document.getElementById('wpFlashBtn');
        if (fb) {
            fb.classList.add('hidden');
            fb.style.background = 'rgba(255,255,255,0.2)';
            fb.style.color = 'white';
        }
    }
    function wpSetupCameraControls(stream) {
        var track = stream.getVideoTracks()[0];
        if (!track) return;
        var caps = {};
        try { caps = (track.getCapabilities ? track.getCapabilities() : {}); } catch (e) {}

        var zoomRange = document.getElementById('wpZoomRange');
        var zoomLabel = document.getElementById('wpZoomLabel');
        var zoomWrap  = document.getElementById('wpZoomWrap');
        var flashBtn  = document.getElementById('wpFlashBtn');

        // Zoom
        if (caps.zoom) {
            wpHwZoom  = true;
            wpZoomMin = caps.zoom.min || 1;
            wpZoomMax = Math.min(caps.zoom.max || 5, 5);
            var step  = parseFloat(((wpZoomMax - wpZoomMin) / 50).toFixed(2)) || 0.1;
            zoomRange.min   = wpZoomMin;
            zoomRange.max   = wpZoomMax;
            zoomRange.step  = step;
            zoomRange.value = wpZoomMin;
        } else {
            wpHwZoom  = false;
            wpZoomMin = 1; wpZoomMax = 3;
            zoomRange.min = 1; zoomRange.max = 3; zoomRange.step = 0.1; zoomRange.value = 1;
        }
        zoomLabel.innerHTML = '1&times;';
        zoomWrap.classList.remove('hidden');

        // Torch / flash
        if (caps.torch) {
            wpHwTorch = true;
            flashBtn.classList.remove('hidden');
        } else {
            wpHwTorch = false;
            flashBtn.classList.add('hidden');
        }
    }

    // Zoom range slider
    document.getElementById('wpZoomRange').addEventListener('input', function () {
        wpCamZoom = parseFloat(this.value);
        var display = (wpCamZoom % 1 === 0 ? wpCamZoom : wpCamZoom.toFixed(1)) + '&times;';
        document.getElementById('wpZoomLabel').innerHTML = display;
        if (wpHwZoom && camStream) {
            var vt = camStream.getVideoTracks()[0];
            if (vt) try { vt.applyConstraints({advanced: [{zoom: wpCamZoom}]}); } catch (e) {}
        } else {
            camVideo.style.transform = wpCamZoom > 1 ? 'scale(' + wpCamZoom + ')' : '';
            camVideo.style.transformOrigin = 'center center';
        }
    });
    document.getElementById('wpZoomOut').addEventListener('click', function () {
        var r = document.getElementById('wpZoomRange');
        r.value = Math.max(wpZoomMin, parseFloat(r.value) - parseFloat(r.step || 0.1));
        r.dispatchEvent(new Event('input'));
    });
    document.getElementById('wpZoomIn').addEventListener('click', function () {
        var r = document.getElementById('wpZoomRange');
        r.value = Math.min(wpZoomMax, parseFloat(r.value) + parseFloat(r.step || 0.1));
        r.dispatchEvent(new Event('input'));
    });

    // Flash / torch toggle
    document.getElementById('wpFlashBtn').addEventListener('click', function () {
        if (!wpHwTorch || !camStream) return;
        wpTorchOn = !wpTorchOn;
        var vt = camStream.getVideoTracks()[0];
        if (vt) try { vt.applyConstraints({advanced: [{torch: wpTorchOn}]}); } catch (e) {}
        if (wpTorchOn) {
            this.style.background = 'rgba(250,204,21,0.85)';
            this.style.color = '#1e1b4b';
        } else {
            this.style.background = 'rgba(255,255,255,0.2)';
            this.style.color = 'white';
        }
    });

    // ── Location chips ────────────────────────────────────────────
    window.wpSetLoc = function (btn, loc) {
        document.querySelectorAll('.wp-loc-btn').forEach(function (b) {
            b.classList.remove('border-violet-600','bg-violet-50','text-violet-700');
            b.classList.add('border-slate-200','bg-slate-50','text-slate-600');
            b.style.borderColor = '';
            b.style.backgroundColor = '';
            b.style.color = '';
        });
        btn.classList.remove('border-slate-200','bg-slate-50','text-slate-600');
        btn.classList.add('border-violet-600','bg-violet-50','text-violet-700');
        btn.style.borderColor = '#7c3aed';
        btn.style.backgroundColor = '#f5f3ff';
        btn.style.color = '#6d28d9';
        document.getElementById('wpLocation').value = loc;
    };
    document.querySelectorAll('.wp-loc-btn').forEach(function (b) {
        b.addEventListener('click', function (e) {
            e.stopPropagation();
            wpSetLoc(b, b.textContent.trim());
        });
    });

    // ── Status ────────────────────────────────────────────────────
    function wpSetStatus(html, type) {
        var el = document.getElementById('wpStatus');
        el.classList.remove('hidden','bg-red-50','text-red-700','bg-emerald-50','text-emerald-700','bg-blue-50','text-blue-700');
        if (type === 'error')   el.classList.add('bg-red-50',     'text-red-700');
        if (type === 'success') el.classList.add('bg-emerald-50', 'text-emerald-700');
        if (type === 'info')    el.classList.add('bg-blue-50',    'text-blue-700');
        el.innerHTML = html;
        el.classList.remove('hidden');
    }

    // ── Auto-upload (fires immediately when photo is set) ────────
    function wpAutoUpload() {
        if (!currentBlob && !currentFile) return;
        lastPhotoId = 0;
        var loc  = document.getElementById('wpLocation').value.trim();
        var note = document.getElementById('wpNote').value.trim();
        var fd   = new FormData();
        fd.append('csrf_token',     csrfToken);
        fd.append('patient_id',     patientId);
        fd.append('wound_location', loc);
        fd.append('description',    note);
        if (currentBlob) {
            fd.append('photo', currentBlob, 'wound-' + Date.now() + '.jpg');
        } else {
            fd.append('photo', currentFile, currentFile.name);
        }
        var btn = document.getElementById('wpSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-arrow-repeat spin mr-1"></i> Auto-saving…';
        wpSetStatus('<i class="bi bi-arrow-repeat spin mr-1"></i> Saving photo to chart…', 'info');
        fetch(uploadUrl, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) throw new Error(data.error || 'Upload failed');
            lastPhotoId = data.id || 0;
            savedCount++;
            var badge = document.getElementById('wpBadge');
            badge.textContent = savedCount;
            badge.classList.remove('hidden');
            var grid  = document.getElementById('wpSavedGrid');
            var thumb = document.createElement('div');
            thumb.className = 'relative rounded-xl overflow-hidden aspect-square bg-slate-100';
            thumb.innerHTML = '<img src="' + data.url + '" alt="Wound photo" class="w-full h-full object-cover">'
                + (loc ? '<span class="absolute bottom-0 left-0 right-0 bg-black/50 text-white text-[9px] text-center px-1 py-0.5 truncate">' + loc + '</span>' : '');
            grid.prepend(thumb);
            document.getElementById('wpSavedWrap').classList.remove('hidden');
            wpSetStatus('<i class="bi bi-check-circle-fill mr-1"></i> Photo saved! Optionally add location or note below.', 'success');
            btn.innerHTML = '<i class="bi bi-pencil-fill mr-1"></i> Update Location / Note';
            btn.disabled = false;
        })
        .catch(function (err) {
            wpSetStatus('<i class="bi bi-x-circle-fill mr-1"></i> ' + err.message + ' — tap to retry.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-upload-fill mr-1"></i> Retry Save';
        });
    }

    // ── Submit — updates location/note on already-saved photo ─────
    window.wpSubmit = function () {
        var btn  = document.getElementById('wpSubmitBtn');
        var loc  = document.getElementById('wpLocation').value.trim();
        var note = document.getElementById('wpNote').value.trim();

        // If photo was already auto-saved, update its location/note
        if (lastPhotoId > 0) {
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-arrow-repeat spin mr-1"></i> Updating…';
            fetch(updateUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf: csrfToken, photo_id: lastPhotoId, wound_location: loc, description: note })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) throw new Error(data.error || 'Update failed');
                wpSetStatus('<i class="bi bi-check-circle-fill mr-1"></i> Location / note updated!', 'success');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-camera-fill mr-1"></i> Add Another Photo';
                lastPhotoId = 0;
                wpClearFile();
                document.getElementById('wpLocation').value = '';
                document.getElementById('wpNote').value = '';
                document.querySelectorAll('.wp-loc-btn').forEach(function (b) {
                    b.classList.remove('border-violet-600','bg-violet-50','text-violet-700');
                    b.classList.add('border-slate-200','bg-slate-50','text-slate-600');
                    b.style.borderColor = '';
                    b.style.backgroundColor = '';
                    b.style.color = '';
                });
            })
            .catch(function (err) {
                wpSetStatus('<i class="bi bi-x-circle-fill mr-1"></i> ' + err.message, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-pencil-fill mr-1"></i> Update Location / Note';
            });
            return;
        }

        // No photo set yet — prompt
        if (!currentBlob && !currentFile) {
            wpSetStatus('<i class="bi bi-exclamation-circle mr-1"></i>Please take or choose a photo first.', 'error');
            return;
        }
        wpAutoUpload();
    };

    // ── Quick Notes panel open / close ───────────────────────────
    var QN_VISIT_ID = <?= (int)$_qnVisitId ?>;
    window.qnOpenPanel = function() {
        document.getElementById('qnPanel').style.transform = 'translateX(-50%) translateY(0)';
        var ov = document.getElementById('qnOverlay');
        ov.classList.remove('hidden');
        ov.style.pointerEvents = 'auto';
        document.body.style.overflow = 'hidden';
        setTimeout(function(){ document.getElementById('qnTextarea').focus(); }, 320);
    };
    window.qnClosePanel = function() {
        document.getElementById('qnPanel').style.transform = 'translateX(-50%) translateY(100%)';
        var ov = document.getElementById('qnOverlay');
        ov.classList.add('hidden');
        ov.style.pointerEvents = 'none';
        document.body.style.overflow = '';
    };
    document.getElementById('qnOverlay').addEventListener('click', window.qnClosePanel);

    // ── Wound Note Dictation ───────────────────────────────────────
    var _wpRecog  = null;
    var _wpDictOn = false;
    window.wpToggleDictation = function () {
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SR) { alert('Speech recognition is not supported in this browser. Try Chrome.'); return; }
        var dictBtn   = document.getElementById('wpDictBtn');
        var dictStatus = document.getElementById('wpDictStatus');
        if (_wpDictOn) { if (_wpRecog) _wpRecog.stop(); return; }
        _wpRecog = new SR();
        _wpRecog.lang = 'en-US';
        _wpRecog.continuous = true;
        _wpRecog.interimResults = true;
        var ta       = document.getElementById('wpNote');
        var baseText = ta.value;
        _wpDictOn = true;
        dictBtn.classList.add('bg-rose-500', 'text-white', 'animate-pulse');
        dictBtn.classList.remove('bg-slate-100', 'text-slate-500', 'hover:bg-rose-100', 'hover:text-rose-600');
        dictBtn.innerHTML = '<i class="bi bi-stop-fill text-sm"></i>';
        dictBtn.title = 'Stop dictation';
        dictStatus.classList.remove('hidden');
        _wpRecog.onresult = function (e) {
            var interim = '', final = '';
            for (var i = e.resultIndex; i < e.results.length; i++) {
                if (e.results[i].isFinal) { final += e.results[i][0].transcript; }
                else                      { interim += e.results[i][0].transcript; }
            }
            if (final) { baseText += (baseText && !baseText.endsWith(' ') ? ' ' : '') + final; }
            ta.value = baseText + (interim ? ' ' + interim : '');
            ta.scrollTop = ta.scrollHeight;
        };
        _wpRecog.onerror = function (e) { if (e.error !== 'aborted') console.warn('SR error', e.error); };
        _wpRecog.onend = function () {
            _wpDictOn = false;
            dictBtn.classList.remove('bg-rose-500', 'text-white', 'animate-pulse');
            dictBtn.classList.add('bg-slate-100', 'text-slate-500', 'hover:bg-rose-100', 'hover:text-rose-600');
            dictBtn.innerHTML = '<i class="bi bi-mic-fill text-sm"></i>';
            dictBtn.title = 'Dictate note';
            dictStatus.classList.add('hidden');
        };
        _wpRecog.start();
    };

    // ── Dictation ─────────────────────────────────────────────────
    var _qnRecog = null;
    var _qnDictOn = false;
    window.qnToggleDictation = function() {
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SR) {
            alert('Speech recognition is not supported in this browser. Try Chrome.');
            return;
        }
        if (_qnDictOn) {
            if (_qnRecog) _qnRecog.stop();
            return;
        }
        _qnRecog = new SR();
        _qnRecog.lang = 'en-US';
        _qnRecog.continuous = true;
        _qnRecog.interimResults = true;
        var ta      = document.getElementById('qnTextarea');
        var dictBtn = document.getElementById('qnDictBtn');
        var baseText = ta.value;
        _qnDictOn = true;
        dictBtn.classList.add('bg-rose-500', 'text-white', 'animate-pulse');
        dictBtn.classList.remove('bg-slate-100', 'text-slate-500', 'hover:bg-rose-100', 'hover:text-rose-600');
        dictBtn.innerHTML = '<i class="bi bi-stop-fill text-base"></i>';
        dictBtn.title = 'Stop dictation';
        _qnRecog.onresult = function(e) {
            var interim = '';
            var final   = '';
            for (var i = e.resultIndex; i < e.results.length; i++) {
                if (e.results[i].isFinal) { final += e.results[i][0].transcript; }
                else                      { interim += e.results[i][0].transcript; }
            }
            if (final) { baseText += (baseText && !baseText.endsWith(' ') ? ' ' : '') + final; }
            ta.value = baseText + (interim ? ' ' + interim : '');
            ta.scrollTop = ta.scrollHeight;
        };
        _qnRecog.onerror = function(e) { if (e.error !== 'aborted') console.warn('SR error', e.error); };
        _qnRecog.onend = function() {
            _qnDictOn = false;
            dictBtn.classList.remove('bg-rose-500', 'text-white', 'animate-pulse');
            dictBtn.classList.add('bg-slate-100', 'text-slate-500', 'hover:bg-rose-100', 'hover:text-rose-600');
            dictBtn.innerHTML = '<i class="bi bi-mic-fill text-base"></i>';
            dictBtn.title = 'Dictate note';
        };
        _qnRecog.start();
    };

    // ── Quick Notes save ──────────────────────────────────────────
    window.qnSaveNote = function() {
        var msg = document.getElementById('qnSavedMsg');
        msg.classList.add('hidden');
        if (!QN_VISIT_ID) {
            msg.innerHTML = '<i class="bi bi-exclamation-circle-fill mr-0.5 text-amber-500"></i> <span class="text-amber-600">Open from a scheduled visit to save.</span>';
            msg.classList.remove('hidden');
            setTimeout(function(){ msg.classList.add('hidden'); }, 4000);
            return;
        }
        var text = document.getElementById('qnTextarea').value;
        var msg  = document.getElementById('qnSavedMsg');
        msg.classList.add('hidden');
        fetch(<?= json_encode(BASE_URL . '/api/schedule_update.php') ?>, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf: csrfToken, id: QN_VISIT_ID, action: 'save_note', visit_notes: text })
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.ok) {
                msg.classList.remove('hidden');
                // Update green dot on sticky button
                var btn = document.getElementById('qnFloatBtn');
                if (btn && text.trim()) {
                    var dot = btn.querySelector('span');
                    if (!dot) {
                        dot = document.createElement('span');
                        dot.className = 'absolute -top-1 -right-1 w-4 h-4 bg-emerald-500 rounded-full border-2 border-white';
                        btn.appendChild(dot);
                    }
                }
                setTimeout(function(){ msg.classList.add('hidden'); }, 3000);
            }
        })
        .catch(function(){});
    };

})();
</script>
