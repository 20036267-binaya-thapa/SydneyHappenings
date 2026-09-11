<?php
// ============================================================
// wishlist-toggle.php
// Saves or un-saves an event on the logged-in user's wishlist. POST
// only, and a plain form submit works with JavaScript disabled -
// main.js (Step B6) intercepts the same form to do this without a
// full page reload, but never as the only way it works.
//
// This toggles based on what is actually true in the database right
// now (a SELECT before the insert/delete), not on what the submitted
// form claims the current state is - so it self-corrects even if the
// page was left open for a while and the real state changed elsewhere.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';

// Set only by the fetch() call in main.js - a plain form submit (no
// JavaScript) never sends this header, so it reliably tells the two
// callers apart without needing a query string flag.
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/events.php');
}

requireLogin();

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Your session expired. Please try again.');
    redirect('/events.php');
}

// Only ever redirect back to a path that actually lives on this site.
// A leading "/" makes it a same-site absolute path, but "//" or "/\"
// at the start is a classic open-redirect trick browsers can treat as
// a scheme-relative URL to a completely different host - both are
// rejected here, falling back to a safe default instead.
$redirectPath = '/events.php';
$referrer = $_POST['referrer'] ?? '';
if (is_string($referrer) && $referrer !== '' && $referrer[0] === '/'
    && (!isset($referrer[1]) || ($referrer[1] !== '/' && $referrer[1] !== '\\'))) {
    $redirectPath = $referrer;
}

$eventId = (int) ($_POST['event_id'] ?? 0);

$stmt = $pdo->prepare("SELECT id FROM events WHERE id = :id");
$stmt->execute(['id' => $eventId]);
if ($stmt->fetch() === false) {
    setFlash('error', 'That event could not be found.');
    redirect($redirectPath);
}

$stmt = $pdo->prepare("SELECT id FROM wishlists WHERE user_id = :userId AND event_id = :eventId");
$stmt->execute(['userId' => currentUserId(), 'eventId' => $eventId]);
$existing = $stmt->fetch();

// Defaults to "nothing changed" - only the two success branches below
// (and the caught duplicate-key race) move it away from the state it
// already had, so a genuine DB error correctly reports the unchanged
// state back to the JSON caller instead of claiming a save that never
// happened.
$isNowSaved = $existing !== false;

try {
    if ($existing !== false) {
        $stmt = $pdo->prepare("DELETE FROM wishlists WHERE id = :id");
        $stmt->execute(['id' => $existing['id']]);
        $isNowSaved = false;
        setFlash('success', 'Removed from your saved events.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO wishlists (user_id, event_id) VALUES (:userId, :eventId)");
        $stmt->execute(['userId' => currentUserId(), 'eventId' => $eventId]);
        $isNowSaved = true;
        setFlash('success', 'Saved to your events.');
    }
} catch (PDOException $e) {
    // SQLSTATE 23000 is the uq_wishlist_user_event unique key - two
    // near-simultaneous requests both tried to save the same event.
    // Either way the event ends up saved, so this is not a failure
    // from the user's point of view.
    if ($e->getCode() === '23000') {
        $isNowSaved = true;
        setFlash('success', 'Saved to your events.');
    } else {
        error_log('Wishlist toggle failed: ' . $e->getMessage());
        setFlash('error', 'Something went wrong updating your saved events. Please try again.');
    }
}

if ($isAjax) {
    // main.js only needs the resulting state to update the button - it
    // never reads or shows the flash message, and re-fetching a whole
    // HTML page just to throw it away would be wasteful.
    header('Content-Type: application/json');
    echo json_encode(['saved' => $isNowSaved]);
    exit;
}

redirect($redirectPath);
