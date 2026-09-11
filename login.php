<?php
// ============================================================
// login.php
// Public login form. On success, regenerates the session id (so a
// session token issued before login can never be reused after login),
// then redirects by role.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';

$errors = [];
$email = '';

// A same-site path to return to after logging in, e.g. from event.php's
// "Log in to book" link (?return=/event.php?slug=...). Read on the
// initial GET and carried through the form as a hidden field so it
// survives the POST below - never trusted without the same safe-path
// check wishlist-toggle.php's referrer field already uses, since this
// also ends up directly in a Location header.
$returnPath = $_GET['return'] ?? '';
if (!is_string($returnPath) || $returnPath === '' || $returnPath[0] !== '/'
    || (isset($returnPath[1]) && ($returnPath[1] === '/' || $returnPath[1] === '\\'))) {
    $returnPath = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Your session expired. Please try again.';
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (trim($email) === '' || trim($password) === '') {
        $errors['general'] = 'Please enter both your email and password.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // The same generic message is used whether the email does not
        // exist or the password is wrong - telling an attacker which
        // one was correct would confirm which emails have accounts.
        if ($user === false || !password_verify($password, $user['password_hash'])) {
            $errors['general'] = 'Invalid email or password.';
        } elseif ((int) $user['is_active'] === 0) {
            $errors['general'] = 'This account has been deactivated. Please contact an administrator.';
        } else {
            // A fresh session id stops session fixation: an id that
            // existed before the user proved who they are is discarded.
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            setFlash('success', 'Welcome back, ' . $user['name'] . '.');

            // A same-site "return" path posted with the form (e.g. from
            // event.php's "Log in to book" link) takes priority over
            // $_SESSION['redirect_after_login'], which auth_guard.php
            // sets when a protected page redirects here on its own -
            // both send the user back to where they actually meant to
            // go, rather than the generic role-based dashboard below.
            $redirectTo = $_POST['return'] ?? $_SESSION['redirect_after_login'] ?? null;
            unset($_SESSION['redirect_after_login']);

            // Same same-site-path check as wishlist-toggle.php's
            // referrer field: must start with a single "/", never "//"
            // or "/\" - both of those are scheme-relative URLs a
            // browser could send to a completely different host.
            if (is_string($redirectTo) && $redirectTo !== '' && $redirectTo[0] === '/'
                && (!isset($redirectTo[1]) || ($redirectTo[1] !== '/' && $redirectTo[1] !== '\\'))) {
                header('Location: ' . $redirectTo);
                exit;
            }

            if ($user['role'] === 'admin') {
                redirect('/admin/dashboard.php');
            } elseif ($user['role'] === 'organiser') {
                redirect('/organiser/dashboard.php');
            } else {
                redirect('/index.php');
            }
        }
    }
}

$pageTitle = 'Log In - ' . SITE_NAME;
$pageDescription = 'Log in to your SydneyHappenings account to register for events and manage your bookings.';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Log In</h1>
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

<form method="post" action="<?= BASE_URL ?>/login.php" class="form-card form-card--narrow" novalidate>
    <?= csrfField() ?>
    <?php if ($returnPath !== ''): ?>
        <input type="hidden" name="return" value="<?= e($returnPath) ?>">
    <?php endif; ?>

    <div class="form-field">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
    </div>

    <div class="form-field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-block">Log in</button>
    </div>
</form>

<p>Don't have an account? <a href="<?= BASE_URL ?>/register.php">Create one</a></p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
