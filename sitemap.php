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
    echo json_encode(['error' => 'ไม่สามารถเชื่อมต่อฐานข้อมูล']);
    exit;
}

$sql = "
SELECT 
    d.ups_id, 
    d.status,
    d.latitude, 
    d.longitude,
    p.pea_code_name
FROM 
    ups_devices d
JOIN 
    peacodes p ON d.pea_code_id = p.pea_code_id
";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'ข้อผิดพลาดในการสอบถามฐานข้อมูล: ' . $conn->error]);
    exit;
}

$data = [];
while ($row = $result->fetch_assoc()) {
    switch ($row['status']) {
        case 0: $row['status'] = 'Comm Error'; $row['statusClass'] = 'error'; break;
        case 1: $row['status'] = 'Minor'; $row['statusClass'] = 'warning'; break;
        case 2: $row['status'] = 'Major'; $row['statusClass'] = 'error'; break;
        case 3: $row['status'] = 'Normal'; $row['statusClass'] = 'normal'; break;
        default: $row['status'] = 'Unknown'; $row['statusClass'] = 'error';
    }

    $data[] = [
        'ups_id' => $row['ups_id'],
        'pea_code_name' => $row['pea_code_name'],
        'status' => $row['status'],
        'statusClass' => $row['statusClass'],
        'latitude' => (float)$row['latitude'],
        'longitude' => (float)$row['longitude']
    ];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
$conn->close();
?>  