<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_role('lecturer', '../');

require_once __DIR__ . '/../config/database.php';

$lecturer_user_id = $_SESSION['user_id'];
$course_id = (int)($_GET['id'] ?? 0);

// ---- Get this lecturer's internal lecturers.id ----
$stmt = $conn->prepare("SELECT id FROM lecturers WHERE user_id = ?");
$stmt->bind_param("i", $lecturer_user_id);
$stmt->execute();
$lecturer_row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$lecturer_id = $lecturer_row['id'] ?? null;

// ---- Fetch course, and confirm it belongs to this lecturer ----
$stmt = $conn->prepare("
    SELECT c.*, d.name AS department_name
    FROM courses c
    JOIN departments d ON d.id = c.department_id
    WHERE c.id = ? AND c.lecturer_id = ?
");
$stmt->bind_param("ii", $course_id, $lecturer_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    // Not their course (or doesn't exist) — bounce them back
    header("Location: dashboard.php");
    exit();
}

// ---- Handle: enroll a student by matric number ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_student'])) {
    $matric_no = trim($_POST['matric_no'] ?? '');

    $stmt = $conn->prepare("SELECT id FROM students WHERE matric_no = ?");
    $stmt->bind_param("s", $matric_no);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        $_SESSION['error'] = "No student found with that matric number.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
        $stmt->bind_param("ii", $student['id'], $course_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $_SESSION['error'] = "This student is already enrolled.";
            $stmt->close();
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $student['id'], $course_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = "Student enrolled successfully.";
        }
    }

    header("Location: course.php?id=" . $course_id);
    exit();
}

// ---- Handle: remove a student ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_student'])) {
    $enrollment_id = (int)$_POST['enrollment_id'];
    $stmt = $conn->prepare("DELETE FROM enrollments WHERE id = ? AND course_id = ?");
    $stmt->bind_param("ii", $enrollment_id, $course_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success'] = "Student removed from course.";

    header("Location: course.php?id=" . $course_id);
    exit();
}

// ---- Handle: upload a material ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    $title = trim($_POST['material_title'] ?? '');

    if (empty($title) || empty($_FILES['material_file']['name'])) {
        $_SESSION['error'] = "Please provide a title and choose a file.";
    } else {
        $file = $_FILES['material_file'];

        $allowed_ext = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'mp4', 'mov', 'jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Upload failed. Please try again.";
        } elseif (!in_array($ext, $allowed_ext, true)) {
            $_SESSION['error'] = "File type not allowed.";
        } else {
            // Store outside the public web root with a random name.
            // The original filename / extension is kept only in the DB.
            $upload_dir = __DIR__ . '/../uploads/materials/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $stored_name = bin2hex(random_bytes(16)) . '.' . $ext;
            $destination = $upload_dir . $stored_name;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $stmt = $conn->prepare("
                    INSERT INTO materials (course_id, title, file_path, file_type, uploaded_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("isssi", $course_id, $title, $stored_name, $ext, $lecturer_id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['success'] = "Material uploaded.";
            } else {
                $_SESSION['error'] = "Could not save the uploaded file.";
            }
        }
    }

    header("Location: course.php?id=" . $course_id);
    exit();
}

// ---- Handle: delete a material ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_material'])) {
    $material_id = (int)$_POST['material_id'];

    $stmt = $conn->prepare("SELECT file_path FROM materials WHERE id = ? AND course_id = ?");
    $stmt->bind_param("ii", $material_id, $course_id);
    $stmt->execute();
    $material = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($material) {
        $file_path = __DIR__ . '/../uploads/materials/' . $material['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $stmt = $conn->prepare("DELETE FROM materials WHERE id = ?");
        $stmt->bind_param("i", $material_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Material deleted.";
    }

    header("Location: course.php?id=" . $course_id);
    exit();
}

// ---- Handle: open a new attendance session ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['open_attendance'])) {
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 10);
    $duration_minutes = max(1, min(120, $duration_minutes)); // clamp 1–120 mins

    // Close any previously active sessions for this course
    $stmt = $conn->prepare("UPDATE attendance_sessions SET is_active = 0 WHERE course_id = ? AND is_active = 1");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $stmt->close();

    $code = strtoupper(bin2hex(random_bytes(3))); // 6-char code

    $stmt = $conn->prepare("
        INSERT INTO attendance_sessions (course_id, code, closes_at, is_active)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), 1)
    ");
    $stmt->bind_param("isi", $course_id, $code, $duration_minutes);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'] = "Attendance session opened. Code: " . $code;
    header("Location: course.php?id=" . $course_id);
    exit();
}

