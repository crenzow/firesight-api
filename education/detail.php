<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';

/**
 * GET /education/detail.php?content_id=1  (public — no auth required)
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed.', 405);
}

$contentId = (int) ($_GET['content_id'] ?? $_GET['id'] ?? 0);
if ($contentId <= 0) {
    sendError('Missing or invalid content_id.', 422);
}

$pdo = getDbConnection();
$stmt = $pdo->prepare(
    'SELECT content_id, title, category, summary, body, image_path, is_featured, created_at
     FROM fire_education_content WHERE content_id = :id LIMIT 1'
);
$stmt->execute(['id' => $contentId]);
$row = $stmt->fetch();

if (!$row) {
    sendError('Article not found.', 404);
}

$imagePath = $row['image_path'];
if ($imagePath && !str_starts_with($imagePath, 'http')) {
    $fullDiskPath = __DIR__ . '/../' . ltrim($imagePath, '/');
    if (file_exists($fullDiskPath)) {
        $imagePath .= '?v=' . filemtime($fullDiskPath);
    }
}

sendSuccess([
    'content_id' => (int) $row['content_id'],
    'title' => $row['title'],
    'category' => $row['category'],
    'summary' => $row['summary'],
    'body' => $row['body'],
    'image_path' => $imagePath,
    'is_featured' => (bool) $row['is_featured'],
    'created_at' => $row['created_at'],
]);