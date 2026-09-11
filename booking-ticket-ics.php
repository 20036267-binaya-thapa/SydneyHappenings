<?php
// ============================================================
// booking-ticket-ics.php
// Downloads one booking as a .ics calendar file ("Add to calendar" on
// booking-confirmation.php). Same ownership check as that page - a
// reference in the URL must never expose someone else's booking.
//
// Nothing is echoed before the Content-Type/Content-Disposition
// headers below - not even whitespace outside the PHP tags - or the
// download would be corrupted with stray output ahead of it.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';
requireLogin();

$reference = trim($_GET['ref'] ?? '');

$stmt = $pdo->prepare(
    "SELECT r.user_id, r.quantity, r.booking_reference,
            e.title, e.description, e.start_datetime, e.end_datetime,
            v.name AS venue_name, v.address, v.suburb, v.postcode
     FROM registrations r
     JOIN events e ON e.id = r.event_id
     JOIN venues v ON v.id = e.venue_id
     WHERE r.booking_reference = :ref AND r.status = 'registered'"
);
$stmt->execute(['ref' => $reference]);
$booking = $stmt->fetch();

if ($booking === false || (int) $booking['user_id'] !== currentUserId()) {
    setFlash('error', 'That booking could not be found.');
    redirect('/account/my-registrations.php');
}

/**
 * Escapes one value for use inside a single .ics text field. RFC 5545
 * requires a backslash, comma, semicolon, or line break inside text
 * content to be backslash-escaped, or a calendar app may misparse the
 * field (e.g. a comma being read as a list separator).
 * @param string $value
 * @return string
 */
function icsEscapeText($value) {
    return str_replace(
        ['\\', ',', ';', "\r\n", "\n"],
        ['\\\\', '\\,', '\\;', '\\n', '\\n'],
        $value
    );
}

/**
 * Folds one logical .ics line to the 75-octet limit RFC 5545 sets, so
 * older or stricter calendar parsers do not choke on a very long
 * SUMMARY/DESCRIPTION/LOCATION line. Continuation lines start with a
 * single space, which parsers strip back out when unfolding.
 * @param string $line Already-escaped field content, e.g. "SUMMARY:...".
 * @return string
 */
function icsFoldLine($line) {
    $folded = '';
    while (strlen($line) > 75) {
        $folded .= substr($line, 0, 75) . "\r\n ";
        $line = substr($line, 75);
    }
    return $folded . $line;
}

// DTSTART/DTEND are written as "floating" local time (no Z suffix, no
// TZID) rather than converted to UTC - the database only ever stored a
// plain local Sydney date and time, never a timezone, so a UTC
// conversion here would depend on guessing the server's PHP timezone
// setting and could silently shift the event by hours. A floating time
// displays as-is in whatever calendar the .ics is imported into, which
// is the more predictable choice for a single-timezone event site.
$startIcs = date('Ymd\THis', strtotime($booking['start_datetime']));
$endIcs = date('Ymd\THis', strtotime($booking['end_datetime']));

// DTSTAMP (when this file was generated) is required by RFC 5545 to be
// a real UTC timestamp, unlike DTSTART/DTEND above.
$stampIcs = gmdate('Ymd\THis\Z');

$location = $booking['venue_name'] . ', ' . $booking['address'] . ', ' . $booking['suburb'] . ' ' . $booking['postcode'];
$summary = $booking['title'] . ' (' . (int) $booking['quantity'] . ' ticket' . ((int) $booking['quantity'] === 1 ? '' : 's') . ')';

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//SydneyHappenings//Booking//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    // The booking reference is already globally unique (uq_booking_reference)
    // and never reused, so it doubles as a stable, collision-free UID.
    'UID:' . $booking['booking_reference'] . '@sydneyhappenings.example',
    'DTSTAMP:' . $stampIcs,
    'DTSTART:' . $startIcs,
    'DTEND:' . $endIcs,
    'SUMMARY:' . icsEscapeText($summary),
    'DESCRIPTION:' . icsEscapeText($booking['description']),
    'LOCATION:' . icsEscapeText($location),
    'END:VEVENT',
    'END:VCALENDAR',
];

$icsContent = implode("\r\n", array_map('icsFoldLine', $lines)) . "\r\n";

$filename = 'booking-' . $booking['booking_reference'] . '.ics';

header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($icsContent));

echo $icsContent;
