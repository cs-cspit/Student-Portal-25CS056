-- ==========================================
-- STUDENTHUB DATABASE
-- ==========================================

CREATE DATABASE IF NOT EXISTS studenthub_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE studenthub_db;

-- Remove old tables in foreign-key-safe order.
-- WARNING: This deletes existing data in these tables.
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS students;

-- ==========================================
-- 1. STUDENTS TABLE
-- ==========================================

CREATE TABLE students (
    student_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    roll_number VARCHAR(20) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    department VARCHAR(80) NOT NULL,

    PRIMARY KEY (student_id),
    UNIQUE KEY uq_students_roll_number (roll_number),
    UNIQUE KEY uq_students_email (email)
) ENGINE=InnoDB;

-- ==========================================
-- 2. EVENTS TABLE
-- ==========================================

CREATE TABLE events (
    event_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    event_date DATE NOT NULL,
    venue VARCHAR(150) NOT NULL,

    PRIMARY KEY (event_id),
    KEY idx_events_event_date (event_date)
) ENGINE=InnoDB;

-- ==========================================
-- 3. REGISTRATIONS TABLE
-- ==========================================

CREATE TABLE registrations (
    registration_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    registration_status ENUM(
        'Confirmed',
        'Waitlisted',
        'Cancelled'
    ) NOT NULL DEFAULT 'Confirmed',

    PRIMARY KEY (registration_id),

    UNIQUE KEY uq_registration_student_event
        (student_id, event_id),

    KEY idx_registrations_event_id (event_id),

    CONSTRAINT fk_registrations_student
        FOREIGN KEY (student_id)
        REFERENCES students (student_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_registrations_event
        FOREIGN KEY (event_id)
        REFERENCES events (event_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ==========================================
-- 4. INSERT STUDENTS
-- ==========================================

INSERT INTO students
(student_id, roll_number, full_name, email, department)
VALUES
(1, '2026-CS-104', 'Aarav Patel',
 'aarav.patel@example.test', 'CSE'),
(2, '2026-IT-088', 'Krisha Shah',
 'krisha.shah@example.test', 'IT'),
(3, '2026-EE-045', 'Kabir Mehta',
 'kabir.mehta@example.test', 'EE'),
(4, '2026-ME-071', 'Mira Desai',
 'mira.desai@example.test', 'ME'),
(5, '2026-CE-032', 'Ishaan Joshi',
 'ishaan.joshi@example.test', 'CE');

-- ==========================================
-- 5. INSERT EVENTS
-- ==========================================

INSERT INTO events
(event_id, title, category, event_date, venue)
VALUES
(1, 'Cognizance 2026 Annual Tech Fest',
 'Technical', '2026-10-24', 'CSPIT Auditorium'),
(2, 'Accessible Web Design Workshop',
 'Workshop', '2026-11-02', 'CSPIT Lab 633'),
(3, 'Inter-Department Sports Meet',
 'Sports', '2026-11-15', 'University Sports Complex'),
(4, 'Cloud Computing Career Talk',
 'Career', '2026-11-20', 'CSPIT Seminar Hall'),
(5, 'Python Problem-Solving Sprint',
 'Technical', '2026-11-25', 'CSPIT Lab 631');

-- ==========================================
-- 6. INSERT REGISTRATIONS
-- ==========================================

INSERT INTO registrations
(registration_id, student_id, event_id, registration_status)
VALUES
(1, 1, 1, 'Confirmed'),
(2, 1, 2, 'Confirmed'),
(3, 2, 1, 'Waitlisted'),
(4, 3, 3, 'Confirmed'),
(5, 4, 4, 'Confirmed'),
(6, 5, 5, 'Confirmed');

-- ==========================================
-- 7. VERIFY THE DATA
-- ==========================================

SELECT * FROM students;
SELECT * FROM events;
SELECT * FROM registrations;

-- ==========================================
-- 8. PRACTICAL 13 FETCH API STUDENT RECORDS
-- ==========================================

CREATE TABLE IF NOT EXISTS api_students (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    course VARCHAR(80) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_students_email (email),
    KEY idx_api_students_name (full_name)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO api_students (full_name, email, course) VALUES
    ('Aarav Patel', 'aarav.api@example.test', 'B.Tech CSE'),
    ('Krisha Shah', 'krisha.api@example.test', 'B.Tech IT'),
    ('Kabir Mehta', 'kabir.api@example.test', 'B.Tech EE');

-- ==========================================
-- 9. PRACTICAL 14 ADMIN AUDIT LOG
-- ==========================================

CREATE TABLE IF NOT EXISTS audit_logs (
    audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_user_id INT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    details VARCHAR(500) NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (audit_id),
    KEY idx_audit_logs_admin_created (admin_user_id, created_at),
    KEY idx_audit_logs_created (created_at)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;