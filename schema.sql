-- ============================================================
-- SydneyHappenings database schema
-- Step 1 of the build order in CLAUDE.md, section 11.
--
-- Six tables: users, categories, venues, events, registrations, enquiries.
-- All tables use InnoDB (so foreign keys are enforced) and utf8mb4
-- (so the database can store any character, including emoji, correctly).
-- ============================================================

-- Start clean so this script can be re-run during development.
-- Order matters: a table cannot be dropped while another table still
-- has a foreign key pointing at it, so children are dropped before parents.
DROP TABLE IF EXISTS wishlists;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS enquiries;
DROP TABLE IF EXISTS venues;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- ------------------------------------------------------------
-- users
-- Every person who can log in: attendees, organisers, and admins.
-- One table for all three roles, distinguished by the `role` column,
-- keeps login and session handling simple (one users table to check).
-- ------------------------------------------------------------
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    phone           VARCHAR(20)  NULL,
    role            ENUM('attendee', 'organiser', 'admin') NOT NULL DEFAULT 'attendee',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Optional public links, only ever shown on events belonging to an
    -- organiser (or admin) - meaningless for an attendee account, but
    -- kept on the shared users table rather than a separate one-row-
    -- per-organiser table, since only three fields are involved.
    website         VARCHAR(255) NULL,
    facebook        VARCHAR(255) NULL,
    instagram       VARCHAR(255) NULL,

    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- categories
-- Fixed-ish list of event categories (Festivals, Markets, etc).
-- `slug` is the URL-friendly version of the name, used for
-- category links like events.php?category=markets.
-- ------------------------------------------------------------
CREATE TABLE categories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(80) NOT NULL,
    slug            VARCHAR(80) NOT NULL,
    description     VARCHAR(255) NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- venues
-- Physical locations where events are held. Kept separate from
-- events so the same venue can be reused by many events without
-- retyping the address every time.
-- ------------------------------------------------------------
CREATE TABLE venues (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    address         VARCHAR(200) NOT NULL,
    suburb          VARCHAR(80) NOT NULL,
    postcode        VARCHAR(10) NOT NULL,
    capacity        INT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,

    -- Optional map coordinates, used by getDirectionsUrl() to build a
    -- precise Google Maps link. Nullable because not every venue has
    -- had coordinates entered - the helper falls back to a text
    -- address search when they are missing, so a venue is never left
    -- with a broken directions link.
    latitude        DECIMAL(10, 7) NULL,
    longitude       DECIMAL(10, 7) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- events
-- The core table. Each event belongs to exactly one organiser
-- (a user), one category, and one venue - hence the three
-- foreign keys below.
-- ------------------------------------------------------------
CREATE TABLE events (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    organiser_id    INT NOT NULL,
    category_id     INT NOT NULL,
    venue_id        INT NOT NULL,
    title           VARCHAR(150) NOT NULL,
    slug            VARCHAR(180) NOT NULL,
    description     TEXT NOT NULL,
    image           VARCHAR(255) NULL,
    start_datetime  DATETIME NOT NULL,
    end_datetime    DATETIME NOT NULL,
    capacity        INT NOT NULL,
    price           DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    status          ENUM('draft', 'published', 'cancelled') NOT NULL DEFAULT 'draft',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_events_slug (slug),

    -- Foreign keys: an event cannot reference a user, category, or
    -- venue that does not exist. RESTRICT (the default) stops a
    -- category, venue, or organiser account from being deleted
    -- outright while events still reference it - deactivation
    -- (is_active / is_active on the user) is used instead of deletion.
    CONSTRAINT fk_events_organiser FOREIGN KEY (organiser_id) REFERENCES users(id),
    CONSTRAINT fk_events_category  FOREIGN KEY (category_id)  REFERENCES categories(id),
    CONSTRAINT fk_events_venue     FOREIGN KEY (venue_id)     REFERENCES venues(id),

    -- Indexes to speed up the queries the site runs most often:
    -- listing upcoming events in date order, filtering by
    -- published/draft/cancelled, and filtering by category.
    INDEX idx_events_start_datetime (start_datetime),
    INDEX idx_events_status (status),
    INDEX idx_events_category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- registrations
-- The many-to-many link between users and events: one row per
-- attendee per event. ON DELETE CASCADE means if a user or event
-- is ever deleted, their registration rows are cleaned up
-- automatically rather than becoming orphaned.
-- ------------------------------------------------------------
CREATE TABLE registrations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    event_id        INT NOT NULL,

    -- How many tickets this one booking covers. A booking is still a
    -- single row per (user, event) - see uq_user_event below - so a
    -- group of four is one row with quantity 4, not four rows. Every
    -- place elsewhere in the codebase that counts "how full is this
    -- event" must SUM(quantity) rather than COUNT(*) rows, or a
    -- multi-ticket booking would be undercounted.
    quantity        TINYINT NOT NULL DEFAULT 1,

    -- The code shown on a ticket and used to look up
    -- booking-confirmation.php. Nullable because it is only assigned
    -- once a booking actually succeeds (see generateBookingReference()
    -- in functions.php); every row inserted by the application always
    -- supplies one.
    booking_reference VARCHAR(12) NULL,

    status          ENUM('registered', 'cancelled', 'attended', 'no_show') NOT NULL DEFAULT 'registered',
    registered_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- A user can only have one registration row per event. This is
    -- the database-level guarantee that duplicate registrations are
    -- impossible, even if the application code has a bug.
    UNIQUE KEY uq_user_event (user_id, event_id),

    -- A second, independent guarantee that two bookings can never end
    -- up sharing the same reference, even if generateBookingReference()
    -- has a bug or two requests race - the same belt-and-braces pattern
    -- already used for the wishlist and review unique keys.
    UNIQUE KEY uq_booking_reference (booking_reference),

    CONSTRAINT fk_registrations_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    CONSTRAINT fk_registrations_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- ------------------------------------------------------------
-- enquiries
-- Messages sent through the public contact form. Not linked to
-- the users table because a guest (not logged in) can send one.
-- ------------------------------------------------------------
CREATE TABLE enquiries (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    subject         VARCHAR(150) NOT NULL,
    message         TEXT NOT NULL,
    is_handled      TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
