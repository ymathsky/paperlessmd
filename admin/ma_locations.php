<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireAdmin();

$pageTitle = 'MA Location Monitor';
$activeNav = 'ma_locations';

// Get all active MA/admin staff (for the sidebar list)
$staff = $pdo->query("
    SELECT id, full_name, role
    FROM staff
    WHERE active = 1 AND role IN ('ma','admin')
    ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-800">MA Location Monitor</h2>
        <p class="text-slate-500 text-sm mt-0.5">Real-time GPS locations of active MAs &mdash; updates every 60 s</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <span id="locLastUpdated" class="text-xs text-slate-400 italic">Loading&hellip;</span>
        <button id="locRefreshBtn"
                class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200
                       text-slate-700 font-semibold text-sm rounded-xl transition-all">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-5">

    <!-- ── Sidebar: staff list ─────────────────────────────────── -->
    <div class="lg:col-span-1 flex flex-col gap-3">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center gap-2">
                <i class="bi bi-people-fill text-blue-500"></i>
                <span class="font-bold text-sm text-slate-700">Staff</span>
                <span class="ml-auto text-xs text-slate-400"><?= count($staff) ?> members</span>
            </div>
            <ul id="staffList" class="divide-y divide-slate-50">
                <?php foreach ($staff as $m): ?>
                <li class="staff-row flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-slate-50 transition-colors"
                    data-id="<?= (int)$m['id'] ?>">
                    <span class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 font-bold text-xs text-white
                                 <?= $m['role'] === 'admin' ? 'bg-purple-500' : 'bg-blue-500' ?>">
                        <?= mb_strtoupper(mb_substr($m['full_name'], 0, 1)) ?>
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-700 truncate"><?= htmlspecialchars($m['full_name']) ?></p>
                        <p class="text-xs text-slate-400 capitalize"><?= htmlspecialchars($m['role']) ?></p>
                    </div>
                    <span class="staff-dot w-2.5 h-2.5 rounded-full bg-slate-200 shrink-0" title="No data"></span>
                </li>
                <?php endforeach; ?>
                <?php if (empty($staff)): ?>
                <li class="px-4 py-6 text-center text-slate-400 text-sm">No active MAs found.</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Legend -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 px-4 py-3 text-xs text-slate-500 space-y-1.5">
            <p class="font-bold text-slate-600 mb-2">Legend</p>
            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-emerald-500 shrink-0"></span> Online (&lt; 10 min)</div>
            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-amber-400 shrink-0"></span> Away (10–60 min)</div>
            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-slate-300 shrink-0"></span> Offline (&gt; 60 min)</div>
        </div>
    </div>

    <!-- ── Map ────────────────────────────────────────────────── -->
    <div class="lg:col-span-3">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div id="maMap" style="height:520px;width:100%;"></div>
        </div>

        <!-- No-location notice -->
        <div id="noLocNotice"
             class="hidden mt-4 flex items-start gap-3 bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-xl text-sm">
            <i class="bi bi-exclamation-triangle-fill shrink-0 mt-0.5"></i>
            <div>
                <strong class="block font-bold mb-0.5">No location data yet</strong>
                MAs must be logged in and have location sharing enabled in their browser.
                Locations appear here as soon as an MA opens any page.
            </div>
        </div>
    </div>

</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WLlc=" crossorigin=""></script>
<script>
(function () {
    var BASE = window._pdBase || '';
    var map  = L.map('maMap', { zoomControl: true }).setView([39.5, -98.35], 4);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    var markers  = {};   // staff_id → L.marker
    var lastUpdEl = document.getElementById('locLastUpdated');
    var noLocEl   = document.getElementById('noLocNotice');

    function makeIcon(color, initials) {
        var colors = { green: '#10b981', amber: '#f59e0b', grey: '#94a3b8' };
        var c = colors[color] || colors.grey;
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="36" height="44" viewBox="0 0 36 44">'
            + '<path d="M18 0C8.059 0 0 8.059 0 18c0 13.5 18 26 18 26S36 31.5 36 18C36 8.059 27.941 0 18 0z" fill="' + c + '"/>'
            + '<circle cx="18" cy="18" r="12" fill="white" opacity="0.95"/>'
            + '<text x="18" y="22.5" text-anchor="middle" font-size="11" font-family="sans-serif" font-weight="700" fill="' + c + '">'
            + initials + '</text></svg>';
        return L.divIcon({
            html: svg,
            iconSize: [36, 44],
            iconAnchor: [18, 44],
            popupAnchor: [0, -44],
            className: ''
        });
    }

    function statusColor(recordedAt) {
        if (!recordedAt) return 'grey';
        var diff = (Date.now() - new Date(recordedAt).getTime()) / 60000; // minutes
        if (diff < 10)  return 'green';
        if (diff < 60)  return 'amber';
        return 'grey';
    }

    function statusLabel(recordedAt) {
        if (!recordedAt) return 'No data';
        var diff = Math.round((Date.now() - new Date(recordedAt).getTime()) / 60000);
        if (diff < 1) return 'Just now';
        if (diff < 60) return diff + ' min ago';
        var h = Math.round(diff / 60);
        return h + ' hr ago';
    }

    function dotColor(color) {
        return color === 'green' ? 'bg-emerald-500' : color === 'amber' ? 'bg-amber-400' : 'bg-slate-300';
    }

    function loadLocations() {
        fetch(BASE + '/api/get_locations.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) return;
                var locs = data.locations;

                // Update staff sidebar dots
                document.querySelectorAll('.staff-row').forEach(function (row) {
                    var id  = parseInt(row.dataset.id, 10);
                    var dot = row.querySelector('.staff-dot');
                    var loc = locs.find(function (l) { return parseInt(l.staff_id, 10) === id; });
                    var col = loc ? statusColor(loc.recorded_at) : 'grey';
                    dot.className = 'staff-dot w-2.5 h-2.5 rounded-full shrink-0 ' + dotColor(col);
                    dot.title = loc ? statusLabel(loc.recorded_at) : 'No data';
                });

                // Place / update markers
                var bounds = [];
                locs.forEach(function (loc) {
                    var lat = parseFloat(loc.latitude);
                    var lng = parseFloat(loc.longitude);
                    var col = statusColor(loc.recorded_at);
                    var initials = loc.full_name.split(' ').map(function (w) { return w[0]; }).join('').toUpperCase().substring(0, 2);
                    var icon = makeIcon(col, initials);

                    var accuracy = loc.accuracy ? parseFloat(loc.accuracy).toFixed(0) + ' m' : 'Unknown';
                    var popup =
                        '<div class="text-sm" style="min-width:160px">' +
                        '<p class="font-bold text-slate-800 mb-1">' + loc.full_name + '</p>' +
                        '<p class="text-slate-500 capitalize mb-1">' + loc.role + '</p>' +
                        '<p class="text-slate-600 text-xs">Last seen: <strong>' + statusLabel(loc.recorded_at) + '</strong></p>' +
                        '<p class="text-slate-400 text-xs mt-0.5">Accuracy: ' + accuracy + '</p>' +
                        '<p class="text-slate-400 text-xs">Coords: ' + lat.toFixed(5) + ', ' + lng.toFixed(5) + '</p>' +
                        '</div>';

                    if (markers[loc.staff_id]) {
                        markers[loc.staff_id].setLatLng([lat, lng]).setIcon(icon).getPopup().setContent(popup);
                    } else {
                        markers[loc.staff_id] = L.marker([lat, lng], { icon: icon })
                            .addTo(map)
                            .bindPopup(popup);
                    }
                    bounds.push([lat, lng]);
                });

                // Click sidebar row → pan to marker
                document.querySelectorAll('.staff-row').forEach(function (row) {
                    row.onclick = function () {
                        var id = parseInt(row.dataset.id, 10);
                        if (markers[id]) {
                            map.setView(markers[id].getLatLng(), 15, { animate: true });
                            markers[id].openPopup();
                        }
                    };
                });

                if (bounds.length > 0) {
                    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
                    noLocEl.classList.add('hidden');
                } else {
                    noLocEl.classList.remove('hidden');
                }

                var now = new Date();
                lastUpdEl.textContent = 'Updated ' + now.toLocaleTimeString();
            })
            .catch(function () {
                lastUpdEl.textContent = 'Update failed';
            });
    }

    // Initial load + auto-refresh every 60 s
    loadLocations();
    setInterval(loadLocations, 60000);

    document.getElementById('locRefreshBtn').addEventListener('click', loadLocations);
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
