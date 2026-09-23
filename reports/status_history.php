<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

/**
 * GET /reports/status_history.php?report_id=123  (requires auth)
 * Returns ReportStatusHistoryEntry[] ordered oldest -> newest, matching the
 * order the Report Detail screen's timeline renders in.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed.', 405);
}

$user = requireAuth();

$reportId = (int) ($_GET['report_id'] ?? 0);
if ($reportId <= 0) {
    sendError('Missing or invalid report_id.', 422);
}

$pdo = getDbConnection();

// Same ownership check as detail.php — a resident can't read another
// resident's report timeline just by guessing an ID.
$ownerCheck = $pdo->prepare('SELECT user_id FROM community_report WHERE report_id = :id LIMIT 1');
$ownerCheck->execute(['id' => $reportId]);
$report = $ownerCheck->fetch();

if (!$report) {
    sendError('Report not found.', 404);
}
if ($user['role'] === 'resident' && (int) $report['user_id'] !== (int) $user['user_id']) {
    sendError('You do not have permission to view this report.', 403);
}

$stmt = $pdo->prepare(
    'SELECT history_id, status, notes, created_at FROM report_status_history
     WHERE report_id = :report_id ORDER BY created_at ASC'
);
$stmt->execute(['report_id' => $reportId]);
$rows = $stmt->fetchAll();

$result = array_map(function ($row) {
    return [
        'history_id' => (int) $row['history_id'],
        'status' => $row['status'],
        'notes' => $row['notes'],
        'created_at' => $row['created_at'],
    ];
}, $rows);

sendSuccess($result);