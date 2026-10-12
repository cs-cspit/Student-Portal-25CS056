USE studenthub_db;

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
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

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
