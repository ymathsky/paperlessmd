from PIL import Image, ImageDraw
import os

def make_icon(size):
    img  = Image.new('RGBA', (size, size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)

    # ── Gradient background (deep navy → blue) ───────────────────────
    for y in range(size):
        t = y / (size - 1)
        r = int(15  + (30  - 15)  * t)
        g = int(23  + (64  - 23)  * t)
        b = int(42  + (175 - 42)  * t)
        draw.line([(0, y), (size - 1, y)], fill=(r, g, b, 255))

    # Clip to rounded-rectangle mask
    radius = int(size * 0.22)
    mask   = Image.new('L', (size, size), 0)
    ImageDraw.Draw(mask).rounded_rectangle(
        [(0, 0), (size - 1, size - 1)], radius=radius, fill=255)
    img.putalpha(mask)
    draw = ImageDraw.Draw(img)

    s = size / 192  # scale factor relative to 192 reference

    # ── Clipboard body ────────────────────────────────────────────────
    bx1 = int(34 * s); bx2 = int(158 * s)
    by1 = int(42 * s); by2 = int(168 * s)
    br  = int(12 * s)
    draw.rounded_rectangle([(bx1, by1), (bx2, by2)], radius=br,
                            fill=(255, 255, 255, 240))

    # ── Clipboard clip (top center) ───────────────────────────────────
    cx1 = int(72 * s);  cx2 = int(120 * s)
    cy1 = int(34 * s);  cy2 = int(56 * s)
    draw.rounded_rectangle([(cx1, cy1), (cx2, cy2)], radius=int(10 * s),
                            fill=(15, 23, 42, 255))
    # inner hole highlight
    draw.rounded_rectangle(
        [(int(82*s), int(40*s)), (int(110*s), int(50*s))],
        radius=int(5*s), fill=(96, 165, 250, 255))

    # ── Text lines (3 rows, blue tint) ───────────────────────────────
    lx1 = int(50 * s); lx2 = int(142 * s)
    lh  = max(2, int(6 * s))
    for row_y in [int(76*s), int(92*s), int(108*s)]:
        draw.rounded_rectangle(
            [(lx1, row_y), (lx2 if row_y < int(100*s) else int(120*s), row_y + lh)],
            radius=lh // 2, fill=(191, 219, 254, 200))

    # ── ECG / heartbeat line (teal) ───────────────────────────────────
    ey   = int(140 * s)
    amp  = int(22 * s)
    ex1  = int(44 * s);  ex2  = int(148 * s)
    ew   = ex2 - ex1
    pts  = [
        (ex1,                  ey),
        (ex1 + int(ew*0.25),   ey),
        (ex1 + int(ew*0.38),   ey - amp),
        (ex1 + int(ew*0.46),   ey + int(amp*0.65)),
        (ex1 + int(ew*0.54),   ey - int(amp*0.25)),
        (ex1 + int(ew*0.63),   ey),
        (ex2,                  ey),
    ]
    lw = max(2, int(5 * s))
    for i in range(len(pts) - 1):
        draw.line([pts[i], pts[i+1]], fill=(20, 184, 166, 255), width=lw)

    # ── "MD" badge (bottom-right corner) ─────────────────────────────
    # Small circle badge
    boff = int(10 * s); brad = int(26 * s)
    bcy  = size - boff - brad; bcx = size - boff - brad
    draw.ellipse([(bcx - brad, bcy - brad), (bcx + brad, bcy + brad)],
                 fill=(20, 184, 166, 255))
    # Draw "MD" letters manually as pixel blocks (no font needed)
    # Scale the letter drawing to badge size
    fs  = brad * 0.72  # font scale
    ox  = bcx - int(fs * 1.05)
    oy  = bcy - int(fs * 0.72)
    lth = max(1, int(fs * 0.17))  # line thickness

    def hline(x1, y1, x2):
        draw.rectangle([(x1, y1), (x2, y1 + lth - 1)], fill=(255, 255, 255, 255))

    def vline(x1, y1, y2):
        draw.rectangle([(x1, y1), (x1 + lth - 1, y2)], fill=(255, 255, 255, 255))

    # M: two vertical strokes + diagonal V in middle
    mw = int(fs * 0.9); mh = int(fs * 1.1)
    vline(ox,        oy,        oy + mh)           # left vert
    vline(ox + mw,   oy,        oy + mh)           # right vert
    # diagonals (approximate with line)
    mid_x = ox + mw // 2
    mid_y = oy + mh // 2
    draw.line([(ox + lth, oy + lth), (mid_x, mid_y)],
              fill=(255,255,255,255), width=lth)
    draw.line([(ox + mw - lth, oy + lth), (mid_x, mid_y)],
              fill=(255,255,255,255), width=lth)

    # D: vertical stroke + rounded right side
    dx  = ox + mw + int(fs * 0.25)
    dw  = int(fs * 0.75)
    vline(dx, oy, oy + mh)
    # top and bottom horizontals
    hline(dx, oy,        dx + dw // 2)
    hline(dx, oy + mh,   dx + dw // 2)
    # right arc approximated by short lines
    steps = 8
    for i in range(steps + 1):
        angle_frac = i / steps
        ax = dx + int((dw * 0.5) * (1 - abs(1 - 2*angle_frac)))
        ay = oy + int(mh * angle_frac)
        draw.rectangle([(ax, ay), (ax + lth, ay + max(1, int(mh/steps)))],
                        fill=(255,255,255,255))

    return img


# Generate all sizes
out_dir = '/var/www/paperlessmd/assets/img'
sizes   = {'pwa-icon-192.png': 192, 'pwa-icon-512.png': 512,
           'apple-touch-icon.png': 180, 'favicon-32.png': 32, 'favicon-16.png': 16}

for fname, sz in sizes.items():
    icon = make_icon(sz)
    path = os.path.join(out_dir, fname)
    icon.save(path, 'PNG', optimize=True)
    print(f'{fname}: {sz}x{sz} → {os.path.getsize(path)} bytes')

# favicon.ico (multi-size)
icons = [make_icon(s) for s in [16, 32, 48]]
ico_path = os.path.join(out_dir, 'favicon.ico')
icons[0].save(ico_path, format='ICO', sizes=[(16,16),(32,32),(48,48)], append_images=icons[1:])
print(f'favicon.ico → {os.path.getsize(ico_path)} bytes')

# Also write a clean SVG for the manifest (scalable)
svg = '''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#0f1726"/>
      <stop offset="100%" stop-color="#1e40af"/>
    </linearGradient>
    <clipPath id="rr">
      <rect width="192" height="192" rx="42" ry="42"/>
    </clipPath>
  </defs>
  <rect width="192" height="192" rx="42" fill="url(#bg)"/>
  <g clip-path="url(#rr)">
    <!-- Clipboard body -->
    <rect x="34" y="42" width="124" height="126" rx="12" fill="white" fill-opacity="0.93"/>
    <!-- Clip -->
    <rect x="72" y="34" width="48" height="22" rx="10" fill="#0f1726"/>
    <rect x="82" y="40" width="28" height="10" rx="5" fill="#60a5fa"/>
    <!-- Lines -->
    <rect x="50" y="76" width="92" height="6" rx="3" fill="#bfdbfe" fill-opacity="0.85"/>
    <rect x="50" y="92" width="92" height="6" rx="3" fill="#bfdbfe" fill-opacity="0.85"/>
    <rect x="50" y="108" width="70" height="6" rx="3" fill="#bfdbfe" fill-opacity="0.85"/>
    <!-- ECG line -->
    <polyline points="44,140 68,140 79,118 88,153 96,130 104,140 148,140"
              fill="none" stroke="#14b8a6" stroke-width="5"
              stroke-linecap="round" stroke-linejoin="round"/>
  </g>
  <!-- MD badge -->
  <circle cx="154" cy="154" r="30" fill="#14b8a6"/>
  <text x="154" y="161" text-anchor="middle" font-family="Arial,sans-serif"
        font-size="18" font-weight="900" fill="white">MD</text>
</svg>'''

svg_path = os.path.join(out_dir, 'pmd-logo.svg')
with open(svg_path, 'w') as f:
    f.write(svg)
print(f'pmd-logo.svg written')
print('All done.')
