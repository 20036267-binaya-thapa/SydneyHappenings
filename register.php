<?php
// ============================================================
// register.php
// Public sign-up form. Creates a new 'attendee' account - organiser
// and admin accounts are only ever created/promoted by an admin from
// /admin/users.php, never through this public form.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';

$errors = [];

// Preserve the user's input across a failed submission (except the
// passwords - those are always re-entered for security).
$name = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Your session expired. Please try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $consent = isset($_POST['consent']);

    $error = validateName($name);
    if ($error) $errors['name'] = $error;

    $error = validateRequired($email, 'Email') ?? validateEmail($email);
    if ($error) {
        $errors['email'] = $error;
    } else {
        $error = validateEmailUnique($pdo, $email);
        if ($error) $errors['email'] = $error;
    }

    $error = validatePassword($password);
    if ($error) $errors['password'] = $error;

    if ($confirmPassword !== $password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    $error = validatePhone($phone);
    if ($error) $errors['phone'] = $error;

    if (!$consent) {
        $errors['consent'] = 'You must agree to the privacy policy to create an account.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash, phone, role)
                 VALUES (:name, :email, :password_hash, :phone, 'attendee')"
            );
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'phone' => $phone !== '' ? $phone : null,
            ]);

            setFlash('success', 'Your account has been created. Please log in.');
            redirect('/login.php');
        } catch (PDOException $e) {
            error_log('Registration failed: ' . $e->getMessage());
            $errors['general'] = 'Something went wrong creating your account. Please try again.';
        }
    }
}

$pageTitle = 'Create an Account - ' . SITE_NAME;
$pageDescription = 'Create a free SydneyHappenings account to register for community and cultural events across Sydney.';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Create an Account</h1>
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

<form method="post" action="<?= BASE_URL ?>/register.php" class="form-card form-card--narrow" novalidate>
    <?= csrfField() ?>

    <div class="form-grid">
        <div class="form-field form-field--full">
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" value="<?= e($name) ?>"
                   required minlength="2" maxlength="100"
                   <?= isset($errors['name']) ? 'class="has-error" aria-describedby="name-error"' : '' ?>>
            <?php if (isset($errors['name'])): ?>
                <p id="name-error" class="field-error"><?= e($errors['name']) ?></p>
            <?php endif; ?>
        </div>

        <div class="form-field form-field--full">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>"
                   required
                   <?= isset($errors['email']) ? 'class="has-error" aria-describedby="email-error"' : '' ?>>
            <?php if (isset($errors['email'])): ?>
                <p id="email-error" class="field-error"><?= e($errors['email']) ?></p>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   required minlength="8"
                   aria-describedby="password-hint<?= isset($errors['password']) ? ' password-error' : '' ?>">
            <p id="password-hint" class="field-hint">At least 8 characters, with one letter and one number.</p>
            <?php if (isset($errors['password'])): ?>
                <p id="password-error" class="field-error"><?= e($errors['password']) ?></p>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   required
                   <?= isset($errors['confirm_password']) ? 'class="has-error" aria-describedby="confirm-password-error"' : '' ?>>
            <?php if (isset($errors['confirm_password'])): ?>
                <p id="confirm-password-error" class="field-error"><?= e($errors['confirm_password']) ?></p>
            <?php endif; ?>
        </div>

        <div class="form-field form-field--full">
            <label for="phone">Phone (optional)</label>
            <input type="tel" id="phone" name="phone" value="<?= e($phone) ?>"
                   <?= isset($errors['phone']) ? 'class="has-error" aria-describedby="phone-error"' : '' ?>>
            <?php if (isset($errors['phone'])): ?>
                <p id="phone-error" class="field-error"><?= e($errors['phone']) ?></p>
            <?php endif; ?>
        </div>

        <div class="form-field form-field--full checkbox-field">
            <input type="checkbox" id="consent" name="consent" value="1"
                   <?= isset($_POST['consent']) ? 'checked' : '' ?>
                   required
                   <?= isset($errors['consent']) ? 'aria-describedby="consent-error"' : '' ?>>
            <label for="consent">I agree to the <a href="<?= BASE_URL ?>/privacy.php">privacy policy</a></label>
        </div>
        <?php if (isset($errors['consent'])): ?>
            <p id="consent-error" class="field-error form-field--full"><?= e($errors['consent']) ?></p>
        <?php endif; ?>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Create account</button>
    </div>
</form>

<p>Already have an account? <a href="<?= BASE_URL ?>/login.php">Log in</a></p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
