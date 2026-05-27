from PIL import Image
import os

logo_path  = '/var/www/paperlessmd/assets/img/logo.png'
out_192    = '/var/www/paperlessmd/assets/img/pwa-icon-192.png'
out_512    = '/var/www/paperlessmd/assets/img/pwa-icon-512.png'
out_touch  = '/var/www/paperlessmd/assets/img/apple-touch-icon.png'

img = Image.open(logo_path).convert('RGBA')
print(f'Original logo: {img.size}')

def make_icon(size):
    """Fit logo inside a square with white background, centered with padding."""
    canvas = Image.new('RGBA', (size, size), (255, 255, 255, 255))
    pad = int(size * 0.1)
    avail = size - pad * 2
    img.thumbnail((avail, avail), Image.LANCZOS)
    x = (size - img.width)  // 2
    y = (size - img.height) // 2
    canvas.paste(img, (x, y), img)
    return canvas.convert('RGB')

icon192 = make_icon(192)
icon192.save(out_192, 'PNG', optimize=True)
print(f'pwa-icon-192.png: {os.path.getsize(out_192)} bytes')

icon512 = make_icon(512)
icon512.save(out_512, 'PNG', optimize=True)
print(f'pwa-icon-512.png: {os.path.getsize(out_512)} bytes')

# Apple touch icon (180x180, white bg)
icon180 = make_icon(180)
icon180.save(out_touch, 'PNG', optimize=True)
print(f'apple-touch-icon.png: {os.path.getsize(out_touch)} bytes')

# Also regenerate favicon from logo (tighter crop for small sizes)
fav_path = '/var/www/paperlessmd/assets/img/favicon.ico'
fav32    = '/var/www/paperlessmd/assets/img/favicon-32.png'
fav16    = '/var/www/paperlessmd/assets/img/favicon-16.png'

def make_fav(size):
    canvas = Image.new('RGBA', (size, size), (255, 255, 255, 255))
    pad = max(1, int(size * 0.05))
    avail = size - pad * 2
    tmp = img.copy()
    tmp.thumbnail((avail, avail), Image.LANCZOS)
    x = (size - tmp.width) // 2
    y = (size - tmp.height) // 2
    canvas.paste(tmp, (x, y), tmp)
    return canvas

f16 = make_fav(16)
f32 = make_fav(32)
f48 = make_fav(48)

f16.save(fav16, 'PNG')
f32.save(fav32, 'PNG')
f16.save(fav_path, format='ICO', sizes=[(16,16),(32,32),(48,48)], append_images=[f32, f48])
print(f'favicon.ico regenerated')
print('Done.')
