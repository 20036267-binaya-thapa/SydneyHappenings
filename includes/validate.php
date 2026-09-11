<?php
// ============================================================
// includes/validate.php
// Every server-side validation rule used by the site's forms, plus
// the CSRF token helpers and the e() escaping wrapper.
//
// Convention: every validateX() function returns a single error
// message (string) if the input is invalid, or null if it is fine.
// Callers collect these into an array of errors and only proceed if
// the array is empty. HTML5 attributes (required, min, pattern, etc.)
// are added in the forms too, but only as a convenience - a request
// could always skip the browser and post directly, so these PHP
// checks are the real gatekeepers.
// ============================================================

/**
 * Escapes a value for safe output inside HTML, preventing XSS.
 * Used on every value that is echoed onto a page, including values
 * that came from the database (another user's event title, etc.).
 * @param string|null $value
 * @return string
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * @param string $value
 * @param string $fieldLabel Human-readable field name for the error message.
 * @return string|null
 */
function validateRequired($value, $fieldLabel) {
    if (trim((string) $value) === '') {
        return $fieldLabel . ' is required.';
    }
    return null;
}

/**
 * @param string $value
 * @param int $min
 * @param int $max
 * @param string $fieldLabel
 * @return string|null
 */
function validateLength($value, $min, $max, $fieldLabel) {
    $len = strlen(trim((string) $value));
    if ($len < $min || $len > $max) {
        return "{$fieldLabel} must be between {$min} and {$max} characters.";
    }
    return null;
}

/**
 * @param string $email
 * @return string|null
 */
function validateEmail($email) {
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return 'Please enter a valid email address.';
    }
    return null;
}

/**
 * Checks that an email address is not already registered.
 * @param PDO $pdo
 * @param string $email
 * @param int|null $excludeUserId Ignore this user's own row (used when
 *        a logged-in user edits their profile without changing email).
 * @return string|null
 */
function validateEmailUnique($pdo, $email, $excludeUserId = null) {
    $sql = "SELECT id FROM users WHERE email = :email";
    $params = ['email' => $email];
    if ($excludeUserId !== null) {
        $sql .= " AND id != :excludeUserId";
        $params['excludeUserId'] = $excludeUserId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetch() !== false) {
        return 'An account with this email already exists.';
    }
    return null;
}

/**
 * @param string $name
 * @return string|null
 */
function validateName($name) {
    $error = validateRequired($name, 'Name');
    if ($error) return $error;

    $error = validateLength($name, 2, 100, 'Name');
    if ($error) return $error;

    if (!preg_match("/^[A-Za-z' -]+$/", trim($name))) {
        return 'Name may only contain letters, spaces, hyphens and apostrophes.';
    }
    return null;
}

/**
 * Australian phone numbers, e.g. "0412 345 678" or "(02) 9876 5432".
 * The field is optional, so an empty value is valid.
 * @param string $phone
 * @return string|null
 */
function validatePhone($phone) {
    $phone = trim($phone);
    if ($phone === '') {
        return null;
    }
    // Accepts digits, spaces, and optional leading "+61" or "(0X)".
    if (!preg_match('/^(\+?61|0)[0-9\s()]{8,12}$/', $phone)) {
        return 'Please enter a valid Australian phone number.';
    }
    return null;
}

/**
 * Validates an optional link field (website, Facebook, Instagram) on
 * an organiser's profile. Empty is valid - only checked once a value
 * is actually supplied. Requires an http/https scheme, so a saved
 * link can never end up pointing at something like a javascript: URL.
 * @param string $url
 * @param string $fieldLabel
 * @return string|null
 */
function validateUrl($url, $fieldLabel) {
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false || !preg_match('#^https?://#i', $url)) {
        return "Please enter a valid {$fieldLabel} URL, starting with http:// or https://.";
    }
    return null;
}

/**
 * @param string $password
 * @return string|null
 */
function validatePassword($password) {
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }
    return null;
}

/**
 * @param mixed $value
 * @param string $fieldLabel
 * @param int|null $min Optional minimum allowed value.
 * @return string|null
 */
function validateInteger($value, $fieldLabel, $min = null) {
    if (!preg_match('/^-?\d+$/', trim((string) $value))) {
        return "{$fieldLabel} must be a whole number.";
    }
    if ($min !== null && (int) $value < $min) {
        return "{$fieldLabel} must be at least {$min}.";
    }
    return null;
}

/**
 * @param mixed $value
 * @param string $fieldLabel
 * @param float|null $min Optional minimum allowed value.
 * @return string|null
 */
function validateDecimal($value, $fieldLabel, $min = null) {
    if (!is_numeric($value)) {
        return "{$fieldLabel} must be a number.";
    }
    if ($min !== null && (float) $value < $min) {
        return "{$fieldLabel} must be at least {$min}.";
    }
    return null;
}

/**
 * Checks that a value is a real, parseable date and time.
 * @param string $value Expected format from a <input type="datetime-local">
 *        field: "Y-m-d\TH:i".
 * @param string $fieldLabel
 * @return string|null
 */
function validateDatetime($value, $fieldLabel) {
    $timestamp = strtotime($value);
    if ($value === '' || $timestamp === false) {
        return "Please enter a valid {$fieldLabel}.";
    }
    return null;
}

/**
 * @param string $value
 * @param string $fieldLabel
 * @return string|null
 */
function validateFutureDatetime($value, $fieldLabel) {
    $timestamp = strtotime($value);
    if ($timestamp === false || $timestamp < time()) {
        return "{$fieldLabel} cannot be in the past.";
    }
    return null;
}

/**
 * @param string $endValue
 * @param string $startValue
 * @param string $endLabel
 * @return string|null
 */
function validateDatetimeAfter($endValue, $startValue, $endLabel) {
    $end = strtotime($endValue);
    $start = strtotime($startValue);
    if ($end === false || $start === false || $end <= $start) {
        return "{$endLabel} must be after the start date and time.";
    }
    return null;
}

/**
 * Validates an uploaded image. The image is optional, so a request
 * with no file attached is valid (returns null).
 * @param array $file One entry from $_FILES, e.g. $_FILES['image'].
 * @return string|null
 */
function validateImageUpload($file) {
    // No file chosen, or the browser sent nothing at all - that is fine,
    // the image field is optional.
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'The image failed to upload. Please try again.';
    }

    $maxBytes = 2 * 1024 * 1024; // 2 MB
    if ($file['size'] > $maxBytes) {
        return 'The image must be 2 MB or smaller.';
    }

    // Checking the file's actual content type (not just the extension the
    // browser reports) stops someone renaming a script to "photo.jpg".
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $mimeType = mime_content_type($file['tmp_name']);
    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        return 'Images must be a JPG, PNG or WebP file.';
    }

    return null;
}

/**
 * Returns the current session's CSRF token, creating one first if this
 * is the first form on this session. The same token is reused for every
 * form for the life of the session.
 * @return string
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * @param string|null $token The token submitted with the form.
 * @return bool True if it matches the session's token.
 */
function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    // hash_equals() compares in constant time, so an attacker cannot use
    // response-timing differences to guess the token character by character.
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Convenience wrapper that outputs the hidden CSRF input every POST
 * form needs. Saves repeating the same line in every form template.
 * @return string HTML for a hidden input field.
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . e(generateCsrfToken()) . '">';
}
