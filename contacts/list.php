<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';

/**
 * GET /contacts/list.php  (public — no auth required, matches
 * contactService.list() on the frontend). Hotline numbers must be reachable
 * even if a resident's session has expired in an emergency.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed.', 405);
}

$pdo = getDbConnection();
$stmt = $pdo->query(
    'SELECT contact_id, name, category, phone_number, description, is_primary
     FROM emergency_contact ORDER BY is_primary DESC, sort_order ASC'
);
$rows = $stmt->fetchAll();

$result = array_map(function ($row) {
    return [
        'contact_id' => (int) $row['contact_id'],
        'name' => $row['name'],
        'category' => $row['category']--,
        'phone_number' => $row['phone_number'],
        'description' => $row['description'],
        'is_primary' => (bool) $row['is_primary'],
    ];
}, $rows);

sendSuccess($result);