<?php
// ============================================================
// admin/venues.php
// Venue CRUD, following the same soft-delete pattern as categories:
// a venue still used by an event is deactivated, not deleted.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole('admin');

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$errors = [];
$name = '';
$address = '';
$suburb = '';
$postcode = '';
$capacity = '';

if ($editId !== null) {
    $stmt = $pdo->prepare("SELECT * FROM venues WHERE id = :id");
    $stmt->execute(['id' => $editId]);
    $editingVenue = $stmt->fetch();
    if ($editingVenue) {
        $name = $editingVenue['name'];
        $address = $editingVenue['address'];
        $suburb = $editingVenue['suburb'];
        $postcode = $editingVenue['postcode'];
        $capacity = $editingVenue['capacity'] ?? '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Your session expired. Please try again.';
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $editId = isset($_POST['venue_id']) && $_POST['venue_id'] !== '' ? (int) $_POST['venue_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $suburb = trim($_POST['suburb'] ?? '');
        $postcode = trim($_POST['postcode'] ?? '');
        $capacity = trim($_POST['capacity'] ?? '');

        $error = validateRequired($name, 'Name') ?? validateLength($name, 2, 120, 'Name');
        if ($error) $errors['name'] = $error;

        $error = validateRequired($address, 'Address') ?? validateLength($address, 5, 200, 'Address');
        if ($error) $errors['address'] = $error;

        $error = validateRequired($suburb, 'Suburb') ?? validateLength($suburb, 2, 80, 'Suburb');
        if ($error) $errors['suburb'] = $error;

        $error = validateRequired($postcode, 'Postcode');
        if (!$error && !preg_match('/^\d{4}$/', $postcode)) {
            $error = 'Postcode must be a 4-digit Australian postcode.';
        }
        if ($error) $errors['postcode'] = $error;

        if ($capacity !== '') {
            $error = validateInteger($capacity, 'Capacity', 1);
            if ($error) $errors['capacity'] = $error;
        }

        if (empty($errors)) {
            try {
                if ($editId) {
                    $stmt = $pdo->prepare(
                        "UPDATE venues SET name = :name, address = :address, suburb = :suburb, postcode = :postcode, capacity = :capacity
                         WHERE id = :id"
                    );
                    $stmt->execute(['name' => $name, 'address' => $address, 'suburb' => $suburb, 'postcode' => $postcode,
                        'capacity' => $capacity !== '' ? $capacity : null, 'id' => $editId]);
                    setFlash('success', 'Venue updated.');
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO venues (name, address, suburb, postcode, capacity) VALUES (:name, :address, :suburb, :postcode, :capacity)"
                    );
                    $stmt->execute(['name' => $name, 'address' => $address, 'suburb' => $suburb, 'postcode' => $postcode,
                        'capacity' => $capacity !== '' ? $capacity : null]);
                    setFlash('success', 'Venue created.');
                }
                redirect('/admin/venues.php');
            } catch (PDOException $e) {
                error_log('Venue save failed: ' . $e->getMessage());
                $errors['general'] = 'Something went wrong saving this venue.';
            }
        }
    } elseif ($action === 'delete') {
        $targetId = (int) ($_POST['venue_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE venue_id = :id");
        $stmt->execute(['id' => $targetId]);
        $eventsUsingIt = (int) $stmt->fetchColumn();

        if ($eventsUsingIt > 0) {
            $stmt = $pdo->prepare("UPDATE venues SET is_active = 0 WHERE id = :id");
            $stmt->execute(['id' => $targetId]);
            setFlash('success', "Venue is used by {$eventsUsingIt} event(s), so it was deactivated instead of deleted.");
        } else {
            $stmt = $pdo->prepare("DELETE FROM venues WHERE id = :id");
            $stmt->execute(['id' => $targetId]);
            setFlash('success', 'Venue deleted.');
        }
        redirect('/admin/venues.php');
    } elseif ($action === 'activate') {
        $targetId = (int) ($_POST['venue_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE venues SET is_active = 1 WHERE id = :id");
        $stmt->execute(['id' => $targetId]);
        setFlash('success', 'Venue reactivated.');
        redirect('/admin/venues.php');
    }
}

$venues = $pdo->query("SELECT * FROM venues ORDER BY name ASC")->fetchAll();

$pageTitle = 'Manage Venues - ' . SITE_NAME;
$pageDescription = 'Add, edit and deactivate event venues.';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Manage Venues</h1>

<?php if (!empty($errors)): ?>
    <div class="error-summary" role="alert">
        <h2>Please fix the following:</h2>
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<h2><?= $editId ? 'Edit Venue' : 'Add Venue' ?></h2>
<form method="post" action="<?= BASE_URL ?>/admin/venues.php" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <?php if ($editId): ?><input type="hidden" name="venue_id" value="<?= $editId ?>"><?php endif; ?>

    <div class="form-field">
        <label for="name">Venue name</label>
        <input type="text" id="name" name="name" value="<?= e($name) ?>" required
               <?= isset($errors['name']) ? 'class="has-error" aria-describedby="name-error"' : '' ?>>
        <?php if (isset($errors['name'])): ?><p id="name-error" class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
    </div>

    <div class="form-field">
        <label for="address">Street address</label>
        <input type="text" id="address" name="address" value="<?= e($address) ?>" required
               <?= isset($errors['address']) ? 'class="has-error" aria-describedby="address-error"' : '' ?>>
        <?php if (isset($errors['address'])): ?><p id="address-error" class="field-error"><?= e($errors['address']) ?></p><?php endif; ?>
    </div>

    <div class="form-field">
        <label for="suburb">Suburb</label>
        <input type="text" id="suburb" name="suburb" value="<?= e($suburb) ?>" required
               <?= isset($errors['suburb']) ? 'class="has-error" aria-describedby="suburb-error"' : '' ?>>
        <?php if (isset($errors['suburb'])): ?><p id="suburb-error" class="field-error"><?= e($errors['suburb']) ?></p><?php endif; ?>
    </div>

    <div class="form-field">
        <label for="postcode">Postcode</label>
        <input type="text" id="postcode" name="postcode" value="<?= e($postcode) ?>" required pattern="\d{4}"
               <?= isset($errors['postcode']) ? 'class="has-error" aria-describedby="postcode-error"' : '' ?>>
        <?php if (isset($errors['postcode'])): ?><p id="postcode-error" class="field-error"><?= e($errors['postcode']) ?></p><?php endif; ?>
    </div>

    <div class="form-field">
        <label for="capacity">Venue capacity (optional)</label>
        <input type="number" id="capacity" name="capacity" value="<?= e((string) $capacity) ?>" min="1"
               <?= isset($errors['capacity']) ? 'class="has-error" aria-describedby="capacity-error"' : '' ?>>
        <?php if (isset($errors['capacity'])): ?><p id="capacity-error" class="field-error"><?= e($errors['capacity']) ?></p><?php endif; ?>
    </div>

    <button type="submit" class="btn"><?= $editId ? 'Save changes' : 'Add venue' ?></button>
    <?php if ($editId): ?><a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/venues.php">Cancel</a><?php endif; ?>
</form>

<h2>All Venues</h2>
<div class="data-table-wrapper">
    <table class="data-table">
        <caption>Venues</caption>
        <thead>
            <tr>
                <th scope="col">Name</th>
                <th scope="col">Suburb</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($venues as $venue): ?>
                <tr>
                    <td><?= e($venue['name']) ?></td>
                    <td><?= e($venue['suburb']) ?></td>
                    <td><?= (int) $venue['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
                    <td>
                        <a class="btn btn-small" href="<?= BASE_URL ?>/admin/venues.php?edit=<?= (int) $venue['id'] ?>">Edit</a>
                        <?php if ((int) $venue['is_active'] === 1): ?>
                            <form method="post" action="<?= BASE_URL ?>/admin/venues.php" class="logout-form" data-confirm="Delete or deactivate '<?= e($venue['name']) ?>'?">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="venue_id" value="<?= (int) $venue['id'] ?>">
                                <button type="submit" class="btn btn-small btn-danger">Delete</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= BASE_URL ?>/admin/venues.php" class="logout-form">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="venue_id" value="<?= (int) $venue['id'] ?>">
                                <button type="submit" class="btn btn-small btn-secondary">Reactivate</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
