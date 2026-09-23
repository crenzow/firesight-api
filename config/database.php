<?php
/**
 * Database connection config.
 * Edit DB_HOST / DB_NAME / DB_USER / DB_PASS to match your local MySQL/MariaDB
 * setup (defaults below match a stock XAMPP/Laragon install).
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'firesight_db');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Returns a shared PDO connection. Using PDO (not mysqli) so we get
 * prepared statements with named/positional parameters everywhere,
 * which is our main defense against SQL injection across this API.
 */
function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'message' => 'Database connection failed. Please check your local database configuration.',
            ]);
            exit;
        }
    }

    return $pdo;
}