<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_role('lecturer', '../');

require_once __DIR__ . '/../config/database.php';

$lecturer_user_id = $_SESSION['user_id'];

// Get this lecturer's internal lecturers.id (not users.id)
$stmt = $conn->prepare("SELECT id FROM lecturers WHERE user_id = ?");
$stmt->bind_param("i", $lecturer_user_id);
$stmt->execute();
$lecturer_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$lecturer_id = $lecturer_row['id'] ?? null;

// ---- Handle "create course" form submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    $code          = trim($_POST['code'] ?? '');
    $title         = trim($_POST['title'] ?? '');
    $department_id = (int)($_POST['department_id'] ?? 0);
    $level         = $_POST['level'] ?? '';

    if (empty($code) || empty($title) || $department_id <= 0 || empty($level)) {
        $_SESSION['error'] = "Please fill in all course fields.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM courses WHERE code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $_SESSION['error'] = "A course with this code already exists.";
            $stmt->close();
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO courses (code, title, lecturer_id, department_id, level) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiis", $code, $title, $lecturer_id, $department_id, $level);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Course created successfully.";
            } else {
                $_SESSION['error'] = "Could not create course. Please try again.";
            }
            $stmt->close();
        }
    }

    header("Location: dashboard.php");
    exit();
}

// ---- Fetch this lecturer's courses, with student count ----
$courses = [];
if ($lecturer_id) {
    $stmt = $conn->prepare("
        SELECT c.id, c.code, c.title, c.level, d.name AS department_name,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count
        FROM courses c
        JOIN departments d ON d.id = c.department_id
        WHERE c.lecturer_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
}

// ---- Departments for the "create course" dropdown ----
$departments = [];
$dept_result = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
while ($row = $dept_result->fetch_assoc()) {
    $departments[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">

    <!-- ===================== NAVBAR ===================== -->
    <nav class="flex items-center justify-between px-6 lg:px-12 py-4 bg-white border-b border-gray-200">
        <span class="font-bold text-lg">School Portal <span class="text-gray-400 font-normal">| Lecturer</span></span>
        <div class="flex items-center gap-4">
            <span class="text-sm text-gray-600">Hi, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
            <a href="../logout.php" class="text-sm font-semibold px-3 py-1.5 rounded-md border border-gray-300 hover:bg-gray-100">Log out</a>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto px-6 py-8">

        <!-- Notifications -->
        <?php if (isset($_SESSION['error'])) : ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php elseif (isset($_SESSION['success'])) : ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">My Courses</h1>
            <button id="new-course-btn" class="bg-black text-white font-semibold px-4 py-2 rounded-md hover:bg-gray-800">
                + New Course
            </button>
        </div>

        <!-- ===================== COURSE LIST ===================== -->
        <?php if (empty($courses)) : ?>
            <div class="bg-white border border-gray-200 rounded-xl p-10 text-center text-gray-500">
                You haven't created any courses yet. Click "New Course" to get started.
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
                        <p class="text-gray-700 mb-3"><?php echo htmlspecialchars($course['title']); ?></p>
                        <div class="text-sm text-gray-500 flex items-center justify-between">
                            <span><?php echo htmlspecialchars($course['department_name']); ?></span>
                            <span><?php echo (int)$course['student_count']; ?> student<?php echo $course['student_count'] == 1 ? '' : 's'; ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- ===================== NEW COURSE MODAL ===================== -->
    <div id="new-course-modal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center px-4 z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-md">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold">Create a New Course</h2>
                <button id="close-modal-btn" class="text-gray-400 hover:text-black text-xl leading-none">&times;</button>
            </div>

            <form method="post" class="flex flex-col gap-3">
                <input type="hidden" name="create_course" value="1">

                <div>
                    <label class="text-sm font-medium text-gray-700">Course Code</label>
                    <input type="text" name="code" placeholder="e.g. CSC301" required
                        class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1">
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700">Course Title</label>
                    <input type="text" name="title" placeholder="e.g. Data Structures & Algorithms" required
                        class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1">
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700">Department</label>
                    <select name="department_id" required class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1">
                        <option value="">Select department</option>
                        <?php foreach ($departments as $dept) : ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700">Level</label>
                    <select name="level" required class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1">
                        <option value="">Select level</option>
                        <option value="100">100 Level</option>
                        <option value="200">200 Level</option>
                        <option value="300">300 Level</option>
                        <option value="400">400 Level</option>
                        <option value="500">500 Level</option>
                    </select>
                </div>

                <button type="submit" class="bg-black text-white font-bold py-2 rounded-md mt-2 hover:bg-gray-800">
                    Create Course
                </button>
            </form>
        </div>
    </div>

    <script>
        const newCourseBtn   = document.getElementById('new-course-btn');
        const newCourseModal = document.getElementById('new-course-modal');
        const closeModalBtn  = document.getElementById('close-modal-btn');

        newCourseBtn.addEventListener('click', () => newCourseModal.classList.remove('hidden'));
        closeModalBtn.addEventListener('click', () => newCourseModal.classList.add('hidden'));
        newCourseModal.addEventListener('click', (e) => {
            if (e.target === newCourseModal) newCourseModal.classList.add('hidden');
        });
    </script>

</body>

</html>
