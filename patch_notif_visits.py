import re

path = '/var/www/paperlessmd/api/notifications.php'
with open(path, 'r') as f:
    src = f.read()

# Insert after the opening of if (!isBilling()) block
new_block = """    // 2. Today's visits
    try {
        if (isAdmin()) {
            $n = (int)$pdo->query("
                SELECT COUNT(*) FROM schedule
                WHERE visit_date = CURDATE()
                  AND status IN ('pending','en_route')
            ")->fetchColumn();
            $body = "Active visits on today's schedule";
        } else {
            $vStmt = $pdo->prepare("
                SELECT COUNT(*) FROM schedule
                WHERE ma_id = ?
                  AND visit_date = CURDATE()
                  AND status IN ('pending','en_route')
            ");
            $vStmt->execute([$uid]);
            $n = (int)$vStmt->fetchColumn();
            $body = "You have visits on today's route";
        }
        if ($n > 0) $notifs[] = [
            'type'  => 'schedule',
            'icon'  => 'bi-calendar-check-fill',
            'color' => 'indigo',
            'title' => $n . ' visit' . ($n !== 1 ? 's' : '') . ' scheduled today',
            'body'  => $body,
            'link'  => '/schedule.php',
            'count' => $n,
        ];
    } catch (PDOException $e) {}

    // 3. Pending billing upload"""

# Replace old "// 2. Pending billing upload" with new block
old = "    // 2. Pending billing upload"
if old in src:
    src = src.replace(old, new_block, 1)
    # Also renumber the old 3 and 4 to 4 and 5
    src = src.replace("    // 3. E-sign queue", "    // 4. E-sign queue", 1)
    src = src.replace("    // 4. Old drafts", "    // 5. Old drafts", 1)
    print("OK: inserted today's visits block")
else:
    print("NOT FOUND: target anchor")

with open(path, 'w') as f:
    f.write(src)

import subprocess
r = subprocess.run(['php', '-l', path], capture_output=True, text=True)
print(r.stdout.strip())
