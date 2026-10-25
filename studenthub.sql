-- StudentHub integrated database setup.
-- Import into a fresh MySQL database. Statements are idempotent and do not drop data.

CREATE DATABASE IF NOT EXISTS studenthub_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE studenthub_db;

CREATE TABLE IF NOT EXISTS students (
    student_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    roll_number VARCHAR(20) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    department VARCHAR(80) NOT NULL,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_students_roll_number (roll_number),
    UNIQUE KEY uq_students_email (email)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO students (student_id, roll_number, full_name, email, department) VALUES
    (1, '2026-CS-104', 'Aarav Patel', 'aarav.patel@example.test', 'CSE'),
    (2, '2026-IT-088', 'Krisha Shah', 'krisha.shah@example.test', 'IT'),
    (3, '2026-EE-045', 'Kabir Mehta', 'kabir.mehta@example.test', 'EE'),
    (4, '2026-ME-071', 'Mira Desai', 'mira.desai@example.test', 'ME'),
    (5, '2026-CE-032', 'Ishaan Joshi', 'ishaan.joshi@example.test', 'CE');

CREATE TABLE IF NOT EXISTS events (
    event_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    event_date DATE NOT NULL,
    venue VARCHAR(150) NOT NULL,
    PRIMARY KEY (event_id),
    KEY idx_events_event_date (event_date)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO events (event_id, title, category, event_date, venue) VALUES
    (1, 'Cognizance 2026 Annual Tech Fest', 'Technical', '2026-10-24', 'CSPIT Auditorium'),
    (2, 'Accessible Web Design Workshop', 'Workshop', '2026-11-02', 'CSPIT Lab 633'),
    (3, 'Inter-Department Sports Meet', 'Sports', '2026-11-15', 'University Sports Complex'),
    (4, 'Cloud Computing Career Talk', 'Career', '2026-11-20', 'CSPIT Seminar Hall'),
    (5, 'Python Problem-Solving Sprint', 'Technical', '2026-11-25', 'CSPIT Lab 631');

CREATE TABLE IF NOT EXISTS registrations (
    registration_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registration_status ENUM('Confirmed', 'Waitlisted', 'Cancelled') NOT NULL DEFAULT 'Confirmed',
    PRIMARY KEY (registration_id),
    UNIQUE KEY uq_registration_student_event (student_id, event_id),
    KEY idx_registrations_event_id (event_id),
    CONSTRAINT fk_registrations_student
        FOREIGN KEY (student_id) REFERENCES students (student_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_registrations_event
        FOREIGN KEY (event_id) REFERENCES events (event_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO registrations (registration_id, student_id, event_id, registration_status) VALUES
    (1, 1, 1, 'Confirmed'),
    (2, 1, 2, 'Confirmed'),
    (3, 2, 1, 'Waitlisted'),
    (4, 3, 3, 'Confirmed'),
    (5, 4, 4, 'Confirmed'),
    (6, 5, 5, 'Confirmed');

CREATE TABLE IF NOT EXISTS user_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(30) NOT NULL,
    student_id VARCHAR(30) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_accounts_username (username),
    UNIQUE KEY uq_user_accounts_student_id (student_id),
    UNIQUE KEY uq_user_accounts_email (email)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_user_roles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('Admin', 'Student') NOT NULL DEFAULT 'Student',
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_user_roles_user (user_id),
    KEY idx_auth_user_roles_role (role),
    CONSTRAINT fk_auth_user_roles_account
        FOREIGN KEY (user_id) REFERENCES user_accounts (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Demo-only accounts. Change these example credentials after the first login.
INSERT IGNORE INTO user_accounts (full_name, username, student_id, email, password_hash) VALUES
    ('Demo Administrator', 'demo_admin', 'DEMO-ADMIN', 'admin.demo@example.test',
     '$2y$10$4HhM2.HqzXlslZa1jVeaiOs8dwpbzs6Q4LIEexQHYj4cWGnLydGdq'),
    ('Demo Student', 'demo_student', 'DEMO-STUDENT', 'student.demo@example.test',
     '$2y$10$hras7LWHAGLLdKysTps2ouliUtTwQHjkQxhKZR0SM8f/96UCVoIbu');

INSERT INTO auth_user_roles (user_id, role)
SELECT id, CASE username WHEN 'demo_admin' THEN 'Admin' ELSE 'Student' END
FROM user_accounts
WHERE username IN ('demo_admin', 'demo_student')
ON DUPLICATE KEY UPDATE role = VALUES(role);

CREATE TABLE IF NOT EXISTS managed_students (
    student_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    roll_number VARCHAR(20) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    course VARCHAR(80) NOT NULL,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_managed_students_roll_number (roll_number),
    UNIQUE KEY uq_managed_students_email (email),
    KEY idx_managed_students_course_status (course, status)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO managed_students (roll_number, full_name, email, course, status) VALUES
    ('2026-CS-104', 'Aarav Patel', 'aarav.patel@example.test', 'B.Tech CSE', 'Active'),
    ('2026-IT-088', 'Krisha Shah', 'krisha.shah@example.test', 'B.Tech IT', 'Active'),
    ('2026-EE-045', 'Kabir Mehta', 'kabir.mehta@example.test', 'B.Tech EE', 'Active'),
    ('2026-ME-071', 'Mira Desai', 'mira.desai@example.test', 'B.Tech ME', 'Active'),
    ('2026-CE-032', 'Ishaan Joshi', 'ishaan.joshi@example.test', 'B.Tech CE', 'Active');

CREATE TABLE IF NOT EXISTS managed_events (
    event_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    event_date DATE NOT NULL,
    venue VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    poster_filename VARCHAR(80) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY idx_managed_events_date (event_date),
    KEY idx_managed_events_category (category)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO managed_events (title, category, event_date, venue, description)
SELECT 'Cognizance 2026 Annual Tech Fest', 'Technical', '2026-10-24',
       'CSPIT Auditorium', 'Programming, web development, and data structure challenges.'
WHERE NOT EXISTS (
    SELECT 1 FROM managed_events WHERE title = 'Cognizance 2026 Annual Tech Fest'
);

INSERT INTO managed_events (title, category, event_date, venue, description)
SELECT 'Accessible Web Design Workshop', 'Workshop', '2026-11-02',
       'CSPIT Lab 633', 'A practical workshop on accessible forms and responsive design.'
WHERE NOT EXISTS (
    SELECT 1 FROM managed_events WHERE title = 'Accessible Web Design Workshop'
);

CREATE TABLE IF NOT EXISTS api_students (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    course VARCHAR(80) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_students_email (email),
    KEY idx_api_students_name (full_name)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO api_students (full_name, email, course) VALUES
    ('Aarav Patel', 'aarav.api@example.test', 'B.Tech CSE'),
    ('Krisha Shah', 'krisha.api@example.test', 'B.Tech IT'),
    ('Kabir Mehta', 'kabir.api@example.test', 'B.Tech EE');

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
    KEY idx_audit_logs_created (created_at),
    CONSTRAINT fk_audit_logs_admin
        FOREIGN KEY (admin_user_id) REFERENCES user_accounts (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
