<?php
// ============================================================
// sitemap.php
// Outputs an XML sitemap of every public page plus every published
// event, so search engines can discover them. Served as XML, not HTML.
// ============================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=UTF-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $scheme . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;

$staticPages = ['/index.php', '/events.php', '/about.php', '/contact.php', '/privacy.php', '/login.php', '/register.php'];

$stmt = $pdo->query("SELECT slug, updated_at FROM events WHERE status = 'published' ORDER BY start_datetime DESC");
$events = $stmt->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <?php foreach ($staticPages as $path): ?>
    <url>
        <loc><?= e($base . $path) ?></loc>
    </url>
    <?php endforeach; ?>
    <?php foreach ($events as $event): ?>
    <url>
        <loc><?= e($base . '/event.php?slug=' . urlencode($event['slug'])) ?></loc>
        <lastmod><?= date('Y-m-d', strtotime($event['updated_at'])) ?></lastmod>
    </url>
    <?php endforeach; ?>
</urlset>
