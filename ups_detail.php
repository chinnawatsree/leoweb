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
    dev.ups_id,
    COALESCE(se.event_name, 'Unknown') as status,
    COALESCE(e.event_name, 'No Data') as event,
    ud.last_signal_updated,
    ud.input_voltage,
    ud.output_voltage,
    ud.batt_temp as battery_temp,
    ud.sum_batt as battery_voltage,
    ud.ups_temp,
    ud.batt_1 as c1, ud.batt_2 as c2, ud.batt_3 as c3, 
    ud.batt_4 as c4, ud.batt_5 as c5, ud.batt_6 as c6,
    ud.env_temp,
    ud.current_voltage,
    ud.output_i_percent as load_percentage,
    ud.RH as environment_humidity,
    p.pea_code_name
FROM ups_devices dev
JOIN peacodes p ON dev.pea_code_id = p.pea_code_id
LEFT JOIN (
    SELECT ud1.*
    FROM ups_data ud1
    JOIN (
        SELECT ups_id, MAX(last_signal_updated) AS max_date
        FROM ups_data
        GROUP BY ups_id
    ) ud2 ON ud1.ups_id = ud2.ups_id AND ud1.last_signal_updated = ud2.max_date
) ud ON dev.ups_id = ud.ups_id
LEFT JOIN event_detail e ON ud.event_id = e.event_id
LEFT JOIN status_events se ON e.status_id = se.status_id
WHERE dev.ups_id = '$ups_id'
";

$result = $conn->query($sql);

if ($result === FALSE) {
    http_response_code(500);
    echo json_encode(['error' => 'ข้อผิดพลาดในการสอบถามฐานข้อมูล: ' . $conn->error]);
    exit;
}

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    $data = [
        'ups_id' => $row['ups_id'],
        'status' => $row['status'] ?? 'Unknown',
        'event' => $row['event'] ?? 'No Data',
        'last_signal_updated' => $row['last_signal_updated'] ?? 'No Data',
        'input_voltage' => $row['input_voltage'] ?? null,
        'output_voltage' => $row['output_voltage'] ?? null,
        'battery_temp' => $row['battery_temp'] ?? null,
        'battery_voltage' => $row['battery_voltage'] ?? null,
        'ups_temp' => $row['ups_temp'] ?? null,
        'pea_code_name' => $row['pea_code_name'],
        'current' => $row['current_voltage'] ?? null,
        'load_percentage' => $row['load_percentage'] ?? null,
        'environment_humidity' => $row['env_temp'] ?? null,
        'battery_cell_voltages' => [
            (float)($row['c1'] ?? 0),
            (float)($row['c2'] ?? 0),
            (float)($row['c3'] ?? 0),
            (float)($row['c4'] ?? 0),
            (float)($row['c5'] ?? 0),
            (float)($row['c6'] ?? 0)
        ]
    ];
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'ไม่พบ UPS ID นี้ในระบบ']);
}

$conn->close();
?>