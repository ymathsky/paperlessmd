import subprocess

path = '/var/www/paperlessmd/view_document.php'
with open(path) as f:
    src = f.read()

load_snippet = """
// Load saved provider signature for current user
$_provSavedSig  = '';
$_provSavedName = '';
$_provSavedNpi  = '';
if (isset($_SESSION['user_id'])) {
    $__ps = $pdo->prepare("SELECT saved_provider_signature, saved_provider_name, saved_provider_npi FROM staff WHERE id = ? LIMIT 1");
    $__ps->execute([(int)$_SESSION['user_id']]);
    $__pr = $__ps->fetch(PDO::FETCH_ASSOC) ?: [];
    $_provSavedSig  = (string)($__pr['saved_provider_signature'] ?? '');
    $_provSavedName = (string)($__pr['saved_provider_name']      ?? '');
    $_provSavedNpi  = (string)($__pr['saved_provider_npi']       ?? '');
}
"""

anchor = "requireLogin();\n\n$id = (int)($_GET['id'] ?? 0);"
if anchor in src:
    src = src.replace(anchor, "requireLogin();\n" + load_snippet + "\n$id = (int)($_GET['id'] ?? 0);", 1)
    print('OK: injected provider sig load')
else:
    print('NOT FOUND - trying alternate anchor')
    anchor2 = "requireLogin();\n\n$id"
    if anchor2 in src:
        src = src.replace(anchor2, "requireLogin();\n" + load_snippet + "\n$id", 1)
        print('OK: injected (alt anchor)')
    else:
        print('STILL NOT FOUND')

with open(path, 'w') as f:
    f.write(src)

r = subprocess.run(['php', '-l', path], capture_output=True, text=True)
print(r.stdout.strip())
