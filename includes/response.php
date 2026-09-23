<?php
/**
 * Shared response helpers so every endpoint returns JSON in a consistent
 * shape. The frontend's ApiClient (services/api/client.ts) expects:
 *   - success: the raw data (array or object), HTTP 200/201
 *   - error:   { "message": "..." }, non-2xx status
 */

function sendSuccess(mixed $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function sendError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo json_encode(['message' => $message]);
    exit;
}

/**
 * Reads and decodes a JSON request body. Returns [] if the body is empty
 * or not valid JSON (e.g. when the request was actually multipart/form-data,
 * which endpoints should read via $_POST instead).
 */
function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Ensures every key in $required is present and non-empty in $data.
 * Sends a 422 error and exits if any are missing.
 */
function requireFields(array $data, array $required): void
{
    $missing = [];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        sendError('Missing required field(s): ' . implode(', ', $missing), 422);
    }
}