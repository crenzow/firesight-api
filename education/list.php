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
    'SELECT content_id, title, category, summary, body, image_path, is_featured, created_at
     FROM fire_education_content
     ORDER BY is_featured DESC, created_at DESC'
);
$rows = $stmt->fetchAll();

$result = array_map(function ($row) {
    $imagePath = $row['image_path'];
    if ($imagePath && !str_starts_with($imagePath, 'http')) {
        $fullDiskPath = __DIR__ . '/../' . ltrim($imagePath, '/');
        if (file_exists($fullDiskPath)) {
            $imagePath .= '?v=' . filemtime($fullDiskPath);
        }
    }
    return [
        'content_id' => (int) $row['content_id'],
        'title' => $row['title'],
        'category' => $row['category'],
        'summary' => $row['summary'],
        'body' => $row['body'],
        'image_path' => $imagePath,
        'is_featured' => (bool) $row['is_featured'],
        'created_at' => $row['created_at'],
    ];
}, $rows);

sendSuccess($result);