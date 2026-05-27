from PIL import Image
import os

logo_path = '/var/www/paperlessmd/assets/img/logo.png'
favicon_path = '/var/www/paperlessmd/assets/img/favicon.ico'
favicon32_path = '/var/www/paperlessmd/assets/img/favicon-32.png'
favicon16_path = '/var/www/paperlessmd/assets/img/favicon-16.png'

img = Image.open(logo_path)
print(f'Original: {img.size} {img.mode}')

# Convert to RGBA for transparency support
img = img.convert('RGBA')

# Generate favicon.ico with multiple sizes
icon_sizes = [(16, 16), (32, 32), (48, 48)]
icons = []
for size in icon_sizes:
    resized = img.resize(size, Image.LANCZOS)
    icons.append(resized)

icons[0].save(favicon_path, format='ICO', sizes=[(16,16),(32,32),(48,48)], append_images=icons[1:])
print(f'favicon.ico saved: {os.path.getsize(favicon_path)} bytes')

# Also save a 32x32 PNG for modern browsers
icons[1].save(favicon32_path, format='PNG')
print(f'favicon-32.png saved')

# And 16x16
icons[0].save(favicon16_path, format='PNG')
print(f'favicon-16.png saved')
