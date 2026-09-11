<?php
// ============================================================
// privacy.php
// Plain-English privacy notice covering what is collected, why, who
// sees it, how it is protected, retention, deletion requests, and
// cookie use, per CLAUDE.md section 12.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';

$pageTitle = 'Privacy Policy - ' . SITE_NAME;
$pageDescription = 'How SydneyHappenings collects, uses and protects your personal information.';
require_once __DIR__ . '/includes/header.php';
?>

<h1>Privacy Policy</h1>

<h2>What we collect</h2>
<p>
    When you create an account, we collect your name, email address, and password.
    Phone number is optional. If you contact us through the contact form, we collect
    the name, email address and message you provide. We do not collect payment details,
    government identifiers, or any information beyond what is needed to run the site.
</p>

<h2>Why we collect it</h2>
<p>
    Your name and email identify your account and let you log in, register for events,
    and manage your bookings. If you register for an event, your name and registration
    date are shown to that event's organiser so they can manage attendance. We use
    contact form details only to respond to your enquiry.
</p>

<h2>Who sees it</h2>
<p>
    Event organisers can see the name and registration date of people registered for
    their own events only - never your email address or phone number. Site
    administrators can see full account details in order to manage the platform.
    We do not sell or share your information with any other third party.
</p>

<h2>How it is protected</h2>
<p>
    Passwords are never stored directly - they are transformed with a one-way hashing
    function that cannot be reversed, even by us. All data is stored in a database that
    is not directly accessible from the internet, and all form submissions are protected
    against common attacks such as cross-site request forgery.
</p>

<h2>How long we keep it</h2>
<p>
    Account and registration information is kept for as long as your account is active,
    so you can see your event history. Contact form messages are kept until they have
    been handled and are no longer needed.
</p>

<h2>Requesting deletion</h2>
<p>
    You can update or remove your personal details at any time from your
    <a href="<?= BASE_URL ?>/account/profile.php">profile page</a>. To request that your
    account be deleted entirely, <a href="<?= BASE_URL ?>/contact.php">contact us</a> and
    we will remove your account and associated registrations.
</p>

<h2>Cookies</h2>
<p>
    SydneyHappenings uses a single session cookie to keep you logged in while you use
    the site. This cookie does not track you across other websites, and is deleted when
    you log out or close your browser. We do not use advertising or analytics cookies.
</p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
