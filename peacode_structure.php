<?php
header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli("localhost", "root", "", "leonics-testdb");
$conn->set_charset("utf8");

if ($conn->connect_error) {
    echo json_encode(["error" => "DB Connection failed: " . $conn->connect_error], JSON_UNESCAPED_UNICODE);
    exit();
}

$sql = "
SELECT 
    r.region_name,
    s.subregion_name,
    p.pea_code_id,
    p.pea_code_name
FROM 
    peacodes p
JOIN 
    subregions s ON p.subregion_id = s.subregion_id
JOIN 
    regions r ON s.region_id = r.region_id
ORDER BY r.region_name, s.subregion_name, p.pea_code_name
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["error" => "Query failed: " . $conn->error], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit();
}

$data = [];

while ($row = $result->fetch_assoc()) {
    $region = $row['region_name'];
    $subregion = $row['subregion_name'];

    if (!isset($data[$region])) {
        $data[$region] = [];
    }

    if (!isset($data[$region][$subregion])) {
        $data[$region][$subregion] = [];
    }

    $data[$region][$subregion][] = [
        "pea_code_id" => $row["pea_code_id"],
        "pea_code_name" => $row["pea_code_name"]
    ];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
$conn->close();
?>
