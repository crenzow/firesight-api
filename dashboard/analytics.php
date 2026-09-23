<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

/**
 * GET /dashboard/analytics.php  (requires auth, personnel only)
 * Returns DashboardAnalytics for the BFP dashboard home screen.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed.', 405);
}

$user = requireAuth();
if ($user['role'] !== 'personnel') {
    sendError('Unauthorized.', 403);
}

$pdo = getDbConnection();

// Active incidents: pending + verified + dispatched
$activeStmt = $pdo->query(
    "SELECT COUNT(*) FROM community_report WHERE status IN ('pending', 'accepted', 'dispatched')"
);
$activeIncidents = (int) $activeStmt->fetchColumn();

// Pending verification: status = 'pending'
$pendingStmt = $pdo->query(
    "SELECT COUNT(*) FROM community_report WHERE status = 'pending'"
);
$pendingVerification = (int) $pendingStmt->fetchColumn();

// Resolved today (resolved incidents from community_report for current date)
$resolvedStmt = $pdo->query(
    "SELECT COUNT(*) FROM community_report WHERE status = 'resolved' AND DATE(created_at) = CURDATE()"
);
$resolvedToday = (int) $resolvedStmt->fetchColumn();

// Total resolved this month (resolved incidents from community_report for current month)
$monthStmt = $pdo->query(
    "SELECT COUNT(*) FROM community_report WHERE status = 'resolved' AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"
);
$totalThisMonth = (int) $monthStmt->fetchColumn();

sendSuccess([
    'active_incidents' => $activeIncidents,
    'pending_verification' => $pendingVerification,
    'resolved_today' => $resolvedToday,
    'total_this_month' => $totalThisMonth,
]);
