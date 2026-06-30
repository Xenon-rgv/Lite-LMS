<?php
include_once 'config/database.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $role             = $_POST['role'] ?? '';
    $name             = trim($_POST['name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $department_id    = $_POST['department_id'] ?? '';

    // Role-specific fields
    $matric_no = trim($_POST['matric_no'] ?? '');
    $level     = $_POST['level'] ?? '';
    $staff_id  = trim($_POST['staff_id'] ?? '');

    // ---- Shared validation ----
    if (!in_array($role, ['student', 'lecturer'], true)) {
        $_SESSION['error'] = "Please select a role.";
    } elseif (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($department_id)) {
        $_SESSION['error'] = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long.";
    }
    // ---- Role-specific validation ----
    elseif ($role === 'student' && (empty($matric_no) || empty($level))) {
        $_SESSION['error'] = "Please fill in your matric number and level.";
    } elseif ($role === 'lecturer' && empty($staff_id)) {
        $_SESSION['error'] = "Please fill in your staff ID.";
    } else {

        // ---- Check email uniqueness ----
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $_SESSION['error'] = "This email is already registered.";
            $stmt->close();
        } else {
            $stmt->close();

            // ---- Role-specific uniqueness checks ----
            $duplicate = false;

            if ($role === 'student') {
                $stmt = $conn->prepare("SELECT id FROM students WHERE matric_no = ?");
                $stmt->bind_param("s", $matric_no);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $_SESSION['error'] = "This matric number is already registered.";
                    $duplicate = true;
                }
                $stmt->close();
            } else {
                $stmt = $conn->prepare("SELECT id FROM lecturers WHERE staff_id = ?");
                $stmt->bind_param("s", $staff_id);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $_SESSION['error'] = "This staff ID is already registered.";
                    $duplicate = true;
                }
                $stmt->close();
            }

            if (!$duplicate) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $conn->begin_transaction();

                try {
                    // Insert into users
                    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
                    $stmt->execute();
                    $user_id = $stmt->insert_id;
                    $stmt->close();

                    // Insert into role-specific table
                    if ($role === 'student') {
                        $stmt = $conn->prepare("INSERT INTO students (user_id, matric_no, department_id, level) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("isis", $user_id, $matric_no, $department_id, $level);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        $stmt = $conn->prepare("INSERT INTO lecturers (user_id, staff_id, department_id) VALUES (?, ?, ?)");
                        $stmt->bind_param("isi", $user_id, $staff_id, $department_id);
                        $stmt->execute();
                        $stmt->close();
                    }

                    $conn->commit();

                    $_SESSION['success'] = "Registration successful! You can now log in.";
                    $conn->close();
                    header("Location: login.php");
                    exit();

                } catch (mysqli_sql_exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error: Unable to register. Please try again.";
                }
            }
        }
    }

    $conn->close();
    header("Location: register.php");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        input::placeholder,
        select {
            color: rgb(1, 1, 1);
            opacity: 1;
        }

        input:focus::placeholder {
            color: rgb(10, 10, 10);
        }
    </style>
</head>

