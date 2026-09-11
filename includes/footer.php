<?php
// ============================================================
// includes/footer.php
// Closes </main> (opened in header.php), renders the site footer,
// and closes the page. Included at the bottom of every page.
// ============================================================
?>
</main>

<footer class="site-footer">
    <div class="container">
        <p><?= e(SITE_NAME) ?> - community and cultural events across Sydney.</p>
        <nav aria-label="Footer navigation">
            <ul>
                <li><a href="<?= BASE_URL ?>/about.php">About</a></li>
                <li><a href="<?= BASE_URL ?>/contact.php">Contact</a></li>
                <li><a href="<?= BASE_URL ?>/privacy.php">Privacy</a></li>
            </ul>
        </nav>
        <p>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. Built for ICT726 Web Development.</p>
    </div>
</footer>

<?php
    // filemtime() as a cache-buster: the query string changes only
    // when main.js itself actually changes, so a browser that already
    // has an old copy cached is forced to fetch the new one on the
    // very next page load, instead of silently keeping stale behaviour
    // until someone thinks to hard-refresh.
    $mainJsPath = dirname(__DIR__) . '/assets/js/main.js';
    $mainJsVersion = file_exists($mainJsPath) ? filemtime($mainJsPath) : time();
?>
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= $mainJsVersion ?>"></script>
</body>
</html>
