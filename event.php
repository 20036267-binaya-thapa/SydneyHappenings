<?php
// ============================================================
// event.php
// Public event detail page, reached as event.php?slug=... . Shows
// full event details and a registration panel with a live capacity
// count. An unknown slug renders the 404 page.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';

$slug = trim($_GET['slug'] ?? '');

$stmt = $pdo->prepare(
    "SELECT e.*, c.name AS category_name,
            v.name AS venue_name, v.address, v.suburb, v.postcode, v.latitude, v.longitude,
            u.name AS organiser_name, u.website, u.facebook, u.instagram
     FROM events e
     JOIN categories c ON c.id = e.category_id
     JOIN venues v ON v.id = e.venue_id
     JOIN users u ON u.id = e.organiser_id
     WHERE e.slug = :slug"
);
$stmt->execute(['slug' => $slug]);
$event = $stmt->fetch();

if ($event === false) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$registeredCount = getRegisteredCount($pdo, $event['id']);
$spotsRemaining = max(0, (int) $event['capacity'] - $registeredCount);

$eventRating = getEventRating($pdo, $event['id']);

// Reviews are fetched newest first, hidden ones excluded. One extra
// row is fetched over the display limit of 5 so a "show more" link
// can be offered without a separate COUNT query; ?all_reviews=1 skips
// the limit and shows everything.
$showAllReviews = isset($_GET['all_reviews']);
$reviewLimitClause = $showAllReviews ? '' : ' LIMIT 6';
$stmt = $pdo->prepare(
    "SELECT r.*, SUBSTRING_INDEX(u.name, ' ', 1) AS reviewer_first_name
     FROM reviews r
     JOIN users u ON u.id = r.user_id
     WHERE r.event_id = :eventId AND r.is_hidden = 0
     ORDER BY r.created_at DESC" . $reviewLimitClause
);
$stmt->execute(['eventId' => $event['id']]);
$reviews = $stmt->fetchAll();

$hasMoreReviews = !$showAllReviews && count($reviews) > 5;
$displayedReviews = $hasMoreReviews ? array_slice($reviews, 0, 5) : $reviews;

// The full row, not just a yes/no flag - the "already booked" panel
// state needs the quantity and booking reference to show and link to.
$myRegistration = null;
if (isLoggedIn()) {
    $stmt = $pdo->prepare(
        "SELECT quantity, booking_reference FROM registrations
         WHERE user_id = :userId AND event_id = :eventId AND status = 'registered'"
    );
    $stmt->execute(['userId' => currentUserId(), 'eventId' => $event['id']]);
    $myRegistration = $stmt->fetch();
    $myRegistration = $myRegistration !== false ? $myRegistration : null;
}
$alreadyRegistered = $myRegistration !== null;

// A single-row check, not getUserWishlistIds() - that helper fetches
// every event a user has saved, which only makes sense to call once
// for a whole listing page. Here there is only one event to check.
$isSaved = false;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT id FROM wishlists WHERE user_id = :userId AND event_id = :eventId");
    $stmt->execute(['userId' => currentUserId(), 'eventId' => $event['id']]);
    $isSaved = $stmt->fetch() !== false;
}

// Used below to decide the reviews call to action: an edit link if the
// user already has a review, a "write a review" link if they are
// newly eligible, or nothing at all otherwise (e.g. they attended but
// the event has not finished yet).
$existingReview = null;
$canReview = false;
if (isLoggedIn()) {
    $existingReview = getUserReview($pdo, currentUserId(), $event['id']);
    if ($existingReview === null) {
        $canReview = canUserReview($pdo, currentUserId(), $event['id']);
    }
}

$eventStarted = isPastDatetime($event['start_datetime']);
$eventFinished = isPastDatetime($event['end_datetime']);

// ---------- SEO: title, description, canonical, Open Graph, JSON-LD ----------
$pageTitle = $event['title'] . ' - ' . $event['suburb'] . ' | ' . SITE_NAME;
$pageDescription = truncateForMeta($event['description']);

