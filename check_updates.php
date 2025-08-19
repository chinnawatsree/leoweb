<?php
require "db_config.php";

// ตรวจสอบการอัพเดทของทุกอุปกรณ์
$sql = "SELECT 
    u.ups_id,
    s.NBserial,
    s.PEA_SiteName,
    MAX(u.last_signal_updated) as last_update,
    TIMESTAMPDIFF(MINUTE, MAX(u.last_signal_updated), NOW()) as minutes_since_update
FROM ups_data u
JOIN sitedetail s ON u.ups_id = s.UPS_ID
GROUP BY u.ups_id, s.NBserial, s.PEA_SiteName
HAVING minutes_since_update > 10
ORDER BY minutes_since_update DESC";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $log_message = "=== Update Check: " . date('Y-m-d H:i:s') . " ===\n";
    
    while($row = $result->fetch_assoc()) {
        $log_message .= sprintf(
            "Device: %s (%s)\nLocation: %s\nLast Update: %s (%d minutes ago)\n\n",
            $row['ups_id'],
            $row['NBserial'],
            $row['PEA_SiteName'],
            $row['last_update'],
            $row['minutes_since_update']
        );
    }
    
    file_put_contents('update_check.log', $log_message, FILE_APPEND);
}

$conn->close();
?>
