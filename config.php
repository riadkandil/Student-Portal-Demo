<?php
// ================================================================
// CONFIG  –  student-portal/config.php
// Three database types:
//   - Supabase (cloud PostgreSQL via REST API)  →  auth_db
//   - MySQL (phpMyAdmin / XAMPP)                →  courses_db
//   - SQLite (file-based)                        →  notifications_db
// ================================================================

// ---- Supabase (auth_db) ----------------------------------------
// Get these from your Supabase project: Settings → API
define('SUPABASE_URL',      'https://wyovdjsodjbowygegmza.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Ind5b3ZkanNvZGpib3d5Z2VnbXphIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzczOTg1NTMsImV4cCI6MjA5Mjk3NDU1M30.Nx9UY2o8vi9tXGjUvpl3jPVi15acXT9dS_ClAzIdQrM');

// ---- MySQL (courses_db) ----------------------------------------
define('MYSQL_HOST', '127.0.0.1');
define('MYSQL_PORT', 3306);
define('MYSQL_USER', 'root');
define('MYSQL_PASS', '');   // <-- YOUR MYSQL PASSWORD
define('MYSQL_DB',   'courses_db');

// ---- SQLite (notifications_db) ---------------------------------
define('SQLITE_PATH', __DIR__ . '/data/notifications.sqlite');

// ================================================================
// Supabase REST API helper
// ================================================================
function supabase_request(string $endpoint, string $method = 'GET', ?array $body = null): array {
    $url = SUPABASE_URL . '/rest/v1/' . ltrim($endpoint, '/');

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation',
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['__error' => 'cURL: ' . $curlErr];
    }

    $data = json_decode($response, true);
    return [
        '__status' => $httpCode,
        '__data'   => $data,
        '__raw'    => $response,
    ];
}

// ================================================================
// MySQL connection helper
// ================================================================
function connect_mysql(): mysqli {
    $c = @new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB, MYSQL_PORT);
    if ($c->connect_error) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => "Cannot connect to MySQL '" . MYSQL_DB . "': " . $c->connect_error,
            'used'  => ['host' => MYSQL_HOST, 'port' => MYSQL_PORT, 'user' => MYSQL_USER],
            'hint'  => 'Check MYSQL_* settings in config.php'
        ]);
        exit;
    }
    return $c;
}

// ================================================================
// SQLite connection helper (auto-creates table if missing)
// ================================================================
function connect_sqlite(): PDO {
    try {
        $pdo = new PDO('sqlite:' . SQLITE_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS event_queue (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                event      TEXT NOT NULL,
                data       TEXT,
                status     TEXT NOT NULL DEFAULT 'pending',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'SQLite connection failed: ' . $e->getMessage(),
            'path'  => SQLITE_PATH,
            'hint'  => 'Ensure data/ folder exists and is writable'
        ]);
        exit;
    }
}

// ================================================================
// Auth header reading (Apache-safe)
// ================================================================
function read_auth_header(): string {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) return $_SERVER['HTTP_AUTHORIZATION'];
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    if (function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $k => $v)
            if (strcasecmp($k, 'Authorization') === 0) return $v;
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v)
            if (strcasecmp($k, 'Authorization') === 0) return $v;
    }
    return '';
}

// ================================================================
// Decode user from X-User-Token header
// ================================================================
function get_token_user(): ?array {
    $token = $_SERVER['HTTP_X_USER_TOKEN'] ?? '';
    if ($token === '') return null;
    $decoded = base64_decode($token, true);
    if (!$decoded) return null;
    $user = json_decode($decoded, true);
    return is_array($user) ? $user : null;
}

// ================================================================
// Role enforcement
// ================================================================
function require_role(?array $user, string $role): void {
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated – log in again']);
        exit;
    }
    if ($user['role'] !== $role) {
        http_response_code(403);
        echo json_encode(['error' => "Forbidden: only {$role}s can do this (you are a {$user['role']})"]);
        exit;
    }
}
