import os
import re
from bs4 import BeautifulSoup # Required by prompt, but we will use text manipulation for PHP robustness

def patch_schedule():
    filepath = r'c:\xampp\htdocs\pd\schedule.php'
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. Replace the entire Header / Top block (Filters & Date)
    header_regex = re.compile(
        r'<!-- Date nav \+ Title - Mobile Optimized -->(.*?)<style>\s*/\* Screen: hide the dedicated print layout \*/', 
        re.DOTALL
    )
    
    new_header = """<!-- Date nav + Title - Mobile Optimized V2 -->
<style>
/* Hide scrollbar for clean mobile tabs */
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.smooth-scroll { scroll-behavior: smooth; }
</style>
<div class="sticky top-[60px] z-30 bg-white/95 backdrop-blur-sm border-b border-slate-100 pb-3 mb-4 -mx-4 px-4 sm:mx-0 sm:px-0 pt-2 shadow-sm no-print">
    <div class="flex flex-col gap-3">
        <!-- Top Row: Title, GPS & Toggle -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h2 class="text-lg sm:text-xl font-extrabold text-slate-800 tracking-tight">Schedule</h2>
                <span class="text-slate-500 text-xs font-semibold mr-2 max-w-[100px] truncate"><?= h($ma['full_name']) ?></span>
                <?php if (in_array($_SESSION['role'] ?? '', ['ma', 'admin'])): ?>
                <span id="gpsStatusBadge" class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 border border-slate-200"><i class="bi bi-geo-alt"></i>Locating…</span>
                <?php endif; ?>
            </div>
            
            <?php $maParam = $viewAll ? 'all' : $viewMaId; $pParam = $filterProvider !== '' ? '&provider=' . urlencode($filterProvider) : ''; ?>
            <div class="flex items-center bg-slate-100 p-1 rounded-xl shadow-inner shrink-0">
                <a href="?date=<?= $date ?>&ma_id=<?= $maParam ?>&view=day<?= $pParam ?>" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $view === 'day' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">Day</a>
                <a href="?date=<?= $weekStart ?>&ma_id=<?= $maParam ?>&view=week<?= $pParam ?>" class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $view === 'week' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">Week</a>
            </div>
        </div>
        
        <!-- Bottom Row: Nav, Filters, Actions in one horizontal scrolling line -->
        <div class="flex items-center gap-2 overflow-x-auto hide-scrollbar pb-1">
            <!-- Date Nav -->
            <div class="flex items-center bg-indigo-50 rounded-xl border border-indigo-100 shadow-sm shrink-0">
                <?php if ($view === 'week'): ?>
                <a href="?date=<?= $prevWeek ?>&ma_id=<?= $maParam ?>&view=week<?= $pParam ?>" class="px-2 py-1.5 text-indigo-600 hover:bg-indigo-100 rounded-l-xl"><i class="bi bi-chevron-left text-xs"></i></a>
                <span class="px-2 py-1.5 text-xs font-bold text-indigo-700 whitespace-nowrap"><?= date('M j', strtotime($weekStart)) ?> – <?= date('j', strtotime($weekEnd)) ?></span>
                <a href="?date=<?= $nextWeek ?>&ma_id=<?= $maParam ?>&view=week<?= $pParam ?>" class="px-2 py-1.5 text-indigo-600 hover:bg-indigo-100 rounded-r-xl"><i class="bi bi-chevron-right text-xs"></i></a>
                <?php else: ?>
                <a href="?date=<?= $prevDate ?>&ma_id=<?= $maParam ?>&view=day<?= $pParam ?>" class="px-2.5 py-1.5 text-indigo-600 hover:bg-indigo-100 rounded-l-xl"><i class="bi bi-chevron-left text-xs"></i></a>
                <a href="?date=<?= date('Y-m-d') ?>&ma_id=<?= $maParam ?>&view=day<?= $pParam ?>" class="px-3 py-1.5 text-xs font-bold whitespace-nowrap <?= $isToday ? 'bg-indigo-600 text-white' : 'text-indigo-700 hover:bg-indigo-100' ?>"><?= $isToday ? 'Today' : date('M j', strtotime($date)) ?></a>
                <a href="?date=<?= $nextDate ?>&ma_id=<?= $maParam ?>&view=day<?= $pParam ?>" class="px-2.5 py-1.5 text-indigo-600 hover:bg-indigo-100 rounded-r-xl"><i class="bi bi-chevron-right text-xs"></i></a>
                <?php endif; ?>
            </div>
            
            <!-- Filters -->
            <?php if (isAdmin() && $allMas): ?>
            <form method="GET" class="shrink-0 flex items-center">
                <input type="hidden" name="date" value="<?= h($date) ?>"><input type="hidden" name="view" value="<?= h($view) ?>">
                <?php if ($filterProvider !== ''): ?><input type="hidden" name="provider" value="<?= h($filterProvider) ?>"><?php endif; ?>
                <select name="ma_id" onchange="this.form.submit()" class="px-2 py-1.5 border border-slate-200 rounded-xl text-xs font-bold bg-white text-slate-700 shadow-sm outline-none focus:ring-1 focus:ring-indigo-400 appearance-none">
                    <option value="all" <?= $viewAll ? 'selected' : '' ?>>All MAs</option>
                    <?php foreach ($allMas as $m): ?><option value="<?= $m['id'] ?>" <?= (!$viewAll && $m['id'] == $viewMaId) ? 'selected' : '' ?>><?= h($m['full_name']) ?></option><?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
            
            <?php if (!empty($providerOptions)): ?>
            <form method="GET" class="shrink-0 flex items-center">
                <input type="hidden" name="date" value="<?= h($date) ?>"><input type="hidden" name="view" value="<?= h($view) ?>"><input type="hidden" name="ma_id" value="<?= $viewAll ? 'all' : $viewMaId ?>">
                <select name="provider" onchange="this.form.submit()" class="px-2 py-1.5 border border-slate-200 rounded-xl text-xs font-bold bg-white text-slate-700 shadow-sm outline-none focus:ring-1 focus:ring-indigo-400 appearance-none">
                    <option value="">All Providers</option>
                    <?php foreach ($providerOptions as $pOpt): ?><option value="<?= h($pOpt) ?>" <?= $filterProvider === $pOpt ? 'selected' : '' ?>><?= h($pOpt) ?></option><?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>

            <!-- Action buttons -->
            <?php if ($view === 'day' && count($routeAddresses) >= 1): ?>
            <button onclick="openRouteMapModal()" class="shrink-0 flex items-center gap-1 px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-colors">
                <i class="bi bi-map-fill"></i> Map <span class="bg-white/20 px-1 rounded-md text-[10px]"><?= count($routeAddresses) ?></span>
            </button>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
            <a href="<?= BASE_URL ?>/admin/schedule_manage.php?date=<?= $date ?>" class="shrink-0 flex items-center px-2.5 py-1.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold shadow-sm"><i class="bi bi-pencil-fill"></i></a>
            <?php endif; ?>
            <button onclick="window.print()" class="shrink-0 px-2.5 py-1.5 bg-white border border-slate-200 text-slate-600 rounded-xl shadow-sm hover:bg-slate-50"><i class="bi bi-printer-fill text-xs"></i></button>
        </div>
    </div>
</div>

<style>
/* Screen: hide the dedicated print layout */
"""
    content = header_regex.sub(new_header, content)


    # 2. Replace stats cards
    stats_regex = re.compile(
        r'<!-- Status summary bar -->.*?<div class="grid gap-3 mb-6 print-stat-bar".*?foreach \(\$statusDefs as \$key => \$def\): \?>.*?</div>.*?<\?php endforeach; \?>\s*</div>',
        re.DOTALL
    )
    
    new_stats = """<!-- Status summary bar (Compact V2) -->
<div class="flex items-center gap-3 mb-5 overflow-x-auto hide-scrollbar pb-1 print-stat-bar">
    <?php
    $statusDefs = [
        'pending'   => ['label'=>'Pending',   'bg'=>'bg-slate-50',    'text'=>'text-slate-600',   'border'=>'border-slate-200',   'icon'=>'bi-clock'],
        'en_route'  => ['label'=>'En Route',  'bg'=>'bg-blue-50',     'text'=>'text-blue-700',    'border'=>'border-blue-200',    'icon'=>'bi-car-front-fill'],
        'completed' => ['label'=>'Completed', 'bg'=>'bg-emerald-50',  'text'=>'text-emerald-700', 'border'=>'border-emerald-200', 'icon'=>'bi-check-circle-fill'],
        'missed'    => ['label'=>'Missed',    'bg'=>'bg-rose-50',     'text'=>'text-rose-700',    'border'=>'border-rose-200',    'icon'=>'bi-x-circle-fill'],
    ];
    $displayCounts = ($view === 'week') ? $weekCounts : $counts;
    foreach ($statusDefs as $key => $def): ?>
    <div class="flex-shrink-0 flex items-center gap-2 <?= $def['bg'] ?> border <?= $def['border'] ?> rounded-2xl px-3.5 py-2 shadow-sm">
        <i class="bi <?= $def['icon'] ?> <?= $def['text'] ?> text-[15px]"></i>
        <div class="flex flex-col leading-none">
            <span class="text-[13px] font-extrabold <?= $def['text'] ?>"><?= $displayCounts[$key] ?></span>
            <span class="text-[10px] font-medium <?= $def['text'] ?> opacity-80 uppercase tracking-wide"><?= $def['label'] ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>"""
    
    content = stats_regex.sub(new_stats, content)

    # 3. Replace Visit Card
    # We will replace the $renderVisitCard lambda body entirely
    
    visit_card_pattern = re.compile(
        r'(\$_vtl = \[.*?\];.*?\$renderVisitCard = function.*?\$idx, bool \$showMaName\) use \(\$statusDefs, \$_sbc, \$_vtl\): void \{.*?)(<div class="bg-white rounded-2xl border border-slate-100 shadow-\[0_2px_8px_-4px_rgba\(0,0,0,0\.1\)\].*?<!-- Quick Note Expansion -->.*?\</div>\s*</div>\s*<\?php\s*\};\s*\?>)',
        re.DOTALL
    )

    new_visit_card_tail = r"""
    <div class="bg-white rounded-2xl shadow-sm mb-4 overflow-hidden flex flex-col print-visit-card" id="visit-<?= $v['id'] ?>" style="border-left: 5px solid <?= $_sbc[$v['status']] ?>; border-top: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;">
        
        <!-- Header area -->
        <div class="px-4 py-3 flex justify-between items-start border-b border-slate-50">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 text-slate-500 font-black text-[10px] shrink-0"><?= $idx + 1 ?></span>
                <div class="flex flex-col min-w-0">
                    <a href="<?= $href ?>" class="font-extrabold text-slate-800 hover:text-indigo-600 text-[16px] leading-tight truncate tracking-tight">
                        <?= h($v['patient_name']) ?>
                    </a>
                    <div class="flex flex-wrap items-center gap-1.5 mt-1">
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider <?= $sd['bg'] ?> <?= $sd['text'] ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $sd['dot'] ?>"></span><?= $sd['label'] ?>
                        </span>
                        <span class="px-1.5 py-0.5 bg-violet-50 text-violet-700 text-[9px] font-bold uppercase tracking-wider rounded border border-violet-100/50">
                            <?= h($_vtl[$vt] ?? 'Follow-Up') ?>
                        </span>
                    </div>
                </div>
            </div>
            <!-- Edit Icon -->
            <button onclick="openEditModal(<?= htmlspecialchars(json_encode(['id'=>$v['id'],'visit_time'=>$v['visit_time'],'visit_type'=>$v['visit_type'] ?? 'routine','notes'=>$v['notes'] ?? '','provider_name'=>$v['provider_name'] ?? '','visit_order'=>$v['visit_order'],'visit_date'=>$v['visit_date'],'ma_id'=>$v['ma_id'],'patient_name'=>$v['patient_name']]), ENT_QUOTES) ?>)" class="shrink-0 w-7 h-7 flex items-center justify-center bg-slate-50 text-slate-400 rounded-full hover:bg-slate-100 transition-colors ml-2 no-print border border-slate-100">
                <i class="bi bi-pencil-fill text-[11px]"></i>
            </button>
        </div>

        <!-- Body area -->
        <div class="px-4 py-3 flex flex-col gap-2 bg-slate-50/30">
            <div class="flex flex-wrap gap-x-4 gap-y-2 text-[11px] text-slate-500 font-medium">
                <?php if ($v['visit_time']): ?>
                <span class="flex items-center text-slate-700 font-bold"><i class="bi bi-clock mr-1 text-slate-400"></i><?= date('g:i A', strtotime($v['visit_time'])) ?></span>
                <?php endif; ?>
                <?php if ($showMaName && !empty($v['ma_name'])): ?>
                <span class="flex items-center"><i class="bi bi-person-fill mr-1 text-indigo-400"></i><?= h($v['ma_name']) ?></span>
                <?php elseif (!$showMaName && !empty($v['provider_name'])): ?>
                <span class="flex items-center"><i class="bi bi-stethoscope mr-1 text-slate-400"></i><?= h($v['provider_name']) ?></span>
                <?php endif; ?>
                <?php if (!empty($v['visit_started_at'])): ?>
                <span class="flex items-center text-emerald-600"><i class="bi bi-play-circle-fill mr-1"></i>Started <?= date('g:i A', strtotime($v['visit_started_at'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($v['visit_ended_at'])): ?>
                <span class="flex items-center text-rose-500"><i class="bi bi-stop-circle-fill mr-1"></i>Ended <?= date('g:i A', strtotime($v['visit_ended_at'])) ?></span>
                <?php endif; ?>
            </div>

            <div class="flex flex-col gap-1.5 mt-1 text-[11px]">
                <?php if ($v['patient_address']): ?>
                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $addr ?>" target="_blank" rel="noopener" class="flex items-start text-slate-500 hover:text-blue-600 leading-snug break-words">
                    <i class="bi bi-geo-alt-fill mr-1.5 text-slate-400 mt-0.5"></i>
                    <span><?= h($v['patient_address']) ?></span>
                </a>
                <?php endif; ?>
                <?php if ($v['patient_phone']): ?>
                <a href="tel:<?= h(preg_replace('/\D/','',$v['patient_phone'])) ?>" class="flex items-center text-slate-500 hover:text-indigo-600 w-max">
                    <i class="bi bi-telephone-fill mr-1.5 text-slate-400"></i><?= h($v['patient_phone']) ?>
                </a>
                <?php endif; ?>
            </div>

            <?php if ($v['notes']): ?>
            <div class="mt-2 flex items-start gap-1.5 text-[11px] text-amber-800 bg-amber-50/80 border border-amber-100 rounded-xl p-2.5 leading-tight shadow-sm mr-2">
                <i class="bi bi-exclamation-triangle-fill text-amber-500 mt-0.5 text-sm"></i>
                <span class="break-words font-medium"><?= h($v['notes']) ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Status Adjustments Row -->
        <div class="px-4 py-2 border-t border-slate-100 bg-slate-50 flex items-center justify-between no-print overflow-x-auto hide-scrollbar">
            <div class="flex gap-1.5">
                <?php foreach ($statusDefs as $sKey => $sDef): ?>
                <button onclick="updateStatus(<?= $v['id'] ?>, '<?= $sKey ?>')" class="shrink-0 flex items-center justify-center px-2 py-1.5 rounded-lg text-[10px] font-bold transition-all border shadow-sm <?= $v['status'] === $sKey ? $sDef['bg'] . ' ' . $sDef['text'] . ' border-' . explode('-',$sDef['dot'])[1] . '-300 ring-1 ring-' . explode('-',$sDef['dot'])[1] . '-100 shadow-inner' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-100 hover:text-slate-700' ?>">
                    <i class="bi <?= $sDef['icon'] ?> mr-1 text-[11px]"></i> <?= $sDef['label'] ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Action / Primary Footer -->
        <div class="px-4 py-2.5 border-t border-slate-100 bg-white flex items-center justify-between no-print">
            <div class="flex items-center gap-2">
                <?php if ($v['status'] === 'pending'): ?>
                <button onclick="startVisit(<?= $v['id'] ?>, <?= $v['patient_id'] ?>, '<?= h($v['visit_type'] ?? 'routine') ?>', '<?= h($v['visit_subtype'] ?? '') ?>', this)"
                        class="flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition-all shadow-sm">
                    <i class="bi bi-play-fill mr-1 text-sm"></i> Start Visit
                </button>
                <?php elseif ($v['status'] === 'en_route'): ?>
                <a href="<?= BASE_URL . firstFormUrl($v['visit_type'] ?? 'routine', $v['patient_id'], $v['id'], $v['visit_subtype'] ?? '') ?>"
                   class="flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition-all shadow-sm">
                    <i class="bi bi-file-earmark-plus-fill mr-1 text-sm"></i> Forms
                </a>
                <?php endif; ?>
                
                <?php if ($v['status'] === 'en_route'): ?>
                 <button onclick="endVisit(<?= $v['id'] ?>, this)" class="px-3 py-1.5 flex items-center bg-rose-50 text-rose-600 rounded-lg text-xs font-bold border border-rose-200 hover:bg-rose-100 shadow-sm transition-colors">
                     End Visit
                 </button>
                 <?php elseif ($v['status'] === 'completed'): ?>
                 <button onclick="undoEndVisit(<?= $v['id'] ?>, this)" class="px-3 py-1.5 flex items-center bg-amber-50 text-amber-600 rounded-lg text-xs font-bold border border-amber-200 hover:bg-amber-100 shadow-sm transition-colors">
                     Undo End
                 </button>
                 <?php endif; ?>
            </div>
            
            <div class="flex items-center gap-2 shrink-0">
                <?php if ($v['patient_address']): ?>
                 <button onclick="openMapPanel(<?= htmlspecialchars(json_encode($v['patient_address']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($v['patient_name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($mapsUrl), ENT_QUOTES) ?>); if(window._pdSendLocation)window._pdSendLocation();"
                         class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-50 text-blue-600 border border-slate-200 hover:bg-slate-100 shadow-sm transition-colors" title="Map">
                     <i class="bi bi-map-fill text-xs"></i>
                 </button>
                 <?php endif; ?>
                 <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $v['patient_id'] ?>"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-50 text-slate-600 border border-slate-200 hover:bg-slate-100 shadow-sm transition-colors" title="Open Folder">
                     <i class="bi bi-folder2-open text-xs"></i>
                 </a>
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
                    class="w-full px-3 py-2 border border-amber-200 rounded-xl text-xs bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 resize-none transition shadow-sm"
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
?>"""
    m = visit_card_pattern.search(content)
    if m:
        content = content[:m.start(2)] + new_visit_card_tail + content[m.end(2):]
    else:
        print("Warning: Visit card pattern not found.")
    
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
        
    print("Patched schedule.php successfully!")

if __name__ == "__main__":
    patch_schedule()
