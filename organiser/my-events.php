<?php
// ============================================================
// organiser/my-events.php
// Lists only the logged-in organiser's own events, regardless of
// status, with links to edit, view attendees, or delete each one.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole(['organiser', 'admin']);

// registered_count, avg_rating and review_count are each a correlated
// subquery rather than a JOIN + GROUP BY - one query either way, but
// this matches the style already used here for registered_count, and
// avoids a three-way JOIN/GROUP BY across categories and reviews.
$stmt = $pdo->prepare(
    "SELECT e.*, c.name AS category_name,
            (SELECT COALESCE(SUM(r.quantity), 0) FROM registrations r WHERE r.event_id = e.id AND r.status = 'registered') AS registered_count,
            (SELECT AVG(rv.rating) FROM reviews rv WHERE rv.event_id = e.id AND rv.is_hidden = 0) AS avg_rating,
            (SELECT COUNT(*) FROM reviews rv WHERE rv.event_id = e.id AND rv.is_hidden = 0) AS review_count
     FROM events e
     JOIN categories c ON c.id = e.category_id
     WHERE e.organiser_id = :id
     ORDER BY e.start_datetime DESC"
);
$stmt->execute(['id' => currentUserId()]);
$events = $stmt->fetchAll();

$pageTitle = 'My Events - ' . SITE_NAME;
$pageDescription = 'Manage the events you have published on SydneyHappenings.';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>My Events</h1>

<p><a class="btn" href="<?= BASE_URL ?>/organiser/event-form.php">Create a new event</a></p>

<?php if (empty($events)): ?>
    <p class="empty-state">You haven't created any events yet.</p>
<?php else: ?>
    <div class="data-table-wrapper">
        <table class="data-table">
            <caption>Your events</caption>
            <thead>
                <tr>
                    <th scope="col">Title</th>
                    <th scope="col">Category</th>
                    <th scope="col">Start</th>
                    <th scope="col">Status</th>
                    <th scope="col">Registered</th>
                    <th scope="col">Rating</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= e($event['title']) ?></td>
                        <td><?= e($event['category_name']) ?></td>
                        <td><?= e(formatEventDate($event['start_datetime'])) ?></td>
                        <td><span class="badge badge-status-<?= e($event['status']) ?>"><?= e(ucfirst($event['status'])) ?></span></td>
                        <td><?= (int) $event['registered_count'] ?> / <?= (int) $event['capacity'] ?></td>
                        <td>
                            <?php
                                // Unlike the public event cards, this table always
                                // shows the rating cell - "No reviews yet" is useful
                                // information for the organiser managing the event,
                                // not noise to hide the way it would be for a browsing visitor.
                            ?>
                            <?= renderStars(
                                $event['avg_rating'] !== null ? (float) $event['avg_rating'] : null,
                                (int) $event['review_count']
                            ) ?>
                        </td>
                        <td>
                            <div class="button-row">
                                <a class="btn btn-small" href="<?= BASE_URL ?>/organiser/event-form.php?id=<?= (int) $event['id'] ?>">Edit</a>
                                <a class="btn btn-small btn-secondary" href="<?= BASE_URL ?>/organiser/event-attendees.php?id=<?= (int) $event['id'] ?>">Attendees</a>
                                <form method="post" action="<?= BASE_URL ?>/organiser/event-delete.php" class="logout-form"
                                      data-confirm="Delete '<?= e($event['title']) ?>'? This cannot be undone.">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                                    <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
