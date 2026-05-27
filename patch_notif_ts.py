import subprocess, sys

path = '/var/www/paperlessmd/includes/notifications.php'
r = subprocess.run(['cat', path], capture_output=True, text=True)
content = r.stdout

# Move the $ts definition before the heredoc and use $ts in heredoc
old = '''    $html = <<<HTML
<p>A new form has been submitted and is awaiting provider countersignature.</p>
<dl class="meta">
  <dt>Patient</dt><dd>{$patientName}</dd>
  <dt>Form</dt><dd>{$formLabel}</dd>
  <dt>Submitted by</dt><dd>{$maName}</dd>
  <dt>Submitted at</dt><dd>{$_ts}</dd>
</dl>
<p>
  <a href="{$link}" class="btn">Review &amp; Sign</a>
</p>
<p style="font-size:13px;color:#64748b">
  Or view the full <a href="{$queueLink}" style="color:#1d4ed8">E-Sign Queue</a>.
</p>
HTML;

    // Replace placeholder
    $ts   = date('F j, Y \\a\\t g:i a T');
    $html = str_replace('{$_ts}', $ts, $html);'''

new = '''    $ts   = date('F j, Y \\a\\t g:i a T');
    $html = <<<HTML
<p>A new form has been submitted and is awaiting provider countersignature.</p>
<dl class="meta">
  <dt>Patient</dt><dd>{$patientName}</dd>
  <dt>Form</dt><dd>{$formLabel}</dd>
  <dt>Submitted by</dt><dd>{$maName}</dd>
  <dt>Submitted at</dt><dd>{$ts}</dd>
</dl>
<p>
  <a href="{$link}" class="btn">Review &amp; Sign</a>
</p>
<p style="font-size:13px;color:#64748b">
  Or view the full <a href="{$queueLink}" style="color:#1d4ed8">E-Sign Queue</a>.
</p>
HTML;'''

if old not in content:
    print('ERR: pattern not found')
    print('--- context around _ts ---')
    for i, line in enumerate(content.splitlines(), 1):
        if '_ts' in line or 'Replace placeholder' in line:
            print(f'  {i}: {line}')
    sys.exit(1)

content = content.replace(old, new, 1)
r2 = subprocess.run(['tee', path], input=content, capture_output=True, text=True)
if r2.returncode != 0:
    print('ERR:', r2.stderr); sys.exit(1)

print('OK: Fixed $_ts undefined variable in notifications.php')
r3 = subprocess.run(['grep', '-n', '_ts\|$ts', path], capture_output=True, text=True)
for line in r3.stdout.splitlines():
    if '_ts' in line or ('ts' in line and 'date' in line):
        print(' ', line)
