<?php
// ============================================================
// admin/events.php
// All-events management: every event on the platform, regardless of
// organiser, with a keyword and status filter. Editing and deleting
// reuse organiser/event-form.php and organiser/event-delete.php -
// requireEventOwner() already allows an admin to manage any event, so
// there is no need for separate admin-only copies of those pages.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole('admin');

$keyword = trim($_GET['keyword'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$conditions = [];
$params = [];

if ($keyword !== '') {
    $conditions[] = "e.title LIKE :keyword";
    $params['keyword'] = '%' . $keyword . '%';
}
if (in_array($statusFilter, ['draft', 'published', 'cancelled'], true)) {
    $conditions[] = "e.status = :status";
    $params['status'] = $statusFilter;
}

$whereSql = empty($conditions) ? '1=1' : implode(' AND ', $conditions);

$stmt = $pdo->prepare(
    "SELECT e.*, u.name AS organiser_name,
            (SELECT COALESCE(SUM(r.quantity), 0) FROM registrations r WHERE r.event_id = e.id AND r.status = 'registered') AS registered_count
     FROM events e
     JOIN users u ON u.id = e.organiser_id
     WHERE {$whereSql}
     ORDER BY e.start_datetime DESC"
);
$stmt->execute($params);
$events = $stmt->fetchAll();

$pageTitle = 'Manage Events - ' . SITE_NAME;
$pageDescription = 'View and manage every event published on SydneyHappenings.';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Manage Events</h1>

<form class="filter-form" method="get" action="<?= BASE_URL ?>/admin/events.php">
    <div class="form-field">
        <label for="keyword">Title contains</label>
        <input type="search" id="keyword" name="keyword" value="<?= e($keyword) ?>">
    </div>
    <div class="form-field">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All statuses</option>
            <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Published</option>
            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
    </div>
    <button type="submit" class="btn">Filter</button>
    <a href="<?= BASE_URL ?>/admin/events.php" class="btn btn-secondary">Reset</a>
</form>

<?php if (empty($events)): ?>
    <p class="empty-state">No events match this filter.</p>
<?php else: ?>
    <div class="data-table-wrapper">
        <table class="data-table">
            <caption>All events</caption>
            <thead>
                <tr>
                    <th scope="col">Title</th>
                    <th scope="col">Organiser</th>
                    <th scope="col">Start</th>
                    <th scope="col">Status</th>
                    <th scope="col">Registered</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= e($event['title']) ?></td>
                        <td><?= e($event['organiser_name']) ?></td>
                        <td><?= e(formatEventDate($event['start_datetime'])) ?></td>
                        <td><span class="badge badge-status-<?= e($event['status']) ?>"><?= e(ucfirst($event['status'])) ?></span></td>
                        <td><?= (int) $event['registered_count'] ?> / <?= (int) $event['capacity'] ?></td>
                        <td>
                            <a class="btn btn-small" href="<?= BASE_URL ?>/organiser/event-form.php?id=<?= (int) $event['id'] ?>">Edit</a>
                            <a class="btn btn-small btn-secondary" href="<?= BASE_URL ?>/organiser/event-attendees.php?id=<?= (int) $event['id'] ?>">Attendees</a>
                            <form method="post" action="<?= BASE_URL ?>/organiser/event-delete.php" class="logout-form"
                                  data-confirm="Delete '<?= e($event['title']) ?>'? This cannot be undone.">
                                <?= csrfField() ?>
                                <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                                <button type="submit" class="btn btn-small btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
