-- ==============================================================================
-- Lite-LMS Supabase Schema & Security Setup
-- Database: PostgreSQL (Supabase)
-- Features: Auth Trigger, Profiles, Departments, Courses, Enrollments,
--           Materials, Attendance, Realtime Chat, Row Level Security (RLS)
-- ==============================================================================

-- 1. EXTENSIONS
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 2. DEPARTMENTS
CREATE TABLE IF NOT EXISTS public.departments (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    created_at TIMESTAMPTZ DEFAULT TIMEZONE('utc'::text, NOW()) NOT NULL
);

-- Seed default departments
INSERT INTO public.departments (name) VALUES
    ('Computer Science'),
    ('Electrical Engineering'),
    ('Mechanical Engineering'),
    ('Accounting'),
    ('Mass Communication'),
    ('Economics')
ON CONFLICT (name) DO NOTHING;

-- 3. USER PROFILES
-- Connected directly to Supabase auth.users
CREATE TABLE IF NOT EXISTS public.profiles (
    id UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    role TEXT NOT NULL CHECK (role IN ('student', 'lecturer', 'admin')),
    matric_no TEXT UNIQUE,
    staff_id TEXT UNIQUE,
    department_id INT REFERENCES public.departments(id) ON DELETE RESTRICT,
    level TEXT CHECK (level IN ('100', '200', '300', '400', '500')),
    avatar_url TEXT,
    created_at TIMESTAMPTZ DEFAULT TIMEZONE('utc'::text, NOW()) NOT NULL
);

-- Index for fast queries
CREATE INDEX IF NOT EXISTS idx_profiles_role ON public.profiles(role);
CREATE INDEX IF NOT EXISTS idx_profiles_matric_no ON public.profiles(matric_no);
CREATE INDEX IF NOT EXISTS idx_profiles_staff_id ON public.profiles(staff_id);

-- 4. AUTOMATIC PROFILE CREATION TRIGGER
-- When a user signs up via supabase.auth.signUp(), this trigger extracts
-- metadata and creates the row in public.profiles with elevated privileges.
CREATE OR REPLACE FUNCTION public.handle_new_user()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER SET search_path = public
AS $$
BEGIN
    INSERT INTO public.profiles (
        id,
        name,
        email,
        role,
        matric_no,
        staff_id,
        department_id,
        level
    ) VALUES (
        NEW.id,
        COALESCE(NEW.raw_user_meta_data->>'name', 'New User'),
        NEW.email,
        COALESCE(NEW.raw_user_meta_data->>'role', 'student'),
        NEW.raw_user_meta_data->>'matric_no',
        NEW.raw_user_meta_data->>'staff_id',
        NULLIF(NEW.raw_user_meta_data->>'department_id', '')::INT,
        NEW.raw_user_meta_data->>'level'
    );
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS on_auth_user_created ON auth.users;
CREATE TRIGGER on_auth_user_created
    AFTER INSERT ON auth.users
    FOR EACH ROW EXECUTE FUNCTION public.handle_new_user();

