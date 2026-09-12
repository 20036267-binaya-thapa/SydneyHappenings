<?php
// ============================================================
// organiser/event-attendees.php
// Lists everyone registered for one of the organiser's own events,
// and lets the organiser mark each person as attended or a no-show
// after the event starts.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';

$eventId = (int) ($_GET['id'] ?? $_POST['event_id'] ?? 0);

// Confirms the event exists and belongs to this organiser,
// or that the current user is an admin.
$event = requireEventOwner($pdo, $eventId);

// Use MySQL time so this page stays consistent with the rest
// of the website, which uses NOW() for event timing.
$timeStmt = $pdo->query("SELECT NOW()");
$currentTime = $timeStmt->fetchColumn();

// Attendance can only be marked once the event has started.
$eventStarted = $event['start_datetime'] <= $currentTime;


// ============================================================
// Handle attendance status changes
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF protection
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Your session expired. Please try again.');
        redirect('/organiser/event-attendees.php?id=' . $eventId);
    }

    // Prevent attendance from being marked before the event starts
    if (!$eventStarted) {
        setFlash('error', 'Attendance cannot be marked before the event starts.');
        redirect('/organiser/event-attendees.php?id=' . $eventId);
    }

    $registrationId = (int) ($_POST['registration_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';

    // Only allow valid attendance statuses
    if (in_array($newStatus, ['attended', 'no_show', 'registered'], true)) {

        // Make sure the registration belongs to this event
        // and is not already cancelled.
        $stmt = $pdo->prepare(
            "UPDATE registrations
             SET status = :status
             WHERE id = :id
             AND event_id = :eventId
             AND status != 'cancelled'"
        );

        $stmt->execute([
            'status'  => $newStatus,
            'id'      => $registrationId,
            'eventId' => $eventId
        ]);

        if ($stmt->rowCount() > 0) {
            setFlash('success', 'Attendance updated.');
        } else {
            setFlash('error', 'That registration could not be updated.');
        }
    } else {
        setFlash('error', 'Invalid attendance status.');
    }

    redirect('/organiser/event-attendees.php?id=' . $eventId);
}


// ============================================================
// Get attendees
// ============================================================

$stmt = $pdo->prepare(
    "SELECT
        r.id,
        r.status,
        r.registered_at,
        u.name
     FROM registrations r
     JOIN users u ON u.id = r.user_id
     WHERE r.event_id = :eventId
     AND r.status != 'cancelled'
     ORDER BY u.name ASC"
);

$stmt->execute([
    'eventId' => $eventId
]);

$attendees = $stmt->fetchAll();


// Number of booked places
$registeredCount = getRegisteredCount($pdo, $eventId);


// ============================================================
// Page metadata
// ============================================================

$pageTitle = 'Attendees - ' . $event['title'] . ' - ' . SITE_NAME;
$pageDescription = 'View and manage attendees for your event.';

require_once __DIR__ . '/../includes/header.php';
?>


<div class="page-header">

    <h1>Attendees: <?= e($event['title']) ?></h1>

    <p>
        <?= $registeredCount ?>
        of
        <?= (int) $event['capacity'] ?>
        places booked.
    </p>

</div>


<?php if (empty($attendees)): ?>

    <p class="empty-state">
        No one has registered for this event yet.
    </p>

<?php else: ?>

    <div class="data-table-wrapper">

        <table class="data-table data-table--stack">

            <caption>
                Attendees for <?= e($event['title']) ?>
            </caption>

            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Registered on</th>
                    <th scope="col">Status</th>
                    <th scope="col">Mark attendance</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($attendees as $attendee): ?>

                    <tr>

                        <td data-label="Name">
                            <?= e($attendee['name']) ?>
                        </td>

                        <td data-label="Registered on">
                            <?= e(formatEventDate($attendee['registered_at'])) ?>
                        </td>

                        <td data-label="Status">
                            <?= e(
                                ucfirst(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $attendee['status']
                                    )
                                )
                            ) ?>
                        </td>

                        <td data-label="Mark attendance">

                            <?php if ($eventStarted): ?>

                                <div class="button-row">

                                    <form
                                        method="post"
                                        action="<?= BASE_URL ?>/organiser/event-attendees.php"
                                        class="logout-form"
                                    >

                                        <?= csrfField() ?>

                                        <input
                                            type="hidden"
                                            name="event_id"
                                            value="<?= $eventId ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="registration_id"
                                            value="<?= (int) $attendee['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="new_status"
                                            value="attended"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-small"
                                        >
                                            Attended
                                        </button>

                                    </form>


                                    <form
                                        method="post"
                                        action="<?= BASE_URL ?>/organiser/event-attendees.php"
                                        class="logout-form"
                                    >

                                        <?= csrfField() ?>

                                        <input
                                            type="hidden"
                                            name="event_id"
                                            value="<?= $eventId ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="registration_id"
                                            value="<?= (int) $attendee['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="new_status"
                                            value="no_show"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-small btn-secondary"
                                        >
                                            No-show
                                        </button>

                                    </form>

                                </div>

                            <?php else: ?>

                                <span>
                                    Available after event starts
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

<?php endif; ?>


<p>
    <a
        class="btn btn-secondary"
        href="<?= BASE_URL ?>/organiser/my-events.php"
    >
        Back to my events
    </a>
</p>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>