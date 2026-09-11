<?php
// ============================================================
// includes/header.php
// Shared page header: <head>, skip link, site header and navigation,
// and the flash message area. Opens <main> - the including page is
// responsible for closing it before requiring footer.php.
//
// The calling page must require includes/auth_guard.php first (so
// isLoggedIn()/currentUserRole()/displayFlash() exist), and should set
// these variables before requiring this file:
//   $pageTitle        (required) - text for <title>
//   $pageDescription  (required) - text for <meta name="description">
//   $canonicalUrl     (optional) - overrides the auto-generated canonical
//                       URL. Used by event.php, whose ?slug= parameter
//                       is the page's real identity, not a filter.
//   $extraHead        (optional) - raw HTML string appended to <head>,
//                       used for Open Graph tags and JSON-LD on event.php.
// ============================================================

if (!isset($pageTitle)) {
    $pageTitle = SITE_NAME;
}
if (!isset($pageDescription)) {
    $pageDescription = 'Discover community and cultural events across Sydney.';
}
if (!isset($canonicalUrl)) {
    $canonicalUrl = currentCanonicalUrl();
}

// Open Graph / Twitter Card defaults - every page gets a complete,
// valid set of these tags, not just event.php. A calling page can
// override any of the four by setting the variable itself before
// requiring this file (event.php does, for its own title/description/
// image); everything else falls back to the page's own $pageTitle/
// $pageDescription, "website", and a generic site image.
if (!isset($ogType)) {
    $ogType = 'website';
}
if (!isset($ogTitle)) {
    $ogTitle = $pageTitle;
}
if (!isset($ogDescription)) {
    $ogDescription = $pageDescription;
}
if (!isset($ogImage)) {
    // Must be absolute - Open Graph/Twitter consumers never resolve a
    // relative path themselves.
    $ogScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $ogImage = $ogScheme . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/assets/img/og-default.png';
}

// Used only by the user-menu avatar/trigger below. Distinctively named
// (not $firstName/$initial) since this file is require()'d into every
// page's global scope and must not collide with a calling page's own
// variables.
$headerUserFirstName = '';
$headerUserInitial = '';
if (isLoggedIn()) {
    $headerUserFirstName = explode(' ', currentUserName())[0];
    $headerUserInitial = mb_strtoupper(mb_substr($headerUserFirstName, 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en-AU">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<?php
    // Same filemtime() cache-buster as footer.php's main.js include -
    // a style.css already cached from before a change is fetched fresh
    // on the next load rather than staying stale until a hard refresh.
    $styleCssPath = dirname(__DIR__) . '/assets/css/style.css';
    $styleCssVersion = file_exists($styleCssPath) ? filemtime($styleCssPath) : time();
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= $styleCssVersion ?>">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:type" content="<?= e($ogType) ?>">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<meta property="og:title" content="<?= e($ogTitle) ?>">
<meta property="og:description" content="<?= e($ogDescription) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($ogTitle) ?>">
<meta name="twitter:description" content="<?= e($ogDescription) ?>">
<meta name="twitter:image" content="<?= e($ogImage) ?>">
<?php if (isset($extraHead)) { echo $extraHead; } ?>
</head>
<body>
<a href="#main-content" class="skip-link">Skip to main content</a>

<header class="site-header" id="siteHeader">
    <div class="container header-inner">
        <a href="<?= BASE_URL ?>/index.php" class="site-logo"><?= e(SITE_NAME) ?></a>

        <button type="button" class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="siteNav">
            <span class="sr-only">Menu</span>&#9776;
        </button>

        <?php if (isLoggedIn()): ?>
            <!-- On mobile the avatar stays visible in the header beside the
                 toggle, even though .header-actions itself is hidden there -
                 this markup order lets that happen without duplicating it. -->
            <details class="user-menu user-menu-mobile-only">
                <summary class="user-menu-trigger">
                    <span class="user-avatar" aria-hidden="true"><?= e($headerUserInitial) ?></span>
                    <span class="sr-only">Account menu</span>
                </summary>
                <?php require __DIR__ . '/user-menu-panel.php'; ?>
            </details>
        <?php endif; ?>

        <nav class="site-nav" id="siteNav" aria-label="Main navigation">
            <ul>
                <li><a href="<?= BASE_URL ?>/index.php" <?= isCurrentPage('index.php') ? 'aria-current="page"' : '' ?>>Home</a></li>
                <li><a href="<?= BASE_URL ?>/events.php" <?= isCurrentPage('events.php') ? 'aria-current="page"' : '' ?>>Events</a></li>
                <li><a href="<?= BASE_URL ?>/about.php" <?= isCurrentPage('about.php') ? 'aria-current="page"' : '' ?>>About</a></li>
                <li><a href="<?= BASE_URL ?>/contact.php" <?= isCurrentPage('contact.php') ? 'aria-current="page"' : '' ?>>Contact</a></li>

                <?php if (!isLoggedIn()): ?>
                    <!-- Mobile-only duplicate of the Log in/Register links that
                         also live in .header-actions - hidden at 768px+ via CSS
                         so only one copy is ever visible at a given width. -->
                    <li class="site-nav-mobile-auth">
                        <a href="<?= BASE_URL ?>/login.php" <?= isCurrentPage('login.php') ? 'aria-current="page"' : '' ?>>Log in</a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/register.php" <?= isCurrentPage('register.php') ? 'aria-current="page"' : '' ?>>Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="header-actions">
            <?php if (isLoggedIn()): ?>
                <?php if (currentUserRole() === 'admin'): ?>
                    <a class="header-actions-link" href="<?= BASE_URL ?>/admin/dashboard.php" <?= (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false && !isCurrentPage('reviews.php')) ? 'aria-current="page"' : '' ?>>Admin</a>
                <?php endif; ?>
                <?php if (currentUserRole() === 'organiser' || currentUserRole() === 'admin'): ?>
                    <a class="header-actions-link" href="<?= BASE_URL ?>/organiser/dashboard.php" <?= (strpos($_SERVER['SCRIPT_NAME'], '/organiser/') !== false && !isCurrentPage('analytics.php')) ? 'aria-current="page"' : '' ?>>Organiser</a>
                <?php endif; ?>

                <details class="user-menu user-menu-desktop-only">
                    <summary class="user-menu-trigger">
                        <span class="user-avatar" aria-hidden="true"><?= e($headerUserInitial) ?></span>
                        <span class="user-menu-name"><?= e($headerUserFirstName) ?></span>
                        <span class="sr-only">Account menu</span>
                        <span class="user-menu-chevron" aria-hidden="true">&#9662;</span>
                    </summary>
                    <?php require __DIR__ . '/user-menu-panel.php'; ?>
                </details>
            <?php else: ?>
                <a class="header-actions-link" href="<?= BASE_URL ?>/login.php" <?= isCurrentPage('login.php') ? 'aria-current="page"' : '' ?>>Log in</a>
                <a class="btn" href="<?= BASE_URL ?>/register.php">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="container">
    <?php displayFlash(); ?>
</div>

<main id="main-content" class="container">
