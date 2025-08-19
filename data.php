<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'db_config.php';

    $pea_code_id = isset($_GET['pea_code_id']) ? intval($_GET['pea_code_id']) : 0;

    $sql = "
SELECT 
    d.ups_id,
    e.status_id,
    e.event_name,
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
LEFT JOIN 
    (
      SELECT ud1.*
      FROM ups_data ud1
      JOIN (
        SELECT ups_id, MAX(last_signal_updated) AS max_date
        FROM ups_data
        GROUP BY ups_id
      ) ud2 ON ud1.ups_id = ud2.ups_id AND ud1.last_signal_updated = ud2.max_date
    ) ud ON d.ups_id = ud.ups_id
LEFT JOIN 
    event_detail e ON ud.event_id = e.event_id
JOIN 
    subregions s ON d.subregion_id = s.subregion_id
JOIN 
    regions r ON s.region_id = r.region_id
JOIN 
    peacodes p ON d.pea_code_id = p.pea_code_id
WHERE 1
";

    $params = [];
    $types = '';

    if ($pea_code_id > 0) {
        $sql .= " AND d.pea_code_id = ?";
        $params[] = $pea_code_id;
        $types .= 'i'; // 'i' for integer
    }

    $sql .= " ORDER BY ud.last_signal_updated DESC LIMIT 100";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("ไม่สามารถเตรียมคำสั่ง SQL ได้: " . $conn->error);
    }

    if ($pea_code_id > 0) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception("ไม่สามารถดึงข้อมูลได้: " . $stmt->error);
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        // แปลง status_id เป็นข้อความ
        $status_id = $row['status_id'] ?? 'UNK';
        switch ($status_id) {
            case '000': $status_text = 'Comm Error'; $status_icon = '❌'; break;
            case '001': $status_text = 'Minor'; $status_icon = '⚠️'; break;
            case '010': $status_text = 'Major'; $status_icon = '🔴'; break;
            case '011': $status_text = 'Normal'; $status_icon = '✅'; break;
            default: $status_text = 'Unknown'; $status_icon = '❓';
        }

        // ถ้า event_name ว่าง ใช้ status_text แทน
        $event_text = !empty($row['event_name']) ? $row['event_name'] : $status_text;

        $data[] = [
            'ups_id'            => $row['ups_id'],
            'status'            => $status_icon,  // ไอคอน
            'event'             => $event_text,   // ข้อความ
            'last_signal_updated' => $row['last_signal_updated'] ?? 'No Data',
            'input_voltage'     => $row['input_voltage'],
            'output_voltage'    => $row['output_voltage'],
            'batt_temp'         => $row['batt_temp'],
            'battery_voltage'   => $row['battery_voltage'],
            'ups_temp'          => $row['ups_temp'],
            'subregion_name'    => $row['subregion_name'],
            'region_name'       => $row['region_name'],
            'pea_code_name'     => $row['pea_code_name']
        ];
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>