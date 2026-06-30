-- =========================================================
-- Database Schema: User Registration (Student / Lecturer)
-- =========================================================

CREATE DATABASE IF NOT EXISTS school_portal
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE school_portal;

-- ---------------------------------------------------------
-- departments
-- Lookup table shared by both Student and Lecturer forms
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS departments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- users
-- Core account table (extends your existing users table
-- with a `role` column to tell student/lecturer apart)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('student', 'lecturer') NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_role (role)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- students
-- One-to-one extension of `users` for role = 'student'
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    matric_no       VARCHAR(50) NOT NULL UNIQUE,
    department_id   INT UNSIGNED NOT NULL,
    level           ENUM('100', '200', '300', '400', '500') NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_students_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_students_department
        FOREIGN KEY (department_id) REFERENCES departments(id)
        ON DELETE RESTRICT,

    INDEX idx_matric_no (matric_no)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- lecturers
-- One-to-one extension of `users` for role = 'lecturer'
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS lecturers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    staff_id        VARCHAR(50) NOT NULL UNIQUE,
    department_id   INT UNSIGNED NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_lecturers_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_lecturers_department
        FOREIGN KEY (department_id) REFERENCES departments(id)
        ON DELETE RESTRICT,

    INDEX idx_staff_id (staff_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Seed a few departments so the dropdown isn't empty
-- ---------------------------------------------------------
INSERT INTO departments (name) VALUES
    ('Computer Science'),
    ('Electrical Engineering'),
    ('Mechanical Engineering'),
    ('Accounting'),
    ('Mass Communication'),
    ('Economics')
ON DUPLICATE KEY UPDATE name = name;

-- ---------------------------------------------------------
-- courses
-- A course belongs to one lecturer and one department.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS courses (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(20) NOT NULL UNIQUE,
    title           VARCHAR(150) NOT NULL,
    lecturer_id     INT UNSIGNED NOT NULL,
    department_id   INT UNSIGNED NOT NULL,
    level           ENUM('100', '200', '300', '400', '500') NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_courses_lecturer
        FOREIGN KEY (lecturer_id) REFERENCES lecturers(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_courses_department
        FOREIGN KEY (department_id) REFERENCES departments(id)
        ON DELETE RESTRICT,

    INDEX idx_course_code (code)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- enrollments
-- Which students are registered for which course.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS enrollments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id  INT UNSIGNED NOT NULL,
    course_id   INT UNSIGNED NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_enrollments_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_enrollments_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE CASCADE,

    UNIQUE KEY uniq_student_course (student_id, course_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- materials
-- Files lecturers upload for a course. The real file lives
-- outside the public web root; file_path is never sent to
-- the browser directly — everything goes through stream.php.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS materials (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id       INT UNSIGNED NOT NULL,
    title           VARCHAR(150) NOT NULL,
    file_path       VARCHAR(255) NOT NULL,
    file_type       VARCHAR(50)  NOT NULL,
    uploaded_by     INT UNSIGNED NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_materials_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_materials_uploader
        FOREIGN KEY (uploaded_by) REFERENCES lecturers(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- attendance_sessions
-- A lecturer opens a timed window with a short code.
-- Students mark themselves present while it's open.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance_sessions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id   INT UNSIGNED NOT NULL,
    code        VARCHAR(10) NOT NULL,
    opened_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closes_at   DATETIME NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,

    CONSTRAINT fk_attendance_sessions_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE CASCADE,

    INDEX idx_session_code (code)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- attendance_records
-- One row per student per session they marked themselves
-- present for.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance_records (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id  INT UNSIGNED NOT NULL,
    student_id  INT UNSIGNED NOT NULL,
    marked_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_attendance_records_session
        FOREIGN KEY (session_id) REFERENCES attendance_sessions(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_attendance_records_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON DELETE CASCADE,

    UNIQUE KEY uniq_session_student (session_id, student_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- chat_messages
-- One shared thread per course. Any enrolled student or the
-- course's lecturer can post into it. Polling-based (no
-- websockets) — student/lecturer pages just re-fetch on a
-- timer.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_messages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id   INT UNSIGNED NOT NULL,
    sender_id   INT UNSIGNED NOT NULL,   -- references users.id
    message     TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_chat_messages_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_chat_messages_sender
        FOREIGN KEY (sender_id) REFERENCES users(id)
        ON DELETE CASCADE,

    INDEX idx_course_created (course_id, created_at)
) ENGINE=InnoDB;

