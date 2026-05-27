#!/usr/bin/env python3
"""
patch_ma_location_popup.py
- GPS update: shows actual local time in clinic timezone
- Adds reverse-geocoded address line to popup (Nominatim, cached)
"""
BASE = '/var/www/paperlessmd'

def read(p):
    return open(p, encoding='utf-8').read()

def write(p, c):
    open(p, 'w', encoding='utf-8').write(c)

path = BASE + '/admin/ma_locations.php'
c = read(path)

# ── 1. Replace statusLabel function to include formatted local time ──────────
old_statusLabel = """    function statusLabel(recordedAt) {
        if (!recordedAt) return 'No data';
        var diff = Math.round((Date.now() - new Date(recordedAt).getTime()) / 60000);
        if (diff < 1) return 'Just now';
        if (diff < 60) return diff + ' min ago';
        var h = Math.round(diff / 60);
        return h + ' hr ago';
    }"""

new_statusLabel = """    function statusLabel(recordedAt) {
        if (!recordedAt) return 'No data';
        var utcStr = recordedAt.endsWith('Z') ? recordedAt : recordedAt.replace(' ', 'T') + 'Z';
        var diff = Math.round((Date.now() - new Date(utcStr).getTime()) / 60000);
        var tz   = (typeof window._pdTimezone !== 'undefined') ? window._pdTimezone : 'America/Chicago';
        var fmt  = new Intl.DateTimeFormat('en-US', {
            timeZone: tz, hour: 'numeric', minute: '2-digit',
            month: 'short', day: 'numeric', hour12: true
        });
        var localTime = fmt.format(new Date(utcStr));
        var relative;
        if (diff < 1)  relative = 'Just now';
        else if (diff < 60) relative = diff + ' min ago';
        else { var h = Math.round(diff / 60); relative = h + ' hr ago'; }
        return relative + ' \u2014 ' + localTime;
    }"""

assert old_statusLabel in c, "FAIL: statusLabel not found"
c = c.replace(old_statusLabel, new_statusLabel, 1)
print("✓ statusLabel updated with timezone")

# ── 2. Add reverse-geocode cache + fetchAddress function before loadLocations ─
old_loadLocations_start = "    function loadLocations() {"

new_geocode_cache = """    // Reverse geocode cache: "lat,lng" → address string
    var _geoCache = {};
    var _geoPending = {};

    function fetchAddress(lat, lng, staffId, marker) {
        var key = lat.toFixed(5) + ',' + lng.toFixed(5);
        if (_geoCache[key]) {
            updatePopupAddress(marker, staffId, _geoCache[key]);
            return;
        }
        if (_geoPending[key]) return; // already in flight
        _geoPending[key] = true;

        var url = 'https://nominatim.openstreetmap.org/reverse?lat=' + lat
                + '&lon=' + lng + '&format=json&zoom=16';
        fetch(url, { headers: { 'Accept-Language': 'en' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var addr = data && data.display_name
                    ? formatAddress(data.address || {}, data.display_name)
                    : 'Address unavailable';
                _geoCache[key] = addr;
                delete _geoPending[key];
                updatePopupAddress(marker, staffId, addr);
            })
            .catch(function () {
                _geoCache[key] = 'Address unavailable';
                delete _geoPending[key];
            });
    }

    function formatAddress(a, fallback) {
        // Build a short readable address from Nominatim address components
        var parts = [];
        if (a.road)           parts.push(a.house_number ? a.house_number + ' ' + a.road : a.road);
        if (a.suburb || a.neighbourhood) parts.push(a.suburb || a.neighbourhood);
        if (a.city || a.town || a.village) parts.push(a.city || a.town || a.village);
        if (a.state)          parts.push(a.state);
        return parts.length >= 2 ? parts.join(', ') : fallback.split(',').slice(0, 3).join(',').trim();
    }

    function updatePopupAddress(marker, staffId, address) {
        var popup = marker.getPopup();
        if (!popup) return;
        var el = popup.getElement
            ? popup.getElement()
            : document.querySelector('.leaflet-popup-content');
        if (el) {
            var addrEl = el.querySelector('.popup-address');
            if (addrEl) addrEl.textContent = address;
        }
    }

    function loadLocations() {"""

assert old_loadLocations_start in c, "FAIL: loadLocations function start not found"
c = c.replace(old_loadLocations_start, new_geocode_cache, 1)
print("✓ Reverse geocode helpers added")

# ── 3. Add address line to popup HTML + trigger fetchAddress ─────────────────
old_popup = """                    var popup =
                        '<div class="text-sm" style="min-width:170px">' +
                        '<p class="font-bold text-slate-800 mb-0.5">' + loc.full_name + '</p>' +
                        '<p class="text-slate-500 capitalize mb-2">' + loc.role + '</p>' +
                        '<p class="text-xs mb-0.5"><span class="font-semibold text-slate-700">Session:</span> ' + onlineStatus + '</p>' +
                        '<p class="text-xs mb-0.5"><span class="font-semibold text-slate-700">GPS update:</span> ' + statusLabel(loc.recorded_at) + '</p>' +
                        '<p class="text-slate-400 text-xs mt-0.5">Accuracy: ' + accuracy + '</p>' +
                        '<p class="text-slate-400 text-xs">Coords: ' + lat.toFixed(5) + ', ' + lng.toFixed(5) + '</p>' +
                        '</div>';

                    if (markers[loc.staff_id]) {
                        markers[loc.staff_id].setLatLng([lat, lng]).setIcon(icon).getPopup().setContent(popup);
                    } else {
                        markers[loc.staff_id] = L.marker([lat, lng], { icon: icon })
                            .addTo(map)
                            .bindPopup(popup);
                    }"""

new_popup = """                    var popup =
                        '<div class="text-sm" style="min-width:200px">' +
                        '<p class="font-bold text-slate-800 mb-0.5">' + loc.full_name + '</p>' +
                        '<p class="text-slate-500 capitalize mb-2">' + loc.role + '</p>' +
                        '<p class="text-xs mb-0.5"><span class="font-semibold text-slate-700">Session:</span> ' + onlineStatus + '</p>' +
                        '<p class="text-xs mb-1"><span class="font-semibold text-slate-700">GPS update:</span> ' + statusLabel(loc.recorded_at) + '</p>' +
                        '<p class="text-xs mb-0.5"><span class="font-semibold text-slate-700">Address:</span></p>' +
                        '<p class="popup-address text-slate-500 text-xs mb-1">Looking up\u2026</p>' +
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
                    // Fetch address (cached after first load)
                    fetchAddress(lat, lng, loc.staff_id, markers[loc.staff_id]);"""

assert old_popup in c, "FAIL: popup block not found"
c = c.replace(old_popup, new_popup, 1)
print("✓ Popup updated with address line + geocode trigger")

write(path, c)
print("✓ admin/ma_locations.php saved")
print("\n✅ Done!")
