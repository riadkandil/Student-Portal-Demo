<?php
// ================================================================
// COURSES SERVICE  –  courses-service/courses.php
// Uses MySQL (courses_db) for courses and enrollments.
// Writes events to SQLite (notifications_db).
// ================================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$user   = get_token_user();

$db     = connect_mysql();    // MySQL
$sqlite = connect_sqlite();   // SQLite for events

// Helper: write event to SQLite queue
function queue_event(PDO $sqlite, string $event, array $data): void {
    $stmt = $sqlite->prepare('INSERT INTO event_queue (event, data) VALUES (?, ?)');
    $stmt->execute([$event, json_encode($data)]);
}

// ================================================================
// GET /courses
// ================================================================
if ($method === 'GET' && $action === '/courses') {

    $result  = $db->query('SELECT id, code, name, instructor_id FROM courses ORDER BY id');
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $row['id']            = (int) $row['id'];
        $row['instructor_id'] = (int) $row['instructor_id'];
        $courses[] = $row;
    }
    echo json_encode($courses);

// ================================================================
// POST /courses  (instructor)
// ================================================================
} elseif ($method === 'POST' && $action === '/courses') {

    require_role($user, 'instructor');

    $code = trim($body['code'] ?? '');
    $name = trim($body['name'] ?? '');
    if ($code === '' || $name === '') {
        http_response_code(400);
        echo json_encode(['error' => 'code and name are required']);
        exit;
    }

    $stmt = $db->prepare('INSERT INTO courses (code, name, instructor_id) VALUES (?, ?, ?)');
    $stmt->bind_param('ssi', $code, $name, $user['id']);
    $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        'id'            => $newId,
        'code'          => $code,
        'name'          => $name,
        'instructor_id' => (int) $user['id'],
    ]);

// ================================================================
// POST /enroll  (student)
// ================================================================
} elseif ($method === 'POST' && $action === '/enroll') {

    require_role($user, 'student');

    $courseId = (int) ($body['course_id'] ?? 0);
    if ($courseId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'course_id is required']);
        exit;
    }

    // Lookup course details for notification
    $stmt = $db->prepare('SELECT code, name FROM courses WHERE id = ?');
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$course) {
        http_response_code(404);
        echo json_encode(['error' => 'Course not found']);
        exit;
    }

    // INSERT IGNORE skips silently if duplicate
    $stmt = $db->prepare('INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $user['id'], $courseId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        queue_event($sqlite, 'enrollment', [
            'student_id'   => $user['id'],
            'student_name' => $user['name'],
            'course_id'    => $courseId,
            'course_code'  => $course['code'],
            'course_name'  => $course['name'],
        ]);
        echo json_encode(['success' => true, 'message' => 'Enrolled in ' . $course['code']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Already enrolled in ' . $course['code']]);
    }

// ================================================================
// GET /my-courses  (student)
// ================================================================
} elseif ($method === 'GET' && $action === '/my-courses') {

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    $stmt = $db->prepare(
        'SELECT c.id, c.code, c.name, c.instructor_id
         FROM   courses c
         JOIN   enrollments e ON c.id = e.course_id
         WHERE  e.student_id = ?
         ORDER  BY c.id'
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result  = $stmt->get_result();
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $row['id']            = (int) $row['id'];
        $row['instructor_id'] = (int) $row['instructor_id'];
        $courses[] = $row;
    }
    $stmt->close();
    echo json_encode($courses);

// ================================================================
// POST /drop  (student)
// ================================================================
} elseif ($method === 'POST' && $action === '/drop') {

    require_role($user, 'student');

    $courseId = (int) ($body['course_id'] ?? 0);
    if ($courseId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'course_id is required']);
        exit;
    }

    $stmt = $db->prepare('SELECT code, name FROM courses WHERE id = ?');
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $db->prepare('DELETE FROM enrollments WHERE student_id = ? AND course_id = ?');
    $stmt->bind_param('ii', $user['id'], $courseId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        queue_event($sqlite, 'drop', [
            'student_id'   => $user['id'],
            'student_name' => $user['name'],
            'course_id'    => $courseId,
            'course_code'  => $course['code'] ?? '',
            'course_name'  => $course['name'] ?? '',
        ]);
        echo json_encode(['success' => true, 'message' => 'Dropped ' . ($course['code'] ?? "course #$courseId")]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Not enrolled in that course']);
    }

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Courses service: unknown action "' . $action . '"']);
}

$db->close();
