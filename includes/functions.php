<?php
// ============================================================
// includes/functions.php
// General-purpose helper functions shared across the whole site:
// URL/slug helpers, formatting, database lookups used on many
// pages, and image upload handling.
// This file does not start a session or touch $_SESSION - that is
// auth_guard.php's job.
// ============================================================

/**
 * Sends the browser to another page on this site and stops execution.
 * @param string $path A path starting with "/", e.g. "/login.php".
 * @return void
 */
function redirect($path) {
    header('Location: ' . BASE_URL . $path);
    exit;
}

/**
 * Turns a title into a URL-friendly slug, e.g. "Newtown Night Markets!"
 * becomes "newtown-night-markets".
 * @param string $text
 * @return string
 */
function generateSlug($text) {
    $slug = strtolower(trim($text));
    // Replace anything that isn't a letter or digit with a hyphen.
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    // Collapse repeated hyphens and trim leading/trailing ones.
    $slug = trim(preg_replace('/-+/', '-', $slug), '-');
    return $slug;
}

/**
 * Builds a slug that is guaranteed not to clash with an existing row.
 * If "newtown-markets" is already taken, tries "newtown-markets-2",
 * "newtown-markets-3", and so on.
 * @param PDO $pdo
 * @param string $table Table to check, e.g. "events".
 * @param string $baseText The title to slugify.
 * @param int|null $excludeId Row id to ignore (used when editing, so a
 *        row does not collide with its own current slug).
 * @return string
 */
function makeUniqueSlug($pdo, $table, $baseText, $excludeId = null) {
    $base = generateSlug($baseText);
    $slug = $base;
    $suffix = 2;

    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = :slug";
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= " AND id != :excludeId";
            $params['excludeId'] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetch() === false) {
            // No row uses this slug yet - it is safe to use.
            return $slug;
        }

        $slug = $base . '-' . $suffix;
        $suffix++;
    }
}

/**
 * Formats a MySQL datetime string for display, e.g. "Tue 3 Nov 2026, 6:00 pm".
 * @param string $datetime A "Y-m-d H:i:s" value from the database.
 * @return string
 */
function formatEventDate($datetime) {
    $timestamp = strtotime($datetime);
    return date('D j M Y, g:ia', $timestamp);
}

/**
 * Formats a price for display. Zero is shown as "Free" rather than "$0.00"
 * so free events are obvious at a glance.
 * @param float $price
 * @return string
 */
function formatPrice($price) {
    if ((float) $price <= 0) {
        return 'Free';
    }
    return '$' . number_format((float) $price, 2);
}

/**
 * @param string $datetime A "Y-m-d H:i:s" value from the database.
 * @return bool True if the given datetime is already in the past.
 */
function isPastDatetime($datetime) {
    return strtotime($datetime) < time();
}

/**
 * @param PDO $pdo
 * @return array All active categories, ordered by name, for dropdowns
 *         and the browse-by-category tiles.
 */
function getActiveCategories($pdo) {
    $stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC");
    return $stmt->fetchAll();
}

/**
 * @param PDO $pdo
 * @return array All active venues, ordered by name, for dropdowns.
 */
function getActiveVenues($pdo) {
    $stmt = $pdo->query("SELECT * FROM venues WHERE is_active = 1 ORDER BY name ASC");
    return $stmt->fetchAll();
}

/**
 * Counts how many tickets are currently held against an event's
 * capacity. Only 'registered' rows count - a cancelled registration
 * frees its tickets. This sums quantity rather than counting rows,
 * because one booking (one row) can cover more than one ticket - a
 * row count would understate capacity used by any multi-ticket
 * booking (Feature C).
 * @param PDO $pdo
 * @param int $eventId
 * @return int
 */
function getRegisteredCount($pdo, $eventId) {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(quantity), 0)
         FROM registrations
         WHERE event_id = :eventId
         AND status IN ('registered', 'attended', 'no_show')"
    );

    $stmt->execute([
        'eventId' => $eventId
    ]);

    return (int) $stmt->fetchColumn();
}

/**
 * Strips HTML and shortens text for use in a <meta name="description">
 * tag, e.g. the first 155 characters of an event description.
 * @param string $text
 * @param int $length
 * @return string
 */
function truncateForMeta($text, $length = 155) {
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    if (strlen($plain) <= $length) {
        return $plain;
    }
    // Cut at the last space before the limit so words are not chopped in half.
    $cut = substr($plain, 0, $length);
    $cut = substr($cut, 0, strrpos($cut, ' '));
    return $cut . '...';
}

/**
 * @param string $file The current page's filename, e.g. "events.php".
 * @return bool True if the browser is currently viewing that file, used
 *         to add aria-current="page" to the matching nav link.
 */
