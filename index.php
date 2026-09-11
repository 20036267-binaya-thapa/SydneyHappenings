<?php
// ============================================================
// index.php
// Public home page: hero with a keyword search, three events
// happening this week, browse-by-category tiles, and a prompt to
// publish an event for organisers/admins.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';

// "Happening this week": published events starting between now and
// seven days from now, soonest first, limited to three. The rating
// average and count come from the same query via a LEFT JOIN and
// GROUP BY, rather than a separate getEventRating() call per card -
// one query for three cards instead of four.
$stmt = $pdo->query(
    "SELECT e.*, c.name AS category_name, v.suburb,
            AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count
     FROM events e
     JOIN categories c ON c.id = e.category_id
     JOIN venues v ON v.id = e.venue_id
     LEFT JOIN reviews r ON r.event_id = e.id AND r.is_hidden = 0
     WHERE e.status = 'published'
       AND e.start_datetime BETWEEN NOW() AND (NOW() + INTERVAL 7 DAY)
     GROUP BY e.id, c.name, v.suburb
     ORDER BY e.start_datetime ASC
     LIMIT 3"
);
$thisWeekEvents = $stmt->fetchAll();

// Fetched once before the card loop below - never call
// getUserWishlistIds() (or any per-card query) inside the loop.
// Logged-out visitors get an empty list, so isWishlisted() is always
// false for them without a wasted query.
$wishlistIds = isLoggedIn() ? getUserWishlistIds($pdo, currentUserId()) : [];

$categories = getActiveCategories($pdo);

$pageTitle = SITE_NAME . ' - Community and Cultural Events in Sydney';
$pageDescription = 'Find markets, festivals, workshops and more happening across Sydney. Browse by category or search for what is on this week.';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <h1>What's on in Sydney</h1>
    <p>Browse community and cultural events near you, or publish your own.</p>

    <form method="get" action="<?= BASE_URL ?>/events.php" role="search">
        <div class="form-field">
            <label for="keyword">Search events</label>
            <input type="search" id="keyword" name="keyword" placeholder="e.g. markets, jazz, workshop">
        </div>
        <button type="submit" class="btn">Search events</button>
    </form>
</section>

<section>
    <h2>Happening this week</h2>
    <?php if (empty($thisWeekEvents)): ?>
        <p class="empty-state">Nothing published for this week yet. <a href="<?= BASE_URL ?>/events.php">Browse all events</a>.</p>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($thisWeekEvents as $event): ?>
                <article class="event-card">
                    <div class="event-card-media">
                        <?php if ($event['image']): ?>
                            <img class="event-card-image" src="<?= BASE_URL ?>/assets/uploads/<?= e($event['image']) ?>"
                                 alt="<?= e($event['title']) ?>" width="400" height="267" loading="lazy">
                        <?php else: ?>
                            <div class="image-placeholder" aria-hidden="true">No image available</div>
                        <?php endif; ?>
                        <span class="badge badge-category event-card-badge"><?= e($event['category_name']) ?></span>
                        <?php if (isLoggedIn()): ?>
                            <?php $isSaved = isWishlisted($wishlistIds, $event['id']); ?>
                            <form method="post" action="<?= BASE_URL ?>/wishlist-toggle.php" class="wishlist-form">
                                <?= csrfField() ?>
                                <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                                <input type="hidden" name="referrer" value="<?= e(currentRequestPath()) ?>">
                                <button type="submit" class="wishlist-btn wishlist-btn--on-card" aria-pressed="<?= $isSaved ? 'true' : 'false' ?>">
                                    <span class="wishlist-heart" aria-hidden="true">&#9829;</span>
                                    <span class="sr-only"><?= $isSaved ? 'Saved' : 'Save event' ?></span>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="event-card-body">
                        <h3><a href="<?= BASE_URL ?>/event.php?slug=<?= urlencode($event['slug']) ?>"><span class="event-card-title-text"><?= e($event['title']) ?></span></a></h3>
                        <p class="event-meta"><?= e(formatEventDate($event['start_datetime'])) ?> &middot; <?= e($event['suburb']) ?></p>
                        <div class="event-card-footer">
                            <?php if ((int) $event['review_count'] > 0): ?>
                                <?= renderStars((float) $event['avg_rating'], (int) $event['review_count']) ?>
                            <?php endif; ?>
                            <span class="event-card-price"><?= e(formatPrice($event['price'])) ?></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section>
    <h2>Browse by category</h2>
    <ul class="card-grid category-tiles">
        <?php foreach ($categories as $category): ?>
            <li class="event-card">
                <div class="event-card-body">
                    <h3><a href="<?= BASE_URL ?>/events.php?category=<?= urlencode($category['slug']) ?>"><?= e($category['name']) ?></a></h3>
                    <?php if ($category['description']): ?>
                        <p><?= e($category['description']) ?></p>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section>
    <?php if (isLoggedIn() && in_array(currentUserRole(), ['organiser', 'admin'], true)): ?>
        <h2>Publish your own event</h2>
        <p><a href="<?= BASE_URL ?>/organiser/event-form.php" class="btn">Create a new event</a></p>
    <?php else: ?>
        <h2>Have an event to share?</h2>
        <p>Independent organisers can publish events on SydneyHappenings.
           <a href="<?= BASE_URL ?>/contact.php">Get in touch</a> to become an organiser.</p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
