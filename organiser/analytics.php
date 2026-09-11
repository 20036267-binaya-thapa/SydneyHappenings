<?php
// ============================================================
// organiser/analytics.php
// Performance analytics for an organiser's own published events:
// summary stats, a 12-month registrations trend, a sortable
// per-event table, and a breakdown by category.
//
// Access control: a non-admin ALWAYS sees only their own events -
// $selectedOrganiserId is hard-coded to currentUserId() for them and
// never taken from the request, so editing the URL cannot leak
// another organiser's numbers. An admin gets a dropdown and may view
// one specific organiser or the platform-wide total (no filter).
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole(['organiser', 'admin']);

$isAdmin = currentUserRole() === 'admin';

$organisers = [];
if ($isAdmin) {
    // Only users who actually own at least one event - an admin could
    // in principle own events too (event-form.php allows it), so this
    // is not filtered to role = 'organiser'.
    $organisers = $pdo->query(
        "SELECT DISTINCT u.id, u.name FROM users u JOIN events e ON e.organiser_id = u.id ORDER BY u.name ASC"
    )->fetchAll();

    $selectedOrganiserId = isset($_GET['organiser_id']) && $_GET['organiser_id'] !== '' ? (int) $_GET['organiser_id'] : null;
} else {
    // Never read from $_GET for a non-admin - this is the access
    // control, not just the default value.
    $selectedOrganiserId = currentUserId();
}

$organiserFilterSql = $selectedOrganiserId !== null ? ' AND e.organiser_id = :organiserId' : '';
$organiserFilterParams = $selectedOrganiserId !== null ? ['organiserId' => $selectedOrganiserId] : [];

// ---------- Sorting for the per-event table (works with JS off - a
// plain link to this same page with a different ?sort= value) ----------
$sortOptions = [
    'title'      => ['sql' => 'e.title ASC',        'label' => 'Title',       'aria' => 'ascending'],
    'date'       => ['sql' => 'e.start_datetime DESC', 'label' => 'Date',     'aria' => 'descending'],
    'capacity'   => ['sql' => 'e.capacity DESC',     'label' => 'Capacity',    'aria' => 'descending'],
    'registered' => ['sql' => 'registered_count DESC', 'label' => 'Tickets', 'aria' => 'descending'],
    'fill_rate'  => ['sql' => 'fill_rate DESC',      'label' => 'Fill rate',   'aria' => 'descending'],
    'attended'   => ['sql' => 'attended_count DESC', 'label' => 'Attended',    'aria' => 'descending'],
    'no_show'    => ['sql' => 'no_show_count DESC',  'label' => 'No-show',     'aria' => 'descending'],
    'rating'     => ['sql' => 'avg_rating DESC',     'label' => 'Avg. rating', 'aria' => 'descending'],
];
$sortKey = $_GET['sort'] ?? 'date';
if (!array_key_exists($sortKey, $sortOptions)) {
    $sortKey = 'date';
}

/**
 * Builds a link to this page with the given sort key, keeping every
 * other current query parameter (including organiser_id) unchanged.
 * @param string $sortKey
 * @return string
 */
function analyticsSortLink($sortKey) {
    $query = $_GET;
    $query['sort'] = $sortKey;
    return BASE_URL . '/organiser/analytics.php?' . http_build_query($query);
}

