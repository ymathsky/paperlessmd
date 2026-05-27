#!/usr/bin/env python3
"""
wound_service.py — Wound measurement microservice
Listens on 127.0.0.1:5001
Primary:  GPT-4o Vision API  (set OPENAI_API_KEY in systemd service environment)
Fallback: OpenCV HSV segmentation + HoughLines ruler detection
"""

from flask import Flask, request, jsonify
import cv2
import numpy as np
import base64
import re
import os
import json

try:
    from openai import OpenAI as _OpenAI
    _OPENAI_AVAILABLE = True
except ImportError:
    _OPENAI_AVAILABLE = False

app = Flask(__name__)

# ── Image helpers ─────────────────────────────────────────────────────────────

def decode_image(b64_string):
    """Decode base64 image (handles data: URLs) → numpy BGR array."""
    b64_string = re.sub(r'^data:image/[^;]+;base64,', '', b64_string)
    img_bytes = base64.b64decode(b64_string)
    arr = np.frombuffer(img_bytes, np.uint8)
    return cv2.imdecode(arr, cv2.IMREAD_COLOR)


def encode_image(img, quality=82):
    """Encode numpy BGR array → base64 JPEG string."""
    _, buf = cv2.imencode('.jpg', img, [cv2.IMWRITE_JPEG_QUALITY, quality])
    return base64.b64encode(buf).decode('utf-8')


# ── GPT-4o Vision measurement ─────────────────────────────────────────────────

_GPT4O_PROMPT = (
    "You are a clinical wound measurement AI. Analyze this wound photo carefully.\n\n"
    "Return ONLY valid JSON — no markdown fences, no extra text:\n"
    "{\n"
    '  "ruler_detected": true or false,\n'
    '  "ruler_type": "e.g. disposable cm ruler / Organogenesis wound card / cm tape — or null",\n'
    '  "wound_detected": true or false,\n'
    '  "wound_bbox": [x1,y1,x2,y2] as 0.0-1.0 fractions of image width/height '
    'tightly enclosing ONLY the wound bed (not surrounding skin), or null,\n'
    '  "area_cm2": wound area in square centimeters using ruler for scale, or null,\n'
    '  "length_cm": longest wound dimension in cm, or null,\n'
    '  "width_cm": shortest wound dimension in cm, or null,\n'
    '  "granulation_pct": integer 0-100 percent of wound bed that is red granulation tissue,\n'
    '  "slough_pct": integer 0-100 percent that is yellow/fibrinous slough,\n'
    '  "eschar_pct": integer 0-100 percent that is black/brown eschar/necrosis,\n'
    '  "confidence": "high", "medium", or "low"\n'
    "}\n\n"
    "Rules:\n"
    "- wound_bbox must enclose ONLY the wound bed, not surrounding intact skin\n"
    "- If a ruler/measurement card is visible, all cm measurements MUST be ruler-based\n"
    "- If no ruler, set ruler_detected=false and still estimate from anatomical context\n"
    "- granulation_pct + slough_pct + eschar_pct should sum to approximately 100"
)


def measure_with_gpt4o(img_b64, api_key):
    """
    Send the wound image to GPT-4o Vision and parse the JSON measurement response.
    Returns a dict on success or None on any failure.
    """
    if not _OPENAI_AVAILABLE:
        return None
    try:
        client = _OpenAI(api_key=api_key)
        resp = client.chat.completions.create(
            model='gpt-4o',
            messages=[{
                'role': 'user',
                'content': [
                    {'type': 'text', 'text': _GPT4O_PROMPT},
                    {'type': 'image_url', 'image_url': {
                        'url': 'data:image/jpeg;base64,' + img_b64,
                        'detail': 'high',
                    }},
                ],
            }],
            max_tokens=500,
            temperature=0,
        )
        text = resp.choices[0].message.content.strip()
        # Strip markdown code fences if the model wraps in them
        text = re.sub(r'^```(?:json)?\s*|\s*```$', '', text, flags=re.MULTILINE).strip()
        return json.loads(text)
    except Exception:
        return None


