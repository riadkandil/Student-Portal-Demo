<?php
// ================================================================
// NOTIFICATIONS WORKER  –  notifications-service/worker.php
// CLI script.
//
// Run from CMD:
//   cd C:\xampp\htdocs\student-portal\notifications-service
//   php worker.php
// ================================================================

require_once __DIR__ . '/../config.php';

$db = connect_sqlite();

echo "===========================================" . PHP_EOL;
echo " Notification Worker – SQLite Queue"       . PHP_EOL;
echo "===========================================" . PHP_EOL;

$stmt = $db->query(
    "SELECT id, event, data, created_at
     FROM   event_queue
     WHERE  status = 'pending'
     ORDER  BY created_at ASC"
);

$rows = $stmt->fetchAll();

if (count($rows) === 0) {
    echo "No pending events." . PHP_EOL;
    exit(0);
}

$count = 0;
foreach ($rows as $row) {
    $id   = (int) $row['id'];
    $data = json_decode($row['data'], true) ?: [];

    echo PHP_EOL;
    echo "[{$row['created_at']}] EVENT #{$id}: " . strtoupper($row['event']) . PHP_EOL;
    foreach ($data as $k => $v) echo "  {$k}: {$v}" . PHP_EOL;

    $upd = $db->prepare("UPDATE event_queue SET status = 'done' WHERE id = ?");
    $upd->execute([$id]);

    $count++;
}

echo PHP_EOL;
echo "Done. Processed {$count} event(s)." . PHP_EOL;
