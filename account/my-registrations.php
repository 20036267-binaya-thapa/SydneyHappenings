<?php
// ============================================================
// account/my-registrations.php ("My tickets")
// Shows the logged-in user's own bookings, split into upcoming (still
// cancellable, shown as ticket-stub cards) and past (a simpler list,
// with a "Write a review" link where they are eligible). Cancelled
// registrations are not shown - they are no longer an active booking.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireLogin();

$stmt = $pdo->prepare(
    "SELECT r.event_id, r.quantity, r.booking_reference,
            e.title, e.slug, e.start_datetime, v.name AS venue_name
     FROM registrations r
     JOIN events e ON e.id = r.event_id
     JOIN venues v ON v.id = e.venue_id
     WHERE r.user_id = :userId AND r.status != 'cancelled' AND e.end_datetime > NOW()
     ORDER BY e.start_datetime ASC"
);
$stmt->execute(['userId' => currentUserId()]);
$upcoming = $stmt->fetchAll();

// existing_review_id comes from a LEFT JOIN rather than a per-row
// canUserReview() call in the loop below - one query for the whole
// past list, not one per row. The same three conditions
// canUserReview() checks (attended, the event has actually finished,
// no review yet) are then evaluated in PHP per row from columns this
// single query already fetched.
$stmt = $pdo->prepare(
    "SELECT r.event_id, r.quantity, r.booking_reference, r.status,
            e.title, e.slug, e.start_datetime, e.end_datetime, v.suburb,
            rv.id AS existing_review_id
     FROM registrations r
     JOIN events e ON e.id = r.event_id
     JOIN venues v ON v.id = e.venue_id
     LEFT JOIN reviews rv ON rv.event_id = e.id AND rv.user_id = r.user_id
     WHERE r.user_id = :userId AND r.status != 'cancelled' AND e.end_datetime <= NOW()
     ORDER BY e.start_datetime DESC"
);
$stmt->execute(['userId' => currentUserId()]);
$past = $stmt->fetchAll();

$pageTitle = 'My Tickets - ' . SITE_NAME;
$pageDescription = 'View and manage the events you have booked on SydneyHappenings.';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>My tickets</h1>
</div>

<section aria-labelledby="upcoming-heading">
    <h2 id="upcoming-heading">Current / Upcoming</h2>
    <?php if (empty($upcoming)): ?>
        <p class="empty-state">You have no upcoming bookings. <a href="<?= BASE_URL ?>/events.php">Browse events</a>.</p>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($upcoming as $booking): ?>
                <article class="ticket-card">
                    <h3><a href="<?= BASE_URL ?>/event.php?slug=<?= urlencode($booking['slug']) ?>"><?= e($booking['title']) ?></a></h3>
                    <p class="event-meta"><?= e(formatEventDate($booking['start_datetime'])) ?> &middot; <?= e($booking['venue_name']) ?></p>
                    <p class="ticket-card-ref">
                        Ref <?= e($booking['booking_reference']) ?> &middot; <?= (int) $booking['quantity'] ?> ticket<?= (int) $booking['quantity'] === 1 ? '' : 's' ?>
                    </p>
                    <div class="ticket-card-actions">
                        <a class="btn btn-secondary btn-small" href="<?= BASE_URL ?>/booking-confirmation.php?ref=<?= urlencode($booking['booking_reference']) ?>">
                            View ticket
                        </a>
                        <?php if (!isPastDatetime($booking['start_datetime'])): ?>

                            <form method="post"
                                action="<?= BASE_URL ?>/cancel-registration.php"
                                data-confirm="Cancel your booking for <?= e($booking['title']) ?>?">

                                <?= csrfField() ?>

                                <input type="hidden"
                                    name="event_id"
                                    value="<?= (int) $booking['event_id'] ?>">

                                <button type="submit" class="btn btn-small btn-danger">
                                    Cancel
                                </button>

                            </form>

                            <?php else: ?>

                            <span class="text-muted">Event started</span>

                            <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section aria-labelledby="past-heading">
    <h2 id="past-heading">Past</h2>
    <?php if (empty($past)): ?>
        <p class="empty-state">You have no past bookings yet.</p>
    <?php else: ?>
        <ul class="past-tickets-list">
            <?php foreach ($past as $booking): ?>
                <?php
                    $canReviewRow = $booking['status'] === 'attended'
                        && isPastDatetime($booking['end_datetime'])
                        && $booking['existing_review_id'] === null;
                ?>
                <li>
                    <a href="<?= BASE_URL ?>/event.php?slug=<?= urlencode($booking['slug']) ?>"><?= e($booking['title']) ?></a>
                    &mdash; <?= e(formatEventDate($booking['start_datetime'])) ?> &middot; <?= e($booking['suburb']) ?>
                    &middot; <?= (int) $booking['quantity'] ?> ticket<?= (int) $booking['quantity'] === 1 ? '' : 's' ?>
                    <?php if ($canReviewRow): ?>
                        &middot; <a href="<?= BASE_URL ?>/review-form.php?event_id=<?= (int) $booking['event_id'] ?>">Write a review</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
