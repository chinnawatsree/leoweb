<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "leonics-testdb";

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password'];

    // Hash รหัสผ่านด้วย SHA256
    $hashedPassword = hash('sha256', $password);

    // เตรียมคำสั่ง SQL ตรวจสอบผู้ใช้ (active เท่านั้น)
    $sql = "
      SELECT u.user_id, u.user_name, r.role_name, s.status_name
      FROM users u
      JOIN roles r ON u.role_id = r.role_id
      JOIN user_status s ON u.status_id = s.status_id
      WHERE u.user_name = ? AND u.user_password = ? AND u.status_id = 0
      LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $hashedPassword);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // เก็บข้อมูล session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_name'] = $user['user_name'];
        $_SESSION['role'] = $user['role_name'];

        // ไปหน้าหลัก (เปลี่ยน URL ตามต้องการ)
        header("Location: sidebar.html");
        exit();
    } else {
        // ล้มเหลว เก็บ error message ใน session
        $_SESSION['error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง หรือบัญชีถูกระงับ";
        header("Location: index.html");
        exit();
    }
} else {
    // ไม่ใช่ POST
    header("Location: index.html");
    exit();
} 