// ---- Fetch enrolled students ----
$students = [];
$stmt = $conn->prepare("
    SELECT e.id AS enrollment_id, s.matric_no, u.name
    FROM enrollments e
    JOIN students s ON s.id = e.student_id
    JOIN users u ON u.id = s.user_id
    WHERE e.course_id = ?
    ORDER BY u.name ASC
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

// ---- Fetch materials ----
$materials = [];
$stmt = $conn->prepare("SELECT id, title, file_type, created_at FROM materials WHERE course_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $materials[] = $row;
}
$stmt->close();

// ---- Fetch active attendance session (if any) ----
$stmt = $conn->prepare("
    SELECT *, TIMESTAMPDIFF(SECOND, NOW(), closes_at) AS seconds_left
    FROM attendance_sessions
    WHERE course_id = ? AND is_active = 1 AND closes_at > NOW()
    ORDER BY opened_at DESC LIMIT 1
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$active_session = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ---- Attendance count for active session ----
$attendance_count = 0;
if ($active_session) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM attendance_records WHERE session_id = ?");
    $stmt->bind_param("i", $active_session['id']);
    $stmt->execute();
    $attendance_count = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['code']); ?> | Lecturer</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">

    <!-- ===================== NAVBAR ===================== -->
    <nav class="flex items-center justify-between px-6 lg:px-12 py-4 bg-white border-b border-gray-200">
        <a href="dashboard.php" class="font-bold text-lg">&larr; My Courses</a>
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

        <!-- Course header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($course['code']); ?> — <?php echo htmlspecialchars($course['title']); ?></h1>
            <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($course['department_name']); ?> · <?php echo htmlspecialchars($course['level']); ?> Level · <?php echo count($students); ?> students</p>
        </div>

        <!-- ===================== TABS ===================== -->
        <div class="flex gap-2 mb-6 border-b border-gray-200">
            <button class="tab-btn px-4 py-2 font-semibold border-b-2 border-black" data-tab="materials">Materials</button>
            <button class="tab-btn px-4 py-2 font-semibold border-b-2 border-transparent text-gray-500" data-tab="students">Students</button>
            <button class="tab-btn px-4 py-2 font-semibold border-b-2 border-transparent text-gray-500" data-tab="attendance">Attendance</button>
            <button class="tab-btn px-4 py-2 font-semibold border-b-2 border-transparent text-gray-500" data-tab="chat">Chat</button>
        </div>

        <!-- ===================== MATERIALS TAB ===================== -->
        <div id="tab-materials" class="tab-panel">
            <div class="bg-white border border-gray-200 rounded-xl p-5 mb-6">
                <h3 class="font-bold mb-3">Upload Material</h3>
                <form method="post" enctype="multipart/form-data" class="flex flex-col gap-3">
                    <input type="hidden" name="upload_material" value="1">
                    <input type="text" name="material_title" placeholder="Title (e.g. Week 1 Slides)" required
                        class="border border-gray-300 rounded-md px-3 py-2">
                    <input type="file" name="material_file" required
                        accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.mov,.jpg,.jpeg,.png"
                        class="border border-gray-300 rounded-md px-3 py-2 bg-white">
                    <button type="submit" class="bg-black text-white font-bold py-2 rounded-md hover:bg-gray-800">Upload</button>
                </form>
            </div>

            <div class="space-y-3">
                <?php if (empty($materials)) : ?>
                    <p class="text-gray-500 text-center py-6">No materials uploaded yet.</p>
                <?php else : ?>
                    <?php foreach ($materials as $m) : ?>
                        <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center justify-between">
                            <div>
                                <p class="font-semibold"><?php echo htmlspecialchars($m['title']); ?></p>
                                <p class="text-xs text-gray-500 uppercase"><?php echo htmlspecialchars($m['file_type']); ?> · <?php echo date('M j, Y', strtotime($m['created_at'])); ?></p>
                            </div>
                            <form method="post" onsubmit="return confirm('Delete this material?');">
                                <input type="hidden" name="delete_material" value="1">
                                <input type="hidden" name="material_id" value="<?php echo $m['id']; ?>">
                                <button type="submit" class="text-red-600 text-sm font-semibold hover:underline">Delete</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===================== STUDENTS TAB ===================== -->
        <div id="tab-students" class="tab-panel hidden">
            <div class="bg-white border border-gray-200 rounded-xl p-5 mb-6">
                <h3 class="font-bold mb-3">Enroll a Student</h3>
                <form method="post" class="flex gap-3">
                    <input type="hidden" name="enroll_student" value="1">
                    <input type="text" name="matric_no" placeholder="Student matric number" required
                        class="flex-1 border border-gray-300 rounded-md px-3 py-2">
                    <button type="submit" class="bg-black text-white font-bold px-5 rounded-md hover:bg-gray-800">Add</button>
                </form>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-100">
                <?php if (empty($students)) : ?>
                    <p class="text-gray-500 text-center py-6">No students enrolled yet.</p>
                <?php else : ?>
                    <?php foreach ($students as $s) : ?>
                        <div class="flex items-center justify-between px-4 py-3">
                            <div>
                                <p class="font-semibold"><?php echo htmlspecialchars($s['name']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($s['matric_no']); ?></p>
                            </div>
                            <form method="post" onsubmit="return confirm('Remove this student from the course?');">
                                <input type="hidden" name="remove_student" value="1">
                                <input type="hidden" name="enrollment_id" value="<?php echo $s['enrollment_id']; ?>">
                                <button type="submit" class="text-red-600 text-sm font-semibold hover:underline">Remove</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===================== ATTENDANCE TAB ===================== -->
        <div id="tab-attendance" class="tab-panel hidden">
            <?php if ($active_session) : ?>
                <div class="bg-white border border-gray-200 rounded-xl p-6 text-center mb-6">
                    <p class="text-gray-500 text-sm mb-1">Active attendance code</p>
                    <p class="text-4xl font-bold tracking-widest mb-3"><?php echo htmlspecialchars($active_session['code']); ?></p>
                    <p class="text-sm text-gray-500" id="time-left" data-seconds="<?php echo (int)$active_session['seconds_left']; ?>"></p>
                    <p class="mt-4 text-sm text-gray-700"><?php echo (int)$attendance_count; ?> student<?php echo $attendance_count == 1 ? '' : 's'; ?> marked present so far</p>
                </div>
            <?php else : ?>
                <div class="bg-white border border-gray-200 rounded-xl p-5 mb-6">
                    <h3 class="font-bold mb-3">Open Attendance Session</h3>
                    <form method="post" class="flex gap-3 items-end">
                        <input type="hidden" name="open_attendance" value="1">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Duration (minutes)</label>
                            <input type="number" name="duration_minutes" value="10" min="1" max="120"
                                class="border border-gray-300 rounded-md px-3 py-2 mt-1 w-32">
                        </div>
                        <button type="submit" class="bg-black text-white font-bold px-5 py-2 rounded-md hover:bg-gray-800">
                            Open Session
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===================== CHAT TAB ===================== -->
        <div id="tab-chat" class="tab-panel hidden">
            <div class="bg-white border border-gray-200 rounded-xl flex flex-col h-[500px]">
                <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-3"></div>
                <form id="chat-form" class="border-t border-gray-200 p-3 flex gap-2">
                    <input type="text" id="chat-input" placeholder="Message your students..." autocomplete="off"
                        class="flex-1 border border-gray-300 rounded-md px-3 py-2">
                    <button type="submit" class="bg-black text-white font-bold px-5 rounded-md hover:bg-gray-800">Send</button>
                </form>
            </div>
        </div>

    </div>

    <script>
        // ---- Tab switching ----
        const tabBtns   = document.querySelectorAll('.tab-btn');
        const tabPanels = document.querySelectorAll('.tab-panel');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                tabBtns.forEach(b => {
                    b.classList.remove('border-black');
                    b.classList.add('border-transparent', 'text-gray-500');
                });
                btn.classList.add('border-black');
                btn.classList.remove('border-transparent', 'text-gray-500');

                tabPanels.forEach(p => p.classList.add('hidden'));
                document.getElementById('tab-' + btn.dataset.tab).classList.remove('hidden');
            });
        });

        // ---- Attendance countdown ----
        const timeLeftEl = document.getElementById('time-left');
        if (timeLeftEl) {
            let secondsLeft = parseInt(timeLeftEl.dataset.seconds, 10);

            function renderCountdown() {
                if (secondsLeft <= 0) {
                    timeLeftEl.textContent = "Session closed — refresh to open a new one.";
                    return;
                }
                const m = Math.floor(secondsLeft / 60);
                const s = secondsLeft % 60;
                timeLeftEl.textContent = `Closes in ${m}m ${s}s`;
                secondsLeft--;
            }
            renderCountdown();
            setInterval(renderCountdown, 1000);
        }

        // ---- Chat: polling ----
        const COURSE_ID = <?php echo (int)$course_id; ?>;
        const chatMessagesEl = document.getElementById('chat-messages');
        const chatForm = document.getElementById('chat-form');
        const chatInput = document.getElementById('chat-input');
        let lastMessageId = 0;

        function escapeHTML(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function renderMessages(messages, append) {
            if (!append) chatMessagesEl.innerHTML = '';

            messages.forEach(msg => {
                const isMine = msg.sender_role === 'lecturer';
                const bubble = document.createElement('div');
                bubble.className = isMine ? 'flex justify-end' : 'flex justify-start';
                bubble.innerHTML = `
                    <div class="${isMine ? 'bg-black text-white' : 'bg-gray-100 text-black'} rounded-lg px-3 py-2 max-w-xs">
                        <p class="text-xs ${isMine ? 'text-gray-300' : 'text-gray-500'} mb-0.5">${escapeHTML(msg.sender_name)}</p>
                        <p class="text-sm">${escapeHTML(msg.message)}</p>
                    </div>
                `;
                chatMessagesEl.appendChild(bubble);
                lastMessageId = Math.max(lastMessageId, parseInt(msg.id, 10));
            });

            chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
        }

        async function fetchMessages(initial = false) {
            try {
                const res = await fetch(`../chat/fetch.php?course_id=${COURSE_ID}&after=${initial ? 0 : lastMessageId}`);
                const data = await res.json();
                if (data.success && data.messages.length > 0) {
                    renderMessages(data.messages, !initial);
                }
            } catch (err) {
                // Silently ignore — next poll will retry
            }
        }

        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = chatInput.value.trim();
            if (!message) return;

            chatInput.value = '';

            try {
                await fetch('../chat/send.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ course_id: COURSE_ID, message })
                });
                fetchMessages();
            } catch (err) {
                alert('Could not send message. Please try again.');
            }
        });

        fetchMessages(true);
        setInterval(() => fetchMessages(false), 4000);
    </script>

</body>

</html>
