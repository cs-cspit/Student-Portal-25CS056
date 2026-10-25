USE studenthub_db;

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
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_user_roles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('Admin', 'Student') NOT NULL DEFAULT 'Student',
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_user_roles_user (user_id),
    CONSTRAINT fk_auth_user_roles_account
        FOREIGN KEY (user_id) REFERENCES user_accounts (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
