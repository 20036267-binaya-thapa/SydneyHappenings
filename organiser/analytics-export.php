<?php
// ============================================================
// organiser/analytics-export.php
// Downloads the "Performance by event" table from analytics.php as a
// CSV file. Same role and ownership scoping as that page - a
// non-admin can only ever export their own events, regardless of
// what is in the query string.
//
// This file sends CSV headers, so nothing at all may be echoed before
// them - no header.php, no footer.php, no stray whitespace outside
// the PHP tags anywhere in this file.
// ============================================================

require_once __DIR__ . '/../includes/auth_guard.php';
requireRole(['organiser', 'admin']);

$isAdmin = currentUserRole() === 'admin';

if ($isAdmin) {
    $selectedOrganiserId = isset($_GET['organiser_id']) && $_GET['organiser_id'] !== '' ? (int) $_GET['organiser_id'] : null;
} else {
    // Same access control as analytics.php: never read from $_GET for
    // a non-admin, so the export cannot be pointed at anyone else's data.
    $selectedOrganiserId = currentUserId();
}

$organiserFilterSql = $selectedOrganiserId !== null ? ' AND e.organiser_id = :organiserId' : '';
$organiserFilterParams = $selectedOrganiserId !== null ? ['organiserId' => $selectedOrganiserId] : [];

// registered_count and fill_rate SUM(quantity) - same reasoning as
// analytics.php, which this CSV mirrors. attended/no_show stay row
// counts (how many bookings, not how many tickets, ended up in each
// state).
$stmt = $pdo->prepare(
    "SELECT e.title, e.start_datetime, e.capacity, c.name AS category_name,
            (SELECT COALESCE(SUM(r.quantity), 0) FROM registrations r WHERE r.event_id = e.id AND r.status = 'registered') AS registered_count,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'attended') AS attended_count,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'no_show') AS no_show_count,
            (SELECT AVG(rv.rating) FROM reviews rv WHERE rv.event_id = e.id AND rv.is_hidden = 0) AS avg_rating,
            (SELECT COUNT(*) FROM reviews rv WHERE rv.event_id = e.id AND rv.is_hidden = 0) AS review_count,
            ((SELECT COALESCE(SUM(r.quantity), 0) FROM registrations r WHERE r.event_id = e.id AND r.status = 'registered') / NULLIF(e.capacity, 0)) * 100 AS fill_rate
     FROM events e
     JOIN categories c ON c.id = e.category_id
     WHERE e.status = 'published'{$organiserFilterSql}
     ORDER BY e.start_datetime DESC"
);
$stmt->execute($organiserFilterParams);
$events = $stmt->fetchAll();

/**
 * Neutralises formula-injection characters (=, +, -, @) at the start
 * of a CSV cell, so an event/category title cannot be crafted to run
 * a formula when the file is opened in Excel or Sheets. A leading
 * single quote is the standard mitigation and is invisible in normal
 * spreadsheet display.
 * @param string $value
 * @return string
 */
function csvSafe($value) {
    if (preg_match('/^[=+\-@]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

$filename = 'analytics-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputcsv($output, ['Title', 'Category', 'Date', 'Capacity', 'Tickets booked', 'Fill rate (%)', 'Attended', 'No-show', 'Average rating', 'Review count']);

foreach ($events as $event) {
    fputcsv($output, [
        csvSafe($event['title']),
        csvSafe($event['category_name']),
        formatEventDate($event['start_datetime']),
        (int) $event['capacity'],
        (int) $event['registered_count'],
        $event['fill_rate'] !== null ? round((float) $event['fill_rate']) : '',
        (int) $event['attended_count'],
        (int) $event['no_show_count'],
        $event['avg_rating'] !== null ? number_format((float) $event['avg_rating'], 1) : '',
        (int) $event['review_count'],
    ]);
}

fclose($output);
exit;
