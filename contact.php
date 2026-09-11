<?php
// ============================================================
// contact.php
// Public contact form, open to guests. Includes a honeypot field
// ("website") that is hidden from sighted users with CSS but visible
// to most automated bots, which tend to fill in every field they find.
// If it is filled in, the submission is quietly discarded without
// telling the bot anything went wrong.
// ============================================================

require_once __DIR__ . '/includes/auth_guard.php';

$errors = [];
$name = '';
$email = '';
$subject = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Your session expired. Please try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $honeypot = trim($_POST['website'] ?? '');

    if ($honeypot !== '') {
        // Almost certainly a bot. Pretend it worked so it does not
        // learn anything, but never touch the database.
        setFlash('success', 'Thank you for your message. We will be in touch soon.');
        redirect('/contact.php');
    }

    $error = validateRequired($name, 'Name') ?? validateLength($name, 2, 100, 'Name');
    if ($error) $errors['name'] = $error;

    $error = validateRequired($email, 'Email') ?? validateEmail($email);
    if ($error) $errors['email'] = $error;

    $error = validateRequired($subject, 'Subject') ?? validateLength($subject, 5, 150, 'Subject');
    if ($error) $errors['subject'] = $error;

    $error = validateRequired($message, 'Message') ?? validateLength($message, 10, 1000, 'Message');
    if ($error) $errors['message'] = $error;

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO enquiries (name, email, subject, message) VALUES (:name, :email, :subject, :message)"
            );
            $stmt->execute(['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message]);

            setFlash('success', 'Thank you for your message. We will be in touch soon.');
            redirect('/contact.php');
        } catch (PDOException $e) {
            error_log('Enquiry insert failed: ' . $e->getMessage());
            $errors['general'] = 'Something went wrong sending your message. Please try again.';
        }
    }
}

$pageTitle = 'Contact Us - ' . SITE_NAME;
$pageDescription = 'Get in touch with the SydneyHappenings team with a question or to become an event organiser.';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Contact Us</h1>
    <p>Have a question, or want to publish your own events? Send us a message.</p>
</div>

<?php if (!empty($errors)): ?>
    <div class="error-summary" role="alert">
        <h2>Please fix the following:</h2>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>/contact.php" class="form-card" novalidate>
    <?= csrfField() ?>

    <!-- Honeypot field: hidden from real users, left for bots to fill in. -->
    <div class="form-field" style="position:absolute; left:-9999px;" aria-hidden="true">
        <label for="website">Leave this field blank</label>
        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
    </div>

    <div class="form-grid">
        <div class="form-field">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="<?= e($name) ?>" required minlength="2" maxlength="100"
                   <?= isset($errors['name']) ? 'class="has-error" aria-describedby="name-error"' : '' ?>>
            <?php if (isset($errors['name'])): ?><p id="name-error" class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>

        <div class="form-field">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" required
                   <?= isset($errors['email']) ? 'class="has-error" aria-describedby="email-error"' : '' ?>>
            <?php if (isset($errors['email'])): ?><p id="email-error" class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
        </div>

        <div class="form-field form-field--full">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" value="<?= e($subject) ?>" required minlength="5" maxlength="150"
                   <?= isset($errors['subject']) ? 'class="has-error" aria-describedby="subject-error"' : '' ?>>
            <?php if (isset($errors['subject'])): ?><p id="subject-error" class="field-error"><?= e($errors['subject']) ?></p><?php endif; ?>
        </div>

        <div class="form-field form-field--full">
            <label for="message">Message</label>
            <textarea id="message" name="message" rows="6" required minlength="10" maxlength="1000"
                      <?= isset($errors['message']) ? 'class="has-error" aria-describedby="message-error"' : '' ?>><?= e($message) ?></textarea>
            <?php if (isset($errors['message'])): ?><p id="message-error" class="field-error"><?= e($errors['message']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Send message</button>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
