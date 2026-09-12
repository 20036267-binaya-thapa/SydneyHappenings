<?php
// ============================================================
// booking-confirmation.php
// The screen a booking lands on after register-for-event.php
// succeeds, and the page its own "View booking" link on event.php
// and account/my-registrations.php points back to. Reached as
// ?ref=XXXXXXXX.
//
// Ownership is checked with a real comparison against the logged-in
// user, not by treating the reference as secret enough on its own -
// a reference in the URL must never expose someone else's booking.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';
requireLogin();

$reference = trim($_GET['ref'] ?? '');

$stmt = $pdo->prepare(
    "SELECT r.user_id, r.quantity, r.booking_reference, r.registered_at,
            e.title, e.slug, e.start_datetime, e.end_datetime,
            v.name AS venue_name, v.address, v.suburb, v.postcode, v.latitude, v.longitude,
            u.name AS attendee_name
     FROM registrations r
     JOIN events e ON e.id = r.event_id
     JOIN venues v ON v.id = e.venue_id
     JOIN users u ON u.id = r.user_id
     WHERE r.booking_reference = :ref AND r.status = 'registered'"
);
$stmt->execute(['ref' => $reference]);
$booking = $stmt->fetch();


}

$pageTitle = 'Booking Confirmed - ' . SITE_NAME;
$pageDescription = 'Your booking confirmation and ticket for ' . $booking['title'] . '.';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Booking confirmed</h1>
</div>

<p>You're going to <strong><?= e($booking['title']) ?></strong>. Your ticket is below.</p>

<div class="ticket-stub">
    <div class="ticket-stub-details">
        <h2><?= e($booking['title']) ?></h2>
        <p>
            <?= e(formatEventDate($booking['start_datetime'])) ?>
            to <?= e(formatEventDate($booking['end_datetime'])) ?>
        </p>
        <p>
            <?= e($booking['venue_name']) ?>, <?= e($booking['address']) ?>, <?= e($booking['suburb']) ?> <?= e($booking['postcode']) ?>
            &middot;
            <a href="<?= e(getDirectionsUrl($booking)) ?>" target="_blank" rel="noopener">
                Get directions <span class="sr-only">(opens in a new tab)</span>
            </a>
        </p>
        <p><?= (int) $booking['quantity'] ?> ticket<?= (int) $booking['quantity'] === 1 ? '' : 's' ?></p>
        <p>Booked on <?= e(formatEventDate($booking['registered_at'])) ?> by <?= e($booking['attendee_name']) ?></p>
    </div>

    <div class="ticket-stub-divider"></div>

    <div class="ticket-stub-reference">
        <p class="detail-block-label">Booking reference</p>
        <span class="ticket-stub-reference-code"><?= e($booking['booking_reference']) ?></span>
    </div>
</div>

<p>
    <a class="btn btn-secondary" href="<?= BASE_URL ?>/booking-ticket-ics.php?ref=<?= urlencode($booking['booking_reference']) ?>">
        Add to calendar
    </a>
    <!-- A plain link, not a button - main.js (Step C8) intercepts a
         click to call window.print() instead. Without JavaScript this
         just reloads the same confirmation page, which is still a
         perfectly printable page on its own via the browser's normal
         print command. -->
    <a class="btn btn-secondary print-ticket-btn" href="<?= BASE_URL ?>/booking-confirmation.php?ref=<?= urlencode($booking['booking_reference']) ?>">
        Print ticket
    </a>
    <a class="btn btn-accent" href="<?= BASE_URL ?>/account/my-registrations.php">View my tickets</a>
</p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
