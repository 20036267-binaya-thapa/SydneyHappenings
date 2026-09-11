<?php
// ============================================================
// admin/dashboard.php
// Admin landing page: site-wide metric cards and the most popular
// event by registration count.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole('admin');

$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalEvents = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
// Tickets, not booking rows - see getRegisteredCount() in functions.php.
$totalRegistrations = (int) $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM registrations WHERE status = 'registered'")->fetchColumn();
$upcomingPublished = (int) $pdo->query(
    "SELECT COUNT(*) FROM events WHERE status = 'published' AND start_datetime > NOW()"
)->fetchColumn();
$reviewsThisMonth = (int) $pdo->query(
    "SELECT COUNT(*) FROM reviews WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')"
)->fetchColumn();

// "Most popular" by tickets sold, not by number of separate bookings -
// a single 4-ticket booking should outrank four separate 1-ticket ones.
$stmt = $pdo->query(
    "SELECT e.title, e.slug, COALESCE(SUM(r.quantity), 0) AS registration_count
     FROM events e
     LEFT JOIN registrations r ON r.event_id = e.id AND r.status = 'registered'
     GROUP BY e.id
     ORDER BY registration_count DESC
     LIMIT 1"
);
$mostPopular = $stmt->fetch();

$pageTitle = 'Admin Dashboard - ' . SITE_NAME;
$pageDescription = 'Site-wide overview of users, events and registrations.';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Admin Dashboard</h1>

<div class="metric-cards">
    <div class="metric-card">
        <div class="metric-number"><?= $totalUsers ?></div>
        <div>Total users</div>
    </div>
    <div class="metric-card">
        <div class="metric-number"><?= $totalEvents ?></div>
        <div>Total events</div>
    </div>
    <div class="metric-card">
        <div class="metric-number"><?= $upcomingPublished ?></div>
        <div>Upcoming published events</div>
    </div>
    <div class="metric-card">
        <div class="metric-number"><?= $totalRegistrations ?></div>
        <div>Active tickets booked</div>
    </div>
    <div class="metric-card">
        <div class="metric-number"><?= $reviewsThisMonth ?></div>
        <div>Reviews this month</div>
    </div>
</div>

<?php if ($mostPopular && (int) $mostPopular['registration_count'] > 0): ?>
    <h2>Most popular event</h2>
    <p>
        <a href="<?= BASE_URL ?>/event.php?slug=<?= urlencode($mostPopular['slug']) ?>"><?= e($mostPopular['title']) ?></a>
        with <?= (int) $mostPopular['registration_count'] ?> tickets booked.
    </p>
<?php endif; ?>

<p>
    <a class="btn" href="<?= BASE_URL ?>/admin/users.php">Manage users</a>
    <a class="btn" href="<?= BASE_URL ?>/admin/events.php">Manage events</a>
    <a class="btn" href="<?= BASE_URL ?>/admin/categories.php">Manage categories</a>
    <a class="btn" href="<?= BASE_URL ?>/admin/venues.php">Manage venues</a>
    <a class="btn" href="<?= BASE_URL ?>/admin/enquiries.php">View enquiries</a>
</p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
