<?php
// ============================================================
// admin/users.php
// Lists every user on the platform. Admins can jump to user-edit.php
// to change a role or details, or quickly toggle an account active/
// inactive from here. An admin can never deactivate their own account
// from this page - the button is simply not shown on their own row.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Your session expired. Please try again.');
        redirect('/admin/users.php');
    }

    $targetId = (int) $_POST['toggle_user_id'];

    if ($targetId === currentUserId()) {
        // Belt-and-braces: even if this were ever submitted, an admin
        // cannot deactivate their own account.
        setFlash('error', 'You cannot deactivate your own account.');
    } else {
        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = :id");
        $stmt->execute(['id' => $targetId]);
        setFlash('success', 'User status updated.');
    }

    redirect('/admin/users.php');
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'Manage Users - ' . SITE_NAME;
$pageDescription = 'View and manage all user accounts on SydneyHappenings.';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Manage Users</h1>
</div>

<div class="data-table-wrapper">
    <table class="data-table">
        <caption>All users</caption>
        <thead>
            <tr>
                <th scope="col">Name</th>
                <th scope="col">Email</th>
                <th scope="col">Role</th>
                <th scope="col">Status</th>
                <th scope="col">Joined</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= e($user['name']) ?></td>
                    <td><?= e($user['email']) ?></td>
                    <td><?= e(ucfirst($user['role'])) ?></td>
                    <td><?= (int) $user['is_active'] === 1 ? 'Active' : 'Deactivated' ?></td>
                    <td><?= e(formatEventDate($user['created_at'])) ?></td>
                    <td>
                        <div class="button-row">
                            <a class="btn btn-small" href="<?= BASE_URL ?>/admin/user-edit.php?id=<?= (int) $user['id'] ?>">Edit</a>
                            <?php if ((int) $user['id'] !== currentUserId()): ?>
                                <form method="post" action="<?= BASE_URL ?>/admin/users.php" class="logout-form">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="toggle_user_id" value="<?= (int) $user['id'] ?>">
                                    <button type="submit" class="btn btn-small btn-secondary">
                                        <?= (int) $user['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
