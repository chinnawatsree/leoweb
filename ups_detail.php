<?php
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'leonics-testdb';

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูล: ' . $conn->connect_error]);
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
    d.c1, d.c2, d.c3, d.c4, d.c5, d.c6,
    d.env_temp,
    d.avg_voltage,
    d.current_voltage,
    p.pea_code_name
FROM ups_devices d
JOIN peacodes p ON d.pea_code_id = p.pea_code_id
WHERE d.ups_id = '$ups_id'
LIMIT 1
";

$result = $conn->query($sql);

if ($result === FALSE) {
    http_response_code(500);
    echo json_encode(['error' => 'ข้อผิดพลาดในการสอบถามฐานข้อมูล: ' . $conn->error]);
    exit;
}

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    switch ($row['status']) {
        case 0: $row['status'] = 'Comm Error'; break;
        case 1: $row['status'] = 'Minor'; break;
        case 2: $row['status'] = 'Major'; break;
        case 3: $row['status'] = 'Normal'; break;
        default: $row['status'] = 'Unknown';
    }
    
    $data = [
        'ups_id' => $row['ups_id'],
        'status' => $row['status'],
        'last_signal_updated' => $row['last_signal_updated'],
        'input_voltage' => $row['input_voltage'],
        'output_voltage' => $row['output_voltage'],
        'battery_temp' => $row['battery_temp'],
        'battery_voltage' => $row['battery_voltage'],
        'ups_temp' => $row['ups_temp'],
        'pea_code_name' => $row['pea_code_name'],
        'current' => $row['current_voltage'],
        'load_percentage' => null, // ไม่มีในฐานข้อมูล
        'environment_humidity' => $row['env_temp'],
        'avg_voltage' => $row['avg_voltage'],
        'battery_cell_voltages' => [
            (float)$row['c1'],
            (float)$row['c2'],
            (float)$row['c3'],
            (float)$row['c4'],
            (float)$row['c5'],
            (float)$row['c6']
        ]
    ];
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'ไม่พบข้อมูล UPS นี้']);
}

$conn->close();
?>