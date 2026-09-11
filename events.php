<?php
// ============================================================
// events.php
// Public, filterable, paginated list of published events, split into
// an "Upcoming" tab (the original default) and a "Past events" tab.
// Past events are shown because reviews can only ever be left for an
// event that has already happened (see canUserReview() in
// functions.php) - hiding every past event unconditionally meant
// there was nowhere a rated event could ever appear, so "Top rated"
// sorting had nothing meaningful to sort.
// Every filter is read from $_GET so the page can be bookmarked or
// shared with the filters already applied, and pagination/tab links
// carry the same filters forward.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';

// Events remain in Upcoming while they are still running.
// They move to Past only after their end time has passed.
$when = ($_GET['when'] ?? '') === 'past' ? 'past' : 'upcoming';

$keyword = trim($_GET['keyword'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');
$suburb = trim($_GET['suburb'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$freeOnly = isset($_GET['free_only']);
// Logged out visitors have no wishlist to filter by, so the checkbox
// is never shown to them and this always resolves to false even if
// someone adds ?saved_only=1 to the URL by hand.
$savedOnly = isLoggedIn() && isset($_GET['saved_only']);
$sort = ($_GET['sort'] ?? '') === 'top_rated' ? 'top_rated' : 'date';

// Fetched once, up here rather than after the main query below, so it
// is available both for the "Saved only" filter condition and for
// marking each card's heart icon further down - never call
// getUserWishlistIds() (or any per-card query) inside the card loop.
$wishlistIds = isLoggedIn() ? getUserWishlistIds($pdo, currentUserId()) : [];

// How many filters are currently active, shown on the mobile "Filters"
// toggle button so a collapsed bar still hints at what is applied.
$activeFilterCount = 0;
foreach ([$keyword, $categorySlug, $suburb, $dateFrom, $dateTo] as $activeValue) {
    if ($activeValue !== '') {
        $activeFilterCount++;
    }
}
if ($freeOnly) {
    $activeFilterCount++;
}
if ($savedOnly) {
    $activeFilterCount++;
}

$perPage = 9;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// Build the WHERE clause piece by piece so only the filters actually
// supplied are added to the query - an empty filter is simply skipped.
$conditions = ["e.status = 'published'"];
$conditions[] = $when === 'past' ? "e.end_datetime <= NOW()" : "e.end_datetime > NOW()";
$params = [];

if ($keyword !== '') {
    // Two distinctly-named placeholders bound to the same value, not
    // :keyword reused twice - this server's native (non-emulated)
    // prepares do not reliably support binding one named placeholder
    // to two positions in the same query (same class of issue as
    // canUserReview() in functions.php), and threw SQLSTATE[HY093]
    // "Invalid parameter number" on every keyword search.
    $conditions[] = "(e.title LIKE :keywordTitle OR e.description LIKE :keywordDescription)";
    $params['keywordTitle'] = '%' . $keyword . '%';
    $params['keywordDescription'] = '%' . $keyword . '%';
}
if ($categorySlug !== '') {
    $conditions[] = "c.slug = :categorySlug";
    $params['categorySlug'] = $categorySlug;
}
if ($suburb !== '') {
    $conditions[] = "v.suburb LIKE :suburb";
    $params['suburb'] = '%' . $suburb . '%';
}
if ($dateFrom !== '' && validateDatetime($dateFrom, 'Date from') === null) {
    $conditions[] = "e.start_datetime >= :dateFrom";
    $params['dateFrom'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '' && validateDatetime($dateTo, 'Date to') === null) {
    $conditions[] = "e.start_datetime <= :dateTo";
    $params['dateTo'] = $dateTo . ' 23:59:59';
}
if ($freeOnly) {
    $conditions[] = "e.price = 0";
}
if ($savedOnly) {
    if (empty($wishlistIds)) {
        // Nothing saved yet - short-circuit to zero rows rather than
        // running an "IN ()" query, which is invalid SQL.
        $conditions[] = "1 = 0";
    } else {
        // One distinctly-named placeholder per saved event id, built
        // the same way as the keyword fix above - never string-build
        // the values themselves into the query.
        $savedPlaceholders = [];
        foreach ($wishlistIds as $index => $savedId) {
            $key = 'savedId' . $index;
            $savedPlaceholders[] = ':' . $key;
            $params[$key] = $savedId;
        }
        $conditions[] = "e.id IN (" . implode(', ', $savedPlaceholders) . ")";
    }
}

$whereSql = implode(' AND ', $conditions);

// Count how many rows match, so we know how many pages there are.
$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM events e
     JOIN categories c ON c.id = e.category_id
     JOIN venues v ON v.id = e.venue_id
     WHERE {$whereSql}"
);
$countStmt->execute($params);
$totalEvents = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalEvents / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// "Top rated" sorts by the average of visible reviews, with unrated
// events pushed to the end rather than sorted as if they scored zero.
// "AVG(r.rating) IS NULL" is 0 for a rated event and 1 for an unrated
// one, so ordering by it ascending puts the unrated events last;
// start_datetime is still the tie-breaker either way.
//
// The first operand repeats the actual AVG(r.rating) expression rather
// than reusing the avg_rating alias - MySQL allows an aggregate alias
// as a bare ORDER BY key (avg_rating DESC below is fine) but rejects
// it as an operand inside another expression like IS NULL alongside
// GROUP BY, throwing SQLSTATE[42S22] error 1247 ("reference to group
// function"). Repeating the aggregate call sidesteps that restriction.
// Past events sort soonest-finished-last by default (most recent
// first) rather than soonest-first, which would bury the events that
// just happened - and therefore have the newest reviews - at the end.
$dateDirection = $when === 'past' ? 'DESC' : 'ASC';
$orderBySql = $sort === 'top_rated'
    ? "ORDER BY AVG(r.rating) IS NULL, avg_rating DESC, e.start_datetime {$dateDirection}"
    : "ORDER BY e.start_datetime {$dateDirection}";

// LIMIT/OFFSET cannot be bound the same way as other values on every
// PDO driver, so they are cast to int and inserted directly - safe
// here because they came from (int) casts above, never raw user input.
//
// The rating average and count come from this same query via a LEFT
// JOIN to reviews and a GROUP BY, rather than a separate
// getEventRating() call per card in the loop below - one query for
// the whole page of (up to 9) cards, not one per card.
$sql = "SELECT e.*, c.name AS category_name, v.suburb,
               AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count
        FROM events e
        JOIN categories c ON c.id = e.category_id
        JOIN venues v ON v.id = e.venue_id
        LEFT JOIN reviews r ON r.event_id = e.id AND r.is_hidden = 0
        WHERE {$whereSql}
        GROUP BY e.id, c.name, v.suburb
        {$orderBySql}
        LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$categories = getActiveCategories($pdo);

/**
 * Builds a query string for a pagination link, keeping the current
 * filters and only changing the page number.
 * @param int $targetPage
 * @return string
 */
function eventsPageLink($targetPage) {
    $query = $_GET;
    $query['page'] = $targetPage;
    return BASE_URL . '/events.php?' . http_build_query($query);
}

/**
 * Builds a link to switch between the "Upcoming" and "Past events"
 * tabs, keeping every other filter but resetting back to page 1 -
 * the current page number almost never makes sense in the other tab,
 * which has a different number of matching events.
 * @param string $targetWhen "upcoming" or "past".
 * @return string
 */
function eventsWhenLink($targetWhen) {
    $query = $_GET;
    $query['when'] = $targetWhen;
    unset($query['page']);
    return BASE_URL . '/events.php?' . http_build_query($query);
}

$pageTitle = 'Browse Events - ' . SITE_NAME;
$pageDescription = 'Search and filter community and cultural events across Sydney by category, suburb, date and price.';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Browse Events</h1>
</div>

<nav class="events-tabs" aria-label="Event timeframe">
    <a class="events-tab <?= $when === 'upcoming' ? 'is-active' : '' ?>"
       href="<?= e(eventsWhenLink('upcoming')) ?>"
       aria-current="<?= $when === 'upcoming' ? 'page' : 'false' ?>">Current / Upcoming</a>
    <a class="events-tab <?= $when === 'past' ? 'is-active' : '' ?>"
       href="<?= e(eventsWhenLink('past')) ?>"
       aria-current="<?= $when === 'past' ? 'page' : 'false' ?>">Past events</a>
</nav>

<details class="filter-bar-details">
    <summary class="btn btn-secondary filter-bar-summary">
        Filters<?= $activeFilterCount > 0 ? ' (' . $activeFilterCount . ')' : '' ?>
    </summary>

    <form method="get" action="<?= BASE_URL ?>/events.php" class="filter-bar">
        <input type="hidden" name="when" value="<?= e($when) ?>">
        <div class="filter-bar-field filter-bar-search">
            <label for="keyword">Things to do</label>
            <input type="search" id="keyword" name="keyword" value="<?= e($keyword) ?>" placeholder="Search events&hellip;">
        </div>

        <div class="filter-bar-field">
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category['slug']) ?>" <?= $categorySlug === $category['slug'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-bar-field">
            <label for="suburb">Where</label>
            <input type="text" id="suburb" name="suburb" value="<?= e($suburb) ?>" placeholder="Any suburb">
        </div>

        <fieldset class="filter-bar-field filter-bar-when">
            <legend>When</legend>
            <div class="filter-bar-when-inputs">
                <label for="date_from" class="sr-only">From</label>
                <input type="date" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
                <label for="date_to" class="sr-only">To</label>
                <input type="date" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
            </div>
        </fieldset>

        <div class="filter-bar-field filter-bar-toggle checkbox-field">
            <input type="checkbox" id="free_only" name="free_only" value="1" <?= $freeOnly ? 'checked' : '' ?>>
            <label for="free_only">Free only</label>
        </div>

        <?php if (isLoggedIn()): ?>
            <div class="filter-bar-field filter-bar-toggle checkbox-field">
                <input type="checkbox" id="saved_only" name="saved_only" value="1" <?= $savedOnly ? 'checked' : '' ?>>
                <label for="saved_only">Saved only</label>
            </div>
        <?php endif; ?>

        <div class="filter-bar-actions">
            <button type="submit" class="btn">Search</button>
            <a href="<?= BASE_URL ?>/events.php" class="filter-bar-reset">Reset</a>
        </div>
    </form>
</details>

<div class="results-row">
    <p class="results-count">
        <?= $totalEvents ?> event<?= $totalEvents === 1 ? '' : 's' ?> found.
    </p>

    <form method="get" action="<?= BASE_URL ?>/events.php" class="sort-form">
        <input type="hidden" name="keyword" value="<?= e($keyword) ?>">
        <input type="hidden" name="category" value="<?= e($categorySlug) ?>">
        <input type="hidden" name="suburb" value="<?= e($suburb) ?>">
        <input type="hidden" name="date_from" value="<?= e($dateFrom) ?>">
        <input type="hidden" name="date_to" value="<?= e($dateTo) ?>">
        <?php if ($freeOnly): ?><input type="hidden" name="free_only" value="1"><?php endif; ?>
        <?php if ($savedOnly): ?><input type="hidden" name="saved_only" value="1"><?php endif; ?>
        <input type="hidden" name="when" value="<?= e($when) ?>">
        <label for="sort" class="sr-only">Sort by</label>
        <select id="sort" name="sort">
            <option value="date" <?= $sort === 'date' ? 'selected' : '' ?>><?= $when === 'past' ? 'Most recent first' : 'Soonest first' ?></option>
            <option value="top_rated" <?= $sort === 'top_rated' ? 'selected' : '' ?>>Top rated</option>
        </select>
        <button type="submit" class="btn btn-secondary">Apply</button>
    </form>
</div>

<?php if (empty($events)): ?>
    <p class="empty-state">
        <?php if ($when === 'past'): ?>
            No past events match your search.
        <?php else: ?>
            No events match your search.
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/events.php">Clear all filters</a> and try again.
    </p>
<?php else: ?>
    <div class="card-grid">
        <?php foreach ($events as $event): ?>
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

    <?php if ($totalPages > 1): ?>
        <ul class="pagination" aria-label="Event list pages">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li>
                    <?php if ($i === $page): ?>
                        <span class="current" aria-current="page"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= e(eventsPageLink($i)) ?>">Page <?= $i ?></a>
                    <?php endif; ?>
                </li>
            <?php endfor; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