function isCurrentPage($file) {
    return basename($_SERVER['SCRIPT_NAME']) === $file;
}

/**
 * Builds the canonical URL for the current page: scheme + host + path,
 * deliberately excluding the query string so that filter/sort
 * parameters never create duplicate-content canonical URLs.
 * @return string
 */
function currentCanonicalUrl() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = strtok($_SERVER['REQUEST_URI'], '?');
    return $scheme . '://' . $host . $path;
}

/**
 * Validates and saves an uploaded event image, renaming it to a random
 * file name so the original name (which may contain hostile characters,
 * or collide with another file) is never trusted.
 * Call validateImageUpload() first - this function assumes the file has
 * already passed validation and only re-checks the essentials.
 * @param array $file One entry from $_FILES, e.g. $_FILES['image'].
 * @return string|null The new filename on success, or null if no file
 *         was uploaded.
 */
function saveEventImage($file) {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $allowedExtensions = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $mimeType = mime_content_type($file['tmp_name']);
    $extension = $allowedExtensions[$mimeType] ?? null;

    if ($extension === null) {
        throw new RuntimeException('Invalid image type.');
    }

    $uploadDir = dirname(__DIR__) . '/assets/uploads/';

    // Make sure the upload directory exists.
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new RuntimeException('Could not create the upload directory.');
        }
    }

    // Make sure PHP can write to the folder.
    if (!is_writable($uploadDir)) {
        throw new RuntimeException('The upload directory is not writable.');
    }

    $newFilename = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = $uploadDir . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }

    return $newFilename;
}

/**
 * Calculates an event's average rating and review count, from visible
 * (not hidden) reviews only.
 * @param PDO $pdo
 * @param int $eventId
 * @return array ['average' => float|null, 'count' => int]. average is
 *         null when the event has no visible reviews yet.
 */
function getEventRating($pdo, $eventId) {
    $stmt = $pdo->prepare(
        "SELECT AVG(rating) AS average, COUNT(*) AS count
         FROM reviews
         WHERE event_id = :eventId AND is_hidden = 0"
    );
    $stmt->execute(['eventId' => $eventId]);
    $row = $stmt->fetch();

    return [
        'average' => $row['average'] !== null ? (float) $row['average'] : null,
        'count'   => (int) $row['count'],
    ];
}

/**
 * Checks whether a user is currently allowed to review an event: the
 * event must have finished, the user must have a registrations row for
 * it with status 'attended', and they must not already have a review
 * for it. All three checks happen in one query - a LEFT JOIN to
 * reviews with an IS NULL check stands in for the "no existing review"
 * condition, instead of a separate round trip to the database.
 * @param PDO $pdo
 * @param int $userId
 * @param int $eventId
 * @return bool
 */
function canUserReview($pdo, $userId, $eventId) {
    $stmt = $pdo->prepare(
        "SELECT e.id
         FROM events e
         JOIN registrations r ON r.event_id = e.id AND r.user_id = :attendUserId AND r.status = 'attended'
         LEFT JOIN reviews rv ON rv.event_id = e.id AND rv.user_id = :reviewUserId
         WHERE e.id = :eventId AND e.end_datetime < NOW() AND rv.id IS NULL"
    );
    // The same user id is bound twice, once for each join, because the
    // query references it in two different places - PDO does not allow
    // a single named placeholder to stand for two different columns.
    $stmt->execute([
        'attendUserId' => $userId,
        'reviewUserId' => $userId,
        'eventId'      => $eventId,
    ]);
    return $stmt->fetch() !== false;
}

/**
 * @param PDO $pdo
 * @param int $userId
 * @param int $eventId
 * @return array|null The user's review row for this event, or null if
 *         they have not reviewed it.
 */
function getUserReview($pdo, $userId, $eventId) {
    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE user_id = :userId AND event_id = :eventId");
    $stmt->execute(['userId' => $userId, 'eventId' => $eventId]);
    $review = $stmt->fetch();
    return $review !== false ? $review : null;
}

/**
 * Builds the small "star rating" widget used on the event detail page.
 * The star characters are purely decorative and hidden from screen
 * readers with aria-hidden="true" - the actual figure is always given
 * as plain visible text too, e.g. "4.3 out of 5 from 12 reviews", so
 * the rating is never conveyed by the star icons alone.
 * @param float|null $average From getEventRating()['average'].
 * @param int $count From getEventRating()['count'].
 * @return string HTML for the rating widget.
 */
