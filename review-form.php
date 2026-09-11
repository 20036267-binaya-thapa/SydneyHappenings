<?php
// ============================================================
// review-form.php
// One form that handles both creating a new review and editing an
// existing one for the same event, reached as
// review-form.php?event_id=17. A user can only ever hold one review
// per event - editing UPDATEs that same row rather than inserting a
// second one, backed by the reviews table's own unique key as a
// last line of defence against a race between two requests.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';

// 1. Must be logged in.
requireLogin();

$eventId = (int) ($_GET['event_id'] ?? 0);

// 2. The event must exist.
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = :id");
$stmt->execute(['id' => $eventId]);
$event = $stmt->fetch();

if ($event === false) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$eventUrl = '/event.php?slug=' . urlencode($event['slug']);

// An existing review means this is an edit - fetched up front so both
// the access check below and the form-prefill further down can use it.
$existingReview = getUserReview($pdo, currentUserId(), $eventId);
$isEdit = $existingReview !== null;

// 3. Either this is an edit of the user's own existing review, or
// they are newly eligible: the event has finished, they attended it,
// and they have not reviewed it before. canUserReview() already
// returns false once a review exists, so these two conditions never
// both need to be true - they cover the create and edit cases.
if (!$isEdit && !canUserReview($pdo, currentUserId(), $eventId)) {
    setFlash('error', 'You can only review events you attended, after they have finished.');
    redirect($eventUrl);
}

$errors = [];

$rating = $existingReview['rating'] ?? '';
$comment = $existingReview['comment'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Your session expired. Please try again.';
    }

    $rating = $_POST['rating'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    $error = validateRequired($rating, 'Rating') ?? validateInteger($rating, 'Rating', 1);
    if (!$error && (int) $rating > 5) {
        $error = 'Rating must be between 1 and 5.';
    }
    if ($error) $errors['rating'] = $error;

    $error = validateRequired($comment, 'Comment') ?? validateLength($comment, 20, 1000, 'Comment');
    if ($error) $errors['comment'] = $error;

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare("UPDATE reviews SET rating = :rating, comment = :comment WHERE id = :id");
                $stmt->execute(['rating' => $rating, 'comment' => $comment, 'id' => $existingReview['id']]);
                setFlash('success', 'Your review has been updated.');
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO reviews (event_id, user_id, rating, comment) VALUES (:eventId, :userId, :rating, :comment)"
                );
                $stmt->execute([
                    'eventId' => $eventId, 'userId' => currentUserId(), 'rating' => $rating, 'comment' => $comment,
                ]);
                setFlash('success', 'Thanks for your review.');
            }
            redirect($eventUrl);
        } catch (PDOException $e) {
            // SQLSTATE 23000 is a constraint violation - here, almost
            // certainly the uq_review_user_event unique key, meaning a
            // review from this user for this event was inserted by
            // another request between the page loading and this
            // submission. Either way the user ends up with one review,
            // so this is not a failure from their point of view.
            if ($e->getCode() === '23000') {
                setFlash('error', 'You have already reviewed this event.');
                redirect($eventUrl);
            }
            error_log('Review save failed: ' . $e->getMessage());
            $errors['general'] = 'Something went wrong saving your review. Please try again.';
        }
    }
}

$pageTitle = ($isEdit ? 'Edit Your Review' : 'Write a Review') . ' - ' . $event['title'] . ' | ' . SITE_NAME;
$pageDescription = 'Share your experience of ' . $event['title'] . ' on ' . SITE_NAME . '.';
require_once __DIR__ . '/includes/header.php';
?>

<h1><?= $isEdit ? 'Edit your review' : 'Write a review' ?></h1>
<p class="event-meta">For <a href="<?= BASE_URL . $eventUrl ?>"><?= e($event['title']) ?></a></p>

<?php if (!empty($errors)): ?>
    <div class="error-summary" role="alert">
        <h2>Please fix the following:</h2>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>/review-form.php?event_id=<?= (int) $eventId ?>" novalidate>
    <?= csrfField() ?>

    <fieldset class="form-field" <?= isset($errors['rating']) ? 'aria-describedby="rating-error"' : '' ?>>
        <legend>Rating</legend>
        <div class="rating-input" id="ratingStars">
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <span class="rating-star">
                    <input type="radio" id="rating-<?= $i ?>" name="rating" value="<?= $i ?>"
                           <?= (string) $rating === (string) $i ? 'checked' : '' ?> required>
                    <label for="rating-<?= $i ?>">
                        <span class="rating-icon" aria-hidden="true">&#9733;</span>
                        <?= $i ?> star<?= $i === 1 ? '' : 's' ?>
                    </label>
                </span>
            <?php endfor; ?>
        </div>
        <?php if (isset($errors['rating'])): ?><p id="rating-error" class="field-error"><?= e($errors['rating']) ?></p><?php endif; ?>
    </fieldset>

    <div class="form-field">
        <label for="comment">Your review</label>
        <textarea id="comment" name="comment" rows="6" required minlength="20" maxlength="1000"
                  <?= isset($errors['comment']) ? 'class="has-error" aria-describedby="comment-error comment-count"' : 'aria-describedby="comment-count"' ?>><?= e($comment) ?></textarea>
        <p id="comment-count" class="char-counter" aria-live="polite">Between 20 and 1000 characters.</p>
        <?php if (isset($errors['comment'])): ?><p id="comment-error" class="field-error"><?= e($errors['comment']) ?></p><?php endif; ?>
    </div>

    <button type="submit" class="btn"><?= $isEdit ? 'Save changes' : 'Submit review' ?></button>
    <a class="btn btn-secondary" href="<?= BASE_URL . $eventUrl ?>">Cancel</a>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
