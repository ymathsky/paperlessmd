import subprocess

files = [
    '/var/www/paperlessmd/forms/vital_cs.php',
    '/var/www/paperlessmd/forms/new_patient_pocket.php',
]

old = """// All active meds pre-filled, then at least 2 empty rows for new entries
$medRows = [];
foreach ($activeMeds as $m) {
    $medRows[] = [
        'med_id'   => $m['id'],
        'med_name' => $m['med_name'],
        'med_freq' => $m['med_frequency'],
        'med_type' => 'Refill',   // existing active meds default to Refill
    ];
}
$emptyTarget = max(count($activeMeds) + 2, 6);
while (count($medRows) < $emptyTarget) {
    $medRows[] = ['med_id' => 0, 'med_name' => '', 'med_freq' => '', 'med_type' => ''];
}"""

new = """// Active meds pre-filled; add 1 blank row only when there are no active meds
$medRows = [];
foreach ($activeMeds as $m) {
    $medRows[] = [
        'med_id'   => $m['id'],
        'med_name' => $m['med_name'],
        'med_freq' => $m['med_frequency'],
        'med_type' => 'Refill',   // existing active meds default to Refill
    ];
}
if (empty($medRows)) {
    $medRows[] = ['med_id' => 0, 'med_name' => '', 'med_freq' => '', 'med_type' => ''];
}"""

for path in files:
    with open(path) as f:
        src = f.read()
    if old in src:
        src = src.replace(old, new, 1)
        with open(path, 'w') as f:
            f.write(src)
        r = subprocess.run(['php', '-l', path], capture_output=True, text=True)
        print(f'OK {path}: {r.stdout.strip()}')
    else:
        print(f'NOT FOUND in {path}')
