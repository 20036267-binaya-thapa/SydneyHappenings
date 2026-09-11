<?php
// ============================================================
// admin/enquiries.php
// Lists messages submitted through the public contact form and lets
// an admin mark them handled/unhandled.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Your session expired. Please try again.');
        redirect('/admin/enquiries.php');
    }

    $enquiryId = (int) ($_POST['enquiry_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE enquiries SET is_handled = NOT is_handled WHERE id = :id");
    $stmt->execute(['id' => $enquiryId]);
    setFlash('success', 'Enquiry updated.');
    redirect('/admin/enquiries.php');
}

$enquiries = $pdo->query("SELECT * FROM enquiries ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'Enquiries - ' . SITE_NAME;
$pageDescription = 'View messages submitted through the contact form.';
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Enquiries</h1>

<?php if (empty($enquiries)): ?>
    <p class="empty-state">No enquiries have been submitted yet.</p>
<?php else: ?>
    <div class="data-table-wrapper">
        <table class="data-table">
            <caption>Contact form enquiries</caption>
            <thead>
                <tr>
                    <th scope="col">From</th>
                    <th scope="col">Subject</th>
                    <th scope="col">Message</th>
                    <th scope="col">Received</th>
                    <th scope="col">Status</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enquiries as $enquiry): ?>
                    <tr>
                        <td><?= e($enquiry['name']) ?><br><?= e($enquiry['email']) ?></td>
                        <td><?= e($enquiry['subject']) ?></td>
                        <td><?= nl2br(e($enquiry['message'])) ?></td>
                        <td><?= e(formatEventDate($enquiry['created_at'])) ?></td>
                        <td><?= (int) $enquiry['is_handled'] === 1 ? 'Handled' : 'Unhandled' ?></td>
                        <td>
                            <form method="post" action="<?= BASE_URL ?>/admin/enquiries.php" class="logout-form">
                                <?= csrfField() ?>
                                <input type="hidden" name="enquiry_id" value="<?= (int) $enquiry['id'] ?>">
                                <button type="submit" class="btn btn-small btn-secondary">
                                    Mark <?= (int) $enquiry['is_handled'] === 1 ? 'unhandled' : 'handled' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
