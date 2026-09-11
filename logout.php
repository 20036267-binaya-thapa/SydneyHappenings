<?php
// ============================================================
// logout.php
// Logging out changes state (ends the session), so like every other
// state-changing action on this site it is POST only - a GET request
// (e.g. a link, or a prefetch by the browser) can never log a user out.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    redirect('/index.php');
}

// Clear all session data, then destroy the session itself and its
// cookie, so nothing about the login survives on this browser.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

session_start();
setFlash('success', 'You have been logged out.');
redirect('/index.php');
