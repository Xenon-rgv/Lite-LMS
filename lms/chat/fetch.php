<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_login('../');

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$course_id  = (int)($_GET['course_id'] ?? 0);
$after_id   = (int)($_GET['after'] ?? 0);
$user_id    = $_SESSION['user_id'];
$role       = $_SESSION['role'];

if ($course_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

// ---- Authorization: must be the course's lecturer or an enrolled student ----
$authorized = false;

if ($role === 'lecturer') {
    $stmt = $conn->prepare("
        SELECT c.id FROM courses c
        JOIN lecturers l ON l.id = c.lecturer_id
        WHERE c.id = ? AND l.user_id = ?
    ");
    $stmt->bind_param("ii", $course_id, $user_id);
    $stmt->execute();
    $authorized = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();
} elseif ($role === 'student') {
    $stmt = $conn->prepare("
        SELECT e.id FROM enrollments e
        JOIN students s ON s.id = e.student_id
        WHERE e.course_id = ? AND s.user_id = ?
    ");
    $stmt->bind_param("ii", $course_id, $user_id);
    $stmt->execute();
    $authorized = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();
}

if (!$authorized) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not part of this course.']);
    $conn->close();
    exit();
}

$stmt = $conn->prepare("
    SELECT cm.id, cm.message, cm.created_at, cm.sender_id, u.name AS sender_name, u.role AS sender_role
    FROM chat_messages cm
    JOIN users u ON u.id = cm.sender_id
    WHERE cm.course_id = ? AND cm.id > ?
    ORDER BY cm.id ASC
    LIMIT 100
");
$stmt->bind_param("ii", $course_id, $after_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $row['is_self'] = ((int)$row['sender_id'] === (int)$user_id);
    $messages[] = $row;
}
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'messages' => $messages]);
