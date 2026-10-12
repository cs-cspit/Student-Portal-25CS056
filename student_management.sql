USE studenthub_db;

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
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO managed_students (roll_number, full_name, email, course, status) VALUES
    ('2026-CS-104', 'Aarav Patel', 'aarav.patel@example.test', 'B.Tech CSE', 'Active'),
    ('2026-IT-088', 'Krisha Shah', 'krisha.shah@example.test', 'B.Tech IT', 'Active'),
    ('2026-EE-045', 'Kabir Mehta', 'kabir.mehta@example.test', 'B.Tech EE', 'Active'),
    ('2026-ME-071', 'Mira Desai', 'mira.desai@example.test', 'B.Tech ME', 'Active'),
    ('2026-CE-032', 'Ishaan Joshi', 'ishaan.joshi@example.test', 'B.Tech CE', 'Active');
