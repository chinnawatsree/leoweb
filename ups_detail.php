<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'db_config.php';

$ups_id = isset($_GET['ups_id']) ? $conn->real_escape_string($_GET['ups_id']) : '';

if (!$ups_id) {
    http_response_code(400);
    echo json_encode(['error' => 'UPS ID ไม่ถูกต้อง']);
    exit;
}

$sql = "
SELECT
    d.ups_id,
    s.event_name as status,
    d.last_signal_updated,
    d.input_voltage,
    d.output_voltage,
    d.batt_temp as battery_temp,
    d.sum_batt as battery_voltage,
    d.ups_temp,
    d.batt_1 as c1, d.batt_2 as c2, d.batt_3 as c3, 
    d.batt_4 as c4, d.batt_5 as c5, d.batt_6 as c6,
    d.env_temp,
    d.current_voltage,
    d.output_i_percent as load_percentage,
    d.RH as environment_humidity,
    p.pea_code_name
FROM ups_data d
JOIN status_events s ON d.status_id = s.status_id
JOIN ups_devices dev ON d.ups_id = dev.ups_id
JOIN peacodes p ON dev.pea_code_id = p.pea_code_id
WHERE d.ups_id = '$ups_id'
ORDER BY d.last_signal_updated DESC
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
    
    // Status already converted by JOIN with status_events table
    $status = ucfirst($row['status']);
    
    $data = [
        'ups_id' => $row['ups_id'],
        'status' => $status,
        'last_signal_updated' => $row['last_signal_updated'],
        'input_voltage' => $row['input_voltage'],
        'output_voltage' => $row['output_voltage'],
        'battery_temp' => $row['battery_temp'],
        'battery_voltage' => $row['battery_voltage'],
        'ups_temp' => $row['ups_temp'],
        'pea_code_name' => $row['pea_code_name'],
        'current' => $row['current_voltage'],
        'load_percentage' => $row['load_percentage'],
        'environment_humidity' => $row['environment_humidity'] ?? $row['env_temp'],
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