// One query for the whole per-event table. Each count is a correlated
// subquery (same style as organiser/my-events.php) rather than a JOIN
// + GROUP BY, since several independent counts are needed per event -
// still one round trip to the database, not one query per row.
// fill_rate repeats the registered_count subquery because a SELECT
// alias cannot be reused by another expression in the same SELECT
// list; NULLIF guards it against a divide-by-zero if capacity were
// ever 0 (the event form already blocks that, but the guard is free).
// registered_count and fill_rate both SUM(quantity) - capacity is a
// ticket count, so a multi-ticket booking must fill more of it than a
// single-ticket one (see getRegisteredCount() in functions.php).
// attended/no_show/cancelled stay as row counts on purpose: they
// describe how many bookings ended up in each state, a distinct
// question from how many tickets were held against capacity.
$stmt = $pdo->prepare(
    "SELECT e.id, e.title, e.slug, e.start_datetime, e.capacity, c.name AS category_name,
            (SELECT COALESCE(SUM(r.quantity), 0) FROM registrations r WHERE r.event_id = e.id AND r.status = 'registered') AS registered_count,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'attended') AS attended_count,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'no_show') AS no_show_count,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'cancelled') AS cancelled_count,
            (SELECT AVG(rv.rating) FROM reviews rv WHERE rv.event_id = e.id AND rv.is_hidden = 0) AS avg_rating,
            (SELECT COUNT(*) FROM reviews rv WHERE rv.event_id = e.id AND rv.is_hidden = 0) AS review_count,
            ((SELECT COALESCE(SUM(r.quantity), 0) FROM registrations r WHERE r.event_id = e.id AND r.status = 'registered') / NULLIF(e.capacity, 0)) * 100 AS fill_rate
     FROM events e
     JOIN categories c ON c.id = e.category_id
     WHERE e.status = 'published'{$organiserFilterSql}
     ORDER BY {$sortOptions[$sortKey]['sql']}"
);
$stmt->execute($organiserFilterParams);
$events = $stmt->fetchAll();

// ---------- Summary cards and the by-category breakdown are both
// derived from the $events array already fetched above, in PHP -
// no extra database round trips, and every division is guarded so an
// organiser with zero events (or zero capacity, in theory) never hits
// a PHP division-by-zero warning. ----------
$totalFillRateSum = 0.0;
$fillRateEventCount = 0;
$totalAttended = 0;
$totalNoShow = 0;
$totalCancelled = 0;
$totalRegistrationAttempts = 0;
$categoryStats = [];

foreach ($events as $event) {
    if ($event['fill_rate'] !== null) {
        $totalFillRateSum += (float) $event['fill_rate'];
        $fillRateEventCount++;
    }

    $registered = (int) $event['registered_count'];
    $attended = (int) $event['attended_count'];
    $noShow = (int) $event['no_show_count'];
    $cancelled = (int) $event['cancelled_count'];

    $totalAttended += $attended;
    $totalNoShow += $noShow;
    $totalCancelled += $cancelled;
    $totalRegistrationAttempts += $registered + $attended + $noShow + $cancelled;

    $categoryName = $event['category_name'];
    if (!isset($categoryStats[$categoryName])) {
        $categoryStats[$categoryName] = ['event_count' => 0, 'total_registrations' => 0, 'fill_rate_sum' => 0.0, 'fill_rate_count' => 0];
    }
    $categoryStats[$categoryName]['event_count']++;
    $categoryStats[$categoryName]['total_registrations'] += $registered;
    if ($event['fill_rate'] !== null) {
        $categoryStats[$categoryName]['fill_rate_sum'] += (float) $event['fill_rate'];
        $categoryStats[$categoryName]['fill_rate_count']++;
    }
}

$avgFillRate = $fillRateEventCount > 0 ? $totalFillRateSum / $fillRateEventCount : null;
$attendanceRate = ($totalAttended + $totalNoShow) > 0 ? ($totalAttended / ($totalAttended + $totalNoShow)) * 100 : null;
$cancellationRate = $totalRegistrationAttempts > 0 ? ($totalCancelled / $totalRegistrationAttempts) * 100 : null;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM events e WHERE e.status = 'published'{$organiserFilterSql}");
$stmt->execute($organiserFilterParams);
$totalEventsPublished = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(r.quantity), 0) FROM registrations r JOIN events e ON e.id = r.event_id
     WHERE r.status = 'registered'{$organiserFilterSql}"
);
$stmt->execute($organiserFilterParams);
$totalRegistrations = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT AVG(rv.rating) AS avg_rating, COUNT(rv.id) AS review_count
     FROM reviews rv JOIN events e ON e.id = rv.event_id
     WHERE rv.is_hidden = 0{$organiserFilterSql}"
);
$stmt->execute($organiserFilterParams);
$ratingSummary = $stmt->fetch();

