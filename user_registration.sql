CREATE DATABASE IF NOT EXISTS studenthub_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE studenthub_db;

CREATE TABLE IF NOT EXISTS user_accounts (
    user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(30) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_user_accounts_username (username),
    UNIQUE KEY uq_user_accounts_email (email)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Roles are stored separately so existing Practical 9 user_accounts tables remain compatible.
CREATE TABLE IF NOT EXISTS auth_user_roles (
    user_id INT UNSIGNED NOT NULL,
    role ENUM('Admin', 'Student') NOT NULL DEFAULT 'Student',
    PRIMARY KEY (user_id),
    CONSTRAINT fk_auth_user_roles_account
        FOREIGN KEY (user_id) REFERENCES user_accounts (user_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- Demo-only accounts. Passwords: Admin@123 and Student@123.
-- Change these credentials after importing the SQL file.
INSERT IGNORE INTO user_accounts (full_name, username, email, password_hash) VALUES
    ('Demo Administrator', 'demo_admin', 'admin.demo@example.test',
     '$2y$10$4HhM2.HqzXlslZa1jVeaiOs8dwpbzs6Q4LIEexQHYj4cWGnLydGdq'),
    ('Demo Student', 'demo_student', 'student.demo@example.test',
     '$2y$10$hras7LWHAGLLdKysTps2ouliUtTwQHjkQxhKZR0SM8f/96UCVoIbu');

INSERT INTO auth_user_roles (user_id, role)
SELECT user_id,
       CASE username
           WHEN 'demo_admin' THEN 'Admin'
           ELSE 'Student'
       END
FROM user_accounts
WHERE username IN ('demo_admin', 'demo_student')
ON DUPLICATE KEY UPDATE role = VALUES(role);
