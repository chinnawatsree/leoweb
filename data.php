<?php
header('Content-Type: application/json; charset=utf-8');

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

// ตรวจสอบว่า query สำเร็จไหม
if (!$result) {
    echo json_encode(["error" => "ไม่สามารถดึงข้อมูลได้: " . $conn->error]);
    $conn->close();
    exit();
}

// เตรียมข้อมูลออกเป็น JSON
$data = [];
while ($row = $result->fetch_assoc()) {
    // ตรวจสอบและแปลงค่าจาก binary status เป็น readable
    // สมมุติว่า field status เป็น tinyint(1) หรือ boolean
    $status = $row['status'];
    
    if ($status === null) {
        $row['status'] = 'ไม่ทราบ';
    } else if ($status == 0 || $status === "\x00") {
        $row['status'] = 'ไม่ใช้งาน';
    } else {
        $row['status'] = 'ใช้งาน';
    }

    $data[] = $row;
}

// ส่งผลลัพธ์ JSON
echo json_encode($data, JSON_UNESCAPED_UNICODE);

// ปิดการเชื่อมต่อ
$conn->close();
?>