// ---------- Registrations over the last 12 months, GROUP BY
// DATE_FORMAT(registered_at, '%Y-%m'). This counts every registration
// row created in that window regardless of its current status - it is
// a signup-activity trend, not a count of currently-active places
// (that is what the "Total registrations" card above is for). Months
// with no rows do not come back from the query at all, so the full
// 12-month range is pre-filled with zero first and the query results
// are merged on top - a bar chart with silent gaps would be misleading. ----------
$monthlyRegistrations = [];
for ($i = 11; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime("-{$i} months"));
    $monthlyRegistrations[$monthKey] = 0;
}

$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(r.registered_at, '%Y-%m') AS month, COUNT(*) AS registration_count
     FROM registrations r
     JOIN events e ON e.id = r.event_id
     WHERE r.registered_at >= DATE_SUB(NOW(), INTERVAL 11 MONTH){$organiserFilterSql}
     GROUP BY DATE_FORMAT(r.registered_at, '%Y-%m')"
);
$stmt->execute($organiserFilterParams);
foreach ($stmt->fetchAll() as $row) {
    if (isset($monthlyRegistrations[$row['month']])) {
        $monthlyRegistrations[$row['month']] = (int) $row['registration_count'];
    }
}
// Bar widths are relative to the busiest month in the window; floored
// at 1 so a chart with zero registrations everywhere never divides by
// zero (every bar just renders at 0% width in that case).
$maxMonthlyRegistrations = max(1, max($monthlyRegistrations));

$pageTitle = 'Analytics - ' . SITE_NAME;
$pageDescription = 'Performance analytics for your published events.';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Analytics</h1>
</div>

