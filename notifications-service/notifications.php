<?php
// ================================================================
// NOTIFICATIONS SERVICE  –  notifications-service/notifications.php
// Reads from SQLite event queue.
// ================================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$user   = get_token_user();

$db = connect_sqlite();

if ($method === 'GET' && $action === '/notifications') {

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    $stmt = $db->query(
        "SELECT id, event, data, status, created_at
         FROM   event_queue
         ORDER  BY created_at DESC
         LIMIT  100"
    );

    $rows = [];
    while ($row = $stmt->fetch()) {
        $data = json_decode($row['data'], true) ?: [];

        // Students only see their own events
        if ($user['role'] === 'student') {
            if (!isset($data['student_id']) || (int)$data['student_id'] !== (int)$user['id']) {
                continue;
            }
        }

        $rows[] = [
            'id'         => (int) $row['id'],
            'event'      => $row['event'],
            'data'       => $data,
            'status'     => $row['status'],
            'created_at' => $row['created_at'],
        ];
    }

    echo json_encode($rows);

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Notifications service: unknown action "' . $action . '"']);
}
