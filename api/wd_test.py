#!/usr/bin/env python3
import cv2, numpy as np, sys
sys.path.insert(0, '/var/www/paperlessmd/api')
from wound_service import detect_wound, detect_wound_grabcut, detect_ruler_scale

img_path = '/var/www/paperlessmd/uploads/photos/p1_20260517_043505_c3e8a8cc.jpg'
img = cv2.imread(img_path)
if img is None:
    print('Could not read image')
    sys.exit(1)

h, w = img.shape[:2]
print('Image size: %dx%d' % (w, h))

hsv = cv2.cvtColor(img, cv2.COLOR_BGR2HSV)
s_channel = hsv[:,:,1]
v_channel = hsv[:,:,2]
print('Saturation mean/max: %.1f / %d' % (s_channel.mean(), s_channel.max()))
print('Value mean/max: %.1f / %d' % (v_channel.mean(), v_channel.max()))

mask_r1     = cv2.inRange(hsv, (0,   55, 40),  (18,  255, 255))
mask_r2     = cv2.inRange(hsv, (155, 55, 40),  (180, 255, 255))
mask_yellow = cv2.inRange(hsv, (14,  45, 55),  (38,  255, 225))
mask_dark   = cv2.inRange(hsv, (0,   0,   0),  (180, 200,  65))
mask_brown  = cv2.inRange(hsv, (8,   55, 20),  (30,  220, 150))
total_px = h * w
print('Red pixels: %d (%.1f%%)' % (mask_r1.sum()//255, 100*(mask_r1.sum()//255)/total_px))
print('Red2 pixels: %d (%.1f%%)' % (mask_r2.sum()//255, 100*(mask_r2.sum()//255)/total_px))
print('Yellow pixels: %d (%.1f%%)' % (mask_yellow.sum()//255, 100*(mask_yellow.sum()//255)/total_px))
print('Dark pixels: %d (%.1f%%)' % (mask_dark.sum()//255, 100*(mask_dark.sum()//255)/total_px))
print('Brown pixels: %d (%.1f%%)' % (mask_brown.sum()//255, 100*(mask_brown.sum()//255)/total_px))

combined = cv2.bitwise_or(mask_r1, mask_r2)
for m in [mask_yellow, mask_dark, mask_brown]:
    combined = cv2.bitwise_or(combined, m)
print('Combined pixels: %d (%.1f%%)' % (combined.sum()//255, 100*(combined.sum()//255)/total_px))

# Run morphological ops
bright = cv2.inRange(hsv, (0, 0, 225), (180, 28, 255))
combined = cv2.bitwise_and(combined, cv2.bitwise_not(bright))
k_close = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (23, 23))
k_open  = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (7,  7))
combined = cv2.morphologyEx(combined, cv2.MORPH_CLOSE, k_close)
combined = cv2.morphologyEx(combined, cv2.MORPH_OPEN,  k_open)

contours, _ = cv2.findContours(combined, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
print('Contours found: %d' % len(contours))
for i, c in enumerate(sorted(contours, key=cv2.contourArea, reverse=True)[:5]):
    area = cv2.contourArea(c)
    pct = 100*area/total_px
    valid = total_px*0.002 < area < total_px*0.65
    print('  contour %d: area=%d (%.1f%%) valid=%s' % (i, area, pct, valid))

contour, mask = detect_wound(img)
if contour is not None:
    print('detect_wound: FOUND area=%d' % int(cv2.contourArea(contour)))
else:
    print('detect_wound: NOT FOUND')
    contour2, mask2 = detect_wound_grabcut(img)
    if contour2 is not None:
        print('GrabCut: FOUND area=%d' % int(cv2.contourArea(contour2)))
    else:
        print('GrabCut: NOT FOUND')

ruler, ppc = detect_ruler_scale(img)
if ppc:
    print('Ruler: detected ppc=%.1f' % ppc)
else:
    print('Ruler: not detected')