// The slug is this page's real identity (not a filter), so unlike
// events.php the canonical URL keeps the query string here.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$pageUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/event.php?slug=' . urlencode($event['slug']);
$canonicalUrl = $pageUrl;

$imageUrl = $event['image'] ? ($scheme . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/assets/uploads/' . $event['image']) : null;

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Event',
    'name' => $event['title'],
    'startDate' => date('c', strtotime($event['start_datetime'])),
    'endDate' => date('c', strtotime($event['end_datetime'])),
    'description' => truncateForMeta($event['description'], 500),
    'location' => [
        '@type' => 'Place',
        'name' => $event['venue_name'],
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $event['address'],
            'addressLocality' => $event['suburb'],
            'postalCode' => $event['postcode'],
            'addressRegion' => 'NSW',
            'addressCountry' => 'AU',
        ],
    ],
    'offers' => [
        '@type' => 'Offer',
        'price' => number_format((float) $event['price'], 2, '.', ''),
        'priceCurrency' => 'AUD',
        'availability' => $spotsRemaining > 0 ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
        'url' => $pageUrl,
    ],
    'organizer' => [
        '@type' => 'Organization',
        'name' => $event['organiser_name'],
    ],
];

// An aggregateRating with zero reviews is invalid structured data and
// fails Google's Rich Results Test, so it is only added once at least
// one visible review exists.
if ($eventRating['count'] > 0) {
    $jsonLd['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => round($eventRating['average'], 1),
        'reviewCount' => $eventRating['count'],
        'bestRating' => 5,
        'worstRating' => 1,
    ];
}

if ($imageUrl) {
    $jsonLd['image'] = $imageUrl;
}

// header.php reads these to build this page's Open Graph/Twitter tags
// itself (Feature D) - set here rather than left to its defaults, so
// the tags describe this specific event rather than the site in
// general. $extraHead is JSON-LD only now, so nothing is duplicated.
$ogType = 'event';
$ogTitle = $event['title'];
$ogDescription = $pageDescription;
if ($imageUrl) {
    $ogImage = $imageUrl;
}

$extraHead = '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";

// External links only - no new queries, no app routes touched.
// getDirectionsUrl() prefers the venue's exact coordinates when set,
// falling back to the same address-search style URL this used to
// build by hand - $event already carries address/suburb/postcode and
// latitude/longitude from the query above.
$directionsUrl = getDirectionsUrl($event);
$facebookShareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($pageUrl);
$twitterShareUrl = 'https://twitter.com/intent/tweet?url=' . urlencode($pageUrl) . '&text=' . urlencode($event['title']);

require_once __DIR__ . '/includes/header.php';
?>