<?php if ($isAdmin): ?>
    <form method="get" action="<?= BASE_URL ?>/organiser/analytics.php" class="filter-form">
        <div class="form-field">
            <label for="organiser_id">Organiser</label>
            <select id="organiser_id" name="organiser_id">
                <option value="">All organisers (platform-wide)</option>
                <?php foreach ($organisers as $organiser): ?>
                    <option value="<?= (int) $organiser['id'] ?>" <?= $selectedOrganiserId === (int) $organiser['id'] ? 'selected' : '' ?>>
                        <?= e($organiser['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn">View</button>
    </form>
<?php endif; ?>

<ul class="stat-grid">
    <li class="stat-card">
        <div class="stat-label">Published events</div>
        <div class="stat-value"><?= $totalEventsPublished ?></div>
    </li>
    <li class="stat-card">
        <div class="stat-label">Total tickets booked</div>
        <div class="stat-value"><?= $totalRegistrations ?></div>
    </li>
    <li class="stat-card">
        <div class="stat-label">Average fill rate</div>
        <div class="stat-value"><?= $avgFillRate !== null ? round($avgFillRate) . '%' : '&mdash;' ?></div>
        <div class="stat-sublabel">Tickets booked against capacity</div>
    </li>
    <li class="stat-card">
        <div class="stat-label">Attendance rate</div>
        <div class="stat-value"><?= $attendanceRate !== null ? round($attendanceRate) . '%' : '&mdash;' ?></div>
        <div class="stat-sublabel">Of past events with attendance marked</div>
    </li>
    <li class="stat-card">
        <div class="stat-label">Cancellation rate</div>
        <div class="stat-value"><?= $cancellationRate !== null ? round($cancellationRate) . '%' : '&mdash;' ?></div>
    </li>
    <li class="stat-card">
        <div class="stat-label">Average rating</div>
        <div class="stat-value">
            <?= $ratingSummary['avg_rating'] !== null ? number_format((float) $ratingSummary['avg_rating'], 1) : '&mdash;' ?>
        </div>
        <div class="stat-sublabel"><?= (int) $ratingSummary['review_count'] ?> review<?= (int) $ratingSummary['review_count'] === 1 ? '' : 's' ?></div>
    </li>
</ul>

<?php if (empty($events)): ?>
    <p class="empty-state">
        No published events yet<?= $isAdmin && $selectedOrganiserId !== null ? ' for this organiser' : '' ?>.
        <?php if (!$isAdmin): ?><a href="<?= BASE_URL ?>/organiser/event-form.php">Create your first event</a>.<?php endif; ?>
    </p>
<?php else: ?>

    <section class="analytics-section" aria-labelledby="trend-heading">
        <h2 id="trend-heading">Registrations over the last 12 months</h2>
        <div class="bar-chart">
            <?php foreach ($monthlyRegistrations as $monthKey => $count): ?>
                <div class="bar-row">
                    <div class="bar-label"><?= e(date('M Y', strtotime($monthKey . '-01'))) ?></div>
                    <div class="bar-bar">
                        <div class="bar-track">
                            <div class="bar-fill" style="width: <?= round(($count / $maxMonthlyRegistrations) * 100) ?>%"></div>
                        </div>
                        <div class="bar-value"><?= $count ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="analytics-section" aria-labelledby="performance-heading">
        <h2 id="performance-heading">Performance by event</h2>
        <p>
            <a class="btn btn-secondary" href="<?= BASE_URL ?>/organiser/analytics-export.php<?= $isAdmin && $selectedOrganiserId !== null ? '?organiser_id=' . $selectedOrganiserId : '' ?>">
                Export CSV
            </a>
        </p>
        <div class="data-table-wrapper">
            <table class="data-table">
                <caption>Your published events, sorted by <?= e(strtolower($sortOptions[$sortKey]['label'])) ?></caption>
                <thead>
                    <tr>
                        <?php foreach ($sortOptions as $key => $option): ?>
                            <th scope="col" <?= $sortKey === $key ? 'aria-sort="' . $option['aria'] . '"' : '' ?>>
                                <a href="<?= e(analyticsSortLink($key)) ?>"><?= e($option['label']) ?></a>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><a href="<?= BASE_URL ?>/event.php?slug=<?= urlencode($event['slug']) ?>"><?= e($event['title']) ?></a></td>
                            <td><?= e(formatEventDate($event['start_datetime'])) ?></td>
                            <td><?= (int) $event['capacity'] ?></td>
                            <td><?= (int) $event['registered_count'] ?></td>
                            <td><?= $event['fill_rate'] !== null ? round((float) $event['fill_rate']) . '%' : '&mdash;' ?></td>
                            <td><?= (int) $event['attended_count'] ?></td>
                            <td><?= (int) $event['no_show_count'] ?></td>
                            <td>
                                <?= renderStars(
                                    $event['avg_rating'] !== null ? (float) $event['avg_rating'] : null,
                                    (int) $event['review_count']
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="analytics-section" aria-labelledby="category-heading">
        <h2 id="category-heading">Performance by category</h2>
        <div class="data-table-wrapper">
            <table class="data-table data-table--stack">
                <caption>Events, tickets booked and fill rate by category</caption>
                <thead>
                    <tr>
                        <th scope="col">Category</th>
                        <th scope="col">Events</th>
                        <th scope="col">Tickets booked</th>
                        <th scope="col">Average fill rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoryStats as $categoryName => $stats): ?>
                        <?php $categoryFillRate = $stats['fill_rate_count'] > 0 ? $stats['fill_rate_sum'] / $stats['fill_rate_count'] : null; ?>
                        <tr>
                            <td data-label="Category"><?= e($categoryName) ?></td>
                            <td data-label="Events"><?= $stats['event_count'] ?></td>
                            <td data-label="Tickets booked"><?= $stats['total_registrations'] ?></td>
                            <td data-label="Average fill rate"><?= $categoryFillRate !== null ? round($categoryFillRate) . '%' : '&mdash;' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
