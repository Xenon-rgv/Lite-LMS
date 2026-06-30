<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_login('../');

require_once __DIR__ . '/../config/database.php';

$material_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if ($material_id <= 0) {
    http_response_code(400);
    die("Invalid request.");
}

// ---- Fetch material + course ----
$stmt = $conn->prepare("
    SELECT m.*, c.lecturer_id, c.id AS course_id
    FROM materials m
    JOIN courses c ON c.id = m.course_id
    WHERE m.id = ?
");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$material = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$material) {
    http_response_code(404);
    die("Material not found.");
}

// ---- Authorization check ----
$authorized = false;

if ($role === 'lecturer') {
    $stmt = $conn->prepare("SELECT id FROM lecturers WHERE user_id = ? AND id = ?");
    $stmt->bind_param("ii", $user_id, $material['lecturer_id']);
    $stmt->execute();
    $authorized = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();
} elseif ($role === 'student') {
    $stmt = $conn->prepare("
        SELECT e.id FROM enrollments e
        JOIN students s ON s.id = e.student_id
        WHERE s.user_id = ? AND e.course_id = ?
    ");
    $stmt->bind_param("ii", $user_id, $material['course_id']);
    $stmt->execute();
    $authorized = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();
}

$conn->close();

if (!$authorized) {
    http_response_code(403);
    die("You do not have access to this material.");
}

// ---- Serve the file ----
$file_path = __DIR__ . '/../uploads/materials/' . $material['file_path'];

if (!file_exists($file_path)) {
    http_response_code(404);
    die("File not found on server.");
}

$mime_types = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'mp4'  => 'video/mp4',
    'mov'  => 'video/quicktime',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
];

$ext = strtolower($material['file_type']);
$mime = $mime_types[$ext] ?? 'application/octet-stream';

// inline = render in-browser, never trigger a save dialog.
// No Content-Disposition: attachment, no filename hint to the browser.
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file_path));
header('Content-Disposition: inline');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

readfile($file_path);
exit();
