<?php
// ============================================================
// includes/db.php
// Opens one shared PDO database connection for the whole site.
// Any page that needs the database does:
//     require_once __DIR__ . '/includes/db.php';
// and then has a ready-to-use $pdo variable.
// ============================================================

require_once __DIR__ . '/../config.php';

try {
    // The DSN (Data Source Name) tells PDO which driver to use (mysql),
    // which server and database to connect to, and which character set
    // to use. utf8mb4 matches the charset used in schema.sql.
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        // Throw a PDOException on any database error instead of just
        // returning false, so mistakes are never silently ignored.
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

        // Rows come back as associative arrays, e.g. $row['title'],
        // instead of the default mix of numeric and named keys.
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // Use the database driver's real prepared statements rather
        // than PHP emulating them. Real prepared statements send the
        // query and the data separately, which is what actually
        // prevents SQL injection and keeps numeric types correct.
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // Never show a raw exception to a visitor - it can leak the
    // database name, table structure, or credentials. The detail goes
    // to the server's error log; the visitor sees a plain message.
    error_log('Database connection failed: ' . $e->getMessage());
    die('Sorry, something went wrong on our end. Please try again later.');
}
