-- ============================================================
-- migration_booking.sql
-- Feature C (booking: quantity, reference, confirmation), Step C1.
-- Run this against a database that already has migration_reviews.sql
-- and migration_wishlist.sql applied.
--
-- registrations.quantity: how many tickets one booking covers - a
-- booking is still one row (one person books, possibly for a group),
-- so capacity used is SUM(quantity), not COUNT(*) of rows.
-- registrations.booking_reference: the code shown on a ticket and
-- used to look up booking-confirmation.php - unique so a reference
-- can never accidentally point at two different bookings.
-- venues.latitude/longitude: powers the "Get directions" link
-- (getDirectionsUrl()); nullable because older/seed venues may not
-- have coordinates, which the helper falls back to an address search
-- for.
-- users.website/facebook/instagram: optional public links shown on
-- an organiser's events; nullable and meaningless for attendees.
-- ============================================================

ALTER TABLE registrations
    ADD COLUMN quantity TINYINT NOT NULL DEFAULT 1 AFTER event_id,
    ADD COLUMN booking_reference VARCHAR(12) NULL AFTER quantity,
    ADD UNIQUE KEY uq_booking_reference (booking_reference);

ALTER TABLE venues
    ADD COLUMN latitude DECIMAL(10, 7) NULL,
    ADD COLUMN longitude DECIMAL(10, 7) NULL;

ALTER TABLE users
    ADD COLUMN website VARCHAR(255) NULL,
    ADD COLUMN facebook VARCHAR(255) NULL,
    ADD COLUMN instagram VARCHAR(255) NULL;
