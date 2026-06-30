<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_role('student', '../');

require_once __DIR__ . '/../config/database.php';

$student_user_id = $_SESSION['user_id'];
$course_id = (int)($_GET['id'] ?? 0);

// ---- Get this student's internal students.id ----
$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $student_user_id);
$stmt->execute();
$student_row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$student_id = $student_row['id'] ?? null;

// ---- Confirm this student is enrolled in the course ----
$stmt = $conn->prepare("
    SELECT c.*, d.name AS department_name, u.name AS lecturer_name
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    JOIN departments d ON d.id = c.department_id
    JOIN lecturers l ON l.id = c.lecturer_id
    JOIN users u ON u.id = l.user_id
    WHERE e.course_id = ? AND e.student_id = ?
");
$stmt->bind_param("ii", $course_id, $student_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    // Not enrolled (or course doesn't exist) — bounce them back
    header("Location: dashboard.php");
    exit();
}

// ---- Handle: mark attendance with a code ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $entered_code = strtoupper(trim($_POST['attendance_code'] ?? ''));

    $stmt = $conn->prepare("
        SELECT * FROM attendance_sessions
        WHERE course_id = ? AND code = ? AND is_active = 1 AND closes_at > NOW()
    ");
    $stmt->bind_param("is", $course_id, $entered_code);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$session) {
        $_SESSION['error'] = "Invalid or expired attendance code.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM attendance_records WHERE session_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $session['id'], $student_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $_SESSION['error'] = "You've already marked attendance for this session.";
            $stmt->close();
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO attendance_records (session_id, student_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $session['id'], $student_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = "Attendance marked. You're checked in!";
        }
    }

    header("Location: course.php?id=" . $course_id);
    exit();
}

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

