import os
import re

file_path = 'c:\\xampp\\htdocs\\pd\\schedule.php'

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Replace the Header/Nav block with a slick, horizontal scrollable mobile-first UI
old_header_regex = re.compile(
    r'<!-- Date nav \+ Title -->.*?</div><!-- /row 2 -->\s*</div>\n</div>',
    re.DOTALL
)

new_header = """<!-- Date nav + Title - Mobile Optimized -->
<style>
/* Hide scrollbar for clean mobile tabs */
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.smooth-scroll { scroll-behavior: smooth; }
</style>
<div class="flex flex-col gap-4 mb-6 no-print">
    <!-- Title & Toggle -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl sm:text-2xl font-extrabold text-slate-800 tracking-tight flex items-center">
                <i class="bi bi-calendar3 text-indigo-500 mr-2"></i>Schedule
            </h2>
            <p class="text-slate-500 text-xs sm:text-sm mt-0.5 font-medium">
                <?= h($ma['full_name']) ?>
            </p>
        </div>
        
        <?php $maParam = $viewAll ? 'all' : $viewMaId;
              $pParam  = $filterProvider !== '' ? '&provider=' . urlencode($filterProvider) : ''; ?>
        <!-- Day/Week Toggle -->
        <div class="flex items-center bg-slate-100 p-1 rounded-xl shadow-inner">
            <a href="?date=<?= $date ?>&ma_id=<?= $maParam ?>&view=day<?= $pParam ?>"
               class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all <?= $view === 'day' ? 'bg-white text-indigo-700 shadow shadow-slate-200/50' : 'text-slate-500 hover:text-slate-700' ?>">
                Day
            </a>
            <a href="?date=<?= $weekStart ?>&ma_id=<?= $maParam ?>&view=week<?= $pParam ?>"
               class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all <?= $view === 'week' ? 'bg-white text-indigo-700 shadow shadow-slate-200/50' : 'text-slate-500 hover:text-slate-700' ?>">
                Week
            </a>
        </div>
    </div>

    <?php if (in_array($_SESSION['role'] ?? '', ['ma', 'admin'])): ?>
    <div class="-mt-2">
        <span id="gpsStatusBadge" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] sm:text-xs font-bold rounded-full bg-slate-100 text-slate-500 border border-slate-200">
            <i class="bi bi-geo-alt"></i> Locating…
        </span>
    </div>
    <?php endif; ?>

    <!-- Mobile Scrollable Date Nav -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 -mx-4 px-4 sm:mx-0 sm:px-0 hide-scrollbar smooth-scroll">
        <?php if ($view === 'week'): ?>
            <a href="?date=<?= $prevWeek ?>&ma_id=<?= $maParam ?>&view=week<?= $pParam ?>" class="shrink-0 p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-600 shadow-sm"><i class="bi bi-chevron-left text-sm"></i></a>
            <div class="shrink-0 px-4 py-2 bg-indigo-50 border border-indigo-100 rounded-xl text-indigo-700 font-bold text-sm shadow-sm flex items-center justify-center">
                <?= date('M j', strtotime($weekStart)) ?> – <?= date('M j', strtotime($weekEnd)) ?>
            </div>
            <a href="?date=<?= $nextWeek ?>&ma_id=<?= $maParam ?>&view=week<?= $pParam ?>" class="shrink-0 p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-600 shadow-sm"><i class="bi bi-chevron-right text-sm"></i></a>
        <?php else: ?>
            <a href="?date=<?= $prevDate ?>&ma_id=<?= $maParam ?>&view=day<?= $pParam ?>" class="shrink-0 p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-600 shadow-sm"><i class="bi bi-chevron-left text-sm"></i></a>
            <a href="?date=<?= date('Y-m-d') ?>&ma_id=<?= $maParam ?>&view=day<?= $pParam ?>" 
               class="shrink-0 px-4 py-2 rounded-xl text-sm font-bold shadow-sm <?= $isToday ? 'bg-indigo-600 text-white border-transparent' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
               Today
            </a>
            <div class="shrink-0 px-4 py-2 bg-indigo-50 border border-indigo-100 rounded-xl text-indigo-700 font-bold text-sm shadow-sm flex items-center justify-center min-w-[120px]">
                <?= date('D, M j', strtotime($date)) ?>
            </div>
            <a href="?date=<?= $nextDate ?>&ma_id=<?= $maParam ?>&view=day<?= $pParam ?>" class="shrink-0 p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 text-slate-600 shadow-sm"><i class="bi bi-chevron-right text-sm"></i></a>
        <?php endif; ?>
    </div>

    <!-- Admin Filters & Actions -->
    <div class="flex items-center gap-2 overflow-x-auto pb-2 -mx-4 px-4 sm:mx-0 sm:px-0 hide-scrollbar">
        <?php if (isAdmin() && $allMas): ?>
        <form method="GET" class="shrink-0">
            <input type="hidden" name="date" value="<?= h($date) ?>">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <?php if ($filterProvider !== ''): ?><input type="hidden" name="provider" value="<?= h($filterProvider) ?>"><?php endif; ?>
            <div class="relative">
                <i class="bi bi-person-badge absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <select name="ma_id" onchange="this.form.submit()" class="pl-8 pr-8 py-2 border border-slate-200 rounded-xl text-xs font-semibold bg-white text-slate-700 shadow-sm appearance-none focus:ring-2 focus:ring-indigo-400">
                    <option value="all" <?= $viewAll ? 'selected' : '' ?>>All MAs</option>
                    <?php foreach ($allMas as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= (!$viewAll && $m['id'] == $viewMaId) ? 'selected' : '' ?>><?= h($m['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <i class="bi bi-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] pointer-events-none"></i>
            </div>
        </form>
        <?php endif; ?>
        
        <?php if (!empty($providerOptions)): ?>
        <form method="GET" class="shrink-0">
            <input type="hidden" name="date" value="<?= h($date) ?>">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <input type="hidden" name="ma_id" value="<?= $viewAll ? 'all' : $viewMaId ?>">
            <div class="relative">
                <i class="bi bi-stethoscope absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <select name="provider" onchange="this.form.submit()" class="pl-8 pr-8 py-2 border border-slate-200 rounded-xl text-xs font-semibold bg-white text-slate-700 shadow-sm appearance-none focus:ring-2 focus:ring-indigo-400">
                    <option value="">All Providers</option>
                    <?php foreach ($providerOptions as $pOpt): ?>
                    <option value="<?= h($pOpt) ?>" <?= $filterProvider === $pOpt ? 'selected' : '' ?>><?= h($pOpt) ?></option>
                    <?php endforeach; ?>
                </select>
                <i class="bi bi-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] pointer-events-none"></i>
            </div>
        </form>
        <?php endif; ?>
        
        <?php if ($view === 'day' && count($routeAddresses) >= 1): ?>
        <button onclick="openRouteMapModal()" class="shrink-0 flex items-center justify-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-colors">
            <i class="bi bi-map-fill"></i> Map
            <span class="bg-emerald-500 border border-emerald-400 text-white text-[10px] px-1.5 py-0.5 rounded-full leading-none"><?= count($routeAddresses) ?></span>
        </button>
        <?php endif; ?>
        <?php if (isAdmin()): ?>
        <a href="<?= BASE_URL ?>/admin/schedule_manage.php?date=<?= $date ?>" class="shrink-0 flex items-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold shadow-sm transition-colors">
            <i class="bi bi-pencil-fill"></i> Manage
        </a>
        <?php endif; ?>
        <button onclick="window.print()" class="shrink-0 w-8 h-8 flex items-center justify-center bg-white border border-slate-200 text-slate-500 rounded-xl shadow-sm hover:bg-slate-50 transition-colors">
            <i class="bi bi-printer-fill text-sm"></i>
        </button>
    </div>
</div>"""

