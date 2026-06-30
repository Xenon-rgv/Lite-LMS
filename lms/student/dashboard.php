<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_role('student', '../');

require_once __DIR__ . '/../config/database.php';

$student_user_id = $_SESSION['user_id'];

// Get this student's internal students.id
$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $student_user_id);
$stmt->execute();
$student_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$student_id = $student_row['id'] ?? null;

// ---- Fetch enrolled courses ----
$courses = [];
if ($student_id) {
    $stmt = $conn->prepare("
        SELECT c.id, c.code, c.title, c.level, d.name AS department_name, u.name AS lecturer_name,
               (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.id) AS material_count
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        JOIN departments d ON d.id = c.department_id
        JOIN lecturers l ON l.id = c.lecturer_id
        JOIN users u ON u.id = l.user_id
        WHERE e.student_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">

    <!-- ===================== NAVBAR ===================== -->
    <nav class="flex items-center justify-between px-6 lg:px-12 py-4 bg-white border-b border-gray-200">
        <span class="font-bold text-lg">School Portal <span class="text-gray-400 font-normal">| Student</span></span>
        <div class="flex items-center gap-4">
            <span class="text-sm text-gray-600">Hi, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
            <a href="../logout.php" class="text-sm font-semibold px-3 py-1.5 rounded-md border border-gray-300 hover:bg-gray-100">Log out</a>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto px-6 py-8">

        <h1 class="text-2xl font-bold mb-6">My Courses</h1>

        <?php if (empty($courses)) : ?>
            <div class="bg-white border border-gray-200 rounded-xl p-10 text-center text-gray-500">
                You're not enrolled in any courses yet. Ask your lecturer to add your matric number to their course.
            </div>
        <?php else : ?>
            <div class="grid sm:grid-cols-2 gap-4">
                <?php foreach ($courses as $course) : ?>
                    <a href="course.php?id=<?php echo $course['id']; ?>"
                        class="block bg-white border border-gray-200 rounded-xl p-5 hover:border-black transition">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-lg"><?php echo htmlspecialchars($course['code']); ?></span>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded-md text-gray-600"><?php echo htmlspecialchars($course['level']); ?> Level</span>
                        </div>
                        <p class="text-gray-700 mb-1"><?php echo htmlspecialchars($course['title']); ?></p>
                        <p class="text-sm text-gray-500 mb-3">Lecturer: <?php echo htmlspecialchars($course['lecturer_name']); ?></p>
                        <div class="text-sm text-gray-500 flex items-center justify-between">
                            <span><?php echo htmlspecialchars($course['department_name']); ?></span>
                            <span><?php echo (int)$course['material_count']; ?> material<?php echo $course['material_count'] == 1 ? '' : 's'; ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

</body>

</html>
