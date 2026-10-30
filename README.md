# Lite-LMS (Supabase & Tailwind Edition)

A serverless, modern Learning Management System built using **HTML5**, **Tailwind CSS**, and **Supabase (PostgreSQL, Supabase Auth, Storage, and Realtime WebSockets)**.

Ready for 1-click deployment on **Vercel** or **GitHub Pages**.

---

## 🚀 Key Features

* **Zero-Server Maintenance**: Runs on pure static HTML + JS without any Apache, Nginx, or PHP runtime.
* **Supabase Authentication**: Secure JWT sessions with role management (`student` & `lecturer`), and built-in password reset.
* **Row-Level Security (RLS)**: Enforced directly at the database level so unauthorized users cannot access course materials or student records.
* **Timed Attendance Check-In**:
  * Lecturers generate 5-character alphanumeric codes with live countdown timers (5–60 mins).
  * Students enter the code while the window is active.
  * Realtime attendee list updates instantly on the lecturer's screen as students check in.
* **Private Storage for Materials**: Handouts, PDFs, and slide decks are stored in a private Supabase Storage bucket (`course-materials`) and accessed via temporary signed URLs.
* **Instant Course Chat**: Powered by Supabase Realtime WebSocket subscriptions (zero database polling).

---

## 📁 Repository Structure

```text
Lite-LMS/
├── index.html             # Landing page
├── login.html             # Login & Forgot Password modal
├── register.html          # Student & Lecturer registration
├── js/
│   ├── config.js          # Supabase URL & Anon Key config
│   └── auth.js            # Auth guards & session management
├── student/
│   ├── dashboard.html     # Enrolled courses list
│   └── course.html        # Attendance check-in, materials, realtime chat
├── lecturer/
│   ├── dashboard.html     # Created courses, create course modal
│   └── course.html        # Attendance manager, upload materials, roster, chat
├── supabase/
│   └── schema.sql         # Complete PostgreSQL schema, triggers & RLS
└── README.md              # Setup & deployment guide
```

---

## 🛠️ Complete Setup & Deployment Guide

### Phase 1: Set Up Supabase
1. Go to [supabase.com](https://supabase.com) and create a free account/project.
2. In your project, click **SQL Editor** in the left navigation.
3. Open `supabase/schema.sql` from this repository, copy its contents, paste them into the SQL editor, and click **Run**.
   * *This automatically creates all tables, triggers, RLS policies, storage bucket, and Realtime publications.*
4. In your Supabase Dashboard, go to **Project Settings** (gear icon) &rarr; **API**:
   * Copy the **Project URL** (e.g., `https://xyz.supabase.co`).
   * Copy the **anon public API Key** (`eyJ...`).
5. Open `js/config.js` in this repository and paste your credentials:
   ```javascript
   const SUPABASE_CONFIG = {
       URL: "https://xyz.supabase.co",
       ANON_KEY: "eyJ..."
   };
   ```

---

### Phase 2: Commit & Push to GitHub
Run the following in your terminal:
```bash
git add .
git commit -m "Migrate Lite-LMS to HTML5, Tailwind & Supabase"
git push origin main
```

---

### Phase 3: Deploy to Vercel (Free)
1. Go to [vercel.com](https://vercel.com) and sign in with your GitHub account.
2. Click **"Add New..."** &rarr; **"Project"**.
3. Select your **Lite-LMS** repository and click **Import**.
4. In the Project Configuration screen:
   * **Framework Preset**: Other (Static / Auto-detected)
   * **Root Directory**: `./` (leave default)
   * **Build & Output Settings**: Leave empty (no build needed!)
5. Click **"Deploy"**.
6. In ~15 seconds, Vercel will generate your live production URL (e.g., `https://lite-lms.vercel.app`).
