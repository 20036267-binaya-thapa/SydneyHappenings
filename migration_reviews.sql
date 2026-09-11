-- ============================================================
-- migration_reviews.sql
-- Adds the reviews table (CLAUDE.md section 14). Run this against
-- an existing SydneyHappenings database that already has schema.sql
-- applied - it only adds one new table and does not touch users,
-- events, or any other existing table.
--
-- The same CREATE TABLE statement is also appended to schema.sql,
-- so a fresh install (schema.sql then seed.sql) does not need this
-- file run separately.
-- ============================================================

DROP TABLE IF EXISTS reviews;

-- ------------------------------------------------------------
-- reviews
-- One row per review. A review belongs to exactly one event and
-- one user, so both are foreign keys with ON DELETE CASCADE - if
-- an event or user is ever deleted, their reviews are cleaned up
-- automatically rather than becoming orphaned.
-- ------------------------------------------------------------
CREATE TABLE reviews (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    event_id        INT NOT NULL,
    user_id         INT NOT NULL,
    rating          TINYINT NOT NULL,
    comment         TEXT NOT NULL,
    is_hidden       TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- A user can only leave one review per event. This is the
    -- database-level guarantee that duplicate reviews are
    -- impossible, even if the application code has a bug - the
    -- same protection the registrations table already uses for
    -- duplicate registrations.
    UNIQUE KEY uq_review_user_event (user_id, event_id),

    CONSTRAINT fk_reviews_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,

    -- Speeds up the query event.php runs to list an event's visible
    -- reviews in one go: WHERE event_id = ... AND is_hidden = 0.
    INDEX idx_reviews_event (event_id, is_hidden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
