<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

/**
 * POST /auth/logout.php  (requires Authorization: Bearer <token>)
 * Deletes the current token so it can no longer be used — the frontend also
 * clears its local secure storage regardless of whether this call succeeds.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed.', 405);
}

requireAuth(); // ensures the token is valid before we bother deleting it

$token = getBearerToken();
$pdo = getDbConnection();
$stmt = $pdo->prepare('DELETE FROM auth_token WHERE token = :token');
$stmt->execute(['token' => $token]);

sendSuccess(['message' => 'Signed out successfully.']);