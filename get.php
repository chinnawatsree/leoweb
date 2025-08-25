<?php
declare(strict_types=1);

// Configuration values
$HighAmbient = 45;  // Temperature threshold for ambient temperature
$UpsHighLoad = 85;  // Percentage threshold for UPS load
$UpsHighTemp = 50;  // Temperature threshold for UPS

// Initialize calculation variables
$SUMdata = 0;
$AVGdata = 0;

// Set timezone
date_default_timezone_set("Asia/Bangkok");

/**
 * Debug logging function
 * @param string $label Log entry label
 * @param string $val Optional value to log
 */
function dbg(string $label, string $val = ""): void {
    $log_filename = 'get_php_log.txt';
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $label;
    if ($val !== "") {
        $entry .= " | " . $val;
    }
    $entry .= "\n";
    file_put_contents($log_filename, $entry, FILE_APPEND);
}

// Check required parameters
if (isset($_GET['submit']) && isset($_GET['id']) && isset($_GET['data'])) {
    dbg("START get.php", "URI=".$_SERVER['REQUEST_URI']." | METHOD=".$_SERVER['REQUEST_METHOD']);
    echo "ACK";
    $date=date('Y-m-d H:i:s');

    $SN = $_GET['id'];
    $data = (explode(",",$_GET['data']));

    $rssi = $data[0];
    $temp = $data[1];
    $humid = $data[2];

    $dataDev = (explode("~",$data[3]));
    $dataUPS = (explode(":",$dataDev[0]));
    $dataLBM = (explode(":",$dataDev[1]));
    $commUPS = 1;
    $commLBM = 1;

    if (count($dataUPS) == 8)
        $sqlUPS = "'".$dataUPS[0]."','".$dataUPS[1]."','".$dataUPS[2]."','".$dataUPS[3]."','".$dataUPS[4]."','".$dataUPS[5]."','".$dataUPS[6]."','".$dataUPS[7]."'";
    else {
        $sqlUPS = "null,null,null,null,null,null,null,'err'";
        $commUPS = 0;
    }

    if (count($dataLBM) == 10) {
        $sqlLBM = "'".$dataLBM[0]."','".$dataLBM[1]."','".$dataLBM[2]."','".$dataLBM[3]."','".$dataLBM[4]."','".$dataLBM[5]."','".$dataLBM[6]."','".$dataLBM[7]."','".$dataLBM[8]."','".$dataLBM[9]."'";
        
        // คำนวณ sum และ average เฉพาะเมื่อมีค่าแบตเตอรี่ที่ไม่เป็น 0 หรือค่าว่าง
        $validBatteryValues = array_filter(array_slice($dataLBM, 1, 6), function($value) {
            return is_numeric($value) && floatval($value) > 0;
        });
        
        if (!empty($validBatteryValues)) {
            $SUMdata = array_sum($validBatteryValues);
            $AVGdata = number_format($SUMdata / count($validBatteryValues), 2);
        } else {
            $SUMdata = null;
            $AVGdata = null;
        }
    }
    else {
        $sqlLBM = "'err',null,null,null,null,null,null,null,null,null";
        $commLBM = 0;
        $SUMdata = null;
        $AVGdata = null;
    }

    try {
        require "db_config.php";
        
        dbg("Connected to database successfully");

        // ========== หา ups_id จาก ups_devices ==========
        $stmt = $conn->prepare("SELECT ups_id FROM ups_devices WHERE NBserial = ?");
        $stmt->bind_param("s", $SN);
        
        dbg("Query ups_devices by NBserial", "NBserial=" . $SN);
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $ups_id = "";
        if ($row = $result->fetch_assoc()) {
            $ups_id = $row['ups_id'];
            dbg("Fetched ups_id from ups_devices", $ups_id);
        } else {
            dbg("No matching NBserial in ups_devices", $SN);
        }
        $stmt->close();

        // ========== ตรวจสอบ ups_id ==========
        if($ups_id == ""){
            dbg("Empty ups_id", "NBserial=$SN_safe");
            echo "ACKERR: Unknown NBserial '$SN_safe'. Please add to ups_devices and link ups_id.";
            exit;
        }

                // คำนวณสถานะย่อยของแต่ละระบบ (000/001/010/011)
        $nb_status = ($rssi != "" && $temp != "" && $humid != "") ? "011" : "000"; // Normal if all values present
        
        $ups_status = "000"; // Default to Comm Error
        if ($commUPS) {
            if ($dataUPS[0] != "" && $dataUPS[4] != "") {
                $ups_status = "011"; // Normal if has input and output voltage
                
                // Check for UPS issues
                if (floatval($dataUPS[3]) > $UpsHighLoad) $ups_status = "001"; // High Load
                if (floatval($dataUPS[6]) > $UpsHighTemp) $ups_status = "001"; // High Temperature
                if (floatval($dataUPS[0]) < 180) $ups_status = "010"; // Input Voltage Low
            } else {
                $ups_status = "001"; // Minor if connected but missing data
            }
        }
        
        $lbm_status = "000"; // Default to Comm Error
        if ($commLBM) {
            // ตรวจสอบว่ามีค่าอุณหภูมิและแบตเตอรี่ทุกลูก
            $allBatteriesValid = true;
            for ($i = 1; $i <= 6; $i++) {
                $battVar = "batt" . $i;
                if (empty($$battVar) || floatval($$battVar) <= 0) {
                    $allBatteriesValid = false;
                    break;
                }
            }

            if ($dataLBM[0] != "" && $SUMdata > 0 && $allBatteriesValid) {
                $lbm_status = "011"; // Normal if all conditions are met
                
                // Check for LBM issues
                if (floatval($dataLBM[0]) > $UpsHighTemp) {
                    $lbm_status = "001"; // High Temperature
                }
                
                // ตรวจสอบแรงดันแบตเตอรี่แต่ละลูก
                for ($i = 1; $i <= 6; $i++) {
                    $battVar = "batt" . $i;
                    if (floatval($$battVar) < 30) { // ถ้าแรงดันแบตเตอรี่ลูกใดลูกหนึ่งต่ำกว่า 30V
                        $lbm_status = "001";
                        break;
                    }
                }
                
                if ($SUMdata < 180) {
                    $lbm_status = "001"; // Low Total Battery Voltage
                }
            } else {
                $lbm_status = "001"; // Minor if connected but missing or invalid data
            }
        }

        dbg("Status Calculation", "NB:$nb_status UPS:$ups_status LBM:$lbm_status");

        // ========== INSERT ข้อมูลลง ups_data ==========
        dbg("Battery Values", "SUM: " . ($SUMdata ?? 'null') . ", AVG: " . ($AVGdata ?? 'null'));
        
        $stmt = $conn->prepare("INSERT INTO ups_data (
            `ups_id`, `signal`, `env_temp`, `RH`, `last_signal_updated`,
            `input_voltage`, `input_freq_hz`, `input_fault_v`, `output_i_percent`, `output_voltage`,
            `ups_temp`, `batt_temp`, `batt_1`, `batt_2`, `batt_3`, `batt_4`, `batt_5`, `batt_6`,
            `batt_v_per_cell`, `LBM_ampTemp`, `avg_voltage`, `sum_batt`, `current_voltage`,
            `LBM_status`, `nb_status_id`, `ups_status_id`, `lbm_status_id`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // สร้างตัวแปรเพื่อเก็บค่าที่จะใส่ใน prepared statement
        $input_voltage = $commUPS ? $dataUPS[0] : null;
        $input_freq = $commUPS ? $dataUPS[1] : null;
        $input_fault = $commUPS ? $dataUPS[2] : null;
        $output_percent = $commUPS ? $dataUPS[3] : null;
        $output_voltage = $commUPS ? $dataUPS[4] : null;
        $ups_temperature = $commUPS ? $dataUPS[6] : null;
        
        $batt_temperature = $commLBM ? $dataLBM[0] : null;
        $batt1 = $commLBM ? $dataLBM[1] : null;
        $batt2 = $commLBM ? $dataLBM[2] : null;
        $batt3 = $commLBM ? $dataLBM[3] : null;
        $batt4 = $commLBM ? $dataLBM[4] : null;
        $batt5 = $commLBM ? $dataLBM[5] : null;
        $batt6 = $commLBM ? $dataLBM[6] : null;
        $batt_vcell = $commLBM ? $dataLBM[7] : null;
        $lbm_temp = $commLBM ? $dataLBM[8] : null;
        $current_v = $commLBM ? $dataLBM[9] : null;
        
        // กำหนดค่า LBM_status ตามเงื่อนไข
        if ($commLBM) {
            $lbm_temp_status = '00000000';  // เชื่อมต่อได้ปกติ
        } elseif ($dataLBM[0] === '' || $dataLBM[0] === null) {
            $lbm_temp_status = 'err';  // มีข้อผิดพลาด
        } else {
            $lbm_temp_status = $dataLBM[0];  // ค่าอุณหภูมิปกติ
        }

        // เพิ่ม logging เพื่อตรวจสอบค่าสถานะ
        dbg("LBM Status Values", "LBM Status ID: $lbm_status, LBM Temp Status: $lbm_temp_status");

        $stmt->bind_param("sssssssssssssssssssssssssss",
            $ups_id, $rssi, $temp, $humid, $date,
            $input_voltage, $input_freq, $input_fault, $output_percent, $output_voltage,
            $ups_temperature, $batt_temperature, 
            $batt1, $batt2, $batt3, $batt4, $batt5, $batt6,
            $batt_vcell, $lbm_temp, $AVGdata, $SUMdata, $current_v,
            $lbm_temp_status, $nb_status, $ups_status, $lbm_status
        );

        if ($stmt->execute()) {
            dbg("Data inserted successfully");
        } else {
            dbg("Insert Error", $stmt->error);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        dbg("Error", $e->getMessage());
        echo "ACKERR: " . $e->getMessage();
    } finally {
        if (isset($conn)) {
            $conn->close();
        }
    }
}
?>
