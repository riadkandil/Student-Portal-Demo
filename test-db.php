<?php
// ================================================================
// DB TEST SCRIPT  –  student-portal/test-db.php
// Visit: http://localhost/student-portal/test-db.php
// Tests Supabase + MySQL + SQLite connectivity.
// ================================================================

require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "==========================================" . PHP_EOL;
echo " Polyglot Database Connectivity Test"      . PHP_EOL;
echo "==========================================" . PHP_EOL . PHP_EOL;

// ---- 1. Supabase ----
echo "1. Supabase (auth_db)" . PHP_EOL;
echo "   URL:  " . SUPABASE_URL . PHP_EOL;
echo "   Key:  " . (SUPABASE_ANON_KEY === 'YOUR_ANON_PUBLIC_KEY_HERE'
                    ? '(NOT SET – edit config.php)' 
                    : '(set, length ' . strlen(SUPABASE_ANON_KEY) . ')') . PHP_EOL;

if (SUPABASE_URL === 'https://YOUR_PROJECT_REF.supabase.co' || SUPABASE_ANON_KEY === 'YOUR_ANON_PUBLIC_KEY_HERE') {
    echo "   [SKIP] Set SUPABASE_URL and SUPABASE_ANON_KEY in config.php first" . PHP_EOL;
} else {
    $result = supabase_request('users?select=id,name,email,role', 'GET');
    
    if (isset($result['__error'])) {
        echo "   [FAIL] " . $result['__error'] . PHP_EOL;
    } elseif ($result['__status'] === 200) {
        $count = is_array($result['__data']) ? count($result['__data']) : 0;
        echo "   [OK]   Connected to Supabase" . PHP_EOL;
        echo "   [OK]   Found {$count} users" . PHP_EOL;
        if ($count === 0) {
            echo "   [HINT] Run setup/1_supabase_users.sql in the Supabase SQL Editor" . PHP_EOL;
        }
    } else {
        echo "   [FAIL] HTTP " . $result['__status'] . PHP_EOL;
        echo "   [DATA] " . substr($result['__raw'], 0, 200) . PHP_EOL;
        if ($result['__status'] === 401) {
            echo "   [HINT] Wrong API key. Use the 'anon public' key, not service_role." . PHP_EOL;
        } elseif ($result['__status'] === 404) {
            echo "   [HINT] Table 'users' not found. Run setup/1_supabase_users.sql." . PHP_EOL;
        }
    }
}

echo PHP_EOL;

// ---- 2. MySQL ----
echo "2. MySQL (courses_db)" . PHP_EOL;
echo "   Host: " . MYSQL_HOST . ":" . MYSQL_PORT . PHP_EOL;
echo "   User: " . MYSQL_USER . PHP_EOL;
echo "   Pass: " . (MYSQL_PASS === '' ? '(empty)' : '(length ' . strlen(MYSQL_PASS) . ')') . PHP_EOL;

try {
    $mysql = @new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB, MYSQL_PORT);
    if ($mysql->connect_error) {
        echo "   [FAIL] " . $mysql->connect_error . PHP_EOL;
    } else {
        echo "   [OK]   Connected to MySQL " . MYSQL_DB . PHP_EOL;
        $result = $mysql->query('SELECT COUNT(*) as cnt FROM courses');
        if ($result) {
            $row = $result->fetch_assoc();
            echo "   [OK]   Found {$row['cnt']} courses" . PHP_EOL;
        }
        $mysql->close();
    }
} catch (Exception $e) {
    echo "   [FAIL] " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// ---- 3. SQLite ----
echo "3. SQLite (notifications_db)" . PHP_EOL;
echo "   Path: " . SQLITE_PATH . PHP_EOL;

try {
    $sqlite = new PDO('sqlite:' . SQLITE_PATH, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "   [OK]   Connected to SQLite" . PHP_EOL;

    $sqlite->exec("
        CREATE TABLE IF NOT EXISTS event_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event TEXT NOT NULL,
            data TEXT,
            status TEXT NOT NULL DEFAULT 'pending',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $stmt = $sqlite->query('SELECT COUNT(*) as cnt FROM event_queue');
    $row = $stmt->fetch();
    echo "   [OK]   Found {$row['cnt']} events in queue" . PHP_EOL;

} catch (PDOException $e) {
    echo "   [FAIL] " . $e->getMessage() . PHP_EOL;
    echo "   [HINT] Create student-portal/data/ folder if missing" . PHP_EOL;
}

echo PHP_EOL;
echo "==========================================" . PHP_EOL;
echo "If all three show [OK], you're ready." . PHP_EOL;
echo "Open: http://localhost/student-portal/frontend/index.html" . PHP_EOL;
