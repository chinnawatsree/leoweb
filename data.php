<?php
header('Content-Type: application/json');

// ตั้งค่าการเชื่อมต่อ MySQL
$host = "localhost";
$user = "root";
$password = "";
$database = "leonics-testdb";

// เชื่อมต่อฐานข้อมูล
$conn = new mysqli($host, $user, $password, $database);
$conn->set_charset("utf8");

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    echo json_encode(["error" => "เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $conn->connect_error]);
    exit();
}

// สร้างคำสั่ง SQL
$sql = "SELECT * FROM customer";
$result = $conn->query($sql);

// เตรียมข้อมูลออกเป็น JSON
$data = [];
while ($row = $result->fetch_assoc()) {
    // แปลงค่าจาก binary status เป็น readable
    $row['status'] = ($row['status'] === "\x00\x00\x00\x00\x00") ? 'ไม่ใช้งาน' : 'ใช้งาน';
    $data[] = $row;
}

// ส่งผลลัพธ์
echo json_encode($data);

// ปิดการเชื่อมต่อ
$conn->close();
