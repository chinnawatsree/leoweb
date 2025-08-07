<?php
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'leonics-testdb';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(['error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูล']);
  exit;
}

$ups_id = isset($_GET['ups_id']) ? $conn->real_escape_string($_GET['ups_id']) : '';

if (!$ups_id) {
  http_response_code(400);
  echo json_encode(['error' => 'UPS ID ไม่ถูกต้อง']);
  exit;
}

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
FROM ups_devices d
JOIN subregions s ON d.subregion_id = s.subregion_id
JOIN regions r ON d.region_id = r.region_id
JOIN peacodes p ON d.pea_code_id = p.pea_code_id
WHERE d.ups_id = '$ups_id'
LIMIT 1
";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
  $row = $result->fetch_assoc();
  
  // แปลง status จากตัวเลขเป็นข้อความ
    switch ($row['status']) {
        case 0: $row['status'] = 'Comm Error'; break;
        case 1: $row['status'] = 'Minor'; break;
        case 2: $row['status'] = 'Major'; break;
        case 3: $row['status'] = 'Normal'; break;
        default: $row['status'] = 'Unknown';
    }

  // === ส่วนที่ต้องเพิ่มข้อมูลใหม่เข้ามา ===
  // คุณต้องเพิ่มข้อมูลเหล่านี้จากฐานข้อมูลของคุณเอง
  $row['current'] = -1.5;
  $row['load_percentage'] = 0;
  $row['environment_humidity'] = 68.5;
  $row['battery_cell_voltages'] = [13.56, 13.35, 13.59, 13.58, 13.59, 13.51];
  // ======================================
  
  echo json_encode($row, JSON_UNESCAPED_UNICODE);
} else {
  http_response_code(404);
  echo json_encode(['error' => 'ไม่พบข้อมูล UPS นี้']);
}

$conn->close();
?>