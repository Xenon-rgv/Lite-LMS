<?php
session_start();

// If already logged in, skip the landing page and go straight to their dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'student') {
        header("Location: student/dashboard.php");
    } else {
        header("Location: lecturer/dashboard.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Portal — Learn, Teach, Track Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-white text-black">

    <!-- ===================== NAVBAR ===================== -->
    <nav class="flex items-center justify-between px-6 lg:px-16 py-5 border-b border-gray-200">
        <span class="font-bold text-xl">School Portal</span>
        <div class="flex items-center gap-3">
            <a href="login.php" class="px-4 py-2 text-sm font-semibold rounded-md hover:bg-gray-100">Log In</a>
            <a href="register.php" class="px-4 py-2 text-sm font-semibold rounded-md bg-black text-white hover:bg-gray-800">Sign Up</a>
        </div>
    </nav>

    <!-- ===================== HERO ===================== -->
    <section class="px-6 lg:px-16 py-16 lg:py-24 text-center max-w-3xl mx-auto">
        <h1 class="text-3xl lg:text-5xl font-bold leading-tight">
            One place for course materials, attendance, and everything in between.
        </h1>
        <p class="mt-6 text-gray-600 text-base lg:text-lg">
            Lecturers post materials and run live attendance sessions. Students
            view materials securely and check in with a single code. Simple,
            fast, and built for the classroom.
        </p>
        <div class="mt-8 flex items-center justify-center gap-4">
            <a href="register.php" class="px-6 py-3 bg-black text-white font-semibold rounded-md hover:bg-gray-800">
                Get Started
            </a>
            <a href="login.php" class="px-6 py-3 border border-gray-300 font-semibold rounded-md hover:bg-gray-100">
                I already have an account
            </a>
        </div>
        <p class="mt-4 text-sm text-gray-400">You need an account to view any course content.</p>
    </section>

    <!-- ===================== FEATURES ===================== -->
    <section class="px-6 lg:px-16 py-16 bg-gray-50 border-t border-gray-200">
        <div class="max-w-5xl mx-auto grid sm:grid-cols-3 gap-8">

            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <div class="w-10 h-10 rounded-md bg-black text-white flex items-center justify-center font-bold mb-4">1</div>
                <h3 class="font-bold text-lg mb-2">Course Materials</h3>
                <p class="text-gray-600 text-sm">
                    Lecturers upload slides, documents, and videos for each course.
                    Materials open in a secure in-browser viewer — no download links,
                    no saving the raw file.
                </p>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <div class="w-10 h-10 rounded-md bg-black text-white flex items-center justify-center font-bold mb-4">2</div>
                <h3 class="font-bold text-lg mb-2">Live Attendance</h3>
                <p class="text-gray-600 text-sm">
                    Lecturers open a short attendance window with a code.
                    Students mark themselves present while the session is live —
                    no paper, no spreadsheets.
                </p>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <div class="w-10 h-10 rounded-md bg-black text-white flex items-center justify-center font-bold mb-4">3</div>
                <h3 class="font-bold text-lg mb-2">Built for Both Roles</h3>
                <p class="text-gray-600 text-sm">
                    Students and lecturers get completely separate dashboards,
                    tailored to what they actually need to do — viewing vs. managing.
                </p>
            </div>

        </div>
    </section>

    <!-- ===================== HOW IT WORKS ===================== -->
    <section class="px-6 lg:px-16 py-16">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-2xl font-bold text-center mb-10">How it works</h2>
            <div class="grid sm:grid-cols-2 gap-10">
                <div>
                    <h3 class="font-semibold text-black mb-3">For Lecturers</h3>
                    <ul class="text-gray-600 text-sm space-y-2 list-disc list-inside">
                        <li>Create an account as a Lecturer</li>
                        <li>Set up your course and add students</li>
                        <li>Upload materials for students to view</li>
                        <li>Open an attendance session anytime during class</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold text-black mb-3">For Students</h3>
                    <ul class="text-gray-600 text-sm space-y-2 list-disc list-inside">
                        <li>Create an account as a Student</li>
                        <li>View materials for your enrolled courses</li>
                        <li>Enter the attendance code while a session is live</li>
                        <li>Track what you've attended, all in one place</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================== CTA STRIP ===================== -->
    <section class="px-6 lg:px-16 py-14 bg-black text-white text-center">
        <h2 class="text-2xl font-bold mb-3">Ready to get started?</h2>
        <p class="text-gray-300 mb-6">Create your free account in under a minute.</p>
        <a href="register.php" class="px-6 py-3 bg-white text-black font-semibold rounded-md hover:bg-gray-200 inline-block">
            Sign Up Now
        </a>
    </section>

    <!-- ===================== FOOTER ===================== -->
    <footer class="px-6 lg:px-16 py-6 text-center text-sm text-gray-400 border-t border-gray-200">
        &copy; <?php echo date('Y'); ?> School Portal. All rights reserved.
    </footer>

</body>

</html>
