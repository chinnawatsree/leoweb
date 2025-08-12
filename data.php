<?php
header('Content-Type: application/json; charset=utf-8');

$host = "localhost";
$user = "root";
$password = "";
$database = "leonics-testdb";

$conn = new mysqli($host, $user, $password, $database);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    echo json_encode(["error" => "เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $conn->connect_error]);
    exit();
}

$pea_code_id = isset($_GET['pea_code_id']) ? intval($_GET['pea_code_id']) : 0;

$sql = "
SELECT 
    d.ups_id,
    ud.status_id,
    ud.last_signal_updated,
    ud.input_voltage,
    ud.output_voltage,
    ud.batt_temp,
    ud.sum_batt AS battery_voltage,
    ud.ups_temp,
    s.subregion_name,
    r.region_name,
    p.pea_code_name
FROM 
    ups_devices d
JOIN 
    ups_data ud ON d.ups_id = ud.ups_id
JOIN 
    subregions s ON d.subregion_id = s.subregion_id
JOIN 
    regions r ON d.region_id = r.region_id
JOIN 
    peacodes p ON d.pea_code_id = p.pea_code_id
WHERE 1
";

// กรองตาม pea_code_id ถ้าไม่ใช่ 0
if ($pea_code_id > 0) {
    $sql .= " AND d.pea_code_id = $pea_code_id";
}

// เพิ่ม ORDER BY เพื่อดึงข้อมูลล่าสุดก่อน (ปรับตามต้องการ)
$sql .= " ORDER BY ud.last_signal_updated DESC LIMIT 100";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["error" => "ไม่สามารถดึงข้อมูลได้: " . $conn->error]);
    $conn->close();
    exit();
}

$data = [];
while ($row = $result->fetch_assoc()) {
    // แปลงสถานะจาก status_id เป็นข้อความ
    switch ($row['status_id']) {
        case '000': $row['status'] = 'Comm Error'; break;
        case '001': $row['status'] = 'Minor'; break;
        case '010': $row['status'] = 'Major'; break;
        case '011': $row['status'] = 'Normal'; break;
        default: $row['status'] = 'Unknown';
    }
    unset($row['status_id']); // ลบ status_id ออกไม่ส่งไปด้วย

    $data[] = $row;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
$conn->close();
?>
