<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

/**
 * GET /notifications/list.php  (requires auth)
 * Returns only the current user's notifications, most recent first.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed.', 405);
}

$user = requireAuth();
$pdo = getDbConnection();

$stmt = $pdo->prepare(
    'SELECT n.notification_id, n.title, n.message, n.notification_type, n.is_read, n.created_at,
            r.report_id, r.latitude, r.longitude
     FROM notification n
    LEFT JOIN community_report r ON n.message REGEXP CONCAT("(^|[^0-9])#", r.report_id, "([^0-9]|$)")
     WHERE n.user_id = :user_id
     ORDER BY n.created_at DESC LIMIT 50'
);
$stmt->execute(['user_id' => $user['user_id']]);
$rows = $stmt->fetchAll();

$result = array_map(function ($row) {
    return [
        'notification_id' => (int) $row['notification_id'],
        'title' => $row['title'],
        'message' => $row['message'],
        'notification_type' => $row['notification_type'],
        'is_read' => (bool) $row['is_read'],
        'created_at' => $row['created_at'],
        'report_id' => $row['report_id'] !== null ? (int) $row['report_id'] : null,
        'latitude' => $row['latitude'] !== null ? (float) $row['latitude'] : null,
        'longitude' => $row['longitude'] !== null ? (float) $row['longitude'] : null,
    ];
}, $rows);

sendSuccess($result);