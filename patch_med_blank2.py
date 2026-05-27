import subprocess

path = '/var/www/paperlessmd/forms/new_patient_pocket.php'
with open(path) as f:
    src = f.read()

old = """$medRows = [];
foreach ($activeMeds as $m) {
    $medRows[] = ['med_id' => $m['id'], 'med_name' => $m['med_name'], 'med_freq' => $m['med_frequency'], 'med_type' => 'Refill'];
}
$emptyTarget = max(count($activeMeds) + 2, 6);
while (count($medRows) < $emptyTarget) {
    $medRows[] = ['med_id' => 0, 'med_name' => '', 'med_freq' => '', 'med_type' => ''];
}"""

new = """$medRows = [];
foreach ($activeMeds as $m) {
    $medRows[] = ['med_id' => $m['id'], 'med_name' => $m['med_name'], 'med_freq' => $m['med_frequency'], 'med_type' => 'Refill'];
}
if (empty($medRows)) {
    $medRows[] = ['med_id' => 0, 'med_name' => '', 'med_freq' => '', 'med_type' => ''];
}"""

if old in src:
    src = src.replace(old, new, 1)
    with open(path, 'w') as f:
        f.write(src)
    r = subprocess.run(['php', '-l', path], capture_output=True, text=True)
    print('OK:', r.stdout.strip())
else:
    print('NOT FOUND')
