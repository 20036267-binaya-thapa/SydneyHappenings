<?php
// ============================================================
// organiser/event-form.php
// One form that handles both creating a new event (no "id" in the
// URL) and editing an existing one (?id=17). Editing goes through
// requireEventOwner(), which redirects away if the event does not
// belong to the logged-in organiser (admins may edit any event).
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';

$eventId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = $eventId !== null;
$existingEvent = null;

if ($isEdit) {
    $existingEvent = requireEventOwner($pdo, $eventId);
} else {
    requireRole(['organiser', 'admin']);
}

$errors = [];

// Populate the form's starting values: from the existing event when
// editing, or blank defaults when creating.
$title = $existingEvent['title'] ?? '';
$description = $existingEvent['description'] ?? '';
$categoryId = $existingEvent['category_id'] ?? '';
$venueId = $existingEvent['venue_id'] ?? '';
$startDatetime = $existingEvent ? date('Y-m-d\TH:i', strtotime($existingEvent['start_datetime'])) : '';
$endDatetime = $existingEvent ? date('Y-m-d\TH:i', strtotime($existingEvent['end_datetime'])) : '';
$capacity = $existingEvent['capacity'] ?? '';
$price = $existingEvent['price'] ?? '0.00';
$status = $existingEvent['status'] ?? 'draft';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Your session expired. Please try again.';
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = $_POST['category_id'] ?? '';
    $venueId = $_POST['venue_id'] ?? '';
    $startDatetime = $_POST['start_datetime'] ?? '';
    $endDatetime = $_POST['end_datetime'] ?? '';
    $capacity = $_POST['capacity'] ?? '';
    $price = $_POST['price'] ?? '';
    $status = $_POST['status'] ?? 'draft';

    $error = validateRequired($title, 'Title') ?? validateLength($title, 5, 150, 'Title');
    if ($error) $errors['title'] = $error;

    $error = validateRequired($description, 'Description') ?? validateLength($description, 30, 100000, 'Description');
    if ($error) $errors['description'] = $error;

    $error = validateRequired($categoryId, 'Category');
    if (!$error) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = :id AND is_active = 1");
        $stmt->execute(['id' => $categoryId]);
        if ($stmt->fetch() === false) {
            $error = 'Please choose a valid, active category.';
        }
    }
    if ($error) $errors['category_id'] = $error;

    $error = validateRequired($venueId, 'Venue');
    if (!$error) {
        $stmt = $pdo->prepare("SELECT id FROM venues WHERE id = :id AND is_active = 1");
        $stmt->execute(['id' => $venueId]);
        if ($stmt->fetch() === false) {
            $error = 'Please choose a valid, active venue.';
        }
    }
    if ($error) $errors['venue_id'] = $error;

    $error = validateDatetime($startDatetime, 'start date and time') ?? validateFutureDatetime($startDatetime, 'Start date and time');
    if ($error) $errors['start_datetime'] = $error;

    $error = validateDatetime($endDatetime, 'end date and time');
    if (!$error) {
        $error = validateDatetimeAfter($endDatetime, $startDatetime, 'End date and time');
    }
    if ($error) $errors['end_datetime'] = $error;

    $error = validateInteger($capacity, 'Capacity', 1);
    if (!$error && $isEdit) {
        // Shrinking capacity below the number of people already holding
        // a place would silently overbook the event - block it instead.
        $currentRegistrations = getRegisteredCount($pdo, $eventId);
        if ((int) $capacity < $currentRegistrations) {
            $error = "Capacity cannot be less than the {$currentRegistrations} people already registered.";
        }
    }
    if ($error) $errors['capacity'] = $error;

    $error = validateDecimal($price, 'Price', 0);
    if ($error) $errors['price'] = $error;

    if (!in_array($status, ['draft', 'published', 'cancelled'], true)) {
        $errors['status'] = 'Please choose a valid status.';
    }

    $imageFile = $_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE];
    $error = validateImageUpload($imageFile);
    if ($error) $errors['image'] = $error;

    if (empty($errors)) {
        try {
            $slug = makeUniqueSlug($pdo, 'events', $title, $eventId);
            $startForDb = date('Y-m-d H:i:s', strtotime($startDatetime));
            $endForDb = date('Y-m-d H:i:s', strtotime($endDatetime));

            $newImage = $imageFile['error'] === UPLOAD_ERR_NO_FILE
                ? ($existingEvent['image'] ?? null)
                : saveEventImage($imageFile);

                if ($isEdit) {
                    $stmt = $pdo->prepare(
                        "UPDATE events SET category_id = :categoryId, venue_id = :venueId, title = :title,
                            slug = :slug, description = :description, image = :image,
                            start_datetime = :start, end_datetime = :end, capacity = :capacity,
                            price = :price, status = :status
                         WHERE id = :id"
                    );
                
                    $stmt->execute([
                        'categoryId' => $categoryId,
                        'venueId' => $venueId,
                        'title' => $title,
                        'slug' => $slug,
                        'description' => $description,
                        'image' => $newImage,
                        'start' => $startForDb,
                        'end' => $endForDb,
                        'capacity' => $capacity,
                        'price' => $price,
                        'status' => $status,
                        'id' => $eventId,
                    ]);
                
                    // If an event is moved back into the future,
                    // previous attendance results are no longer valid.
                    // Reset attended/no-show registrations back to registered.
                    $stmt = $pdo->prepare(
                        "UPDATE registrations
                         SET status = 'registered'
                         WHERE event_id = :eventId
                         AND status IN ('attended', 'no_show')
                         AND :startTime > NOW()"
                    );
                
                    $stmt->execute([
                        'eventId' => $eventId,
                        'startTime' => $startForDb
                    ]);
                
                    setFlash('success', 'Event updated.');
                } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO events (organiser_id, category_id, venue_id, title, slug, description,
                        image, start_datetime, end_datetime, capacity, price, status)
                     VALUES (:organiserId, :categoryId, :venueId, :title, :slug, :description,
                        :image, :start, :end, :capacity, :price, :status)"
                );
                $stmt->execute([
                    'organiserId' => currentUserId(), 'categoryId' => $categoryId, 'venueId' => $venueId,
                    'title' => $title, 'slug' => $slug, 'description' => $description, 'image' => $newImage,
                    'start' => $startForDb, 'end' => $endForDb, 'capacity' => $capacity,
                    'price' => $price, 'status' => $status,
                ]);
                setFlash('success', 'Event created.');
            }

            redirect('/organiser/my-events.php');
        } catch (Throwable $e) {
            error_log('Event save failed: ' . $e->getMessage());
            $errors['general'] = 'Something went wrong saving this event. Please try again.';
        }
    }
}

