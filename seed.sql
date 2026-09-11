-- ============================================================
-- SydneyHappenings seed data
-- Run this after schema.sql, against an empty database.
-- All names, emails, and events below are fictional, invented for
-- this assignment - no real people, businesses, or events.
--
-- Dates are written as offsets from CURDATE() (today), not fixed
-- calendar dates, so "past", "this week" and "later" stay true no
-- matter when this file is actually imported.
--
-- Every seeded user shares the password: Password123
-- (hashed below with PHP's password_hash(), never stored in plain text)
-- ============================================================

-- Clear existing data (children first, so foreign keys are not
-- violated) and reset the auto-increment counters, so this file can
-- be re-run from a clean slate during development.
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM wishlists;
DELETE FROM reviews;
DELETE FROM registrations;
DELETE FROM enquiries;
DELETE FROM events;
DELETE FROM venues;
DELETE FROM categories;
DELETE FROM users;

ALTER TABLE wishlists AUTO_INCREMENT = 1;
ALTER TABLE reviews AUTO_INCREMENT = 1;
ALTER TABLE registrations AUTO_INCREMENT = 1;
ALTER TABLE enquiries AUTO_INCREMENT = 1;
ALTER TABLE events AUTO_INCREMENT = 1;
ALTER TABLE venues AUTO_INCREMENT = 1;
ALTER TABLE categories AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- users: 1 admin, 2 organisers, 3 attendees
-- Password for every account is: Password123
-- ------------------------------------------------------------
INSERT INTO users (name, email, password_hash, phone, role, is_active, website, facebook, instagram) VALUES
('Olivia Chen',      'admin@sydneyhappenings.example',   '$2y$10$QffQZ4s/QCkq/obY2wCGaO4rf4bSxUG8vioeAuihLuTMURhLYFZXa', '0400 111 222', 'admin',     1, NULL, NULL, NULL), -- id 1
('Marcus Webb',      'marcus.webb@example.com',          '$2y$10$QffQZ4s/QCkq/obY2wCGaO4rf4bSxUG8vioeAuihLuTMURhLYFZXa', '0400 222 333', 'organiser', 1, 'https://example.com/marcus-webb-events', 'https://facebook.com/marcuswebbevents', NULL), -- id 2
('Priya Natarajan',  'priya.natarajan@example.com',      '$2y$10$QffQZ4s/QCkq/obY2wCGaO4rf4bSxUG8vioeAuihLuTMURhLYFZXa', '0400 333 444', 'organiser', 1, 'https://example.com/priya-natarajan-events', NULL, 'https://instagram.com/priyanatarajanevents'), -- id 3
('Jack Thompson',    'jack.thompson@example.com',        '$2y$10$QffQZ4s/QCkq/obY2wCGaO4rf4bSxUG8vioeAuihLuTMURhLYFZXa', '0400 444 555', 'attendee',  1, NULL, NULL, NULL), -- id 4
('Sofia Rossi',      'sofia.rossi@example.com',          '$2y$10$QffQZ4s/QCkq/obY2wCGaO4rf4bSxUG8vioeAuihLuTMURhLYFZXa', '0400 555 666', 'attendee',  1, NULL, NULL, NULL), -- id 5
('Ben Nguyen',       'ben.nguyen@example.com',           '$2y$10$QffQZ4s/QCkq/obY2wCGaO4rf4bSxUG8vioeAuihLuTMURhLYFZXa', NULL,           'attendee',  1, NULL, NULL, NULL); -- id 6

-- ------------------------------------------------------------
-- categories (8, matching CLAUDE.md section 4)
-- ------------------------------------------------------------
INSERT INTO categories (name, slug, description) VALUES
('Festivals',       'festivals',       'Large-scale community and cultural festivals.'),         -- id 1
('Markets',         'markets',         'Street, night and farmers markets.'),                     -- id 2
('Food & Drink',    'food-drink',      'Food trucks, tastings and dining events.'),                -- id 3
('Arts & Culture',  'arts-culture',    'Exhibitions, comedy, theatre and cultural events.'),       -- id 4
('Sports & Fitness','sports-fitness',  'Fun runs, fitness classes and sporting events.'),          -- id 5
('Family & Kids',   'family-kids',     'Events suited to children and families.'),                 -- id 6
('Music',           'music',           'Live music, concerts and gigs.'),                          -- id 7
('Workshops',       'workshops',       'Hands-on classes and skill-building sessions.');           -- id 8

-- ------------------------------------------------------------
-- venues (8, across different Sydney suburbs)
-- ------------------------------------------------------------
INSERT INTO venues (name, address, suburb, postcode, capacity, latitude, longitude) VALUES
('Sydney Town Hall',           '483 George St',        'Sydney',        '2000', 800,  -33.8735021, 151.2065079),  -- id 1
('Bondi Pavilion',             'Queen Elizabeth Dr',    'Bondi Beach',   '2026', 300,  -33.8908368, 151.2743154),  -- id 2
('Parramatta Park',            'Pitt St',               'Parramatta',    '2150', 5000, -33.8140587, 151.0063938), -- id 3
('Newtown Community Centre',   '1 Bedford St',          'Newtown',       '2042', 150,  -33.8987955, 151.1793921), -- id 4
('Manly Wharf Precinct',       'East Esplanade',        'Manly',         '2095', 400,  -33.7969421, 151.2871864), -- id 5
('Chatswood Concourse',        'Victor St',             'Chatswood',     '2067', 600,  -33.7967525, 151.1817764), -- id 6
('Marrickville Warehouse',     '142 Addison Rd',        'Marrickville',  '2204', 250,  -33.9106622, 151.1547903), -- id 7
('Darling Harbour Precinct',   '1-25 Murray St',        'Darling Harbour','2000', 2000, -33.8688000, 151.2010000); -- id 8

-- ------------------------------------------------------------
-- events (20): a mix of past, this-week, and later; free and paid;
-- one full, one nearly full, one draft, one cancelled.
-- ------------------------------------------------------------
INSERT INTO events (organiser_id, category_id, venue_id, title, slug, description, start_datetime, end_datetime, capacity, price, status) VALUES

-- past events
(2, 2, 4, 'Newtown Night Markets',
 'newtown-night-markets',
 'A vibrant evening market through the streets of Newtown, with local makers, street food stalls, and live buskers. A regular favourite for the inner west.',
 (CURDATE() - INTERVAL 30 DAY) + INTERVAL 17 HOUR, (CURDATE() - INTERVAL 30 DAY) + INTERVAL 22 HOUR, 200, 0.00, 'published'),

(3, 1, 1, 'Sydney Winter Music Festival',
 'sydney-winter-music-festival',
 'An all-day celebration of live music at Sydney Town Hall, featuring local bands across three stages and a licensed bar.',
 (CURDATE() - INTERVAL 45 DAY) + INTERVAL 12 HOUR, (CURDATE() - INTERVAL 45 DAY) + INTERVAL 20 HOUR, 500, 45.00, 'published'),

(2, 5, 2, 'Bondi Beach Yoga Sunrise',
 'bondi-beach-yoga-sunrise',
 'Start your day with a guided outdoor yoga session on the sand at Bondi, suitable for all experience levels. Mats provided.',
 (CURDATE() - INTERVAL 10 DAY) + INTERVAL 6 HOUR, (CURDATE() - INTERVAL 10 DAY) + INTERVAL 8 HOUR, 60, 15.00, 'published'),

(3, 3, 3, 'Parramatta Food Truck Friday',
 'parramatta-food-truck-friday',
 'A rotating line-up of Sydney''s best food trucks set up in Parramatta Park, with free entry and live acoustic music.',
 (CURDATE() - INTERVAL 5 DAY) + INTERVAL 16 HOUR, (CURDATE() - INTERVAL 5 DAY) + INTERVAL 21 HOUR, 300, 0.00, 'published'),

-- this week
(2, 7, 6, 'Chatswood Jazz Night',
 'chatswood-jazz-night',
 'An intimate evening of live jazz at the Chatswood Concourse, featuring a quartet of Sydney Conservatorium graduates.',
 (CURDATE() + INTERVAL 1 DAY) + INTERVAL 19 HOUR, (CURDATE() + INTERVAL 1 DAY) + INTERVAL 22 HOUR, 150, 25.00, 'published'),

(3, 8, 7, 'Marrickville Craft Workshop',
 'marrickville-craft-workshop',
 'Learn hand embroidery basics in this beginner-friendly workshop, run out of the Marrickville Warehouse studio space. All materials supplied.',
 (CURDATE() + INTERVAL 3 DAY) + INTERVAL 10 HOUR, (CURDATE() + INTERVAL 3 DAY) + INTERVAL 13 HOUR, 30, 40.00, 'published'),

(2, 6, 8, 'Darling Harbour Kids Carnival',
 'darling-harbour-kids-carnival',
 'Rides, face painting, and family entertainment along the Darling Harbour foreshore. Free entry, rides sold separately.',
 (CURDATE() + INTERVAL 4 DAY) + INTERVAL 9 HOUR, (CURDATE() + INTERVAL 4 DAY) + INTERVAL 15 HOUR, 400, 0.00, 'published'),

(3, 4, 5, 'Manly Art Walk',
 'manly-art-walk',
 'A self-guided walking trail past pop-up galleries and sculpture installations around Manly Wharf, with local artists on hand to talk about their work.',
 (CURDATE() + INTERVAL 6 DAY) + INTERVAL 11 HOUR, (CURDATE() + INTERVAL 6 DAY) + INTERVAL 15 HOUR, 100, 0.00, 'published'),

-- later
(2, 7, 1, 'Sydney Town Hall Chamber Concert',
 'sydney-town-hall-chamber-concert',
 'A seated chamber music performance in the Sydney Town Hall''s main auditorium, featuring works by Mozart and local composers.',
 (CURDATE() + INTERVAL 14 DAY) + INTERVAL 19 HOUR + INTERVAL 30 MINUTE, (CURDATE() + INTERVAL 14 DAY) + INTERVAL 21 HOUR + INTERVAL 30 MINUTE, 250, 55.00, 'published'),

(3, 1, 2, 'Bondi Multicultural Festival',
 'bondi-multicultural-festival',
 'A free, all-ages festival celebrating Sydney''s cultural diversity with food stalls, dance performances and craft stands along Bondi Beach.',
 (CURDATE() + INTERVAL 18 DAY) + INTERVAL 10 HOUR, (CURDATE() + INTERVAL 18 DAY) + INTERVAL 18 HOUR, 350, 0.00, 'published'),

(3, 3, 7, 'Sunset Rooftop Wine Tasting',
 'sunset-rooftop-wine-tasting',
 'A small-group wine tasting evening showcasing five NSW regional wineries, paired with light canapes. Strictly limited spots.',
 (CURDATE() + INTERVAL 21 DAY) + INTERVAL 17 HOUR + INTERVAL 30 MINUTE, (CURDATE() + INTERVAL 21 DAY) + INTERVAL 20 HOUR + INTERVAL 30 MINUTE, 5, 60.00, 'published'),

(2, 8, 6, 'Chatswood Pottery Workshop',
 'chatswood-pottery-workshop',
 'A hands-on introduction to wheel-thrown pottery, run in small groups so everyone gets plenty of time with an instructor.',
 (CURDATE() + INTERVAL 24 DAY) + INTERVAL 10 HOUR, (CURDATE() + INTERVAL 24 DAY) + INTERVAL 13 HOUR, 6, 35.00, 'published'),

(3, 4, 4, 'Newtown Comedy Night',
 'newtown-comedy-night',
 'A line-up of five local stand-up comedians at the Newtown Community Centre, hosted by a rotating MC. BYO drinks welcome.',
 (CURDATE() + INTERVAL 28 DAY) + INTERVAL 20 HOUR, (CURDATE() + INTERVAL 28 DAY) + INTERVAL 23 HOUR, 120, 20.00, 'published'),

(2, 5, 8, 'Sydney Harbour Fun Run',
 'sydney-harbour-fun-run',
 'A 5km fun run along the Darling Harbour foreshore, open to all fitness levels, with a family-friendly 1km option.',
 (CURDATE() + INTERVAL 30 DAY) + INTERVAL 7 HOUR, (CURDATE() + INTERVAL 30 DAY) + INTERVAL 9 HOUR, 600, 10.00, 'published'),

(3, 6, 5, 'Manly Kids Science Fair',
 'manly-kids-science-fair',
 'Interactive science demonstrations and experiments for primary-school-aged children, run by volunteer STEM educators.',
 (CURDATE() + INTERVAL 35 DAY) + INTERVAL 10 HOUR, (CURDATE() + INTERVAL 35 DAY) + INTERVAL 15 HOUR, 150, 0.00, 'published'),

(2, 3, 3, 'Parramatta Cultural Food Festival',
 'parramatta-cultural-food-festival',
 'A celebration of Western Sydney''s culinary diversity, with over 40 food stalls representing cuisines from around the world.',
 (CURDATE() + INTERVAL 40 DAY) + INTERVAL 11 HOUR, (CURDATE() + INTERVAL 40 DAY) + INTERVAL 18 HOUR, 800, 0.00, 'published'),

(3, 7, 2, 'Bondi Summer Music Series',
 'bondi-summer-music-series',
 'An outdoor concert series on the Bondi Pavilion lawn, featuring up-and-coming Sydney artists across several genres.',
 (CURDATE() + INTERVAL 50 DAY) + INTERVAL 18 HOUR, (CURDATE() + INTERVAL 50 DAY) + INTERVAL 22 HOUR, 300, 30.00, 'published'),

(2, 4, 1, 'Sydney Town Hall Art Exhibition',
 'sydney-town-hall-art-exhibition',
 'A free public exhibition of contemporary works by emerging Sydney-based artists, running for one day only.',
 (CURDATE() + INTERVAL 60 DAY) + INTERVAL 10 HOUR, (CURDATE() + INTERVAL 60 DAY) + INTERVAL 17 HOUR, 200, 0.00, 'published'),

-- one draft (not yet published) and one cancelled
(3, 2, 7, 'Marrickville Makers Market',
 'marrickville-makers-market',
 'A new monthly market spotlighting independent designers and makers from the inner west. Details still being finalised.',
 (CURDATE() + INTERVAL 70 DAY) + INTERVAL 10 HOUR, (CURDATE() + INTERVAL 70 DAY) + INTERVAL 15 HOUR, 100, 0.00, 'draft'),

(2, 1, 6, 'Chatswood Winter Festival',
 'chatswood-winter-festival',
 'A winter festival with food stalls, ice sculptures and family entertainment. Unfortunately cancelled due to venue works.',
 (CURDATE() + INTERVAL 15 DAY) + INTERVAL 11 HOUR, (CURDATE() + INTERVAL 15 DAY) + INTERVAL 17 HOUR, 400, 0.00, 'cancelled');

-- ------------------------------------------------------------
-- registrations (about 40), including:
--  - past events with attendance already marked (attended / no_show)
--  - event 11 (Sunset Rooftop Wine Tasting, capacity 5) fully booked
--  - event 12 (Chatswood Pottery Workshop, capacity 6) nearly full
--  - event 20 (cancelled) with registrations made before cancellation
-- ------------------------------------------------------------
-- Every row is backfilled with a booking_reference, the way a real
-- booking always gets one at insert time (see generateBookingReference()
-- in functions.php, Step C2). quantity is left at 1 for every seeded
-- row so the existing "full" (event 11) and "nearly full" (event 12)
-- scenarios stay exactly as full as their comments say - bumping any
-- of those quantities would silently push them over capacity.
INSERT INTO registrations (user_id, event_id, status, quantity, booking_reference) VALUES
-- event 1: Newtown Night Markets (past)
(4, 1, 'attended', 1, 'XEDTFJZS'), (5, 1, 'no_show', 1, 'F2HHSKNJ'),
-- event 2: Sydney Winter Music Festival (past)
(1, 2, 'attended', 1, 'TB4UPECY'), (4, 2, 'attended', 1, '4TBZE7YT'), (6, 2, 'attended', 1, 'FBKV4AT3'),
-- event 3: Bondi Beach Yoga Sunrise (past)
(5, 3, 'attended', 1, '2NEAB3WD'), (6, 3, 'attended', 1, 'H2JXEBVS'),
-- event 4: Parramatta Food Truck Friday (past)
(4, 4, 'no_show', 1, 'BQYWGANJ'), (6, 4, 'attended', 1, 'CZCRA3QS'),
-- event 5: Chatswood Jazz Night (this week)
(3, 5, 'registered', 1, 'SA4AABXB'), (4, 5, 'registered', 1, '77RNWBNE'), (5, 5, 'registered', 1, 'Q3TPK7VZ'),
-- event 6: Marrickville Craft Workshop (this week)
(1, 6, 'registered', 1, 'A68MZFDR'), (6, 6, 'registered', 1, 'M3EEGVS9'),
-- event 7: Darling Harbour Kids Carnival (this week)
(4, 7, 'registered', 1, '2XZJU9XA'), (5, 7, 'registered', 1, 'M7AGJBGV'),
-- event 8: Manly Art Walk (this week)
(1, 8, 'registered', 1, 'QC9DDKM2'), (6, 8, 'registered', 1, '4AB56H75'),
-- event 9: Sydney Town Hall Chamber Concert
(3, 9, 'registered', 1, 'JTWWFKFA'),
-- event 10: Bondi Multicultural Festival
(2, 10, 'registered', 1, 'H793E6FK'),
-- event 11: Sunset Rooftop Wine Tasting - FULL (capacity 5, 5 registered)
(1, 11, 'registered', 1, 'UUZDVP6Q'), (2, 11, 'registered', 1, 'M8RT84DD'), (4, 11, 'registered', 1, 'AF8ZPVPD'), (5, 11, 'registered', 1, '786FMTW8'), (6, 11, 'registered', 1, '42JMT3H6'),
-- event 12: Chatswood Pottery Workshop - NEARLY FULL (capacity 6, 5 registered)
(1, 12, 'registered', 1, 'XFJH4WCQ'), (3, 12, 'registered', 1, '36388R69'), (4, 12, 'registered', 1, 'EVGWSYD3'), (5, 12, 'registered', 1, 'MTZGT3VG'), (6, 12, 'registered', 1, '7BBGKFKJ'),
-- event 13: Newtown Comedy Night
(4, 13, 'registered', 1, '6RNR2X9N'),
-- event 14: Sydney Harbour Fun Run
(5, 14, 'registered', 1, '5KF8VVP8'), (6, 14, 'registered', 1, 'CR6K766A'),
-- event 15: Manly Kids Science Fair
(1, 15, 'registered', 1, 'KUA6PXX9'),
-- event 16: Parramatta Cultural Food Festival
(4, 16, 'registered', 1, 'K82AMXBY'),
-- event 17: Bondi Summer Music Series
(5, 17, 'registered', 1, 'TNMGVWVK'),
-- event 18: Sydney Town Hall Art Exhibition
(6, 18, 'registered', 1, 'XDE5K9MA'),
-- event 20: Chatswood Winter Festival - registered before it was cancelled
(4, 20, 'registered', 1, 'UMEFWD8F'), (5, 20, 'registered', 1, 'XGWX5NWQ');

-- ------------------------------------------------------------
-- extra 'attended' registrations for past events (1-4), added so
-- there are 12 distinct (user, event) attended pairs to draw the
-- 12 seeded reviews below from - reviews.sql's unique key means
-- each of these pairs can only be reviewed once.
-- ------------------------------------------------------------
INSERT INTO registrations (user_id, event_id, status, quantity, booking_reference) VALUES
-- event 1: Newtown Night Markets - two more attendees
(2, 1, 'attended', 1, 'QG3UTXPX'), (3, 1, 'attended', 1, '9YGKT4YK'),
-- event 2: Sydney Winter Music Festival - one more attendee
(5, 2, 'attended', 1, 'V2Q9S5BP'),
-- event 3: Bondi Beach Yoga Sunrise - one more attendee
(3, 3, 'attended', 1, 'QFUPA8RK'),
-- event 4: Parramatta Food Truck Friday - one more attendee
(1, 4, 'attended', 1, 'SZPCF3R7');

-- ------------------------------------------------------------
-- reviews (12), one per attendee per past event they attended.
-- Mostly positive (4s and 5s), a couple of 3s, one 2. One review
-- is hidden (is_hidden = 1) as if it had been flagged and taken
-- down by an admin pending follow-up.
-- ------------------------------------------------------------
INSERT INTO reviews (event_id, user_id, rating, comment, is_hidden) VALUES
-- event 1: Newtown Night Markets
(1, 4, 5, 'Such a great night out - loads of stalls, the dumplings were amazing, and the buskers made it feel really special. Will be back next time!', 0),
(1, 2, 4, 'Good variety of stalls and a lovely atmosphere, though it was fairly crowded by 7pm. Worth going early.', 0),
(1, 3, 5, 'One of the best markets in the inner west. Loved supporting local makers and the food was excellent.', 0),
-- event 2: Sydney Winter Music Festival
(2, 1, 5, 'Fantastic line-up across all three stages. Well organised for such a big crowd.', 0),
(2, 4, 4, 'Really enjoyed the day, though the bar queues got long in the afternoon. Music was excellent.', 0),
(2, 6, 3, 'Decent festival but a bit overpriced for what was on offer. Sound quality on the second stage was patchy.', 0),
(2, 5, 5, 'Brilliant day out. Discovered a few new local bands I will definitely follow now.', 0),
-- event 3: Bondi Beach Yoga Sunrise
(3, 5, 5, 'Beautiful way to start the day. The instructor was welcoming to beginners and the view was unbeatable.', 0),
(3, 6, 4, 'Really peaceful session, though it would be good to have a few more mats on hand for busy mornings.', 0),
(3, 3, 3, 'Nice concept but it was quite windy on the sand that morning, which made it hard to hold some poses.', 0),
-- event 4: Parramatta Food Truck Friday
(4, 6, 2, 'Waited over 40 minutes for food from two of the trucks and one ran out of menu items before we got served.', 1),
(4, 1, 4, 'Great range of trucks and a relaxed park setting. Would go again but maybe arrive earlier to beat the queues.', 0);

-- ------------------------------------------------------------
-- wishlists (15), spread across the three seeded attendees (5 each)
-- and 15 distinct published events, so account/my-wishlist.php has
-- real data to show for each of them. created_at is staggered so
-- "newest saved first" has something meaningful to sort by.
-- ------------------------------------------------------------
INSERT INTO wishlists (user_id, event_id, created_at) VALUES
-- Jack Thompson (id 4)
(4, 2,  NOW() - INTERVAL 20 DAY),
(4, 5,  NOW() - INTERVAL 14 DAY),
(4, 9,  NOW() - INTERVAL 9 DAY),
(4, 14, NOW() - INTERVAL 4 DAY),
(4, 17, NOW() - INTERVAL 1 DAY),
-- Sofia Rossi (id 5)
(5, 1,  NOW() - INTERVAL 18 DAY),
(5, 6,  NOW() - INTERVAL 12 DAY),
(5, 10, NOW() - INTERVAL 8 DAY),
(5, 13, NOW() - INTERVAL 3 DAY),
(5, 16, NOW() - INTERVAL 1 DAY),
-- Ben Nguyen (id 6)
(6, 3,  NOW() - INTERVAL 16 DAY),
(6, 7,  NOW() - INTERVAL 11 DAY),
(6, 11, NOW() - INTERVAL 7 DAY),
(6, 15, NOW() - INTERVAL 2 DAY),
(6, 18, NOW() - INTERVAL 1 HOUR);

-- ------------------------------------------------------------
-- enquiries (4, one already handled)
-- ------------------------------------------------------------
INSERT INTO enquiries (name, email, subject, message, is_handled, created_at) VALUES
('Amelia Foster', 'amelia.foster@example.com', 'Question about becoming an organiser',
 'Hi, I run a small community garden group in Marrickville and would love to list our monthly working bees. How do I get an organiser account?',
 1, NOW() - INTERVAL 12 DAY),
('Daniel Kim', 'daniel.kim@example.com', 'Accessibility feedback',
 'Just wanted to say the site works really well with my screen reader - the skip link and error messages were easy to follow. Thank you!',
 0, NOW() - INTERVAL 6 DAY),
('Grace O''Sullivan', 'grace.osullivan@example.com', 'Venue capacity question',
 'Is the capacity listed for a venue the maximum for the whole venue, or just the room our event would be held in? Keen to book Chatswood Concourse.',
 0, NOW() - INTERVAL 3 DAY),
('Tom Andrews', 'tom.andrews@example.com', 'Partnership enquiry',
 'I work for a local council and we are interested in cross-promoting our community events through your platform. Who would be best to speak with?',
 0, NOW() - INTERVAL 1 DAY);
