-- ============================================================
-- migration_wishlist.sql
-- Adds the wishlists table (Feature B). Run this against an existing
-- SydneyHappenings database that already has schema.sql applied - it
-- only adds one new table and does not touch any existing table.
--
-- The same CREATE TABLE statement is also appended to schema.sql, so
-- a fresh install (schema.sql then seed.sql) does not need this file
-- run separately.
-- ============================================================

DROP TABLE IF EXISTS wishlists;

-- ------------------------------------------------------------
-- wishlists
-- One row per "saved" event: a user bookmarking an event to look at
-- again later, distinct from actually registering for it. Both
-- foreign keys use ON DELETE CASCADE - if a user or event is ever
-- deleted, their wishlist rows are cleaned up automatically rather
-- than becoming orphaned.
-- ------------------------------------------------------------
CREATE TABLE wishlists (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    event_id        INT NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- A user can only save an event once. This is the database-level
    -- guarantee that duplicate wishlist rows are impossible, even if
    -- the application code has a bug - the same protection the
    -- registrations and reviews tables already use.
    UNIQUE KEY uq_wishlist_user_event (user_id, event_id),

    CONSTRAINT fk_wishlists_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    CONSTRAINT fk_wishlists_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