function renderStars($average, $count) {
    if ($count === 0 || $average === null) {
        return '<div class="rating">'
            . '<span class="rating-icons" aria-hidden="true">&#9734;&#9734;&#9734;&#9734;&#9734;</span>'
            . '<span class="rating-text">No reviews yet</span>'
            . '</div>';
    }

    // Round to the nearest whole star for the decorative icons - the
    // precise figure (e.g. 4.3) is only ever given in the visible text.
    $filledStars = (int) round($average);
    $icons = str_repeat('&#9733;', $filledStars) . str_repeat('&#9734;', 5 - $filledStars);

    $text = number_format($average, 1) . ' out of 5 from ' . $count . ' review' . ($count === 1 ? '' : 's');

    return '<div class="rating">'
        . '<span class="rating-icons" aria-hidden="true">' . $icons . '</span>'
        . '<span class="rating-text">' . e($text) . '</span>'
        . '</div>';
}

/**
 * All of a user's saved (wishlisted) event ids, fetched once. Call
 * this once per listing page before the card loop, then check each
 * card against the returned array with isWishlisted() - never call
 * this inside the loop itself, which would be one query per card.
 * @param PDO $pdo
 * @param int $userId
 * @return int[] Event ids, e.g. [4, 9, 17].
 */
function getUserWishlistIds($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT event_id FROM wishlists WHERE user_id = :userId");
    $stmt->execute(['userId' => $userId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * @param int[] $wishlistIds From getUserWishlistIds().
 * @param int $eventId
 * @return bool
 */
function isWishlisted($wishlistIds, $eventId) {
    return in_array((int) $eventId, $wishlistIds, true);
}

/**
 * The current request's path and query string with BASE_URL stripped
 * off the front - the same format redirect() and every existing
 * redirect('/somewhere') call in this codebase already expects, so a
 * value from this function can always be handed straight to
 * redirect() without BASE_URL ending up duplicated in the result.
 * Used to send the user back to whatever page they toggled their
 * wishlist from (see wishlist-toggle.php).
 * @return string Always starts with "/" and is never empty.
 */
function currentRequestPath() {
    $uri = $_SERVER['REQUEST_URI'];
    if (BASE_URL !== '' && strpos($uri, BASE_URL) === 0) {
        $uri = substr($uri, strlen(BASE_URL));
    }
    return $uri === '' ? '/' : $uri;
}

/**
 * Generates a booking reference: an 8-character code drawn only from
 * uppercase letters and digits with the easily-confused characters
 * removed (0/O, 1/I/L), so a reference printed on a ticket or read
 * aloud is never ambiguous. Retries with a fresh random code if the
 * one just generated is already in use.
 *
 * The SELECT below only narrows the odds of a collision to almost
 * nothing - it is a check-then-act race, not a guarantee, because two
 * requests could both pass this check for the same code a moment
 * apart. uq_booking_reference on the registrations table (see
 * schema.sql) is the real guarantee: whatever code this function
 * returns, the INSERT that uses it must still be wrapped in a
 * try/catch for SQLSTATE 23000, the same backstop pattern already
 * used for wishlists and reviews.
 * @param PDO $pdo
 * @return string An 8-character reference, e.g. "K7QWXT4B".
 */
function generateBookingReference($pdo) {
    $characters = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $checkStmt = $pdo->prepare("SELECT id FROM registrations WHERE booking_reference = :ref");

    do {
        $reference = '';
        for ($i = 0; $i < 8; $i++) {
            $reference .= $characters[random_int(0, strlen($characters) - 1)];
        }
        $checkStmt->execute(['ref' => $reference]);
        $alreadyUsed = $checkStmt->fetch() !== false;
    } while ($alreadyUsed);

    return $reference;
}

/**
 * The most tickets a single booking may cover. Returned from one
 * place so register-for-event.php's server-side validation and the
 * quantity stepper's max attribute in event.php/main.js can never
 * drift apart and disagree about the limit.
 * @return int
 */
function getMaxTicketsPerBooking() {
    return 4;
}

/**
 * Builds a Google Maps "get directions" link for a venue. Prefers
 * exact coordinates when the venue has them; falls back to a
 * URL-encoded text address search when it does not, so a venue with
 * no latitude/longitude never ends up with a broken or missing link.
 * @param array $venue A row from the venues table (needs address,
 *        suburb, postcode, and optionally latitude/longitude).
 * @return string
 */
function getDirectionsUrl($venue) {
    if ($venue['latitude'] !== null && $venue['longitude'] !== null) {
        return 'https://www.google.com/maps/dir/?api=1&destination=' . $venue['latitude'] . ',' . $venue['longitude'];
    }

    $address = $venue['address'] . ', ' . $venue['suburb'] . ' ' . $venue['postcode'];
    return 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($address);
}
