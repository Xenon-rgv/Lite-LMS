/**
 * Lite-LMS Authentication & Route Guard Helpers
 */

async function getCurrentUser() {
    if (!supabase) return null;
    const { data: { user }, error } = await supabase.auth.getUser();
    if (error || !user) return null;
    return user;
}

async function getCurrentProfile() {
    const user = await getCurrentUser();
    if (!user) return null;

    const { data: profile, error } = await supabase
        .from('profiles')
        .select('*, departments(name)')
        .eq('id', user.id)
        .single();

    if (error) {
        console.error("Error fetching user profile:", error);
        return null;
    }
    return profile;
}

/**
 * Route protection for student/lecturer pages
 * @param {string|null} expectedRole - 'student', 'lecturer', or null for any authenticated user
 * @returns {Promise<{user, profile}>}
 */
async function requireAuth(expectedRole = null) {
    if (!supabase) {
        alert("Supabase client is not loaded. Please verify frontend/js/config.js.");
        return null;
    }

    const { data: { session } } = await supabase.auth.getSession();

    if (!session) {
        // Find relative path to login.html
        const isInSubdir = window.location.pathname.includes('/student/') || window.location.pathname.includes('/lecturer/');
        window.location.href = isInSubdir ? '../login.html' : 'login.html';
        return null;
    }

    const profile = await getCurrentProfile();

    if (!profile) {
        console.warn("User has an auth session but no public.profiles entry.");
        return { session, profile: null };
    }

    if (expectedRole && profile.role !== expectedRole) {
        showToast(`Access restricted to ${expectedRole}s.`, 'error');
        setTimeout(() => {
            if (profile.role === 'lecturer') {
                window.location.href = '../lecturer/dashboard.html';
            } else {
                window.location.href = '../student/dashboard.html';
            }
        }, 1200);
        return null;
    }

    return { session, profile };
}

/**
 * Redirect logged in users away from login/register/index pages
 */
async function redirectIfLoggedIn() {
    if (!supabase) return;
    const { data: { session } } = await supabase.auth.getSession();
    if (session) {
        const profile = await getCurrentProfile();
        if (profile) {
            if (profile.role === 'lecturer') {
                window.location.href = 'lecturer/dashboard.html';
            } else {
                window.location.href = 'student/dashboard.html';
            }
        }
    }
}

/**
 * Log out user and redirect to login
 */
async function logout() {
    if (supabase) {
        await supabase.auth.signOut();
    }
    const isInSubdir = window.location.pathname.includes('/student/') || window.location.pathname.includes('/lecturer/');
    window.location.href = isInSubdir ? '../login.html' : 'login.html';
}
