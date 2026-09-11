<?php
// ============================================================
// account/change-password.php
// Lets a logged-in user change their own password. Requires the
// current password to be re-entered, so a session left open on a
// shared computer cannot be used to lock the real owner out.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireLogin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Your session expired. Please try again.';
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
    $stmt->execute(['id' => currentUserId()]);
    $user = $stmt->fetch();

    if (!password_verify($currentPassword, $user['password_hash'])) {
        $errors['current_password'] = 'Current password is incorrect.';
    }

    $error = validatePassword($newPassword);
    if ($error) $errors['new_password'] = $error;

    if ($confirmPassword !== $newPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $stmt->execute(['hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => currentUserId()]);

            setFlash('success', 'Your password has been changed.');
            redirect('/account/profile.php');
        } catch (PDOException $e) {
            error_log('Password change failed: ' . $e->getMessage());
            $errors['general'] = 'Something went wrong changing your password. Please try again.';
        }
    }
}

$pageTitle = 'Change Password - ' . SITE_NAME;
$pageDescription = 'Change the password for your SydneyHappenings account.';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Change Password</h1>

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

<form method="post" action="<?= BASE_URL ?>/account/change-password.php" class="form-card" novalidate>
    <?= csrfField() ?>

    <div class="form-field">
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" required
               <?= isset($errors['current_password']) ? 'class="has-error" aria-describedby="current-password-error"' : '' ?>>
        <?php if (isset($errors['current_password'])): ?><p id="current-password-error" class="field-error"><?= e($errors['current_password']) ?></p><?php endif; ?>
    </div>

    <div class="form-field">
        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password" required minlength="8"
               aria-describedby="new-password-hint<?= isset($errors['new_password']) ? ' new-password-error' : '' ?>">
        <p id="new-password-hint" class="field-hint">At least 8 characters, with one letter and one number.</p>
        <?php if (isset($errors['new_password'])): ?><p id="new-password-error" class="field-error"><?= e($errors['new_password']) ?></p><?php endif; ?>
    </div>

    <div class="form-field">
        <label for="confirm_password">Confirm new password</label>
        <input type="password" id="confirm_password" name="confirm_password" required
               <?= isset($errors['confirm_password']) ? 'class="has-error" aria-describedby="confirm-password-error"' : '' ?>>
        <?php if (isset($errors['confirm_password'])): ?><p id="confirm-password-error" class="field-error"><?= e($errors['confirm_password']) ?></p><?php endif; ?>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Change password</button>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
