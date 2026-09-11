<?php
// ============================================================
// account/profile.php
// Lets any logged-in user edit their own name, email and phone.
// Password changes are handled separately on change-password.php.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireLogin();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute(['id' => currentUserId()]);
$user = $stmt->fetch();

// Links are only ever meaningful on an organiser's (or admin's) own
// events, so the whole "Links" section - fields, validation, and the
// three extra columns in the UPDATE below - only applies to those
// roles. An attendee's row simply keeps these columns NULL.
$showLinks = in_array(currentUserRole(), ['organiser', 'admin'], true);

$errors = [];
$name = $user['name'];
$email = $user['email'];
$phone = $user['phone'] ?? '';
$website = $user['website'] ?? '';
$facebook = $user['facebook'] ?? '';
$instagram = $user['instagram'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Your session expired. Please try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $error = validateName($name);
    if ($error) $errors['name'] = $error;

    $error = validateRequired($email, 'Email') ?? validateEmail($email);
    if ($error) {
        $errors['email'] = $error;
    } else {
        $error = validateEmailUnique($pdo, $email, currentUserId());
        if ($error) $errors['email'] = $error;
    }

    $error = validatePhone($phone);
    if ($error) $errors['phone'] = $error;

    if ($showLinks) {
        $website = trim($_POST['website'] ?? '');
        $facebook = trim($_POST['facebook'] ?? '');
        $instagram = trim($_POST['instagram'] ?? '');

        $error = validateUrl($website, 'Website');
        if ($error) $errors['website'] = $error;

        $error = validateUrl($facebook, 'Facebook');
        if ($error) $errors['facebook'] = $error;

        $error = validateUrl($instagram, 'Instagram');
        if ($error) $errors['instagram'] = $error;
    }

    if (empty($errors)) {
        try {
            if ($showLinks) {
                $stmt = $pdo->prepare(
                    "UPDATE users SET name = :name, email = :email, phone = :phone,
                            website = :website, facebook = :facebook, instagram = :instagram
                     WHERE id = :id"
                );
                $stmt->execute([
                    'name'      => $name,
                    'email'     => $email,
                    'phone'     => $phone !== '' ? $phone : null,
                    'website'   => $website !== '' ? $website : null,
                    'facebook'  => $facebook !== '' ? $facebook : null,
                    'instagram' => $instagram !== '' ? $instagram : null,
                    'id'        => currentUserId(),
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = :name, email = :email, phone = :phone WHERE id = :id");
                $stmt->execute(['name' => $name, 'email' => $email, 'phone' => $phone !== '' ? $phone : null, 'id' => currentUserId()]);
            }

            $_SESSION['user_name'] = $name;
            setFlash('success', 'Your profile has been updated.');
            redirect('/account/profile.php');
        } catch (PDOException $e) {
            error_log('Profile update failed: ' . $e->getMessage());
            $errors['general'] = 'Something went wrong saving your profile. Please try again.';
        }
    }
}

$pageTitle = 'My Profile - ' . SITE_NAME;
$pageDescription = 'View and update your SydneyHappenings account details.';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>My Profile</h1>
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

<form method="post" action="<?= BASE_URL ?>/account/profile.php" class="form-card" novalidate>
    <?= csrfField() ?>

    <fieldset class="form-section">
        <legend>Your details</legend>
        <div class="form-grid">
            <div class="form-field">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" value="<?= e($name) ?>" required
                       <?= isset($errors['name']) ? 'class="has-error" aria-describedby="name-error"' : '' ?>>
                <?php if (isset($errors['name'])): ?><p id="name-error" class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
            </div>

            <div class="form-field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>" required
                       <?= isset($errors['email']) ? 'class="has-error" aria-describedby="email-error"' : '' ?>>
                <?php if (isset($errors['email'])): ?><p id="email-error" class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
            </div>

            <div class="form-field form-field--full">
                <label for="phone">Phone (optional)</label>
                <input type="tel" id="phone" name="phone" value="<?= e($phone) ?>"
                       <?= isset($errors['phone']) ? 'class="has-error" aria-describedby="phone-error"' : '' ?>>
                <?php if (isset($errors['phone'])): ?><p id="phone-error" class="field-error"><?= e($errors['phone']) ?></p><?php endif; ?>
            </div>
        </div>
    </fieldset>

    <?php if ($showLinks): ?>
        <fieldset class="form-section">
            <legend>Links</legend>
            <p>Shown on your events' pages so attendees can find you elsewhere. All optional.</p>
            <div class="form-grid">
                <div class="form-field form-field--full">
                    <label for="website">Website (optional)</label>
                    <input type="url" id="website" name="website" value="<?= e($website) ?>" placeholder="https://example.com"
                           <?= isset($errors['website']) ? 'class="has-error" aria-describedby="website-error"' : '' ?>>
                    <?php if (isset($errors['website'])): ?><p id="website-error" class="field-error"><?= e($errors['website']) ?></p><?php endif; ?>
                </div>

                <div class="form-field">
                    <label for="facebook">Facebook (optional)</label>
                    <input type="url" id="facebook" name="facebook" value="<?= e($facebook) ?>" placeholder="https://facebook.com/yourpage"
                           <?= isset($errors['facebook']) ? 'class="has-error" aria-describedby="facebook-error"' : '' ?>>
                    <?php if (isset($errors['facebook'])): ?><p id="facebook-error" class="field-error"><?= e($errors['facebook']) ?></p><?php endif; ?>
                </div>

                <div class="form-field">
                    <label for="instagram">Instagram (optional)</label>
                    <input type="url" id="instagram" name="instagram" value="<?= e($instagram) ?>" placeholder="https://instagram.com/yourpage"
                           <?= isset($errors['instagram']) ? 'class="has-error" aria-describedby="instagram-error"' : '' ?>>
                    <?php if (isset($errors['instagram'])): ?><p id="instagram-error" class="field-error"><?= e($errors['instagram']) ?></p><?php endif; ?>
                </div>
            </div>
        </fieldset>
    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn">Save changes</button>
    </div>
</form>

<p><a href="<?= BASE_URL ?>/account/change-password.php">Change my password</a></p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
