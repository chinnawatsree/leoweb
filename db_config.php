<?php
// @noinspection PhpUndefinedClassInspection
declare(strict_types=1);

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'leonics-testdb';

try {
    /** @var \mysqli $conn */
    $conn = @new \mysqli($host, $user, $pass, $dbname);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception('ไม่สามารถเชื่อมต่อฐานข้อมูล: ' . $conn->connect_error);
    }

    // Set charset
    $conn->set_charset("utf8");

} catch (Exception $e) {
    // For API endpoints, return JSON error
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}

// Check connection
if ($conn->connect_error) {
    // For API endpoints, return JSON error
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูล: ' . $conn->connect_error]);
    exit();
}
?>