content = old_header_regex.sub(new_header, content, count=1)


# 2. Replace the $renderVisitCard function to use modern layout and flexboxes
start_str = "$renderVisitCard = function(array $v, int $idx, bool $showMaName) use ($statusDefs, $_sbc, $_vtl): void {"
end_str = "    <?php\n};\n?>"

start_idx = content.find(start_str)
end_idx = content.find(end_str, start_idx) + len(end_str)

new_render_visit_card = '''$renderVisitCard = function(array $v, int $idx, bool $showMaName) use ($statusDefs, $_sbc, $_vtl): void {
    $sd      = $statusDefs[$v['status']];
    $addr    = $v['patient_address'] ? rawurlencode($v['patient_address']) : '';
    $mapsUrl = $addr ? 'https://www.google.com/maps/dir/?api=1&destination='.$addr : '#';
    $vt      = $v['visit_type'] ?? 'routine';
    $href    = in_array($v['status'], ['pending','en_route'])
        ? BASE_URL . firstFormUrl($v['visit_type'] ?? 'routine', $v['patient_id'], $v['id'], $v['visit_subtype'] ?? '')
        : BASE_URL . '/patient_view.php?id=' . $v['patient_id'];
    ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-[0_2px_8px_-4px_rgba(0,0,0,0.1)] mb-4 overflow-hidden flex flex-col relative print-visit-card" id="visit-<?= $v['id'] ?>">
        <!-- Left vertical status bar indicator -->
        <div class="absolute left-0 top-0 bottom-0 w-1.5" style="background-color: <?= $_sbc[$v['status']] ?>;"></div>
        
        <!-- Header area -->
        <div class="pl-4 pr-3 py-3 flex justify-between items-start border-b border-slate-50">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 text-slate-500 font-black text-[10px] shrink-0"><?= $idx + 1 ?></span>
                <div class="flex flex-col min-w-0">
                    <a href="<?= $href ?>" class="font-extrabold text-slate-800 hover:text-indigo-600 text-[15px] leading-tight truncate">
                        <?= h($v['patient_name']) ?>
                    </a>
                    <div class="flex flex-wrap items-center gap-1.5 mt-1">
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider <?= $sd['bg'] ?> <?= $sd['text'] ?>">
                            <span class="w-1 h-1 rounded-full <?= $sd['dot'] ?>"></span><?= $sd['label'] ?>
                        </span>
                        <span class="px-1.5 py-0.5 bg-violet-50 text-violet-700 text-[9px] font-bold uppercase tracking-wider rounded border border-violet-100/50">
                            <?= h($_vtl[$vt] ?? 'Follow-Up') ?>
                        </span>
                    </div>
                </div>
            </div>
            <!-- Quick Actions Top Right -->
            <div class="flex gap-1.5 shrink-0 ml-2 no-print">
                <?php if ($v['status'] === 'pending'): ?>
                <button onclick="startVisit(<?= $v['id'] ?>, <?= $v['patient_id'] ?>, '<?= h($v['visit_type'] ?? 'routine') ?>', '<?= h($v['visit_subtype'] ?? '') ?>', this)"
                        class="flex items-center justify-center w-8 h-8 sm:w-auto sm:px-3 sm:py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-sm">
                    <i class="bi bi-play-fill text-[16px] sm:text-xs"></i> <span class="hidden sm:inline ml-1">Start</span>
                </button>
                <?php elseif ($v['status'] === 'en_route'): ?>
                <a href="<?= BASE_URL . firstFormUrl($v['visit_type'] ?? 'routine', $v['patient_id'], $v['id'], $v['visit_subtype'] ?? '') ?>"
                   class="flex items-center justify-center w-8 h-8 sm:w-auto sm:px-3 sm:py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all shadow-sm">
                    <i class="bi bi-file-earmark-plus-fill text-[14px] sm:text-xs"></i> <span class="hidden sm:inline ml-1">Forms</span>
                </a>
                <?php endif; ?>
                
                <!-- More menu (triggering edit modal) -->
                 <button onclick="openEditModal(<?= htmlspecialchars(json_encode(['id'=>$v['id'],'visit_time'=>$v['visit_time'],'visit_type'=>$v['visit_type'] ?? 'routine','notes'=>$v['notes'] ?? '','provider_name'=>$v['provider_name'] ?? '','visit_order'=>$v['visit_order'],'visit_date'=>$v['visit_date'],'ma_id'=>$v['ma_id'],'patient_name'=>$v['patient_name']]), ENT_QUOTES) ?>)"
                         class="flex items-center justify-center w-8 h-8 bg-slate-50 text-slate-500 rounded-xl hover:bg-slate-100 transition-colors border border-slate-100 shadow-sm">
                     <i class="bi bi-pencil-fill text-xs"></i>
                 </button>
            </div>
        </div>

        <!-- Body area -->
        <div class="pl-4 pr-3 py-2.5 flex flex-col gap-2">
            <div class="flex flex-wrap gap-x-3 gap-y-1.5 text-xs text-slate-500 font-medium">
                <?php if ($v['visit_time']): ?>
                <span class="flex items-center text-slate-700 font-bold bg-slate-100 px-2 py-0.5 rounded-md"><i class="bi bi-clock mr-1.5 text-slate-400"></i><?= date('g:i A', strtotime($v['visit_time'])) ?></span>
                <?php endif; ?>
                <?php if ($showMaName && !empty($v['ma_name'])): ?>
                <span class="flex items-center"><i class="bi bi-person-fill mr-1.5 text-indigo-400 text-xs"></i><?= h($v['ma_name']) ?></span>
                <?php elseif (!$showMaName && !empty($v['provider_name'])): ?>
                <span class="flex items-center"><i class="bi bi-stethoscope mr-1.5 text-slate-400 text-xs"></i><?= h($v['provider_name']) ?></span>
                <?php endif; ?>
                <?php if (!empty($v['visit_started_at'])): ?>
                <span class="flex items-center text-emerald-600"><i class="bi bi-play-circle-fill mr-1.5"></i>Started <?= date('g:i A', strtotime($v['visit_started_at'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($v['visit_ended_at'])): ?>
                <span class="flex items-center text-rose-500"><i class="bi bi-stop-circle-fill mr-1.5"></i>Ended <?= date('g:i A', strtotime($v['visit_ended_at'])) ?></span>
                <?php endif; ?>
            </div>

            <div class="flex flex-col gap-1.5 mt-0.5 text-xs border-l-2 border-slate-100 pl-2.5 ml-1">
                <?php if ($v['patient_address']): ?>
                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $addr ?>" target="_blank" rel="noopener"
                   class="flex items-start text-slate-500 hover:text-blue-600 leading-snug break-words pr-2">
                    <i class="bi bi-geo-alt-fill mr-2 text-slate-400 mt-0.5"></i>
                    <span><?= h($v['patient_address']) ?></span>
                </a>
                <?php endif; ?>
                <?php if ($v['patient_phone']): ?>
                <a href="tel:<?= h(preg_replace('/\D/','',$v['patient_phone'])) ?>" class="flex items-center text-slate-500 hover:text-indigo-600 w-max">
                    <i class="bi bi-telephone-fill mr-2 text-slate-400"></i><?= h($v['patient_phone']) ?>
                </a>
                <?php endif; ?>
            </div>

            <?php if ($v['notes']): ?>
            <div class="mt-1 flex items-start gap-1.5 text-[11px] text-amber-800 bg-amber-50/80 border border-amber-100 rounded-xl p-2.5 leading-tight shadow-sm mr-2">
                <i class="bi bi-exclamation-triangle-fill text-amber-500 mt-0.5 text-sm"></i>
                <span class="break-words font-medium"><?= h($v['notes']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Action / Status Toolbar -->
        <div class="flex items-center justify-between pl-4 pr-3 py-2 bg-slate-50 border-t border-slate-100 no-print">
             <!-- Status Toggles -->
             <div class="flex gap-1.5 overflow-x-auto hide-scrollbar sm:flex-wrap">
                <?php foreach ($statusDefs as $sKey => $sDef): ?>
                <button onclick="updateStatus(<?= $v['id'] ?>, '<?= $sKey ?>')"
                        class="shrink-0 flex items-center justify-center px-2 py-1.5 rounded-lg text-[10px] font-bold transition-all border shadow-sm
                               <?= $v['status'] === $sKey
                                   ? $sDef['bg'] . ' ' . $sDef['text'] . ' border-' . explode('-',$sDef['dot'])[1] . '-300 ring-1 ring-' . explode('-',$sDef['dot'])[1] . '-100 shadow-inner'
                                   : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-100 hover:text-slate-700' ?>">
                    <i class="bi <?= $sDef['icon'] ?> mr-1 text-[11px]"></i> <?= $sDef['label'] ?>
                </button>
                <?php endforeach; ?>
             </div>
             
             <!-- Special tools -->
             <div class="flex items-center gap-2 shrink-0 pl-3">
                 <?php if ($v['patient_address']): ?>
                 <button onclick="openMapPanel(<?= htmlspecialchars(json_encode($v['patient_address']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($v['patient_name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($mapsUrl), ENT_QUOTES) ?>); if(window._pdSendLocation)window._pdSendLocation();"
                         class="w-8 h-8 flex items-center justify-center rounded-full bg-white text-blue-600 border border-blue-100 hover:bg-blue-50 shadow-sm transition-colors">
                     <i class="bi bi-map-fill text-xs"></i>
                 </button>
                 <?php endif; ?>
                 <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $v['patient_id'] ?>"
                    class="w-8 h-8 flex items-center justify-center rounded-full bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm transition-colors">
                     <i class="bi bi-folder2-open text-xs"></i>
                 </a>
                 <?php if ($v['status'] === 'en_route'): ?>
                 <button onclick="endVisit(<?= $v['id'] ?>, this)" class="w-8 h-8 flex items-center justify-center rounded-full bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 shadow-sm transition-colors">
                     <i class="bi bi-stop-circle-fill text-xs"></i>
                 </button>
                 <?php elseif ($v['status'] === 'completed'): ?>
                 <button onclick="undoEndVisit(<?= $v['id'] ?>, this)" class="w-8 h-8 flex items-center justify-center rounded-full bg-amber-50 text-amber-600 border border-amber-200 hover:bg-amber-100 shadow-sm transition-colors">
                     <i class="bi bi-arrow-counterclockwise text-xs"></i>
                 </button>
                 <?php endif; ?>
             </div>
        </div>

        <!-- Quick Note Expansion -->
        <div class="border-t border-slate-100 bg-white no-print">
            <button type="button" onclick="toggleNotes(this, <?= $v['id'] ?>)"
                    class="w-full flex items-center gap-2 px-4 py-2.5 text-[11px] font-bold text-left transition-colors
                           <?= !empty($v['visit_notes']) ? 'text-amber-700 bg-amber-50/50 hover:bg-amber-50' : 'text-slate-500 hover:bg-slate-50' ?>">
                <i class="bi bi-pencil-square text-[13px]"></i>
                <?php if (!empty($v['visit_notes'])): ?>
                    <span class="truncate flex-1 font-medium"><?= h(mb_strimwidth($v['visit_notes'], 0, 60, '...')) ?></span>
                    <span class="shrink-0 px-1.5 py-0.5 bg-amber-200 text-amber-800 rounded text-[9px] font-black uppercase tracking-wide">Saved</span>
                <?php else: ?>
                    <span class="flex-1 font-medium">Add clinical note...</span>
                <?php endif; ?>
                <i class="bi bi-chevron-down text-[10px] shrink-0 note-chevron transition-transform"></i>
            </button>
            <div class="note-panel hidden px-4 pb-3 pt-2 bg-amber-50/30">
                <textarea id="note-<?= $v['id'] ?>"
                    class="w-full px-3 py-2 border border-amber-200 rounded-xl text-xs bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 resize-none transition"
                    rows="2" placeholder="Quick observation..."><?= h($v['visit_notes'] ?? '') ?></textarea>
                <div class="flex items-center justify-end gap-2 mt-2">
                    <span class="note-saved-msg hidden text-[10px] text-emerald-600 font-bold uppercase tracking-wide">
                        <i class="bi bi-check-circle-fill mr-0.5"></i> Saved
                    </span>
                    <button type="button" onclick="saveNote(<?= $v['id'] ?>, this)"
                            class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white text-[11px] font-bold rounded-lg transition-all shadow-sm">
                        Save Note
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
};
?>'''

if start_idx != -1:
    content = content[:start_idx] + new_render_visit_card + content[end_idx:]

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Schedule layout updated successfully.")