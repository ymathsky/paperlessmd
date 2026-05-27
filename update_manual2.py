#!/usr/bin/env python3
with open('/var/www/paperlessmd/user_manual.html', 'r') as f:
    c = f.read()

changes = 0

def rep(old, new, desc):
    global c, changes
    if old in c:
        c = c.replace(old, new, 1)
        changes += 1
        print(f"  OK: {desc}")
    else:
        print(f"  MISS: {desc}")

rep(
    '    <p>10 clinical and consent forms with patient e-signature, ICD-10 search, and PDF export.</p>',
    '    <p>12 clinical and consent forms with patient e-signature, ICD-10 search, and PDF export.</p>',
    "Update form count"
)

rep(
    '        <li><a href="#s5g">Assigned MA</a></li>',
    '''        <li><a href="#s5g">Assigned MA</a></li>
        <li><a href="#s5h">Diagnoses (ICD-10)</a></li>
        <li><a href="#s5i">Care Notes</a></li>
        <li><a href="#s5j">SOAP Notes</a></li>''',
    "Add patient subsections to TOC"
)

rep(
    '    <li><a href="#s11b">e-Sign Queue (Provider Countersignatures)</a></li>',
    '''    <li><a href="#s11b">e-Sign Queue (Provider Countersignatures)</a></li>
    <li><a href="#s12">12 Messages — Internal Staff Chat</a></li>
    <li><a href="#s12b">12.5 MA Location Monitor</a></li>
    <li><a href="#s12c">12.7 Notifications</a></li>''',
    "Add new sections to TOC"
)

with open('/var/www/paperlessmd/user_manual.html', 'w') as f:
    f.write(c)

print(f"\nDone. {changes} replacements applied.")