# ── Ruler detection ───────────────────────────────────────────────────────────

def detect_ruler_scale(img):
    """
    Detect a ruler in the image and return (ruler_line, pixels_per_cm).

    Strategy:
    1. Find the longest nearly-horizontal or nearly-vertical line (ruler edge)
       using Hough Line Transform.
    2. Sample a narrow strip along that line and detect periodic tick marks.
    3. Measure median pixel spacing between ticks → pixels per cm.

    Returns: (ruler_line [x1,y1,x2,y2], pixels_per_cm float) or (None, None).
    """
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    h, w = gray.shape

    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    edges   = cv2.Canny(blurred, 30, 100, apertureSize=3)

    min_len = int(min(w, h) * 0.20)   # ruler must be ≥20% of shorter side
    lines   = cv2.HoughLinesP(
        edges, 1, np.pi / 180,
        threshold=60,
        minLineLength=min_len,
        maxLineGap=15
    )
    if lines is None:
        return None, None

    # Keep only nearly-horizontal (±15°) or nearly-vertical (75-105°) lines
    candidates = []
    for line in lines:
        x1, y1, x2, y2 = line[0]
        length = float(np.hypot(x2 - x1, y2 - y1))
        angle  = abs(np.degrees(np.arctan2(y2 - y1, x2 - x1)))
        if angle < 15 or angle > 165:
            candidates.append((length, 'h', line[0]))
        elif 75 < angle < 105:
            candidates.append((length, 'v', line[0]))

    if not candidates:
        return None, None

    candidates.sort(key=lambda c: c[0], reverse=True)
    _, orientation, best_line = candidates[0]
    x1, y1, x2, y2 = best_line

    # Sample strip along the detected ruler line
    if orientation == 'h':
        cx_ruler = int((x1 + x2) / 2)
        cy_ruler = int((y1 + y2) / 2)
        x_from = max(0, min(x1, x2))
        x_to   = min(w, max(x1, x2))
        y_from = max(0, cy_ruler - 25)
        y_to   = min(h, cy_ruler + 25)
        strip  = gray[y_from:y_to, x_from:x_to]
        sum_axis = 0   # ticks are vertical lines → sum across rows → per-column profile
    else:
        cx_ruler = int((x1 + x2) / 2)
        cy_ruler = int((y1 + y2) / 2)
        y_from = max(0, min(y1, y2))
        y_to   = min(h, max(y1, y2))
        x_from = max(0, cx_ruler - 25)
        x_to   = min(w, cx_ruler + 25)
        strip  = gray[y_from:y_to, x_from:x_to]
        sum_axis = 1   # ticks are horizontal lines → sum across cols → per-row profile

    if strip.size == 0:
        return best_line, None

    strip_edges = cv2.Canny(strip, 20, 80)
    profile     = strip_edges.sum(axis=sum_axis).astype(float)
    if profile.max() == 0:
        return best_line, None

    threshold     = profile.max() * 0.25
    tick_positions = np.where(profile > threshold)[0]

    if len(tick_positions) < 4:
        return best_line, None

    # Cluster nearby ticks into single marks
    clusters = []
    cs = tick_positions[0]
    prev = tick_positions[0]
    for pos in tick_positions[1:]:
        if pos - prev > 5:
            clusters.append(int((cs + prev) / 2))
            cs = pos
        prev = pos
    clusters.append(int((cs + prev) / 2))

    if len(clusters) < 4:
        return best_line, None

    spacings = np.diff(clusters)
    med = float(np.median(spacings))
    spacings = spacings[(spacings > med * 0.4) & (spacings < med * 2.2)]

    if len(spacings) < 2:
        return best_line, None

    tick_px = float(np.median(spacings))
    if tick_px < 4:
        return best_line, None

    # Assume 1mm ticks → pixels_per_cm = tick_px * 10
    ppc = tick_px * 10
    if ppc < 15:
        ppc = tick_px * 5    # 2mm ticks
    elif ppc > 600:
        ppc = tick_px * 2    # 5mm ticks

    return best_line, float(ppc)


