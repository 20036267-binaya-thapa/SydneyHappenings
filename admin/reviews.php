<?php
// ============================================================
// admin/reviews.php
// Lists every review on the platform and lets an admin hide or show
// one. Hiding does not delete the row - it just excludes it from
// getEventRating(), the public reviews list, and the JSON-LD
// aggregateRating (all three already filter on is_hidden = 0).
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Your session expired. Please try again.');
        redirect('/admin/reviews.php');
    }

    $reviewId = (int) ($_POST['review_id'] ?? 0);
    // Carries the current filter through the toggle, so hiding a review
    // from the "Visible" view returns the admin to that same view.
    $redirectFilter = in_array($_POST['status'] ?? '', ['visible', 'hidden'], true) ? $_POST['status'] : 'all';

    try {
        $stmt = $pdo->prepare("UPDATE reviews SET is_hidden = NOT is_hidden WHERE id = :id");
        $stmt->execute(['id' => $reviewId]);
        setFlash('success', 'Review updated.');
    } catch (PDOException $e) {
        error_log('Review visibility toggle failed: ' . $e->getMessage());
        setFlash('error', 'Something went wrong updating that review.');
    }

    redirect('/admin/reviews.php' . ($redirectFilter !== 'all' ? '?status=' . $redirectFilter : ''));
}

$filter = in_array($_GET['status'] ?? '', ['visible', 'hidden'], true) ? $_GET['status'] : 'all';

$whereClause = '';
if ($filter === 'visible') {
    $whereClause = 'WHERE r.is_hidden = 0';
} elseif ($filter === 'hidden') {
    $whereClause = 'WHERE r.is_hidden = 1';
}

// $whereClause is never built from raw request input - $filter is
// checked against a fixed whitelist above, so there is nothing here
// for a prepared statement to bind; the query is still read-only and
// touches no user-supplied value directly.
$reviews = $pdo->query(
    "SELECT r.*, e.title AS event_title, e.slug AS event_slug, u.name AS reviewer_name
     FROM reviews r
     JOIN events e ON e.id = r.event_id
     JOIN users u ON u.id = r.user_id
     {$whereClause}
     ORDER BY r.created_at DESC"
)->fetchAll();

$pageTitle = 'Manage Reviews - ' . SITE_NAME;
$pageDescription = 'View and moderate event reviews submitted across SydneyHappenings.';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Manage Reviews</h1>

<ul class="pagination" aria-label="Filter reviews by status">
    <?php foreach (['all' => 'All', 'visible' => 'Visible', 'hidden' => 'Hidden'] as $key => $label): ?>
        <li>
            <?php if ($filter === $key): ?>
                <span class="current" aria-current="page"><?= e($label) ?></span>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/admin/reviews.php<?= $key !== 'all' ? '?status=' . $key : '' ?>"><?= e($label) ?></a>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (empty($reviews)): ?>
    <p class="empty-state">No reviews match this filter.</p>
<?php else: ?>
    <div class="data-table-wrapper">
        <table class="data-table">
            <caption>All reviews<?= $filter !== 'all' ? ' (' . e($filter) . ')' : '' ?></caption>
            <thead>
                <tr>
                    <th scope="col">Event</th>
                    <th scope="col">Reviewer</th>
                    <th scope="col">Rating</th>
                    <th scope="col">Comment</th>
                    <th scope="col">Date</th>
                    <th scope="col">Status</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reviews as $review): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>/event.php?slug=<?= urlencode($review['event_slug']) ?>"><?= e($review['event_title']) ?></a></td>
                        <td><?= e($review['reviewer_name']) ?></td>
                        <td><?= (int) $review['rating'] ?> out of 5</td>
                        <td><?= e(truncateForMeta($review['comment'], 80)) ?></td>
                        <td><?= e(formatEventDate($review['created_at'])) ?></td>
                        <td>
                            <?php if ((int) $review['is_hidden'] === 1): ?>
                                <span class="badge badge--hidden">Hidden</span>
                            <?php else: ?>
                                <span class="badge badge-status-published">Visible</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" action="<?= BASE_URL ?>/admin/reviews.php" class="logout-form"
                                  data-confirm="<?= (int) $review['is_hidden'] === 1 ? 'Show this review to the public again?' : 'Hide this review from public view?' ?>">
                                <?= csrfField() ?>
                                <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                                <?php if ($filter !== 'all'): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
                                <button type="submit" class="btn btn-small btn-secondary">
                                    <?= (int) $review['is_hidden'] === 1 ? 'Show' : 'Hide' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
