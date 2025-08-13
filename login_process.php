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
    $hashedPassword = hash('sha256', $password);

    // ตรวจสอบ username และ password ก่อน
    $sql = "
      SELECT u.user_id, u.user_name, r.role_name, s.status_name, u.status_id
      FROM users u
      JOIN roles r ON u.role_id = r.role_id
      JOIN user_status s ON u.status_id = s.status_id
      WHERE u.user_name = ? AND u.user_password = ?
      LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $hashedPassword);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // ตรวจสอบสถานะ active (สมมติว่า 0 = active, 1 = inactive)
        if ($user['status_id'] == 0) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['user_name'];
            $_SESSION['role'] = $user['role_name'];
            header("Location: sidebar.html");
            exit();
        } else {
            $errorMessage = urlencode("บัญชีนี้ถูกระงับ ไม่สามารถเข้าใช้งานเว็บไซต์ได้");
            header("Location: index.html?error=$errorMessage");
            exit();
        }
    } else {
        $errorMessage = urlencode("username or password incorrect");
        header("Location: index.html?error=$errorMessage");
        exit();

    }
} else {
    header("Location: index.html");
    exit();
}
