<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

/**
 * POST /auth/forgot_password.php
 * Body: { email }
 *
 * Generates a reset token valid for 1 hour. In production this would be
 * emailed to the resident via PHPMailer/SMTP; for local development it is
 * written to PHP's error log instead so you can test the reset flow without
 * configuring a mail server. Search your PHP error log for "PASSWORD RESET
 * TOKEN" after calling this endpoint.
 *
 * Always returns a generic success message, even if the email doesn't
 * exist — this prevents the endpoint from being used to check which emails
 * are registered.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed.', 405);
}

$body = getJsonBody();
requireFields($body, ['email']);

$email = strtolower(trim($body['email']));
if (!isValidEmail($email)) {
    sendError('Please enter a valid email address.', 422);
}

$pdo = getDbConnection();
$stmt = $pdo->prepare('SELECT user_id FROM user WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if ($user) {
    $token = generateSecureToken();
    $insert = $pdo->prepare(
        'INSERT INTO password_reset (user_id, token, is_used, expires_at)
         VALUES (:user_id, :token, 0, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
    );
    $insert->execute(['user_id' => $user['user_id'], 'token' => $token]);

    // TODO (Phase 2 polish): replace with an actual email send via PHPMailer.
    error_log('PASSWORD RESET TOKEN for ' . $email . ': ' . $token);
}

sendSuccess(['message' => 'If an account exists for that email, password reset instructions have been sent.']);