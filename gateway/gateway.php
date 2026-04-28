<?php
// ================================================================
// GATEWAY  –  gateway/gateway.php
// ================================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$route  = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$body   = file_get_contents('php://input');

$auth  = read_auth_header();
$token = '';
if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) $token = trim($m[1]);

if (strpos($route, '/api/auth') === 0) {
    $action = substr($route, strlen('/api/auth'));
    $target = 'http://localhost/student-portal/auth-service/auth.php?action=' . urlencode($action);

} elseif (strpos($route, '/api/courses') === 0) {
    $action = substr($route, strlen('/api/courses'));
    $target = 'http://localhost/student-portal/courses-service/courses.php?action=' . urlencode($action);

} elseif (strpos($route, '/api/notifications') === 0) {
    $action = substr($route, strlen('/api/notifications'));
    $target = 'http://localhost/student-portal/notifications-service/notifications.php?action=' . urlencode($action);

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Unknown route: ' . $route]);
    exit;
}

$ch = curl_init($target);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
if ($body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

$headers = ['Content-Type: application/json'];
if ($token !== '') $headers[] = 'X-User-Token: ' . $token;
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'Gateway cURL error: ' . $curlErr]);
    exit;
}

http_response_code($httpCode);
echo $response;
