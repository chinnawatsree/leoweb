<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'db_config.php';

$sql = "
SELECT 
    d.ups_id, 
    d.status,
    d.latitude, 
    d.longitude,
    d.last_signal_updated,   -- เพิ่มบรรทัดนี้
    p.pea_code_name
FROM 
    ups_devices d
JOIN 
    peacodes p ON d.pea_code_id = p.pea_code_id
WHERE 
    d.latitude IS NOT NULL AND d.longitude IS NOT NULL
";

$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch_assoc()) {
    // แปลง status จากตัวเลขเป็นข้อความ
    switch ($row['status']) {
        case 0: $row['status'] = 'Comm Error'; $row['statusClass'] = 'Comm Error'; break;
        case 1: $row['status'] = 'Minor'; $row['statusClass'] = 'Minor'; break;
        case 2: $row['status'] = 'Major'; $row['statusClass'] = 'Major'; break;
        case 3: $row['status'] = 'Normal'; $row['statusClass'] = 'Normal'; break;
        default: $row['status'] = 'Unknown'; $row['statusClass'] = 'Unknown';
    }

    $data[] = [
        'ups_id' => $row['ups_id'],
        'pea_code_name' => $row['pea_code_name'],
        'status' => $row['status'],
        'statusClass' => $row['statusClass'],
        'latitude' => (float)$row['latitude'],
        'longitude' => (float)$row['longitude'],
        'last_signal_updated' => $row['last_signal_updated'],
    ];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
$conn->close();
?>