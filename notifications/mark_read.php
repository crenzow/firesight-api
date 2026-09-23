<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

/**
 * POST /notifications/mark_read.php  (requires auth)
 * Body: { notification_id: number }  OR  { all: true }
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed.', 405);
}

$user = requireAuth();
$body = getJsonBody();
$pdo = getDbConnection();

if (!empty($body['all'])) {
    $stmt = $pdo->prepare('UPDATE notification SET is_read = 1 WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $user['user_id']]);
    sendSuccess(['message' => 'All notifications marked as read.']);
}

requireFields($body, ['notification_id']);
$notificationId = (int) $body['notification_id'];

// Scoped to the current user so residents can't mark other people's
// notifications as read by guessing IDs.
$stmt = $pdo->prepare(
    'UPDATE notification SET is_read = 1 WHERE notification_id = :id AND user_id = :user_id'
);
$stmt->execute(['id' => $notificationId, 'user_id' => $user['user_id']]);

sendSuccess(['message' => 'Notification marked as read.']);