<?php
// ============================================================
// register-for-event.php
// Books the logged-in user a place at an event. POST only. The six
// checks from CLAUDE.md section 7 run in order, each with its own
// message, plus two more added for Feature C (ticket quantity); the
// database's unique constraints are still trusted as the final safety
// net against a race slipping through two requests submitted at
// almost the same moment.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/events.php');
}

// 1. User must be logged in.
requireLogin();

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Your session expired. Please try again.');
    redirect('/events.php');
}

$eventId = (int) ($_POST['event_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM events WHERE id = :id");
$stmt->execute(['id' => $eventId]);
$event = $stmt->fetch();

// 2. Event must exist.
if ($event === false) {
    setFlash('error', 'That event could not be found.');
    redirect('/events.php');
}

$eventUrl = '/event.php?slug=' . urlencode($event['slug']);

// 3. Event must be published.
if ($event['status'] !== 'published') {
    setFlash('error', 'This event is not open for registration.');
    redirect($eventUrl);
}

// 4. Event must not have already started.
if (isPastDatetime($event['start_datetime'])) {
    setFlash('error', 'This event has already started.');
    redirect($eventUrl);
}

// 5. The user must not already hold an active registration.
$stmt = $pdo->prepare(
    "SELECT id, status, booking_reference
     FROM registrations
     WHERE user_id = :userId AND event_id = :eventId"
);
$stmt->execute([
    'userId'  => currentUserId(),
    'eventId' => $eventId
]);

$existingRegistration = $stmt->fetch();

if ($existingRegistration !== false && $existingRegistration['status'] !== 'cancelled') {
    setFlash('error', 'You are already registered for this event.');
    redirect($eventUrl);
}

// 6. There must be at least one free spot. SUM(quantity), not a row
// count - see getRegisteredCount() in functions.php - so a capacity of
// 10 filled by two 5-ticket bookings correctly reads as full here too.
$registeredCount = getRegisteredCount($pdo, $eventId);
$spotsRemaining = (int) $event['capacity'] - $registeredCount;
if ($spotsRemaining <= 0) {
    setFlash('error', 'This event is fully booked.');
    redirect($eventUrl);
}

// 7. Quantity must be a whole number within the per-booking limit.
$maxTickets = getMaxTicketsPerBooking();
$quantity = filter_var($_POST['quantity'] ?? '', FILTER_VALIDATE_INT);
if ($quantity === false || $quantity < 1 || $quantity > $maxTickets) {
    setFlash('error', "Choose between 1 and {$maxTickets} tickets.");
    redirect($eventUrl);
}

// 8. The requested quantity must fit in what is actually left.
if ($quantity > $spotsRemaining) {
    setFlash('error', "Only {$spotsRemaining} place" . ($spotsRemaining === 1 ? '' : 's') . ' left for this event.');
    redirect($eventUrl);
}

$bookingReference = generateBookingReference($pdo);

try {
    if ($existingRegistration !== false && $existingRegistration['status'] === 'cancelled') {

        // Re-activate the previous cancelled registration.
        $stmt = $pdo->prepare(
            "UPDATE registrations
             SET status = 'registered',
                 quantity = :quantity,
                 booking_reference = :ref,
                 registered_at = NOW()
             WHERE id = :id"
        );

        $stmt->execute([
            'quantity' => $quantity,
            'ref'      => $bookingReference,
            'id'       => $existingRegistration['id'],
        ]);

    } else {

        // First-time registration for this event.
        $stmt = $pdo->prepare(
            "INSERT INTO registrations
                (user_id, event_id, status, quantity, booking_reference)
             VALUES
                (:userId, :eventId, 'registered', :quantity, :ref)"
        );

        $stmt->execute([
            'userId'   => currentUserId(),
            'eventId'  => $eventId,
            'quantity' => $quantity,
            'ref'      => $bookingReference,
        ]);
    }
} catch (PDOException $e) {
    // SQLSTATE 23000 is a unique-key race: either uq_user_event
    // (another request for this exact booking won a moment ago) or,
    // astronomically unlikely, uq_booking_reference colliding despite
    // generateBookingReference()'s own uniqueness check. Rather than
    // guess which one happened, look up whatever registration actually
    // exists now and send the user to its real confirmation page - a
    // fabricated link to $bookingReference could point at a booking
    // that was never actually inserted.
    if ($e->getCode() === '23000') {
        $stmt = $pdo->prepare(
            "SELECT booking_reference FROM registrations
             WHERE user_id = :userId AND event_id = :eventId AND status = 'registered'"
        );
        $stmt->execute(['userId' => currentUserId(), 'eventId' => $eventId]);
        $existingReference = $stmt->fetchColumn();

        if ($existingReference !== false) {
            redirect('/booking-confirmation.php?ref=' . urlencode($existingReference));
        }
    }

    error_log('Registration insert failed: ' . $e->getMessage());
    setFlash('error', 'Something went wrong booking this event. Please try again.');
    redirect($eventUrl);
}

// A real confirmation screen, not just a flash message back on the
// event page - booking-confirmation.php re-checks that this reference
// actually belongs to the logged-in user before showing anything.
redirect('/booking-confirmation.php?ref=' . urlencode($bookingReference));