<article class="event-detail">
    <div class="event-hero">
        <?php if ($event['image']): ?>
            <img class="event-card-image" src="<?= BASE_URL ?>/assets/uploads/<?= e($event['image']) ?>"
                 alt="<?= e($event['title']) ?>" width="800" height="450" loading="lazy">
        <?php else: ?>
            <div class="image-placeholder" aria-hidden="true">No image available</div>
        <?php endif; ?>
    </div>

    <div class="event-layout">
        <div class="event-main">
            <div class="event-detail-badges">
                <span class="badge badge-category"><?= e($event['category_name']) ?></span>
                <?php if ($event['status'] === 'cancelled'): ?>
                    <span class="badge badge-status-cancelled">Cancelled</span>
                <?php elseif ($event['status'] === 'draft'): ?>
                    <span class="badge badge-status-draft">Draft (not yet published)</span>
                <?php endif; ?>
            </div>

            <h1><?= e($event['title']) ?></h1>
            <p class="event-organiser-subtitle">Organised by <?= e($event['organiser_name']) ?></p>

            <div class="event-when">
                <p class="detail-block-label">When</p>
                <p class="detail-block-value"><?= e(formatEventDate($event['start_datetime'])) ?> to <?= e(formatEventDate($event['end_datetime'])) ?></p>
            </div>

            <h2>Description</h2>
            <div class="event-description" id="eventDescription">
                <p><?= nl2br(e($event['description'])) ?></p>
            </div>
            <!-- Hidden by default: PHP always renders the description fully
                 visible. main.js reveals this and collapses the text above -
                 never the other way round, so the full description is what
                 a no-JS visitor gets. -->
            <button type="button" class="description-toggle" id="descriptionToggle"
                    aria-expanded="true" aria-controls="eventDescription" hidden>
                Read more
            </button>

            <div class="detail-block">
                <p class="detail-block-label">Price</p>
                <p class="detail-block-value"><?= e(formatPrice($event['price'])) ?></p>

                <div class="capacity-bar">
                    <?php
                        $percentFull = $event['capacity'] > 0 ? min(100, round(($registeredCount / $event['capacity']) * 100)) : 0;
                        // Colour is a hint only - .capacity-bar-text right
                        // below always carries the real "X of Y left"
                        // figure, so this class is never the only signal.
                        if ($spotsRemaining <= 0) {
                            $capacityBarClass = 'capacity-full';
                        } elseif ($percentFull >= 80) {
                            $capacityBarClass = 'capacity-nearly-full';
                        } else {
                            $capacityBarClass = '';
                        }
                    ?>
                    <div class="capacity-bar-fill <?= $capacityBarClass ?>" style="width: <?= $percentFull ?>%"></div>
                </div>
                <p class="capacity-bar-text">
                    <?= $spotsRemaining ?> of <?= (int) $event['capacity'] ?> place<?= (int) $event['capacity'] === 1 ? '' : 's' ?> left
                </p>

                <?php if ($alreadyRegistered): ?>
                    <!-- Checked first, ahead of the status/started checks below,
                         so a user who booked while an event was published
                         can still see their ticket and cancel it even if the
                         organiser later cancels the event or reverts it to draft. -->
                    <p>
                        You're booked &mdash; <?= (int) $myRegistration['quantity'] ?> ticket<?= (int) $myRegistration['quantity'] === 1 ? '' : 's' ?>,
                        reference <strong><?= e($myRegistration['booking_reference']) ?></strong>.
                    </p>
                    <p>
                        <a class="btn btn-secondary btn-block" href="<?= BASE_URL ?>/booking-confirmation.php?ref=<?= urlencode($myRegistration['booking_reference']) ?>">
                            View booking
                        </a>
                    </p>
                    <?php if (!$eventStarted): ?>

                        <form method="post"
                            action="<?= BASE_URL ?>/cancel-registration.php"
                            data-confirm="Cancel your registration for this event?">

                            <?= csrfField() ?>

                            <input type="hidden"
                                name="event_id"
                                value="<?= (int) $event['id'] ?>">

                            <button type="submit"
                                    class="btn btn-danger btn-block">
                                Cancel
                            </button>

                        </form>

                        <?php elseif ($eventFinished): ?>

                        <p>This event has finished.</p>

                        <?php else: ?>

                        <p>This booking can no longer be cancelled because the event has started.</p>

                        <?php endif; ?>
                <?php elseif ($event['status'] !== 'published'): ?>
                    <p>Registrations are not open for this event.</p>
                    <?php elseif ($eventFinished): ?>
                        <p>This event has finished.</p>

                    <?php elseif ($eventStarted): ?>
                        <p>Registration is closed because this event has already started.</p>
                <?php elseif ($spotsRemaining <= 0): ?>
                    <p>Sold out.</p>
                <?php elseif (!isLoggedIn()): ?>
                    <!-- REQUEST_URI, not currentRequestPath() - login.php
                         puts this straight into a Location header the
                         same way auth_guard.php's redirect_after_login
                         already does, which expects the BASE_URL prefix
                         still attached (currentRequestPath() strips it,
                         which is right for redirect() but wrong here). -->
                    <p>
                        <a class="btn btn-accent btn-block" href="<?= BASE_URL ?>/login.php?return=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                            Log in to book
                        </a>
                    </p>
                <?php else: ?>
                    <?php $maxTickets = min(getMaxTicketsPerBooking(), $spotsRemaining); ?>
                    <form method="post" action="<?= BASE_URL ?>/register-for-event.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">

                        <div class="form-field">
                            <label for="ticketQuantity">Number of tickets</label>
                            <div class="qty-stepper">
                                <!-- Inert without JavaScript on purpose - main.js
                                     (Step C8) wires these up. The number input
                                     next to them is a real, directly-editable
                                     field, so choosing a quantity always works
                                     even when these two buttons do nothing. -->
                                <button type="button" class="qty-stepper-btn" id="ticketQuantityDecrease" aria-label="Decrease number of tickets">&minus;</button>
                                <input type="number" id="ticketQuantity" name="quantity" class="qty-stepper-input"
                                       value="1" min="1" max="<?= $maxTickets ?>" inputmode="numeric"
                                       data-spots-remaining="<?= $spotsRemaining ?>">
                                <button type="button" class="qty-stepper-btn" id="ticketQuantityIncrease" aria-label="Increase number of tickets">&plus;</button>
                            </div>
                            <!-- Starts hidden and empty - a JavaScript-only
                                 enhancement (main.js fills it in and reveals
                                 it), never anything a no-JS visitor needs to
                                 book: the stepper's own min/max and the
                                 server-side checks are what actually enforce
                                 the limit either way. -->
                            <p id="ticketQuantityRemaining" class="field-hint" aria-live="polite" hidden></p>
                        </div>

                        <button type="submit" class="btn btn-accent">Book now</button>
                    </form>
                <?php endif; ?>

                <?php if (isLoggedIn()): ?>
                    <form method="post" action="<?= BASE_URL ?>/wishlist-toggle.php" class="wishlist-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                        <input type="hidden" name="referrer" value="<?= e(currentRequestPath()) ?>">
                        <button type="submit" class="wishlist-btn wishlist-btn--labelled btn-block" aria-pressed="<?= $isSaved ? 'true' : 'false' ?>">
                            <span class="wishlist-heart" aria-hidden="true">&#9829;</span>
                            <span><?= $isSaved ? 'Saved' : 'Save event' ?></span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <section class="event-reviews-section" aria-labelledby="reviews-heading">
                <h2 id="reviews-heading">Reviews</h2>
                <?= renderStars($eventRating['average'], $eventRating['count']) ?>

                <?php if (empty($displayedReviews)): ?>
                    <?php if ($eventFinished): ?>
                        <p>No reviews yet.</p>
                    <?php else: ?>
                        <p>No reviews yet. Reviews open after the event.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="review-list">
                        <?php foreach ($displayedReviews as $review): ?>
                            <article class="review-card">
                                <p class="review-meta">
                                    <strong><?= e($review['reviewer_first_name']) ?></strong>

                                    <span aria-hidden="true">
                                        <?= str_repeat('&#9733;', (int) $review['rating']) .
                                            str_repeat('&#9734;', 5 - (int) $review['rating']) ?>
                                    </span>

                                    Rated <?= (int) $review['rating'] ?> out of 5
                                    &middot;
                                    <?= e(formatEventDate($review['created_at'])) ?>
                                </p>

                                <p><?= nl2br(e($review['comment'])) ?></p>

                                <?php if (isLoggedIn() && (int) $review['user_id'] === (int) currentUserId()): ?>
                                    <div class="review-actions">

                                        <a class="btn btn-small"
                                        href="<?= BASE_URL ?>/review-form.php?event_id=<?= (int) $event['id'] ?>">
                                            Edit review
                                        </a>

                                        <form method="post"
                                            action="<?= BASE_URL ?>/review-delete.php"
                                            class="inline-form"
                                            data-confirm="Delete your review? This cannot be undone.">

                                            <?= csrfField() ?>

                                            <input type="hidden"
                                                name="event_id"
                                                value="<?= (int) $event['id'] ?>">

                                            <button type="submit" class="btn btn-small btn-danger">
                                                Delete review
                                            </button>

                                        </form>

                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($hasMoreReviews): ?>
                        <p><a href="<?= BASE_URL ?>/event.php?slug=<?= e($event['slug']) ?>&amp;all_reviews=1#reviews-heading">Show all <?= (int) $eventRating['count'] ?> reviews</a></p>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!isLoggedIn()): ?>

                    <p>
                        <a class="btn" href="<?= BASE_URL ?>/login.php">
                            Log in to review this event after attending
                        </a>
                    </p>

                    <?php elseif ($existingReview === null && $canReview): ?>

                    <p>
                        <a class="btn"
                        href="<?= BASE_URL ?>/review-form.php?event_id=<?= (int) $event['id'] ?>">
                            Write a review
                        </a>
                    </p>

                <?php endif; ?>
            </section>
        </div>

        <aside class="event-sidebar">
            <div class="detail-block">
                <p class="detail-block-label">Share</p>
                <div class="share-row" aria-label="Share this event">
                    <a class="share-link" href="<?= e($facebookShareUrl) ?>" target="_blank" rel="noopener">
                        Facebook<span class="sr-only"> (opens in a new tab)</span>
                    </a>
                    <a class="share-link" href="<?= e($twitterShareUrl) ?>" target="_blank" rel="noopener">
                        X<span class="sr-only"> (opens in a new tab)</span>
                    </a>
                </div>
                <span class="share-copy-wrap">
                    <label for="share-url" class="sr-only">Event link</label>
                    <input type="text" id="share-url" class="share-url-input" readonly value="<?= e($pageUrl) ?>">
                    <button type="button" class="btn btn-secondary share-copy-btn" data-copy-target="share-url">
                        Copy link
                    </button>
                </span>
            </div>

            <div class="detail-block">
                <p class="detail-block-label">Where</p>
                <p class="detail-block-value">
                    <?= e($event['venue_name']) ?><br>
                    <?= e($event['address']) ?>, <?= e($event['suburb']) ?> <?= e($event['postcode']) ?>
                </p>
                <a class="btn btn-secondary" href="<?= e($directionsUrl) ?>" target="_blank" rel="noopener">
                    Get directions<span class="sr-only"> (opens in a new tab)</span>
                </a>
            </div>

            <?php
                // Omit this whole block when the organiser has none of
                // these three set, rather than showing an almost-empty
                // card - the "Organised by" line near the title already
                // credits them by name regardless.
                $hasOrganiserLinks = $event['website'] || $event['facebook'] || $event['instagram'];
            ?>
            <?php if ($hasOrganiserLinks): ?>
                <div class="detail-block">
                    <p class="detail-block-label">Organiser</p>
                    <p class="detail-block-value"><?= e($event['organiser_name']) ?></p>
                    <div class="share-row" aria-label="<?= e($event['organiser_name']) ?>&rsquo;s links">
                        <?php if ($event['website']): ?>
                            <a class="share-link" href="<?= e($event['website']) ?>" target="_blank" rel="noopener">
                                Website<span class="sr-only"> (opens in a new tab)</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($event['facebook']): ?>
                            <a class="share-link" href="<?= e($event['facebook']) ?>" target="_blank" rel="noopener">
                                Facebook<span class="sr-only"> (opens in a new tab)</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($event['instagram']): ?>
                            <a class="share-link" href="<?= e($event['instagram']) ?>" target="_blank" rel="noopener">
                                Instagram<span class="sr-only"> (opens in a new tab)</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</article>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
