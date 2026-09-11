<?php
// ============================================================
// review-delete.php
// Deletes the logged-in user's own review.
// Reviews belonging to other users cannot be deleted here.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/events.php');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid request. Please try again.');
    redirect('/events.php');
}

$eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

if ($eventId <= 0) {
    setFlash('error', 'Invalid event.');
    redirect('/events.php');
}


// Find the event first so we know where to redirect afterwards.
$stmt = $pdo->prepare(
    "SELECT id, slug
     FROM events
     WHERE id = :eventId"
);

$stmt->execute([
    'eventId' => $eventId
]);

$event = $stmt->fetch();

if ($event === false) {
    setFlash('error', 'Event not found.');
    redirect('/events.php');
}


// Delete only the review belonging to the currently logged-in user.
$stmt = $pdo->prepare(
    "DELETE FROM reviews
     WHERE event_id = :eventId
     AND user_id = :userId"
);

$stmt->execute([
    'eventId' => $eventId,
    'userId' => currentUserId()
]);

if ($stmt->rowCount() > 0) {
    setFlash('success', 'Your review has been deleted.');
} else {
    setFlash('error', 'Review not found.');
}

redirect('/event.php?slug=' . urlencode($event['slug']));