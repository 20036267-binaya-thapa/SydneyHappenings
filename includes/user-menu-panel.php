<?php
// ============================================================
// includes/user-menu-panel.php
// The dropdown content for the account .user-menu in header.php.
// Required twice per page (once for the mobile-only trigger, once
// for the desktop-only trigger) so each has its own independent
// open/close state - only one trigger is ever visible at a given
// viewport width, so this never shows two open menus at once.
// Assumes isLoggedIn() is already true - header.php only requires
// this file inside that check.
// ============================================================
?>
<div class="user-menu-panel">
    <div class="user-menu-header">
        <p class="user-menu-fullname"><?= e(currentUserName()) ?></p>
        <p class="user-menu-role"><?= e(ucfirst(currentUserRole())) ?></p>
    </div>
    <hr class="user-menu-divider">

    <?php if (currentUserRole() === 'admin'): ?>
        <a class="user-menu-item" href="<?= BASE_URL ?>/admin/dashboard.php" <?= (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false && !isCurrentPage('reviews.php')) ? 'aria-current="page"' : '' ?>>Admin dashboard</a>
        <a class="user-menu-item" href="<?= BASE_URL ?>/admin/reviews.php" <?= isCurrentPage('reviews.php') ? 'aria-current="page"' : '' ?>>Reviews</a>
    <?php endif; ?>
    <?php if (currentUserRole() === 'organiser' || currentUserRole() === 'admin'): ?>
        <a class="user-menu-item" href="<?= BASE_URL ?>/organiser/dashboard.php" <?= (strpos($_SERVER['SCRIPT_NAME'], '/organiser/') !== false && !isCurrentPage('analytics.php')) ? 'aria-current="page"' : '' ?>>Organiser dashboard</a>
        <a class="user-menu-item" href="<?= BASE_URL ?>/organiser/analytics.php" <?= isCurrentPage('analytics.php') ? 'aria-current="page"' : '' ?>>Analytics</a>
    <?php endif; ?>

    <a class="user-menu-item" href="<?= BASE_URL ?>/account/my-registrations.php" <?= isCurrentPage('my-registrations.php') ? 'aria-current="page"' : '' ?>>My Tickets</a>
    <a class="user-menu-item" href="<?= BASE_URL ?>/account/my-wishlist.php" <?= isCurrentPage('my-wishlist.php') ? 'aria-current="page"' : '' ?>>Saved Events</a>
    <a class="user-menu-item" href="<?= BASE_URL ?>/account/profile.php" <?= isCurrentPage('profile.php') ? 'aria-current="page"' : '' ?>>My Profile</a>

    <hr class="user-menu-divider">
    <form method="post" action="<?= BASE_URL ?>/logout.php" class="logout-form">
        <?= csrfField() ?>
        <button type="submit" class="user-menu-item user-menu-item-button">Log out</button>
    </form>
</div>