-- 5. COURSES
CREATE TABLE IF NOT EXISTS public.courses (
    id SERIAL PRIMARY KEY,
    code TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    lecturer_id UUID NOT NULL REFERENCES public.profiles(id) ON DELETE CASCADE,
    department_id INT NOT NULL REFERENCES public.departments(id) ON DELETE RESTRICT,
    level TEXT NOT NULL CHECK (level IN ('100', '200', '300', '400', '500')),
    created_at TIMESTAMPTZ DEFAULT TIMEZONE('utc'::text, NOW()) NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_courses_code ON public.courses(code);
CREATE INDEX IF NOT EXISTS idx_courses_lecturer ON public.courses(lecturer_id);

-- 6. ENROLLMENTS
CREATE TABLE IF NOT EXISTS public.enrollments (
    id SERIAL PRIMARY KEY,
    student_id UUID NOT NULL REFERENCES public.profiles(id) ON DELETE CASCADE,
    course_id INT NOT NULL REFERENCES public.courses(id) ON DELETE CASCADE,
    enrolled_at TIMESTAMPTZ DEFAULT TIMEZONE('utc'::text, NOW()) NOT NULL,
    UNIQUE(student_id, course_id)
);

CREATE INDEX IF NOT EXISTS idx_enrollments_student ON public.enrollments(student_id);
CREATE INDEX IF NOT EXISTS idx_enrollments_course ON public.enrollments(course_id);

-- 7. MATERIALS
CREATE TABLE IF NOT EXISTS public.materials (
    id SERIAL PRIMARY KEY,
    course_id INT NOT NULL REFERENCES public.courses(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    file_path TEXT NOT NULL,
    file_type TEXT NOT NULL,
    uploaded_by UUID NOT NULL REFERENCES public.profiles(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ DEFAULT TIMEZONE('utc'::text, NOW()) NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_materials_course ON public.materials(course_id);

-- 8. ATTENDANCE SESSIONS & RECORDS
CREATE TABLE IF NOT EXISTS public.attendance_sessions (
    id SERIAL PRIMARY KEY,
    course_id INT NOT NULL REFERENCES public.courses(id) ON DELETE CASCADE,
    code TEXT NOT NULL,
    opened_at TIMESTAMPTZ DEFAULT TIMEZONE('utc'::text, NOW()) NOT NULL,
    closes_at TIMESTAMPTZ NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE INDEX IF NOT EXISTS idx_attendance_course ON public.attendance_sessions(course_id);

CREATE TABLE IF NOT EXISTS public.attendance_records (
    id SERIAL PRIMARY KEY,
    session_id INT NOT NULL REFERENCES public.attendance_sessions(id) ON DELETE CASCADE,
    student_id UUID NOT NULL REFERENCES public.profiles(id) ON DELETE CASCADE,
    marked_at TIMESTAMPTZ DEFAULT TIMEZONE('utc'::text, NOW()) NOT NULL,
    UNIQUE(session_id, student_id)
);

CREATE INDEX IF NOT EXISTS idx_attendance_records_session ON public.attendance_records(session_id);

-- 9. REALTIME CHAT MESSAGES
CREATE TABLE IF NOT EXISTS public.chat_messages (
    id SERIAL PRIMARY KEY,
    course_id INT NOT NULL REFERENCES public.courses(id) ON DELETE CASCADE,
    sender_id UUID NOT NULL REFERENCES public.profiles(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT TIMEZONE('utc'::text, NOW()) NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_chat_course_created ON public.chat_messages(course_id, created_at);

-- ==============================================================================
-- 10. ENABLE SUPABASE REALTIME
-- ==============================================================================
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_publication_tables 
    WHERE pubname = 'supabase_realtime' AND tablename = 'chat_messages'
  ) THEN
    ALTER PUBLICATION supabase_realtime ADD TABLE public.chat_messages;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM pg_publication_tables 
    WHERE pubname = 'supabase_realtime' AND tablename = 'attendance_records'
  ) THEN
    ALTER PUBLICATION supabase_realtime ADD TABLE public.attendance_records;
  END IF;
END $$;

-- ==============================================================================
-- 11. ROW LEVEL SECURITY (RLS) POLICIES
-- ==============================================================================
ALTER TABLE public.departments ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.courses ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.enrollments ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.materials ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.attendance_sessions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.attendance_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.chat_messages ENABLE ROW LEVEL SECURITY;

-- Departments Policies
CREATE POLICY "Departments are viewable by anyone" ON public.departments
    FOR SELECT TO public USING (true);

-- Profiles Policies
CREATE POLICY "Profiles are viewable by authenticated users" ON public.profiles
    FOR SELECT TO authenticated USING (true);

CREATE POLICY "Users can update their own profile" ON public.profiles
    FOR UPDATE TO authenticated USING (auth.uid() = id);

-- Courses Policies
CREATE POLICY "Courses are viewable by authenticated users" ON public.courses
    FOR SELECT TO authenticated USING (true);

CREATE POLICY "Lecturers can create courses" ON public.courses
    FOR INSERT TO authenticated
    WITH CHECK (
        EXISTS (SELECT 1 FROM public.profiles WHERE id = auth.uid() AND role = 'lecturer')
        AND lecturer_id = auth.uid()
    );

CREATE POLICY "Lecturers can update their own courses" ON public.courses
    FOR UPDATE TO authenticated
    USING (lecturer_id = auth.uid());

CREATE POLICY "Lecturers can delete their own courses" ON public.courses
    FOR DELETE TO authenticated
    USING (lecturer_id = auth.uid());

-- Enrollments Policies
CREATE POLICY "Students and lecturers can view enrollments" ON public.enrollments
    FOR SELECT TO authenticated
    USING (
        student_id = auth.uid() OR
        EXISTS (SELECT 1 FROM public.courses WHERE id = course_id AND lecturer_id = auth.uid())
    );

CREATE POLICY "Lecturers can enroll students into their courses" ON public.enrollments
    FOR INSERT TO authenticated
    WITH CHECK (
        EXISTS (SELECT 1 FROM public.courses WHERE id = course_id AND lecturer_id = auth.uid())
    );

CREATE POLICY "Lecturers can remove students from their courses" ON public.enrollments
    FOR DELETE TO authenticated
    USING (
        EXISTS (SELECT 1 FROM public.courses WHERE id = course_id AND lecturer_id = auth.uid())
    );

-- Materials Policies
CREATE POLICY "Enrolled students and course lecturers can view materials" ON public.materials
    FOR SELECT TO authenticated
    USING (
        EXISTS (SELECT 1 FROM public.enrollments WHERE course_id = materials.course_id AND student_id = auth.uid())
        OR
        EXISTS (SELECT 1 FROM public.courses WHERE id = materials.course_id AND lecturer_id = auth.uid())
    );

CREATE POLICY "Lecturers can upload materials for their courses" ON public.materials
    FOR INSERT TO authenticated
    WITH CHECK (
        EXISTS (SELECT 1 FROM public.courses WHERE id = course_id AND lecturer_id = auth.uid())
        AND uploaded_by = auth.uid()
    );

CREATE POLICY "Lecturers can delete their course materials" ON public.materials
    FOR DELETE TO authenticated
    USING (
        EXISTS (SELECT 1 FROM public.courses WHERE id = course_id AND lecturer_id = auth.uid())
    );

-- Attendance Sessions Policies
CREATE POLICY "Enrolled students and course lecturers can view attendance sessions" ON public.attendance_sessions
    FOR SELECT TO authenticated
    USING (
        EXISTS (SELECT 1 FROM public.enrollments WHERE course_id = attendance_sessions.course_id AND student_id = auth.uid())
        OR
        EXISTS (SELECT 1 FROM public.courses WHERE id = attendance_sessions.course_id AND lecturer_id = auth.uid())
    );

CREATE POLICY "Lecturers can create attendance sessions" ON public.attendance_sessions
    FOR INSERT TO authenticated
    WITH CHECK (
        EXISTS (SELECT 1 FROM public.courses WHERE id = course_id AND lecturer_id = auth.uid())
    );

CREATE POLICY "Lecturers can update attendance sessions" ON public.attendance_sessions
    FOR UPDATE TO authenticated
    USING (
        EXISTS (SELECT 1 FROM public.courses WHERE id = course_id AND lecturer_id = auth.uid())
    );

-- Attendance Records Policies
CREATE POLICY "Lecturers and students can view attendance records" ON public.attendance_records
    FOR SELECT TO authenticated
    USING (
        student_id = auth.uid()
        OR
        EXISTS (
            SELECT 1 FROM public.attendance_sessions s
            JOIN public.courses c ON c.id = s.course_id
            WHERE s.id = attendance_records.session_id AND c.lecturer_id = auth.uid()
        )
    );

CREATE POLICY "Students can mark attendance if session is active" ON public.attendance_records
    FOR INSERT TO authenticated
    WITH CHECK (
        student_id = auth.uid()
        AND EXISTS (
            SELECT 1 FROM public.attendance_sessions s
            WHERE s.id = session_id AND s.is_active = TRUE AND s.closes_at > NOW()
        )
    );

-- Chat Messages Policies
CREATE POLICY "Course members can view chat messages" ON public.chat_messages
    FOR SELECT TO authenticated
    USING (
        EXISTS (SELECT 1 FROM public.enrollments WHERE course_id = chat_messages.course_id AND student_id = auth.uid())
        OR
        EXISTS (SELECT 1 FROM public.courses WHERE id = chat_messages.course_id AND lecturer_id = auth.uid())
    );

CREATE POLICY "Course members can post chat messages" ON public.chat_messages
    FOR INSERT TO authenticated
    WITH CHECK (
        sender_id = auth.uid()
        AND (
            EXISTS (SELECT 1 FROM public.enrollments WHERE course_id = chat_messages.course_id AND student_id = auth.uid())
            OR
            EXISTS (SELECT 1 FROM public.courses WHERE id = chat_messages.course_id AND lecturer_id = auth.uid())
        )
    );

-- ==============================================================================
-- 12. STORAGE BUCKET CREATION (Run in SQL Editor)
-- ==============================================================================
INSERT INTO storage.buckets (id, name, public) 
VALUES ('course-materials', 'course-materials', false)
ON CONFLICT (id) DO NOTHING;

-- Storage RLS: allow authenticated users to upload and download with signed URLs
CREATE POLICY "Authenticated users can upload course materials" 
ON storage.objects FOR INSERT TO authenticated 
WITH CHECK (bucket_id = 'course-materials');

CREATE POLICY "Authenticated users can view/download course materials" 
ON storage.objects FOR SELECT TO authenticated 
USING (bucket_id = 'course-materials');
