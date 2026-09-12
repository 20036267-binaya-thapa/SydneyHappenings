<?php
// ============================================================
// organiser/dashboard.php
// Landing page for organisers (and admins, who can see everything):
// quick metrics about the logged-in user's own events, and links to
// the other organiser pages.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole(['organiser', 'admin']);

$organiserId = currentUserId();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE organiser_id = :id");
$stmt->execute(['id' => $organiserId]);
$totalEvents = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM events WHERE organiser_id = :id AND status = 'published' AND start_datetime > NOW()"
);
$stmt->execute(['id' => $organiserId]);
$upcomingEvents = (int) $stmt->fetchColumn();

// Tickets, not booking rows - a 4-ticket booking counts as 4 here,
// the same SUM(quantity) convention getRegisteredCount() uses.
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(r.quantity), 0) FROM registrations r
     JOIN events e ON e.id = r.event_id
     WHERE e.organiser_id = :id AND r.status = 'registered'"
);
$stmt->execute(['id' => $organiserId]);
$totalRegistrations = (int) $stmt->fetchColumn();

// Average rating and review count across all of this organiser's
// events, from visible reviews only - one query, not one per event.
$stmt = $pdo->prepare(
    "SELECT AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count
     FROM reviews r
     JOIN events e ON e.id = r.event_id
     WHERE e.organiser_id = :id AND r.is_hidden = 0"
);
$stmt->execute(['id' => $organiserId]);
$reviewSummary = $stmt->fetch();

$stmt = $pdo->prepare(
    "SELECT r.*, e.title AS event_title, e.slug AS event_slug,
            SUBSTRING_INDEX(u.name, ' ', 1) AS reviewer_first_name
     FROM reviews r
     JOIN events e ON e.id = r.event_id
     JOIN users u ON u.id = r.user_id
     WHERE e.organiser_id = :id AND r.is_hidden = 0
     ORDER BY r.created_at DESC
     LIMIT 3"
);
$stmt->execute(['id' => $organiserId]);
$recentReviews = $stmt->fetchAll();

$pageTitle = 'Organiser Dashboard - ' . SITE_NAME;
$pageDescription = 'Manage your published events and view attendee numbers.';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Organiser Dashboard</h1>

<div class="metric-cards">
    <div class="metric-card">
        <div class="metric-number"><?= $totalEvents ?></div>
        <div>Total events</div>
    </div>
    <div class="metric-card">
        <div class="metric-number"><?= $upcomingEvents ?></div>
        <div>Upcoming published events</div>
    </div>
    <div class="metric-card">
        <div class="metric-number"><?= $totalRegistrations ?></div>
        <div>Total tickets booked</div>
    </div>
</div>

<div class="button-row">
    <a class="btn" href="<?= BASE_URL ?>/organiser/event-form.php">Create a new event</a>
    <a class="btn btn-secondary" href="<?= BASE_URL ?>/organiser/my-events.php">Manage my events</a>
</div>

<section aria-labelledby="reviews-heading">
    <h2 id="reviews-heading">Reviews</h2>
    <?= renderStars(
        $reviewSummary['avg_rating'] !== null ? (float) $reviewSummary['avg_rating'] : null,
        (int) $reviewSummary['review_count']
    ) ?>

    <?php if (empty($recentReviews)): ?>
        <p class="empty-state">No reviews yet. Reviews appear here once attendees start reviewing your events.</p>
    <?php else: ?>
        <h3>Most recent</h3>
        <div class="review-list">
            <?php foreach ($recentReviews as $review): ?>
                <article class="review-card">
                    <p class="review-meta">
                        <a href="<?= BASE_URL ?>/event.php?slug=<?= urlencode($review['event_slug']) ?>"><?= e($review['event_title']) ?></a>
                        &middot; <strong><?= e($review['reviewer_first_name']) ?></strong>
                        <span aria-hidden="true"><?= str_repeat('&#9733;', (int) $review['rating']) . str_repeat('&#9734;', 5 - (int) $review['rating']) ?></span>
                        Rated <?= (int) $review['rating'] ?> out of 5 &middot; <?= e(formatEventDate($review['created_at'])) ?>
                    </p>
                    <p><?= nl2br(e($review['comment'])) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
