<?php
// Database connection configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'leonics-testdb';

// Create database connection
$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset("utf8");

// Check connection
if ($conn->connect_error) {
    // For API endpoints, return JSON error
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูล: ' . $conn->connect_error]);
    exit();
}
?>