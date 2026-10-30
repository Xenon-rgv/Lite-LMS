/**
 * Lite-LMS Supabase Configuration & Client Initialization
 * 
 * Replace the placeholders below with your Supabase project credentials.
 * You can find them in your Supabase Dashboard under:
 * Settings -> API -> Project URL & Project API Keys (anon public)
 */

const SUPABASE_CONFIG = {
    URL: "https://YOUR_SUPABASE_PROJECT_ID.supabase.co",
    ANON_KEY: "YOUR_SUPABASE_ANON_PUBLIC_KEY"
};

// Check if valid credentials are provided
const isConfigured = !SUPABASE_CONFIG.URL.includes("YOUR_SUPABASE_PROJECT_ID");

if (!isConfigured && window.location.pathname !== '/' && !window.location.pathname.endsWith('index.html')) {
    console.warn("Lite-LMS: Supabase credentials are not configured yet! Please update frontend/js/config.js");
}

// Initialize Supabase Client
const supabase = window.supabase ? window.supabase.createClient(SUPABASE_CONFIG.URL, SUPABASE_CONFIG.ANON_KEY) : null;

// UI Toast Notification Helper
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const colors = {
        success: 'bg-emerald-600 text-white',
        error: 'bg-rose-600 text-white',
        info: 'bg-indigo-600 text-white',
        warning: 'bg-amber-600 text-white'
    };

    toast.className = `px-4 py-3 rounded-xl shadow-xl text-sm font-medium transition-all transform duration-300 translate-y-4 opacity-0 pointer-events-auto flex items-center gap-2 ${colors[type] || colors.info}`;
    toast.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="ml-2 text-white/80 hover:text-white">&times;</button>
    `;

    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.remove('translate-y-4', 'opacity-0');
    }, 10);

    setTimeout(() => {
        toast.classList.add('translate-y-4', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}
