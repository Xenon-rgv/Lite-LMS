<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_login('../');

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$course_id = (int)($data['course_id'] ?? 0);
$message   = trim($data['message'] ?? '');
$user_id   = $_SESSION['user_id'];
$role      = $_SESSION['role'];

if ($course_id <= 0 || $message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

if (mb_strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message too long.']);
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

$stmt = $conn->prepare("INSERT INTO chat_messages (course_id, sender_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $course_id, $user_id, $message);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message_id' => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not send message.']);
}

$stmt->close();
$conn->close();
