<?php
// ============================================================
// 404.php
// Shown whenever a page looks up a record that does not exist (an
// unknown event slug, or an unknown id) - see event.php and
// includes/auth_guard.php's requireEventOwner(). Also works if a
// visitor requests this file directly.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';

if (!headers_sent()) {
    http_response_code(404);
}

$pageTitle = 'Page Not Found - ' . SITE_NAME;
$pageDescription = 'The page you are looking for could not be found.';
require_once __DIR__ . '/includes/header.php';
?>

<h1>Page Not Found</h1>
<p>Sorry, we couldn't find what you were looking for. It may have been removed, or the link may be incorrect.</p>
<p><a class="btn" href="<?= BASE_URL ?>/index.php">Return to the home page</a></p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