// ---- Check if there's an active attendance session ----
$stmt = $conn->prepare("
    SELECT id FROM attendance_sessions
    WHERE course_id = ? AND is_active = 1 AND closes_at > NOW()
    ORDER BY opened_at DESC LIMIT 1
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$active_session = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ---- Has this student already marked attendance for the active session? ----
$already_marked = false;
if ($active_session) {
    $stmt = $conn->prepare("SELECT id FROM attendance_records WHERE session_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $active_session['id'], $student_id);
    $stmt->execute();
    $stmt->store_result();
    $already_marked = $stmt->num_rows > 0;
    $stmt->close();
}

// ---- Attendance history for this student in this course ----
$attendance_history = [];
$stmt = $conn->prepare("
    SELECT ar.marked_at
    FROM attendance_records ar
    JOIN attendance_sessions ats ON ats.id = ar.session_id
    WHERE ats.course_id = ? AND ar.student_id = ?
    ORDER BY ar.marked_at DESC
");
$stmt->bind_param("ii", $course_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $attendance_history[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['code']); ?> | Student</title>
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
            <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($course['department_name']); ?> · <?php echo htmlspecialchars($course['level']); ?> Level · Lecturer: <?php echo htmlspecialchars($course['lecturer_name']); ?></p>
        </div>

        <!-- ===================== TABS ===================== -->
        <div class="flex gap-2 mb-6 border-b border-gray-200">
            <button class="tab-btn px-4 py-2 font-semibold border-b-2 border-black" data-tab="materials">Materials</button>
            <button class="tab-btn px-4 py-2 font-semibold border-b-2 border-transparent text-gray-500" data-tab="attendance">Attendance</button>
            <button class="tab-btn px-4 py-2 font-semibold border-b-2 border-transparent text-gray-500" data-tab="chat">Chat</button>
        </div>

        <!-- ===================== MATERIALS TAB ===================== -->
        <div id="tab-materials" class="tab-panel">
            <div class="space-y-3">
                <?php if (empty($materials)) : ?>
                    <p class="text-gray-500 text-center py-6">No materials posted yet.</p>
                <?php else : ?>
                    <?php foreach ($materials as $m) : ?>
                        <button type="button"
                            class="view-material-btn w-full text-left bg-white border border-gray-200 rounded-xl p-4 flex items-center justify-between hover:border-black transition"
                            data-id="<?php echo $m['id']; ?>" data-title="<?php echo htmlspecialchars($m['title']); ?>" data-type="<?php echo htmlspecialchars($m['file_type']); ?>">
                            <div>
                                <p class="font-semibold"><?php echo htmlspecialchars($m['title']); ?></p>
                                <p class="text-xs text-gray-500 uppercase"><?php echo htmlspecialchars($m['file_type']); ?> · <?php echo date('M j, Y', strtotime($m['created_at'])); ?></p>
                            </div>
                            <span class="text-sm font-semibold text-black">View &rarr;</span>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===================== ATTENDANCE TAB ===================== -->
        <div id="tab-attendance" class="tab-panel hidden">
            <?php if ($active_session && !$already_marked) : ?>
                <div class="bg-white border border-gray-200 rounded-xl p-5 mb-6">
                    <h3 class="font-bold mb-3">An attendance session is live</h3>
                    <form method="post" class="flex gap-3">
                        <input type="hidden" name="mark_attendance" value="1">
                        <input type="text" name="attendance_code" placeholder="Enter code" required maxlength="10"
                            class="flex-1 border border-gray-300 rounded-md px-3 py-2 uppercase tracking-widest">
                        <button type="submit" class="bg-black text-white font-bold px-5 rounded-md hover:bg-gray-800">Check In</button>
                    </form>
                </div>
            <?php elseif ($active_session && $already_marked) : ?>
                <div class="bg-green-50 border border-green-200 rounded-xl p-5 mb-6 text-green-700 text-center">
                    You've already marked attendance for the current session. ✅
                </div>
            <?php else : ?>
                <div class="bg-white border border-gray-200 rounded-xl p-5 mb-6 text-center text-gray-500">
                    No attendance session is currently open for this course.
                </div>
            <?php endif; ?>

            <h3 class="font-bold mb-3">Attendance History</h3>
            <div class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-100">
                <?php if (empty($attendance_history)) : ?>
                    <p class="text-gray-500 text-center py-6">No attendance recorded yet.</p>
                <?php else : ?>
                    <?php foreach ($attendance_history as $h) : ?>
                        <div class="px-4 py-3 text-sm flex justify-between">
                            <span>Marked present</span>
                            <span class="text-gray-500"><?php echo date('M j, Y \a\t g:i A', strtotime($h['marked_at'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===================== CHAT TAB ===================== -->
        <div id="tab-chat" class="tab-panel hidden">
            <div class="bg-white border border-gray-200 rounded-xl flex flex-col h-[500px]">
                <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-3"></div>
                <form id="chat-form" class="border-t border-gray-200 p-3 flex gap-2">
                    <input type="text" id="chat-input" placeholder="Message your lecturer..." autocomplete="off"
                        class="flex-1 border border-gray-300 rounded-md px-3 py-2">
                    <button type="submit" class="bg-black text-white font-bold px-5 rounded-md hover:bg-gray-800">Send</button>
                </form>
            </div>
        </div>

    </div>

    <!-- ===================== SECURE MATERIAL VIEWER MODAL ===================== -->
    <div id="viewer-modal" class="hidden fixed inset-0 bg-black/80 z-50 flex flex-col">
        <div class="flex items-center justify-between px-4 py-3 bg-black text-white">
            <span id="viewer-title" class="font-semibold truncate"></span>
            <button id="viewer-close-btn" class="text-2xl leading-none px-2">&times;</button>
        </div>
        <div id="viewer-body" class="flex-1 overflow-auto flex items-center justify-center bg-gray-900">
            <!-- content injected by JS -->
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

        // ---- Secure material viewer ----
        const viewerModal = document.getElementById('viewer-modal');
        const viewerTitle = document.getElementById('viewer-title');
        const viewerBody   = document.getElementById('viewer-body');
        const viewerCloseBtn = document.getElementById('viewer-close-btn');

        document.querySelectorAll('.view-material-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id    = btn.dataset.id;
                const title = btn.dataset.title;
                const type  = btn.dataset.type.toLowerCase();

                viewerTitle.textContent = title;

                const streamUrl = `../materials/stream.php?id=${id}`;

                if (['jpg', 'jpeg', 'png'].includes(type)) {
                    viewerBody.innerHTML = `<img src="${streamUrl}" class="max-h-full max-w-full select-none pointer-events-none" oncontextmenu="return false" draggable="false">`;
                } else if (['mp4', 'mov'].includes(type)) {
                    viewerBody.innerHTML = `<video src="${streamUrl}" class="max-h-full max-w-full" controls controlsList="nodownload" oncontextmenu="return false"></video>`;
                } else {
                    // PDFs, docs, ppts — render through the browser's built-in viewer in an iframe
                    viewerBody.innerHTML = `<iframe src="${streamUrl}" class="w-full h-full bg-white" oncontextmenu="return false"></iframe>`;
                }

                viewerModal.classList.remove('hidden');
            });
        });

        viewerCloseBtn.addEventListener('click', () => {
            viewerModal.classList.add('hidden');
            viewerBody.innerHTML = '';
        });

        // Deterrent: block right-click and common save/print/devtools shortcuts while viewer is open
        document.addEventListener('contextmenu', (e) => {
            if (!viewerModal.classList.contains('hidden')) e.preventDefault();
        });
        document.addEventListener('keydown', (e) => {
            if (viewerModal.classList.contains('hidden')) return;
            const blocked = (e.ctrlKey || e.metaKey) && ['s', 'p', 'u'].includes(e.key.toLowerCase());
            if (blocked || e.key === 'F12') e.preventDefault();
        });

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
                const isMine = msg.sender_role === 'student' && msg.is_self;
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
