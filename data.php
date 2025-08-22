<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // เปิด error แค่ debug
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Log errors to file
ini_set('log_errors', 1);
ini_set('error_log', 'get_php_log.txt');

try {
    require_once 'db_config.php';
    
    error_log("Starting data retrieval...");

    // อนุญาต query ขนาดใหญ่
    $conn->query("SET SQL_BIG_SELECTS=1");  

    $pea_code_id = isset($_GET['pea_code_id']) ? intval($_GET['pea_code_id']) : 0;

    // SQL ดึงข้อมูล latest ของแต่ละ UPS
    $sql = "
    SELECT 
        d.ups_id,
        CASE
            WHEN ud.ups_status_id = '000' THEN '000'
            WHEN ud.nb_status_id = '000' THEN '000'
            WHEN ud.lbm_status_id = '000' THEN '000'
            WHEN ud.ups_status_id = '010' THEN '010'
            WHEN ud.nb_status_id = '010' THEN '010'
            WHEN ud.lbm_status_id = '010' THEN '010'
            WHEN ud.ups_status_id = '001' THEN '001'
            WHEN ud.nb_status_id = '001' THEN '001'
            WHEN ud.lbm_status_id = '001' THEN '001'
            ELSE '011'
        END as status_id,
        CASE
            WHEN ud.ups_status_id = '000' THEN 'UPS comm error'
            WHEN ud.nb_status_id = '000' THEN 'Site comm error'
            WHEN ud.lbm_status_id = '000' THEN 'LBM comm error'
            WHEN ud.ups_status_id = '010' AND ud.ups_temp > 50 THEN 'UPS High Temp'
            WHEN ud.ups_status_id = '010' AND ud.batt_temp > 65 THEN 'Battery High Temp'
            WHEN ud.ups_status_id = '001' THEN 'Utility fail'
            WHEN ud.nb_status_id = '010' THEN 'High ambient temp'
            WHEN ud.lbm_status_id = '010' THEN 'Battery fail'
            WHEN ud.ups_status_id = '011' THEN 'UPS normal'
            WHEN ud.nb_status_id = '011' THEN 'NB IoT Normal'
            WHEN ud.lbm_status_id = '011' THEN 'LBM normal'
            ELSE 'Normal'
        END as event_name,
        CASE
            WHEN ud.ups_status_id = '000' THEN 'Cannot communicate with UPS'
            WHEN ud.nb_status_id = '000' THEN 'Internet connection lost or site cannot send data'
            WHEN ud.lbm_status_id = '000' THEN 'Cannot communicate with LBM'
            WHEN ud.ups_status_id = '010' AND ud.ups_temp > 50 THEN 'UPS temperature is too high (>50°C)'
            WHEN ud.ups_status_id = '010' AND ud.batt_temp > 65 THEN 'Battery temperature is too high (>65°C)'
            WHEN ud.ups_status_id = '001' THEN 'UPS on battery power'
            WHEN ud.nb_status_id = '010' THEN 'High ambient temperature (>45°C)'
            WHEN ud.lbm_status_id = '010' THEN 'Battery system failure'
            WHEN ud.ups_status_id = '011' THEN 'UPS working normally'
            WHEN ud.nb_status_id = '011' THEN 'Communication system working normally'
            WHEN ud.lbm_status_id = '011' THEN 'Battery system working normally'
            ELSE 'System is working normally'
        END as event_description,
        ud.last_signal_updated,
        ud.input_voltage,
        ud.output_voltage,
        ud.batt_temp,
        ud.sum_batt AS battery_voltage,
        ud.ups_temp,
        s.subregion_name,
        r.region_name,
        p.pea_code_name,
        ud.nb_status_id,
        ud.ups_status_id,
        ud.lbm_status_id
    FROM ups_devices d
    LEFT JOIN (
        SELECT u1.*
        FROM ups_data u1
        JOIN (
            SELECT ups_id, MAX(last_signal_updated) AS max_date
            FROM ups_data
            GROUP BY ups_id
        ) u2 ON u1.ups_id = u2.ups_id AND u1.last_signal_updated = u2.max_date
    ) ud ON d.ups_id = ud.ups_id
    LEFT JOIN status_events nb ON ud.nb_status_id = nb.status_id
    LEFT JOIN status_events ups ON ud.ups_status_id = ups.status_id
    LEFT JOIN status_events lbm ON ud.lbm_status_id = lbm.status_id
    JOIN subregions s ON d.subregion_id = s.subregion_id
    JOIN regions r ON s.region_id = r.region_id
    JOIN peacodes p ON d.pea_code_id = p.pea_code_id
    WHERE 1
    ";

    if ($pea_code_id > 0) {
        $sql .= " AND d.pea_code_id = ?";
    }

    $sql .= " ORDER BY ud.last_signal_updated DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("SQL Prepare Error: " . $conn->error);

    if ($pea_code_id > 0) {
        $stmt->bind_param("i", $pea_code_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) throw new Exception("ไม่สามารถดึงข้อมูลได้: " . $conn->error);

    $data = [];
    while ($row = $result->fetch_assoc()) {
        // ฟังก์ชันสำหรับแปลงรหัสสถานะเป็นไอคอนและข้อความ
        $getStatusInfo = function($status_code) {
            switch ($status_code) {
                case '000': return ['❌', 'Comm Error'];
                case '001': return ['🟡', 'Minor'];
                case '010': return ['�', 'Major'];
                case '011': return ['🟢', 'Normal'];
                default:   return ['❓', 'Unknown'];
            }
        };

        // For debugging
        error_log("Processing row: " . json_encode($row));
        
        // Add debug information
        error_log("NB Status: " . ($row['nb_status_id'] ?? 'NULL'));
        error_log("UPS Status: " . ($row['ups_status_id'] ?? 'NULL'));
        error_log("LBM Status: " . ($row['lbm_status_id'] ?? 'NULL'));
        $data[] = [
            'ups_id'              => $row['ups_id'],
            'status_id'           => $row['status_id'] ?? 'UNK',
            'event_name'          => $row['event_name'] ?? 'Unknown',
            'event_description'   => $row['event_description'] ?? '',
            'region_name'         => $row['region_name'],
            'subregion_name'      => $row['subregion_name'],
            'pea_code_name'       => $row['pea_code_name'],
            'last_signal_updated' => $row['last_signal_updated'],
            'input_voltage'       => $row['input_voltage'],
            'output_voltage'      => $row['output_voltage'],
            'batt_temp'          => $row['batt_temp'],
            'battery_voltage'     => $row['battery_voltage'],
            'ups_temp'           => $row['ups_temp'],
            'last_signal_updated' => $row['last_signal_updated'] ?? 'No Data',
            'input_voltage'       => $row['input_voltage'],
            'output_voltage'      => $row['output_voltage'],
            'batt_temp'           => $row['batt_temp'],
            'battery_voltage'     => $row['battery_voltage'],
            'ups_temp'            => $row['ups_temp'],
            'subregion_name'      => $row['subregion_name'],
            'region_name'         => $row['region_name'],
            'pea_code_name'       => $row['pea_code_name']
        ];
    }

    // Debug final data
    error_log("Final data: " . json_encode($data));
    
    // Send response
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $stmt->close();
    $conn->close();
    
    error_log("Data retrieval completed successfully.");

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