<body class="bg-gray-100">

    <!-- Notification Messages -->
    <div class="container mx-auto mt-4 px-4">
        <?php if (isset($_SESSION['error'])) : ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative max-w-md mx-auto" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
            </div>
        <?php elseif (isset($_SESSION['success'])) : ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative max-w-md mx-auto" role="alert">
                <strong class="font-bold">Success!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="flex flex-col items-center justify-center min-h-screen py-8">

        <div>
            <h2 class="text-black-500 text-[24px] py-[10px] lg:text-[28px] lg:py-[20px] font-bold text-center">
                Sign up and start learning
            </h2>
            <h4 class="text-black text-center text-[16px] lg:text-[18px] md:text-[18px] lg:font-semibold md:font-semibold">
                Create your free account now
            </h4>
        </div>

        <!-- STEP 1: Role selection -->
        <div id="role-select-step" class="flex flex-col items-center mt-8">
            <p class="text-black font-semibold mb-4">I am registering as a:</p>
            <div class="flex gap-4">
                <button type="button" data-role="student"
                    class="role-card border-2 border-gray-300 rounded-md px-8 py-6 flex flex-col items-center gap-2 hover:border-black transition w-[150px]">
                    <span class="font-bold text-black">Student</span>
                </button>
                <button type="button" data-role="lecturer"
                    class="role-card border-2 border-gray-300 rounded-md px-8 py-6 flex flex-col items-center gap-2 hover:border-black transition w-[150px]">
                    <span class="font-bold text-black">Lecturer</span>
                </button>
            </div>
        </div>

        <!-- STEP 2: Registration form (filled in by inline JS based on role) -->
        <form id="register-form" action="register.php" method="post" class="hidden flex-col items-center justify-center mt-8">
            <button type="button" id="back-btn" class="text-sm text-gray-500 hover:text-black mb-4 self-start">&larr; Back</button>

            <input type="hidden" name="role" id="role-input">

            <div id="form-fields" class="flex flex-col items-center"></div>

            <button type="submit" class="bg-black text-white font-bold py-2 px-4 rounded-md mt-2">Sign-Up</button>
        </form>

    </div>

    <!-- ============================================================ -->
    <!-- Inline script: role pick -> dynamic form fields.              -->
    <!-- Form itself still submits normally (POST to register.php),   -->
    <!-- only the field rendering happens here.                       -->
    <!-- ============================================================ -->
    <script>
        const roleSelectStep = document.getElementById('role-select-step');
        const registerForm    = document.getElementById('register-form');
        const roleInput        = document.getElementById('role-input');
        const formFields       = document.getElementById('form-fields');
        const backBtn          = document.getElementById('back-btn');

        const inputClass = "border lg:w-[365px] w-[240px] border-gray-300 rounded-md px-4 py-2 mb-4";

        let departmentsCache = null;

        const sharedFieldsHTML = `
            <input type="text" name="name" placeholder="Name" class="${inputClass}">
            <input type="email" name="email" placeholder="Email" class="${inputClass}">
        `;

        const passwordFieldsHTML = `
            <input type="password" name="password" placeholder="Password" class="${inputClass}">
            <input type="password" name="confirm_password" placeholder="Confirm Password" class="${inputClass}">
        `;

        function departmentSelectHTML() {
            return `
                <select name="department_id" class="${inputClass}">
                    <option value="">Select department</option>
                </select>
            `;
        }

        function studentFieldsHTML() {
            return `
                <input type="text" name="matric_no" placeholder="Matric Number (e.g. CSC/2021/045)" class="${inputClass}">
                ${departmentSelectHTML()}
                <select name="level" class="${inputClass}">
                    <option value="">Select level</option>
                    <option value="100">100 Level</option>
                    <option value="200">200 Level</option>
                    <option value="300">300 Level</option>
                    <option value="400">400 Level</option>
                    <option value="500">500 Level</option>
                </select>
            `;
        }

        function lecturerFieldsHTML() {
            return `
                <input type="text" name="staff_id" placeholder="Staff ID (e.g. STF-2014-102)" class="${inputClass}">
                ${departmentSelectHTML()}
            `;
        }

        function renderForm(role) {
            roleInput.value = role;

            const roleSpecific = role === 'student' ? studentFieldsHTML() : lecturerFieldsHTML();

            formFields.innerHTML = sharedFieldsHTML + roleSpecific + passwordFieldsHTML;

            roleSelectStep.classList.add('hidden');
            registerForm.classList.remove('hidden');
            registerForm.classList.add('flex');

            loadDepartments();
        }

        async function loadDepartments() {
            const select = formFields.querySelector('select[name="department_id"]');
            if (!select) return;

            try {
                if (!departmentsCache) {
                    const res = await fetch('get_departments.php');
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message);
                    departmentsCache = data.departments;
                }

                select.innerHTML = '<option value="">Select department</option>' +
                    departmentsCache.map(d => `<option value="${d.id}">${escapeHTML(d.name)}</option>`).join('');

            } catch (err) {
                select.innerHTML = '<option value="">Could not load departments</option>';
            }
        }

        function escapeHTML(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        document.querySelectorAll('.role-card').forEach(card => {
            card.addEventListener('click', () => renderForm(card.dataset.role));
        });

        backBtn.addEventListener('click', () => {
            registerForm.classList.add('hidden');
            registerForm.classList.remove('flex');
            roleSelectStep.classList.remove('hidden');
        });
    </script>

</body>

</html>
