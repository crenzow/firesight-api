<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/user_helper.php';

/**
 * GET /profile/get.php  (requires auth)
 * Returns the current user's full AppUser shape — used to refresh Profile
 * screen data after an edit, independent of what's cached in secure storage.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed.', 405);
}

$user = requireAuth();
$pdo = getDbConnection();

sendSuccess(getUserWithAddress($pdo, (int) $user['user_id']));