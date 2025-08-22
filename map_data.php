<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'db_config.php';

// ใช้ try-catch แทน mysqli_report เพื่อจัดการ error handling
try {
    $sql = "
SELECT 
    dev.ups_id, 
    se.event_name as status,
    dev.latitude, 
    dev.longitude,
    ud.last_signal_updated,
    p.pea_code_name
FROM 
    ups_devices dev
JOIN 
    peacodes p ON dev.pea_code_id = p.pea_code_id
LEFT JOIN (
    SELECT ud1.*
    FROM ups_data ud1
    JOIN (
        SELECT ups_id, MAX(last_signal_updated) AS max_date
        FROM ups_data
        GROUP BY ups_id
    ) ud2 ON ud1.ups_id = ud2.ups_id AND ud1.last_signal_updated = ud2.max_date
) ud ON dev.ups_id = ud.ups_id
LEFT JOIN 
    status_events se ON ud.status_id = se.status_id
WHERE 
    dev.latitude IS NOT NULL AND dev.longitude IS NOT NULL
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["error" => $conn->error]);
    exit;
}

$locationGroups = [];

while ($row = $result->fetch_assoc()) {
    $status = $row['status'] ? ucfirst($row['status']) : 'Unknown';
    $lat = (float)$row['latitude'];
    $lng = (float)$row['longitude'];
    $locationKey = $lat . ',' . $lng;
    
    if (!isset($locationGroups[$locationKey])) {
        $locationGroups[$locationKey] = [
            'latitude' => $lat,
            'longitude' => $lng,
            'devices' => []
        ];
    }
    
    $locationGroups[$locationKey]['devices'][] = [
        'ups_id' => $row['ups_id'],
        'pea_code_name' => $row['pea_code_name'],
        'status' => $status,
        'statusClass' => $status,
        'last_signal_updated' => $row['last_signal_updated'],
    ];
}

    $data = array_values($locationGroups);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
