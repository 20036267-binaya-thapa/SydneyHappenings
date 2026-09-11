<?php
// ============================================================
// organiser/event-delete.php
// Deletes an event. POST only, with CSRF and ownership checks, since
// this is a destructive, state-changing action. Deleting an event
// also deletes its registrations, because registrations.event_id has
// ON DELETE CASCADE in the schema.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/organiser/my-events.php');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Your session expired. Please try again.');
    redirect('/organiser/my-events.php');
}

$eventId = (int) ($_POST['event_id'] ?? 0);

// requireEventOwner() checks the event exists and belongs to this
// organiser (or that the user is an admin) before anything is deleted.
$event = requireEventOwner($pdo, $eventId);

try {
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = :id");
    $stmt->execute(['id' => $eventId]);
    setFlash('success', 'Event deleted.');
} catch (PDOException $e) {
    error_log('Event delete failed: ' . $e->getMessage());
    setFlash('error', 'Something went wrong deleting this event.');
}

redirect('/organiser/my-events.php');
