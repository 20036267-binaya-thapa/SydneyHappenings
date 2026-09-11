<?php
// ============================================================
// cancel-registration.php
// Cancels the logged-in user's own registration for an event. POST
// only. The row is kept and marked 'cancelled' rather than deleted,
// so history is preserved - this also automatically frees the spot,
// because getRegisteredCount() only counts 'registered' rows.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/account/my-registrations.php');
}

requireLogin();

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Your session expired. Please try again.');
    redirect('/account/my-registrations.php');
}

$eventId = (int) ($_POST['event_id'] ?? 0);

// A user can only ever cancel their own registration - the WHERE
// clause checks both the event and the logged-in user's id, so
// editing the event_id in the form cannot cancel someone else's spot.
$stmt = $pdo->prepare(
    "SELECT r.id, e.start_datetime
     FROM registrations r
     JOIN events e ON e.id = r.event_id
     WHERE r.user_id = :userId
     AND r.event_id = :eventId
     AND r.status = 'registered'"
);

$stmt->execute([
    'userId' => currentUserId(),
    'eventId' => $eventId
]);

$registration = $stmt->fetch();

if ($registration === false) {
    setFlash('error', 'That registration could not be found.');
    redirect('/account/my-registrations.php');
}

if (isPastDatetime($registration['start_datetime'])) {
    setFlash('error', 'This booking can no longer be cancelled because the event has already started.');
    redirect('/account/my-registrations.php');
}   

try {
    $stmt = $pdo->prepare("UPDATE registrations SET status = 'cancelled' WHERE id = :id");
    $stmt->execute(['id' => $registration['id']]);
    setFlash('success', 'Your registration has been cancelled.');
} catch (PDOException $e) {
    error_log('Registration cancel failed: ' . $e->getMessage());
    setFlash('error', 'Something went wrong cancelling your registration.');
}

redirect('/account/my-registrations.php');
