<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/validate.php';

/**
 * POST /auth/reset_password.php
 * Body: { token, new_password }
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed.', 405);
}

$body = getJsonBody();
requireFields($body, ['token', 'new_password']);

$token = trim($body['token']);
$newPassword = (string) $body['new_password'];

if (!isStrongEnoughPassword($newPassword)) {
    sendError('Password must be at least 8 characters.', 422);
}

$pdo = getDbConnection();
$stmt = $pdo->prepare(
    'SELECT * FROM password_reset WHERE token = :token AND is_used = 0 AND expires_at > NOW() LIMIT 1'
);
$stmt->execute(['token' => $token]);
$resetRow = $stmt->fetch();

if (!$resetRow) {
    sendError('This reset link is invalid or has expired. Please request a new one.', 400);
}

try {
    $pdo->beginTransaction();

    $updatePassword = $pdo->prepare('UPDATE user SET password = :password WHERE user_id = :user_id');
    $updatePassword->execute([
        'password' => password_hash($newPassword, PASSWORD_BCRYPT),
        'user_id' => $resetRow['user_id'],
    ]);

    $markUsed = $pdo->prepare('UPDATE password_reset SET is_used = 1 WHERE reset_id = :id');
    $markUsed->execute(['id' => $resetRow['reset_id']]);

    // Invalidate all existing sessions for this account as a security
    // precaution — a reset password implies the old one may be compromised.
    $revokeTokens = $pdo->prepare('DELETE FROM auth_token WHERE user_id = :user_id');
    $revokeTokens->execute(['user_id' => $resetRow['user_id']]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    sendError('Unable to reset your password right now. Please try again.', 500);
}

sendSuccess(['message' => 'Your password has been reset successfully. Please sign in with your new password.']);