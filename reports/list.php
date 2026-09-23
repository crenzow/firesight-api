<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/report_helper.php';

/**
 * GET /reports/list.php?mine=1&limit=10  (requires auth)
 * mine=1 restricts to the current resident's own reports (used by Home and
 * Profile). Personnel/admin could omit `mine` in a future phase to see all
 * reports, but that UI doesn't exist yet, so this endpoint currently only
 * supports the "mine" case in practice.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed.', 405);
}

$user = requireAuth();

$mineOnly = ($_GET['mine'] ?? '0') === '1';
$limit = isset($_GET['limit']) ? max(1, min(50, (int) $_GET['limit'])) : 20;

$pdo = getDbConnection();

$sql = 'SELECT r.*, b.barangay_name FROM community_report r
        LEFT JOIN barangay b ON b.barangay_id = r.barangay_id';
$params = [];

if ($mineOnly || $user['role'] === 'resident') {
    // Residents can only ever see their own reports, regardless of the
    // `mine` flag — this is a hard server-side boundary, not just a UI default.
    $sql .= ' WHERE r.user_id = :user_id';
    $params['user_id'] = $user['user_id'];
}

$sql .= ' ORDER BY r.created_at DESC LIMIT ' . $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

sendSuccess(array_map('formatReportRow', $rows));