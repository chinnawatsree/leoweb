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

// SQL พร้อม WHERE ถ้ามี pea_code_id
$sql = "
SELECT 
    d.ups_id,
    d.status,
    d.last_signal_updated,
    d.last_event,
    d.input_voltage,
    d.output_voltage,
    d.battery_temp,
    d.battery_voltage,
    d.ups_temp,
    s.subregion_name,
    r.region_name,
    p.pea_code_name
FROM 
    ups_devices d
JOIN 
    subregions s ON d.subregion_id = s.subregion_id
JOIN 
    regions r ON d.region_id = r.region_id
JOIN 
    peacodes p ON d.pea_code_id = p.pea_code_id
";

// ถ้ามี pea_code_id ค่อยใส่เงื่อนไข
if ($pea_code_id > 0) {
    $sql .= " WHERE d.pea_code_id = $pea_code_id";
}

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["error" => "ไม่สามารถดึงข้อมูลได้: " . $conn->error]);
    $conn->close();
    exit();
}

$data = [];
while ($row = $result->fetch_assoc()) {
    // แปลง status จากตัวเลขเป็นข้อความ
    switch ($row['status']) {
        case 0: $row['status'] = 'Comm Error'; break;
        case 1: $row['status'] = 'Minor'; break;
        case 2: $row['status'] = 'Major'; break;
        case 3: $row['status'] = 'Normal'; break;
        default: $row['status'] = 'Unknown';
    }

    $data[] = $row;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
$conn->close();
?>
