<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/report_helper.php';

/**
 * GET /reports/detail.php?report_id=123  (requires auth)
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
$stmt = $pdo->prepare(
    'SELECT r.*, b.barangay_name FROM community_report r
     LEFT JOIN barangay b ON b.barangay_id = r.barangay_id
     WHERE r.report_id = :id LIMIT 1'
);
$stmt->execute(['id' => $reportId]);
$row = $stmt->fetch();

if (!$row) {
    sendError('Report not found.', 404);
}

// Residents may only view their own reports; personnel/admin may view any
// (their dashboard doesn't exist yet, but the permission is already correct).
if ($user['role'] === 'resident' && (int) $row['user_id'] !== (int) $user['user_id']) {
    sendError('You do not have permission to view this report.', 403);
}

sendSuccess(formatReportRow($row));