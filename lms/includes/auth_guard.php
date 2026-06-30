<?php
/**
 * Auth guard.
 *
 * Include this at the very top of any page that should only be
 * reachable by a logged-in user. Optionally pass a required role
 * to lock a page to just 'student' or just 'lecturer'.
 *
 * Usage (from inside /lecturer/ or /student/ folders):
 *   require_once __DIR__ . '/../includes/auth_guard.php';
 *   require_role('student');   // or require_role('lecturer')
 *
 * Paths below are relative to the page that includes this file,
 * so every protected page must pass its own "depth" — see the
 * $redirect_prefix parameter.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * @param string $redirect_prefix Path prefix to get back to the project root
 *                                from the including page's folder.
 *                                e.g. '../' if the page lives in /lecturer/ or /student/
 *                                e.g. ''    if the page lives at the project root
 */
function require_login(string $redirect_prefix = ''): void
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . $redirect_prefix . "login.php");
        exit();
    }
}

function require_role(string $role, string $redirect_prefix = ''): void
{
    require_login($redirect_prefix);

    if ($_SESSION['role'] !== $role) {
        // Logged in, but wrong role for this page — send them to their own dashboard
        if ($_SESSION['role'] === 'student') {
            header("Location: " . $redirect_prefix . "student/dashboard.php");
        } else {
            header("Location: " . $redirect_prefix . "lecturer/dashboard.php");
        }
        exit();
    }
}

