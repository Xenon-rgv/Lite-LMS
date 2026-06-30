<?php
include_once 'config/database.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $role     = $_POST['role'] ?? '';
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!in_array($role, ['student', 'lecturer'], true)) {
        $_SESSION['error'] = "Please select a role.";
    } elseif (empty($email) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
    } else {

        $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $_SESSION['error'] = "No account found with that email.";
        } elseif ($user['role'] !== $role) {
            $_SESSION['error'] = "This email is not registered as a " . htmlspecialchars($role) . ".";
        } elseif (!password_verify($password, $user['password'])) {
            $_SESSION['error'] = "Incorrect password.";
        } else {
            // ---- Success: set up session ----
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            $conn->close();

            if ($user['role'] === 'student') {
                header("Location: student/dashboard.php");
            } else {
                header("Location: lecturer/dashboard.php");
            }
            exit();
        }
    }

    $conn->close();
    header("Location: login.php");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        input::placeholder {
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
                Welcome back
            </h2>
            <h4 class="text-black text-center text-[16px] lg:text-[18px] md:text-[18px] lg:font-semibold md:font-semibold">
                Log in to continue
            </h4>
        </div>

        <!-- STEP 1: Role selection -->
        <div id="role-select-step" class="flex flex-col items-center mt-8">
            <p class="text-black font-semibold mb-4">I am logging in as a:</p>
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

        <!-- STEP 2: Login form (filled in by inline JS based on role) -->
        <form id="login-form" action="login.php" method="post" class="hidden flex-col items-center justify-center mt-8">
            <button type="button" id="back-btn" class="text-sm text-gray-500 hover:text-black mb-4 self-start">&larr; Back</button>

            <input type="hidden" name="role" id="role-input">

            <p id="role-label" class="text-sm text-gray-600 mb-3"></p>

            <div id="form-fields" class="flex flex-col items-center"></div>

            <button type="submit" class="bg-black text-white font-bold py-2 px-4 rounded-md mt-2">Log In</button>
        </form>

        <p class="text-sm text-gray-600 mt-6">
            Don't have an account?
            <a href="register.php" class="text-black font-semibold underline">Sign up</a>
        </p>

    </div>

    <!-- ============================================================ -->
    <!-- Inline script: role pick -> show email/password fields.      -->
    <!-- Form itself still submits normally (POST to login.php).     -->
    <!-- ============================================================ -->
    <script>
        const roleSelectStep = document.getElementById('role-select-step');
        const loginForm       = document.getElementById('login-form');
        const roleInput        = document.getElementById('role-input');
        const roleLabel        = document.getElementById('role-label');
        const formFields       = document.getElementById('form-fields');
        const backBtn          = document.getElementById('back-btn');

        const inputClass = "border lg:w-[365px] w-[240px] border-gray-300 rounded-md px-4 py-2 mb-4";

        function loginFieldsHTML() {
            return `
                <input type="email" name="email" placeholder="Email" class="${inputClass}">
                <input type="password" name="password" placeholder="Password" class="${inputClass}">
            `;
        }

        function renderForm(role) {
            roleInput.value = role;
            roleLabel.textContent = "Logging in as " + (role === 'student' ? 'Student' : 'Lecturer');

            formFields.innerHTML = loginFieldsHTML();

            roleSelectStep.classList.add('hidden');
            loginForm.classList.remove('hidden');
            loginForm.classList.add('flex');
        }

        document.querySelectorAll('.role-card').forEach(card => {
            card.addEventListener('click', () => renderForm(card.dataset.role));
        });

        backBtn.addEventListener('click', () => {
            loginForm.classList.add('hidden');
            loginForm.classList.remove('flex');
            roleSelectStep.classList.remove('hidden');
        });
    </script>

</body>

</html>
