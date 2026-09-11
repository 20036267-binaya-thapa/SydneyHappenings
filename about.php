<?php
// ============================================================
// about.php
// Static "about us" page, written as real business copy.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';

$pageTitle = 'About Us - ' . SITE_NAME;
$pageDescription = 'Learn about SydneyHappenings, the community platform connecting Sydney residents with local events and independent organisers.';
require_once __DIR__ . '/includes/header.php';
?>

<h1>About SydneyHappenings</h1>

<p>
    SydneyHappenings is a community events platform built to make it easier for Sydney
    residents to find out what's on nearby, and for independent organisers to reach an
    audience without relying on expensive ticketing platforms or social media algorithms.
</p>

<h2>Why we started</h2>
<p>
    Sydney has an enormous amount of local activity - markets, cultural festivals,
    workshops, sports meet-ups and community fundraisers - but it is often scattered
    across dozens of separate social media pages, suburb noticeboards and mailing lists.
    We built SydneyHappenings as one place to search all of it, organised by category,
    suburb and date.
</p>

<h2>Who it's for</h2>
<p>
    Residents can browse and register for events for free, without creating an account
    just to look around. Independent organisers - community groups, small businesses,
    cultural associations and local councils - can publish events and track who has
    registered, without needing to be technical.
</p>

<h2>How it works</h2>
<p>
    Organisers list an event with a date, venue and capacity. Residents browse or search
    by category, suburb or date, and register for a free place directly through the site.
    Registration numbers update in real time, so both the organiser and prospective
    attendees can see how many spots remain.
</p>

<h2>Get involved</h2>
<p>
    If you run community events in Sydney and would like to publish them here,
    <a href="<?= BASE_URL ?>/contact.php">get in touch</a> - we'll set you up with an
    organiser account.
</p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