$categories = getActiveCategories($pdo);
$venues = getActiveVenues($pdo);

$pageTitle = ($isEdit ? 'Edit Event' : 'Create Event') . ' - ' . SITE_NAME;
$pageDescription = 'Create or edit an event listing on SydneyHappenings.';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><?= $isEdit ? 'Edit Event' : 'Create Event' ?></h1>
</div>

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

<form method="post" action="<?= BASE_URL ?>/organiser/event-form.php<?= $isEdit ? '?id=' . $eventId : '' ?>" class="form-card" enctype="multipart/form-data" novalidate>
    <?= csrfField() ?>

    <fieldset class="form-section">
        <legend>Event details</legend>
        <div class="form-grid">
            <div class="form-field form-field--full">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="<?= e($title) ?>" required minlength="5" maxlength="150"
                       <?= isset($errors['title']) ? 'class="has-error" aria-describedby="title-error"' : '' ?>>
                <?php if (isset($errors['title'])): ?><p id="title-error" class="field-error"><?= e($errors['title']) ?></p><?php endif; ?>
            </div>

            <div class="form-field form-field--full">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="6" required minlength="30"
                          <?= isset($errors['description']) ? 'class="has-error" aria-describedby="description-error"' : '' ?>><?= e($description) ?></textarea>
                <?php if (isset($errors['description'])): ?><p id="description-error" class="field-error"><?= e($errors['description']) ?></p><?php endif; ?>
            </div>

            <div class="form-field">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required
                        <?= isset($errors['category_id']) ? 'class="has-error" aria-describedby="category-error"' : '' ?>>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= (string) $categoryId === (string) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category_id'])): ?><p id="category-error" class="field-error"><?= e($errors['category_id']) ?></p><?php endif; ?>
            </div>

            <div class="form-field">
                <label for="venue_id">Venue</label>
                <select id="venue_id" name="venue_id" required
                        <?= isset($errors['venue_id']) ? 'class="has-error" aria-describedby="venue-error"' : '' ?>>
                    <option value="">Select a venue</option>
                    <?php foreach ($venues as $venue): ?>
                        <option value="<?= (int) $venue['id'] ?>" <?= (string) $venueId === (string) $venue['id'] ? 'selected' : '' ?>>
                            <?= e($venue['name']) ?> (<?= e($venue['suburb']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['venue_id'])): ?><p id="venue-error" class="field-error"><?= e($errors['venue_id']) ?></p><?php endif; ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="form-section">
        <legend>Date and capacity</legend>
        <div class="form-grid">
            <div class="form-field">
                <label for="start_datetime">Start date and time</label>
                <input type="datetime-local" id="start_datetime" name="start_datetime" value="<?= e($startDatetime) ?>" required
                       <?= isset($errors['start_datetime']) ? 'class="has-error" aria-describedby="start-error"' : '' ?>>
                <?php if (isset($errors['start_datetime'])): ?><p id="start-error" class="field-error"><?= e($errors['start_datetime']) ?></p><?php endif; ?>
            </div>

            <div class="form-field">
                <label for="end_datetime">End date and time</label>
                <input type="datetime-local" id="end_datetime" name="end_datetime" value="<?= e($endDatetime) ?>" required
                       <?= isset($errors['end_datetime']) ? 'class="has-error" aria-describedby="end-error"' : '' ?>>
                <?php if (isset($errors['end_datetime'])): ?><p id="end-error" class="field-error"><?= e($errors['end_datetime']) ?></p><?php endif; ?>
            </div>

            <div class="form-field">
                <label for="capacity">Capacity</label>
                <input type="number" id="capacity" name="capacity" value="<?= e((string) $capacity) ?>" required min="1" step="1"
                       <?= isset($errors['capacity']) ? 'class="has-error" aria-describedby="capacity-error"' : '' ?>>
                <?php if (isset($errors['capacity'])): ?><p id="capacity-error" class="field-error"><?= e($errors['capacity']) ?></p><?php endif; ?>
            </div>

            <div class="form-field">
                <label for="price">Price ($AUD, 0 for free)</label>
                <input type="number" id="price" name="price" value="<?= e((string) $price) ?>" required min="0" step="0.01"
                       <?= isset($errors['price']) ? 'class="has-error" aria-describedby="price-error"' : '' ?>>
                <?php if (isset($errors['price'])): ?><p id="price-error" class="field-error"><?= e($errors['price']) ?></p><?php endif; ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="form-section">
        <legend>Media and status</legend>
        <div class="form-grid">
            <div class="form-field">
                <label for="image">Event image (optional)</label>
                <?php if (!empty($existingEvent['image'])): ?>
                    <p class="field-hint">Current image: <?= e($existingEvent['image']) ?>. Choose a new file to replace it.</p>
                <?php endif; ?>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
                       aria-describedby="image-hint<?= isset($errors['image']) ? ' image-error' : '' ?>">
                <p id="image-hint" class="field-hint">JPG, PNG or WebP, up to 2 MB.</p>
                <?php if (isset($errors['image'])): ?><p id="image-error" class="field-error"><?= e($errors['image']) ?></p><?php endif; ?>
            </div>

            <div class="form-field">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft (not visible to the public)</option>
                    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn"><?= $isEdit ? 'Save changes' : 'Create event' ?></button>
        <a class="btn btn-secondary" href="<?= BASE_URL ?>/organiser/my-events.php">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