# ── Wound detection ───────────────────────────────────────────────────────────

def detect_wound(img):
    """
    Detect wound contour using targeted HSV segmentation.
    Uses higher saturation thresholds to separate wound tissue from surrounding skin.
    Handles: granulation (sat-red), slough (yellow/tan), eschar (brown), dark cavity.

    Returns: (contour, mask) or (None, None).
    """
    h, w = img.shape[:2]
    hsv = cv2.cvtColor(img, cv2.COLOR_BGR2HSV)

    # Saturated red — granulation tissue
    # S > 100 excludes periwound pink/inflamed skin (S typically 20-80)
    mask_r1     = cv2.inRange(hsv, (0,   100, 40),  (20,  255, 255))
    mask_r2     = cv2.inRange(hsv, (155, 100, 40),  (180, 255, 255))

    # Yellow / tan / ochre — slough, exudate
    # S > 70 excludes normal tan skin (S typically 20-60)
    mask_yellow = cv2.inRange(hsv, (14,  70, 55),  (38,  255, 225))

    # Brown / dark-tan — eschar, necrotic tissue
    mask_brown  = cv2.inRange(hsv, (8,   55, 20),  (30,  220, 150))

    # Dark cavity — very deep wound, necrosis (require S>15 to exclude neutral dark backgrounds)
    mask_dark   = cv2.inRange(hsv, (0,   15,  0),  (180, 200,  65))

    wound_mask = mask_r1
    for m in [mask_r2, mask_yellow, mask_brown, mask_dark]:
        wound_mask = cv2.bitwise_or(wound_mask, m)

    # Remove bright regions: ruler, glare, gauze
    bright = cv2.inRange(hsv, (0, 0, 225), (180, 28, 255))
    wound_mask = cv2.bitwise_and(wound_mask, cv2.bitwise_not(bright))

    # Morphological cleanup
    k_close = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (23, 23))
    k_open  = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (7,  7))
    wound_mask = cv2.morphologyEx(wound_mask, cv2.MORPH_CLOSE, k_close)
    wound_mask = cv2.morphologyEx(wound_mask, cv2.MORPH_OPEN,  k_open)

    contours, _ = cv2.findContours(wound_mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    if not contours:
        return None, None

    img_area = h * w
    # min 2% filters out tiny text/logo artifacts; max 78% allows large wounds
    valid = [c for c in contours
             if img_area * 0.02 < cv2.contourArea(c) < img_area * 0.78]
    if not valid:
        return None, None

    valid.sort(key=cv2.contourArea, reverse=True)
    return valid[0], wound_mask


def detect_wound_grabcut(img):
    """
    Fallback wound detection using GrabCut.
    Assumes wound is roughly centered in the frame (standard wound photo practice).
    Returns: (contour, mask) or (None, None).
    """
    h, w = img.shape[:2]
    # Use center 76% of image as the initial foreground rectangle
    mx, my = int(w * 0.12), int(h * 0.12)
    rect = (mx, my, w - 2 * mx, h - 2 * my)

    gc_mask = np.zeros((h, w), np.uint8)
    bgd = np.zeros((1, 65), np.float64)
    fgd = np.zeros((1, 65), np.float64)

    try:
        cv2.grabCut(img, gc_mask, rect, bgd, fgd, 5, cv2.GC_INIT_WITH_RECT)
    except Exception:
        return None, None

    fg = np.where(
        (gc_mask == cv2.GC_FGD) | (gc_mask == cv2.GC_PR_FGD), 255, 0
    ).astype(np.uint8)

    k = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (15, 15))
    fg = cv2.morphologyEx(fg, cv2.MORPH_CLOSE, k)
    fg = cv2.morphologyEx(fg, cv2.MORPH_OPEN,  k)

    contours, _ = cv2.findContours(fg, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    if not contours:
        return None, None

    img_area = h * w
    valid = [c for c in contours
             if img_area * 0.005 < cv2.contourArea(c) < img_area * 0.70]
    if not valid:
        return None, None

    valid.sort(key=cv2.contourArea, reverse=True)
    return valid[0], fg


# ── Annotation ────────────────────────────────────────────────────────────────

def annotate_image(img, wound_contour, ruler_line, pixels_per_cm,
                   area_cm2, length_cm, width_cm, ruler_ok=None):
    """Draw contour, oriented bounding box, ruler overlay, and text metrics."""
    out  = img.copy()
    h, w = out.shape[:2]
    font = cv2.FONT_HERSHEY_SIMPLEX
    fs   = max(0.55, min(1.4, w / 900))
    th   = max(1, int(fs * 2.2))

    if wound_contour is not None:
        # Green contour outline
        cv2.drawContours(out, [wound_contour], -1, (0, 200, 60), 3)

        # Orange oriented bounding box
        rect = cv2.minAreaRect(wound_contour)
        box  = np.intp(cv2.boxPoints(rect))
        cv2.drawContours(out, [box], 0, (255, 140, 0), 2)

        # Centroid
        M  = cv2.moments(wound_contour)
        cx = int(M['m10'] / M['m00']) if M['m00'] else w // 2
        cy = int(M['m01'] / M['m00']) if M['m00'] else h // 2

        lines_text = []
        if area_cm2 is not None:
            lines_text.append('Area: %.2f sq cm' % area_cm2)
        if length_cm is not None:
            lines_text.append('L: %.1f  W: %.1f cm' % (length_cm, width_cm))
        _ruler_ok = ruler_ok if ruler_ok is not None else bool(pixels_per_cm)
        if not _ruler_ok:
            lines_text.append('(no ruler - rough estimate)')

        line_h   = int(32 * fs)
        block_h  = len(lines_text) * line_h + 12
        ty_start = (cy - block_h - 10) if cy > h // 3 else (cy + 20)

        for i, txt in enumerate(lines_text):
            ty = ty_start + i * line_h + int(line_h * 0.75)
            (tw, t_ht), _ = cv2.getTextSize(txt, font, fs, th)
            bx1 = max(0,     cx - tw // 2 - 8)
            bx2 = min(w - 1, cx + tw // 2 + 8)
            by1 = max(0,     ty - t_ht - 5)
            by2 = min(h - 1, ty + 5)
            cv2.rectangle(out, (bx1, by1), (bx2, by2), (15, 10, 40), -1)
            cv2.putText(out, txt, (cx - tw // 2, ty),
                        font, fs, (255, 255, 255), th, cv2.LINE_AA)

    # Cyan ruler line overlay
    if ruler_line is not None:
        x1, y1, x2, y2 = ruler_line
        cv2.line(out, (x1, y1), (x2, y2), (0, 220, 255), 3)
        mid_x = (x1 + x2) // 2
        mid_y = (y1 + y2) // 2
        cv2.putText(out, 'Ruler', (mid_x - 25, max(10, mid_y - 12)),
                    font, 0.55, (0, 220, 255), 2, cv2.LINE_AA)

    return out


# ── Routes ────────────────────────────────────────────────────────────────────

@app.route('/health')
def health():
    return jsonify({'ok': True, 'service': 'wound-measure'})


@app.route('/measure', methods=['POST'])
def measure():
    try:
        data = request.get_json(force=True, silent=True) or {}
        if 'image' not in data:
            return jsonify({'success': False, 'error': 'No image provided'}), 400

        img = decode_image(data['image'])
        if img is None:
            return jsonify({'success': False, 'error': 'Could not decode image'}), 400

        # Downscale to max 1600px on the longer side for consistent processing
        max_side = 1600
        h, w = img.shape[:2]
        if max(h, w) > max_side:
            scale = max_side / max(h, w)
            img   = cv2.resize(img, (int(w * scale), int(h * scale)),
                               interpolation=cv2.INTER_AREA)

        area_cm2 = length_cm = width_cm = None
        wound_contour = ruler_line = pixels_per_cm = None
        ruler_detected = wound_detected = False
        tissue_info = None
        method = 'opencv'

        # ── Try GPT-4o Vision first ───────────────────────────────────────────
        api_key = os.environ.get('OPENAI_API_KEY')
        if api_key:
            gpt = measure_with_gpt4o(encode_image(img), api_key)
            if gpt and gpt.get('wound_detected'):
                method         = 'gpt4o'
                wound_detected = True
                ruler_detected = bool(gpt.get('ruler_detected'))
                area_cm2       = gpt.get('area_cm2')
                length_cm      = gpt.get('length_cm')
                width_cm       = gpt.get('width_cm')

                # Build rectangular contour from fractional bbox for annotation
                bbox = gpt.get('wound_bbox')
                if bbox and len(bbox) == 4:
                    x1 = max(0, int(bbox[0] * w))
                    y1 = max(0, int(bbox[1] * h))
                    x2 = min(w, int(bbox[2] * w))
                    y2 = min(h, int(bbox[3] * h))
                    wound_contour = np.array(
                        [[x1, y1], [x2, y1], [x2, y2], [x1, y2]],
                        dtype=np.intp
                    ).reshape(-1, 1, 2)

                # Still run OpenCV ruler detection for the visual line overlay
                ruler_line, _ = detect_ruler_scale(img)

                tissue_info = {k: gpt.get(k) for k in
                               ('granulation_pct', 'slough_pct', 'eschar_pct',
                                'confidence', 'ruler_type')}

        # ── OpenCV fallback ───────────────────────────────────────────────────
        if method == 'opencv':
            ruler_line, pixels_per_cm = detect_ruler_scale(img)
            wound_contour, _          = detect_wound(img)

            # Fallback: GrabCut (assumes wound is roughly centered)
            if wound_contour is None:
                wound_contour, _ = detect_wound_grabcut(img)

            if wound_contour is not None:
                wound_detected = True
                px_area  = float(cv2.contourArea(wound_contour))
                rect     = cv2.minAreaRect(wound_contour)
                rw, rh   = rect[1]
                major_px = max(rw, rh)
                minor_px = min(rw, rh)

                if pixels_per_cm and pixels_per_cm > 0:
                    ruler_detected = True
                    area_cm2  = round(px_area  / (pixels_per_cm ** 2), 2)
                    length_cm = round(major_px / pixels_per_cm,         1)
                    width_cm  = round(minor_px / pixels_per_cm,         1)
                else:
                    rough_ppc = (w + h) / 40
                    area_cm2  = round(px_area  / (rough_ppc ** 2), 1)
                    length_cm = round(major_px / rough_ppc,         1)
                    width_cm  = round(minor_px / rough_ppc,         1)

        annotated_b64 = encode_image(
            annotate_image(img, wound_contour, ruler_line, pixels_per_cm,
                           area_cm2, length_cm, width_cm,
                           ruler_ok=ruler_detected if method == 'gpt4o' else None)
        )

        result = {
            'success':        True,
            'method':         method,
            'ruler_detected': ruler_detected,
            'wound_detected': wound_detected,
            'pixels_per_cm':  pixels_per_cm,
            'area_cm2':       area_cm2,
            'length_cm':      length_cm,
            'width_cm':       width_cm,
            'annotated_image': annotated_b64,
        }
        if tissue_info:
            result['tissue_info'] = tissue_info
        return jsonify(result)

    except Exception as exc:
        return jsonify({'success': False, 'error': str(exc)}), 500


if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5001, debug=False)
