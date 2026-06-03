-- Migration: FuTracker Social Feature
-- Run this on an existing database to add the social columns/tables

ALTER TABLE matches ADD COLUMN IF NOT EXISTS join_code VARCHAR(12) UNIQUE;
ALTER TABLE matches ADD COLUMN IF NOT EXISTS is_open TINYINT(1) DEFAULT 1;

CREATE TABLE IF NOT EXISTS match_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at DATETIME DEFAULT NOW(),
    UNIQUE KEY unique_participation (match_id, user_id),
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
