<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';

/**
 * GET /education/list.php  (public — no auth required)
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed.', 405);
}

$pdo = getDbConnection();
$stmt = $pdo->query(
    'SELECT content_id, title, category, summary, body, image_path, read_minutes, is_featured
     FROM fire_education_content
     ORDER BY is_featured DESC, created_at DESC'
);
$rows = $stmt->fetchAll();

$result = array_map(function ($row) {
    return [
        'content_id' => (int) $row['content_id'],
        'title' => $row['title'],
        'category' => $row['category'],
        'summary' => $row['summary'],
        'body' => $row['body'],
        'image_path' => $row['image_path'],
        'read_minutes' => (int) $row['read_minutes'],
        'is_featured' => (bool) $row['is_featured'],
    ];
}, $rows);

sendSuccess($result);