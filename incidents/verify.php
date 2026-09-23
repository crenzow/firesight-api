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
$notes = $body['notes'] ?? null;

if (!$report_id) {
    sendError('Missing report_id.', 400);
}

try {
    $pdo->beginTransaction();
    
    // Check if incident is pending
    $stmt = $pdo->prepare("SELECT status FROM community_report WHERE report_id = :report_id");
    $stmt->execute(['report_id' => $report_id]);
    $currentStatus = $stmt->fetchColumn();
    
    if (!$currentStatus) {
        sendError('Report not found.', 404);
    }
    if ($currentStatus !== 'pending') {
        sendError('Only pending reports can be verified.', 400);
    }
    
    // Update status
    $stmt = $pdo->prepare("UPDATE community_report SET status = 'accepted' WHERE report_id = :report_id");
    $stmt->execute(['report_id' => $report_id]);
    
    // Insert history
    $stmt = $pdo->prepare("INSERT INTO report_status_history (report_id, status, notes, changed_by) VALUES (:report_id, 'accepted', :notes, :changed_by)");
    $stmt->execute([
        'report_id' => $report_id,
        'notes' => $notes,
        'changed_by' => $user['user_id']
    ]);

    notifyReportStatusChange($pdo, (int) $report_id, 'accepted');
    
    $pdo->commit();
    
    $incident = fetchIncident($pdo, (int)$report_id);
    sendSuccess($incident);
} catch (PDOException $e) {
    $pdo->rollBack();
    sendError('Database error: ' . $e->getMessage(), 500);
}
