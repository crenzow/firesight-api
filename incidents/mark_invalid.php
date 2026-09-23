<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/notification_helper.php';
require_once __DIR__ . '/incident_helper.php';

$user = requireAuth();
if ($user['role'] !== 'personnel') {
    sendError('Unauthorized.', 403);
}

$pdo = getDbConnection();
$body = getJsonBody();

$report_id = $body['report_id'] ?? null;
$reason = $body['reason'] ?? null;

if (!$report_id || !$reason) {
    sendError('Missing report_id or reason.', 400);
}

try {
    $pdo->beginTransaction();
    
    // Update status
    $stmt = $pdo->prepare("UPDATE community_report SET status = 'invalid' WHERE report_id = :report_id");
    $stmt->execute(['report_id' => $report_id]);
    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        sendError('Report not found.', 404);
    }
    
    // Insert history
    $stmt = $pdo->prepare("INSERT INTO report_status_history (report_id, status, notes, changed_by) VALUES (:report_id, 'invalid', :notes, :changed_by)");
    $stmt->execute([
        'report_id' => $report_id,
        'notes' => $reason,
        'changed_by' => $user['user_id']
    ]);

    notifyReportStatusChange($pdo, (int) $report_id, 'invalid');
    
    $pdo->commit();
    
    $incident = fetchIncident($pdo, (int)$report_id);
    sendSuccess($incident);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendError('Database error: ' . $e->getMessage(), 500);
}
