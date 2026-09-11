<?php
// ============================================================
// includes/auth_guard.php
// Starts the session (with secure cookie settings) on every page that
// includes this file, enforces the 30-minute inactivity timeout, and
// provides the functions pages use to check who is logged in and to
// protect pages that require a particular role or event ownership.
//
// This file must be included, and requireLogin()/requireRole()/
// requireEventOwner() called, before any HTML is echoed - a redirect
// only works if nothing has been sent to the browser yet.
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/validate.php';

// Only configure and start the session once, even if this file is
// required by more than one included file on the same page.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,        // expires when the browser closes
        'path'     => '/',
        'httponly' => true,     // not readable from JavaScript, blocks session-stealing via XSS
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Inactivity timeout: if a logged-in session has not made a request in
// the last 30 minutes, force a fresh login rather than trusting a
// session that has been sitting open on a shared or public computer.
define('SESSION_TIMEOUT_SECONDS', 30 * 60);

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
        $_SESSION = [];
        session_destroy();
        session_start();
        setFlash('error', 'You were signed out after 30 minutes of inactivity. Please log in again.');
    }
}
$_SESSION['last_activity'] = time();

/**
 * @return bool True if a user is currently logged in.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * @return int|null The logged-in user's id, or null if not logged in.
 */
function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * @return string|null The logged-in user's role, or null if not logged in.
 */
function currentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * @return string|null The logged-in user's display name, or null.
 */
function currentUserName() {
    return $_SESSION['user_name'] ?? null;
}

/**
 * Stops the page and sends a guest to the login page if nobody is
 * logged in. The current URL is remembered so login.php can send the
 * user back to what they were trying to reach.
 * @return void
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        setFlash('error', 'Please log in to continue.');
        redirect('/login.php');
    }
}

/**
 * Stops the page unless the logged-in user has one of the given roles.
 * @param string|array $roles A single role, or an array of allowed roles.
 * @return void
 */
function requireRole($roles) {
    requireLogin();
    $allowed = is_array($roles) ? $roles : [$roles];
    if (!in_array(currentUserRole(), $allowed, true)) {
        setFlash('error', 'You do not have permission to view that page.');
        redirect('/index.php');
    }
}

/**
 * Confirms the logged-in user is allowed to manage a specific event:
 * the event must exist, and the user must either be its organiser or
 * an admin. This is checked with a real database query every time -
 * an organiser cannot reach another organiser's event just by editing
 * the "id" in the URL, even though the link is never shown to them.
 * @param PDO $pdo
 * @param int $eventId
 * @return array The event row, so the caller does not need to query
 *         for it again.
 */
function requireEventOwner($pdo, $eventId) {
    requireRole(['organiser', 'admin']);

    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = :id");
    $stmt->execute(['id' => $eventId]);
    $event = $stmt->fetch();

    if ($event === false) {
        http_response_code(404);
        require __DIR__ . '/../404.php';
        exit;
    }

    if (currentUserRole() !== 'admin' && (int) $event['organiser_id'] !== currentUserId()) {
        setFlash('error', 'You can only manage your own events.');
        redirect('/organiser/my-events.php');
    }

    return $event;
}

/**
 * Stores a one-time message to show on the next page load, e.g. after
 * a redirect following a form submission.
 * @param string $type "success" or "error" - used as a CSS class.
 * @param string $message
 * @return void
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Outputs the flash message set by setFlash(), if any, and clears it
 * so it does not appear again on the next page.
 * @return void
 */
function displayFlash() {
    if (empty($_SESSION['flash'])) {
        return;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $cssClass = $flash['type'] === 'success' ? 'flash-success' : 'flash-error';
    echo '<div class="flash ' . $cssClass . '" role="alert">' . e($flash['message']) . '</div>';
}
