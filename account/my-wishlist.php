<?php
// ============================================================
// account/my-wishlist.php
// Shows every event the logged-in user has saved, newest saved
// first. Reuses the same event-card markup as index.php/events.php,
// including the wishlist-toggle button, so a user can un-save an
// event straight from this page without visiting it first.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireLogin();

// Every card on this page is, by definition, already saved - no
// separate getUserWishlistIds()/isWishlisted() lookup is needed, the
// button on every card always starts pressed.
$stmt = $pdo->prepare(
    "SELECT e.*, c.name AS category_name, v.suburb,
            AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count
     FROM wishlists w
     JOIN events e ON e.id = w.event_id
     JOIN categories c ON c.id = e.category_id
     JOIN venues v ON v.id = e.venue_id
     LEFT JOIN reviews r ON r.event_id = e.id AND r.is_hidden = 0
     WHERE w.user_id = :userId
     GROUP BY e.id, c.name, v.suburb, w.created_at
     ORDER BY w.created_at DESC"
);
$stmt->execute(['userId' => currentUserId()]);
$savedEvents = $stmt->fetchAll();

$pageTitle = 'Saved Events - ' . SITE_NAME;
$pageDescription = 'Events you have saved to look at again on SydneyHappenings.';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Saved events</h1>
</div>

<?php if (empty($savedEvents)): ?>
    <p class="empty-state">
        You haven't saved any events yet. <a href="<?= BASE_URL ?>/events.php">Browse events</a> and tap the heart on one to save it here.
    </p>
<?php else: ?>
    <div class="card-grid">
        <?php foreach ($savedEvents as $event): ?>
            <article class="event-card">
                <div class="event-card-media">
                    <?php if ($event['image']): ?>
                        <img class="event-card-image" src="<?= BASE_URL ?>/assets/uploads/<?= e($event['image']) ?>"
                             alt="<?= e($event['title']) ?>" width="400" height="267" loading="lazy">
                    <?php else: ?>
                        <div class="image-placeholder" aria-hidden="true">No image available</div>
                    <?php endif; ?>
                    <span class="badge badge-category event-card-badge"><?= e($event['category_name']) ?></span>
                    <form method="post" action="<?= BASE_URL ?>/wishlist-toggle.php" class="wishlist-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                        <input type="hidden" name="referrer" value="<?= e(currentRequestPath()) ?>">
                        <button type="submit" class="wishlist-btn wishlist-btn--on-card" aria-pressed="true">
                            <span class="wishlist-heart" aria-hidden="true">&#9829;</span>
                            <span class="sr-only">Saved</span>
                        </button>
                    </form>
                </div>
                <div class="event-card-body">
                    <h3><a href="<?= BASE_URL ?>/event.php?slug=<?= urlencode($event['slug']) ?>"><span class="event-card-title-text"><?= e($event['title']) ?></span></a></h3>
                    <p class="event-meta"><?= e(formatEventDate($event['start_datetime'])) ?> &middot; <?= e($event['suburb']) ?></p>
                    <div class="event-card-footer">
                        <?php if ((int) $event['review_count'] > 0): ?>
                            <?= renderStars((float) $event['avg_rating'], (int) $event['review_count']) ?>
                        <?php endif; ?>
                        <span class="event-card-price"><?= e(formatPrice($event['price'])) ?></span>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
