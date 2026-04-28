<?php
// ================================================================
// AUTH SERVICE  –  auth-service/auth.php
// Uses Supabase (cloud PostgreSQL via REST API) for authentication.
// Calls Supabase REST endpoint, never direct DB connection.
// ================================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST' && $action === '/login') {

    $email    = trim($body['email']    ?? '');
    $password = trim($body['password'] ?? '');

    if ($email === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['error' => 'email and password are required']);
        exit;
    }

    // Query Supabase: GET /rest/v1/users?email=eq.<email>&select=id,name,email,password,role
    $endpoint = 'users?email=eq.' . urlencode($email) . '&select=id,name,email,password,role';
    $result   = supabase_request($endpoint, 'GET');

    if (isset($result['__error'])) {
        http_response_code(500);
        echo json_encode(['error' => 'Supabase error: ' . $result['__error']]);
        exit;
    }

    if ($result['__status'] !== 200) {
        http_response_code(500);
        echo json_encode([
            'error'  => 'Supabase returned status ' . $result['__status'],
            'detail' => $result['__data']
        ]);
        exit;
    }

    $rows = $result['__data'];
    $user = is_array($rows) && count($rows) > 0 ? $rows[0] : null;

    // Plain-text password check
    if (!$user || $user['password'] !== $password) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password']);
        exit;
    }

    $payload = [
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];

    $token = base64_encode(json_encode($payload));
    echo json_encode(['token' => $token, 'user' => $payload]);

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Auth service: unknown action "' . $action . '"']);
}
