<?php
// ============================================================
// admin/categories.php
// Category CRUD. A category still referenced by at least one event
// cannot be hard-deleted (schema.sql's foreign key would reject it
// anyway) - it is soft-deleted instead, by setting is_active = 0, so
// it disappears from public dropdowns but existing events keep working.
// A category with no events attached is removed outright.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole('admin');

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$errors = [];
$name = '';
$description = '';

if ($editId !== null) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
    $stmt->execute(['id' => $editId]);
    $editingCategory = $stmt->fetch();
    if ($editingCategory) {
        $name = $editingCategory['name'];
        $description = $editingCategory['description'] ?? '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Your session expired. Please try again.';
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $editId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $error = validateRequired($name, 'Name') ?? validateLength($name, 2, 80, 'Name');
        if ($error) $errors['name'] = $error;

        if (empty($errors)) {
            try {
                $slug = makeUniqueSlug($pdo, 'categories', $name, $editId);
                if ($editId) {
                    $stmt = $pdo->prepare(
                        "UPDATE categories SET name = :name, slug = :slug, description = :description WHERE id = :id"
                    );
                    $stmt->execute(['name' => $name, 'slug' => $slug, 'description' => $description !== '' ? $description : null, 'id' => $editId]);
                    setFlash('success', 'Category updated.');
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :description)"
                    );
                    $stmt->execute(['name' => $name, 'slug' => $slug, 'description' => $description !== '' ? $description : null]);
                    setFlash('success', 'Category created.');
                }
                redirect('/admin/categories.php');
            } catch (PDOException $e) {
                error_log('Category save failed: ' . $e->getMessage());
                $errors['general'] = 'Something went wrong saving this category.';
            }
        }
    } elseif ($action === 'delete') {
        $targetId = (int) ($_POST['category_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE category_id = :id");
        $stmt->execute(['id' => $targetId]);
        $eventsUsingIt = (int) $stmt->fetchColumn();

        if ($eventsUsingIt > 0) {
            $stmt = $pdo->prepare("UPDATE categories SET is_active = 0 WHERE id = :id");
            $stmt->execute(['id' => $targetId]);
            setFlash('success', "Category is used by {$eventsUsingIt} event(s), so it was deactivated instead of deleted.");
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute(['id' => $targetId]);
            setFlash('success', 'Category deleted.');
        }
        redirect('/admin/categories.php');
    } elseif ($action === 'activate') {
        $targetId = (int) ($_POST['category_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE categories SET is_active = 1 WHERE id = :id");
        $stmt->execute(['id' => $targetId]);
        setFlash('success', 'Category reactivated.');
        redirect('/admin/categories.php');
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$pageTitle = 'Manage Categories - ' . SITE_NAME;
$pageDescription = 'Add, edit and deactivate event categories.';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Manage Categories</h1>

<?php if (!empty($errors)): ?>
    <div class="error-summary" role="alert">
        <h2>Please fix the following:</h2>
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<h2><?= $editId ? 'Edit Category' : 'Add Category' ?></h2>
<form method="post" action="<?= BASE_URL ?>/admin/categories.php" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <?php if ($editId): ?><input type="hidden" name="category_id" value="<?= $editId ?>"><?php endif; ?>

    <div class="form-field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= e($name) ?>" required
               <?= isset($errors['name']) ? 'class="has-error" aria-describedby="name-error"' : '' ?>>
        <?php if (isset($errors['name'])): ?><p id="name-error" class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
    </div>

    <div class="form-field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="2"><?= e($description) ?></textarea>
    </div>

    <button type="submit" class="btn"><?= $editId ? 'Save changes' : 'Add category' ?></button>
    <?php if ($editId): ?><a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/categories.php">Cancel</a><?php endif; ?>
</form>

<h2>All Categories</h2>
<div class="data-table-wrapper">
    <table class="data-table">
        <caption>Categories</caption>
        <thead>
            <tr>
                <th scope="col">Name</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td><?= e($category['name']) ?></td>
                    <td><?= (int) $category['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
                    <td>
                        <div class="button-row">
                            <a class="btn btn-small" href="<?= BASE_URL ?>/admin/categories.php?edit=<?= (int) $category['id'] ?>">Edit</a>
                            <?php if ((int) $category['is_active'] === 1): ?>
                                <form method="post" action="<?= BASE_URL ?>/admin/categories.php" class="logout-form" data-confirm="Delete or deactivate '<?= e($category['name']) ?>'?">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                                    <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= BASE_URL ?>/admin/categories.php" class="logout-form">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                                    <button type="submit" class="btn btn-small btn-secondary">Reactivate</button>
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
