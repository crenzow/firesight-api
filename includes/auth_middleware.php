<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/response.php';

/**
 * Reads the Authorization: Bearer <token> header, regardless of how the
 * local server exposes headers (some Apache/PHP-FPM configs don't populate
 * $_SERVER['HTTP_AUTHORIZATION'] by default).
 */
function getBearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

    if (!$header && function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'authorization') {
                $header = $value;
                break;
            }
        }
    }

    if (!$header || stripos($header, 'Bearer ') !== 0) {
        return null;
    }

    return trim(substr($header, 7));
}

/**
 * Validates the request's bearer token against auth_token, and returns the
 * authenticated user's row (user_id, role, ...) on success. On failure, it
 * sends a 401 response and terminates the script — callers can assume this
 * function only ever returns a valid, authenticated user.
 */
function requireAuth(): array
{
    $token = getBearerToken();
    if (!$token) {
        sendError('Authentication required. Please sign in again.', 401);
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        'SELECT u.* FROM auth_token t
         JOIN user u ON u.user_id = t.user_id
         WHERE t.token = :token AND (t.expires_at IS NULL OR t.expires_at > NOW())
         LIMIT 1'
    );
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        sendError('Your session has expired. Please sign in again.', 401);
    }

    if ((int) $user['is_active'] === 0) {
        sendError('This account has been deactivated. Please contact BFP Lian.', 403);
    }

    return $user;
}

/** Generates a cryptographically random opaque token for auth_token / password_reset rows. */
function generateSecureToken(): string
{
    return bin2hex(random_bytes(32));
}