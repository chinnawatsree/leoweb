<?php
// ตั้งค่าโซนเวลาให้เป็นของประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// กำหนดชื่อไฟล์สำหรับบันทึกข้อมูล
$log_file = 'incoming_data_log.txt';

// ข้อมูลที่จะบันทึก
$log_message = "";

// ตรวจสอบว่ามีข้อมูลเข้ามาหรือไม่
if ($_SERVER['REQUEST_METHOD'] == 'POST' || !empty($_GET)) {

    // วันที่และเวลาที่ข้อมูลเข้ามา
    $timestamp = date('Y-m-d H:i:s');

    // เริ่มสร้างข้อความสำหรับบันทึก
    $log_message .= "========================================\n";
    $log_message .= "Data received at: " . $timestamp . "\n";
    $log_message .= "Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n";

    // บันทึกข้อมูลจาก GET
    if (!empty($_GET)) {
        $log_message .= "--- GET Data ---\n";
        // ใช้ print_r เพื่อแปลง array เป็น string ที่อ่านง่าย
        $log_message .= print_r($_GET, true);
        $log_message .= "\n";
    }

    // บันทึกข้อมูลจาก POST
    if (!empty($_POST)) {
        $log_message .= "--- POST Data ---\n";
        $log_message .= print_r($_POST, true);
        $log_message .= "\n";
    }

    $log_message .= "========================================\n\n";

    // นำข้อความไปต่อท้ายไฟล์ log
    file_put_contents($log_file, $log_message, FILE_APPEND);

    // แสดงผลลัพธ์บนหน้าจอเพื่อยืนยันการทำงาน
    echo "<h1>Data Logged Successfully</h1>";
    echo "<p>Your request has been recorded in <strong>" . $log_file . "</strong>.</p>";
    echo "<pre>" . htmlspecialchars($log_message) . "</pre>";

} else {
    // กรณีที่เปิดไฟล์นี้ตรงๆ โดยไม่มีข้อมูลส่งมา
    echo "<h1>Request Logger</h1>";
    echo "<p>This script logs incoming GET and POST data.</p>";
    echo "<p>Try accessing it with a query string, like: <a href='?test=123&name=gemini'>?test=123&name=gemini</a></p>";
    if (file_exists($log_file)) {
        echo "<h2>Recent Logs:</h2>";
        echo "<pre>" . htmlspecialchars(file_get_contents($log_file)) . "</pre>";
    }
}

?>