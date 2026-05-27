import subprocess, sys

path = '/var/www/paperlessmd/view_document.php'
r = subprocess.run(['cat', path], capture_output=True, text=True)
content = r.stdout

# Add the saved-sig auto-fill code right after provPad is created in the second extraJs block
old = """    var provPad = new SignaturePad(provCanvas, { penColor: '#3b0764', minWidth: 1.5, maxWidth: 3 });
    var provPlaceholder = document.getElementById('provPlaceholder');
    provPad.addEventListener('beginStroke', function () { provPlaceholder.style.display = 'none'; });
    document.getElementById('provClearBtn').addEventListener('click', function () {"""

new = """    var provPad = new SignaturePad(provCanvas, { penColor: '#3b0764', minWidth: 1.5, maxWidth: 3 });
    var provPlaceholder = document.getElementById('provPlaceholder');
    provPad.addEventListener('beginStroke', function () { provPlaceholder.style.display = 'none'; });
    // Auto-fill saved provider signature into pad
    var provSavedBanner = document.getElementById('provSavedBanner');
    var provSigPadArea  = document.getElementById('provSigPadArea');
    var useManualBtn    = document.getElementById('useManualProvSig');
    if (typeof window._provSavedSignature !== 'undefined') {
        var img = new Image();
        img.onload = function () {
            resizeProvCanvas();
            provPad.fromDataURL(window._provSavedSignature, { width: provCanvas.width, height: provCanvas.height });
            document.getElementById('provPlaceholder').style.display = 'none';
        };
        img.src = window._provSavedSignature;
    }
    useManualBtn && useManualBtn.addEventListener('click', function () {
        provSavedBanner && provSavedBanner.classList.add('hidden');
        provSigPadArea  && provSigPadArea.classList.remove('hidden');
        provPad.clear();
        document.getElementById('provPlaceholder').style.display = '';
    });
    document.getElementById('provClearBtn').addEventListener('click', function () {"""

if old not in content:
    print('ERR: pattern not found')
    # show context
    for i, line in enumerate(content.splitlines(), 1):
        if 'provPlaceholder' in line and 'beginStroke' in line:
            print(f'  line {i}: {line}')
    sys.exit(1)

# Make sure we only replace the SECOND occurrence (in the signed-status extraJs)
count = content.count(old)
print(f'Pattern found {count} time(s)')

if count != 1:
    print('ERR: expected exactly 1 match')
    sys.exit(1)

content = content.replace(old, new, 1)
r2 = subprocess.run(['tee', path], input=content, capture_output=True, text=True)
if r2.returncode != 0:
    print('ERR:', r2.stderr); sys.exit(1)

print('OK: Added saved-sig auto-fill to provider panel JS in view_document.php')
