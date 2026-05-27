path = '/var/www/paperlessmd/schedule.php'
with open(path, 'r') as f:
    content = f.read()

old = """    $wkSql = "
        SELECT sc.*,
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               p.id AS patient_id
        FROM `schedule` sc
        JOIN patients p ON p.id = sc.patient_id
        WHERE sc.ma_id = ? AND sc.visit_date BETWEEN ? AND ?
    ";
    $wkParams = [$viewMaId, $weekStart, $weekEnd];"""

new = """    $wkSql = "
        SELECT sc.*,
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               p.id AS patient_id
        FROM `schedule` sc
        JOIN patients p ON p.id = sc.patient_id
        WHERE sc.visit_date BETWEEN ? AND ?
    ";
    $wkParams = [$weekStart, $weekEnd];
    if (!$viewAll) { $wkSql .= " AND sc.ma_id = ?"; $wkParams[] = $viewMaId; }"""

if old in content:
    content = content.replace(old, new, 1)
    with open(path, 'w') as f:
        f.write(content)
    print('Patched OK')
else:
    print('Pattern not found')
