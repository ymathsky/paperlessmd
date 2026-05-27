import subprocess

# Add provider_npi column to form_submissions
r = subprocess.run(
    ['mysql', '-upduser', '-pYm@thsky12101992', 'paperlessmd'],
    input="ALTER TABLE form_submissions ADD COLUMN provider_npi VARCHAR(20) NULL AFTER provider_name;",
    capture_output=True, text=True
)
print('DB:', 'OK' if r.returncode == 0 else r.stderr.strip()[:150])

# Update sign_provider.php to accept and store provider_npi
path = '/var/www/paperlessmd/api/sign_provider.php'
with open(path) as f:
    src = f.read()

# 1. Parse npi from body
old_parse = "$provName  = trim($body['provider_name'] ?? '');"
new_parse  = "$provName  = trim($body['provider_name'] ?? '');\n$provNpi   = trim($body['provider_npi']  ?? '');"
src = src.replace(old_parse, new_parse, 1)

# 2. Store npi in UPDATE query
old_upd = """$upd = $pdo->prepare("
    UPDATE form_submissions
    SET provider_signature = ?, provider_name = ?
    WHERE id = ?
");
$upd->execute([$signature, $provName ?: null, $formId]);"""
new_upd = """$upd = $pdo->prepare("
    UPDATE form_submissions
    SET provider_signature = ?, provider_name = ?, provider_npi = ?
    WHERE id = ?
");
$upd->execute([$signature, $provName ?: null, $provNpi ?: null, $formId]);"""
src = src.replace(old_upd, new_upd, 1)

with open(path, 'w') as f:
    f.write(src)

r2 = subprocess.run(['php', '-l', path], capture_output=True, text=True)
print('sign_provider.php:', r2.stdout.strip())
print('Done.')
