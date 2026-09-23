<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/incident_helper.php';

$user = requireAuth();
if ($user['role'] !== 'personnel') {
    sendError('Unauthorized.', 403);
}

$pdo = getDbConnection();

$report_id = $_GET['report_id'] ?? null;
if (!$report_id) {
    sendError('Missing report_id parameter.', 400);
}

try {
    $sql = "
        SELECT 
            h.history_id, h.status, h.notes, h.created_at,
            CONCAT(u.first_name, ' ', u.last_name) AS changed_by_name
        FROM report_status_history h
        LEFT JOIN user u ON h.changed_by = u.user_id
        WHERE h.report_id = :report_id
        ORDER BY h.created_at ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['report_id' => $report_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess(['history' => $history]);
} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
