<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

/**
 * GET /announcements/list.php  (requires auth — matches announcementService.list()
 * on the frontend, which does not pass auth=false)
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed.', 405);
}

requireAuth();
$pdo = getDbConnection();

$stmt = $pdo->query(
    'SELECT announcement_id, title, content, announcement_type, created_at
     FROM announcement ORDER BY created_at DESC LIMIT 50'
);
$rows = $stmt->fetchAll();

$result = array_map(function ($row) {
    return [
        'announcement_id' => (int) $row['announcement_id'],
        'title' => $row['title'],
        'content' => $row['content'],
        'announcement_type' => $row['announcement_type'],
        'created_at' => $row['created_at'],
    ];
}, $rows);

sendSuccess($result);