<?php
// ============================================================
// admin/user-edit.php
// Lets an admin change another user's name, email, phone, role and
// active status. When an admin opens their own record, the role and
// active-status controls are disabled so they can never lock
// themselves out or strip their own admin access.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole('admin');

$userId = (int) ($_GET['id'] ?? $_POST['user_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

if ($user === false) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$isSelf = $userId === currentUserId();
$errors = [];

$name = $user['name'];
$email = $user['email'];
$phone = $user['phone'] ?? '';
$role = $user['role'];
$isActive = (int) $user['is_active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Your session expired. Please try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // An admin editing their own account cannot change their own role
    // or active status, no matter what the submitted form contains.
    $role = $isSelf ? $user['role'] : ($_POST['role'] ?? '');
    $isActive = $isSelf ? (int) $user['is_active'] : (isset($_POST['is_active']) ? 1 : 0);

    $error = validateName($name);
    if ($error) $errors['name'] = $error;

    $error = validateRequired($email, 'Email') ?? validateEmail($email);
    if ($error) {
        $errors['email'] = $error;
    } else {
        $error = validateEmailUnique($pdo, $email, $userId);
        if ($error) $errors['email'] = $error;
    }

    $error = validatePhone($phone);
    if ($error) $errors['phone'] = $error;

    if (!$isSelf && !in_array($role, ['attendee', 'organiser', 'admin'], true)) {
        $errors['role'] = 'Please choose a valid role.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE users SET name = :name, email = :email, phone = :phone, role = :role, is_active = :isActive
                 WHERE id = :id"
            );
            $stmt->execute([
                'name' => $name, 'email' => $email, 'phone' => $phone !== '' ? $phone : null,
                'role' => $role, 'isActive' => $isActive, 'id' => $userId,
            ]);
            setFlash('success', 'User updated.');
            redirect('/admin/users.php');
        } catch (PDOException $e) {
            error_log('User update failed: ' . $e->getMessage());
            $errors['general'] = 'Something went wrong saving this user. Please try again.';
        }
    }
}

$pageTitle = 'Edit User - ' . SITE_NAME;
$pageDescription = 'Edit a user account on SydneyHappenings.';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Edit User</h1>

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

<?php if ($isSelf): ?>
    <p class="hint">You are editing your own account, so your role and active status cannot be changed here.</p>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>/admin/user-edit.php?id=<?= $userId ?>" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="user_id" value="<?= $userId ?>">

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

    <div class="form-field">
        <label for="phone">Phone</label>
        <input type="tel" id="phone" name="phone" value="<?= e($phone) ?>"
               <?= isset($errors['phone']) ? 'class="has-error" aria-describedby="phone-error"' : '' ?>>
        <?php if (isset($errors['phone'])): ?><p id="phone-error" class="field-error"><?= e($errors['phone']) ?></p><?php endif; ?>
    </div>

    <div class="form-field">
        <label for="role">Role</label>
        <select id="role" name="role" <?= $isSelf ? 'disabled' : '' ?>>
            <option value="attendee" <?= $role === 'attendee' ? 'selected' : '' ?>>Attendee</option>
            <option value="organiser" <?= $role === 'organiser' ? 'selected' : '' ?>>Organiser</option>
            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
    </div>

    <div class="form-field checkbox-field">
        <input type="checkbox" id="is_active" name="is_active" value="1" <?= $isActive === 1 ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>>
        <label for="is_active">Account active</label>
    </div>

    <button type="submit" class="btn">Save changes</button>
    <a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/users.php">Cancel</a>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
