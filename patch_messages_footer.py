path = '/var/www/paperlessmd/messages.php'
with open(path, 'r') as f:
    src = f.read()

# Remove any bare (untagged) footer include from previous patch attempt
bare = "\ninclude __DIR__ . '/includes/footer.php';\n"
if bare in src and '<?php' not in src.split(bare)[-1]:
    src = src.replace(bare, '', 1)

footer_line = "\n<?php include __DIR__ . '/includes/footer.php'; ?>"

if 'footer.php' not in src:
    src = src.rstrip() + footer_line + '\n'
    with open(path, 'w') as f:
        f.write(src)
    print('footer include added')
else:
    print('footer already present')
