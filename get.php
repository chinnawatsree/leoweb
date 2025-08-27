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

    // กำหนดค่าเริ่มต้นสำหรับสถานะ
    $ups_status_value = 'err';
    $ups_status = '000';
    $ups_raw_status = 'err';  // เพิ่มตัวแปรนี้เพื่อป้องกัน undefined
    $lbm_temp_status = 'err';
    $lbm_status = '000';

    if (count($dataUPS) == 8) {
        $sqlUPS = "'".$dataUPS[0]."','".$dataUPS[1]."','".$dataUPS[2]."','".$dataUPS[3]."','".$dataUPS[4]."','".$dataUPS[5]."','".$dataUPS[6]."','".$dataUPS[7]."'";
        // ดึงค่า UPS status ดิบมาเก็บไว้เลย
        $ups_raw_status = isset($dataUPS[7]) ? $dataUPS[7] : 'err';
        dbg("UPS Raw Status", "Value from device: " . $ups_raw_status);
        
        // คำนวณค่า ups_status สำหรับ status_id
        if ($dataUPS[0] != "" && $dataUPS[4] != "") {
            $ups_status = "011";  // Normal if has input and output voltage
            if (floatval($dataUPS[3]) > $UpsHighLoad || 
                floatval($dataUPS[6]) > $UpsHighTemp || 
                floatval($dataUPS[0]) < 180) {
                $ups_status = "010";  // Major if there are issues
            }
        } else {
            $ups_status = "000";  // Comm Error if missing critical values
        }
    } else {
        $sqlUPS = "null,null,null,null,null,null,null,'err'";
        $ups_status_value = 'err';  // Error indicator for raw status
        $ups_raw_status = 'err';    // เพิ่มเพื่อป้องกัน undefined
        $ups_status = "000";       // Comm Error for status_id
        $commUPS = 0;
    }

    dbg("UPS Status Detail", "Raw status (UPS_status): " . $ups_status_value . ", Calculated status (status_id): " . $ups_status);

    // Log status for debugging
    dbg("UPS Status", "Raw: $ups_status_value, Calculated: $ups_status");

    if (count($dataLBM) == 10) {
        $sqlLBM = "'".$dataLBM[0]."','".$dataLBM[1]."','".$dataLBM[2]."','".$dataLBM[3]."','".$dataLBM[4]."','".$dataLBM[5]."','".$dataLBM[6]."','".$dataLBM[7]."','".$dataLBM[8]."','".$dataLBM[9]."'";
        $lbm_temp_status = empty($dataLBM[0]) ? 'err' : $dataLBM[0];  // เก็บค่า 00000200 จาก LBM
        
        // คำนวณ sum และ average สำหรับแบตเตอรี่ 6 ก้อน
        $validBatteryValues = array();
        for ($i = 1; $i <= 6; $i++) {
            if (isset($dataLBM[$i]) && is_numeric($dataLBM[$i])) {
                $value = floatval($dataLBM[$i]);
                // ยอมรับค่าที่มากกว่า 0 (รวมค่าเล็กๆ เช่น 0.33)
                if ($value >= 0) {
                    $validBatteryValues[] = $value;
                }
            }
        }
        
        if (count($validBatteryValues) == 6) {
            $SUMdata = array_sum($validBatteryValues);
            $AVGdata = number_format($SUMdata / 6, 2);
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
        
        // กำหนดสถานะ LBM และคำอธิบาย
        $lbm_status = "000"; // เริ่มต้นเป็น Comm Error
        $lbm_description = "Cannot communicate with LBM";
        
        if ($commLBM) {
            // เช็ค MCU power supply status (bit 6) ก่อน
            $lbm_status_bits = isset($dataLBM[0]) ? $dataLBM[0] : "00000000";
            if (strlen($lbm_status_bits) >= 7 && substr($lbm_status_bits, -7, 1) === "1") {
                $lbm_status = "010";
                $lbm_description = "MCU power supply fail";
                dbg("LBM Status", "LBM Status bit6=1");
            } else {
                // ตรวจสอบค่าแบตเตอรี่
                $batteryVoltages = array();
                
                // เก็บค่าแรงดันแบตเตอรี่
                for ($i = 1; $i <= 6; $i++) {
                    if (isset($dataLBM[$i]) && is_numeric($dataLBM[$i])) {
                        $value = floatval($dataLBM[$i]);
                        if ($value >= 0.2 && $value <= 0.5) {
                            $batteryVoltages[] = $value;
                        }
                    }
                }

                if (count($batteryVoltages) === 6) {
                    $avgVoltage = array_sum($batteryVoltages) / 6;
                    $maxDeviation = 0;
                    
                    // ตรวจสอบความแตกต่างของแรงดัน
                    foreach ($batteryVoltages as $i => $voltage) {
                        $deviation = abs(($voltage - $avgVoltage) / $avgVoltage) * 100;
                        $maxDeviation = max($maxDeviation, $deviation);
                    }
                    
                    if ($maxDeviation > 10) {
                        $lbm_status = "010";
                        $lbm_description = "Battery fail - High or low voltage from Avg Volt";
                        dbg("LBM Status", $lbm_description);
                    } else {
                        $lbm_status = "011";
                        $lbm_description = "LBM normal";
                        dbg("LBM Status", $lbm_description);
                    }
                } else {
                    $lbm_status = "000";
                    $lbm_description = "Cannot communicate with LBM";
                    dbg("LBM Status", $lbm_description);
                }
            }
        }

        dbg("Status Calculation", "NB:$nb_status UPS:$ups_status LBM:$lbm_status");

        // ========== INSERT ข้อมูลลง ups_data ==========
        dbg("Battery Values", "SUM: " . ($SUMdata ?? 'null') . ", AVG: " . ($AVGdata ?? 'null'));
        
        // กำหนดค่าตัวแปรที่จำเป็นทั้งหมด
        if (count($dataUPS) == 8) {
            $input_voltage = $dataUPS[0];
            $input_freq = $dataUPS[1];
            $input_fault = $dataUPS[2];
            $output_percent = $dataUPS[3];
            $output_voltage = $dataUPS[4];
            $ups_temperature = $dataUPS[6];
        } else {
            $input_voltage = null;
            $input_freq = null;
            $input_fault = null;
            $output_percent = null;
            $output_voltage = null;
            $ups_temperature = null;
        }

        if (count($dataLBM) == 10) {
            $batt_vcell = isset($dataUPS[5]) ? $dataUPS[5] : null;  // ป้องกัน undefined key
            $batt1 = $dataLBM[1];
            $batt2 = $dataLBM[2];
            $batt3 = $dataLBM[3];
            $batt4 = $dataLBM[4];
            $batt5 = $dataLBM[5];
            $batt6 = $dataLBM[6];
            $batt_temperature = $dataLBM[7];  // -50.0 จาก dataLBM[7]
            $lbm_temp = $dataLBM[8];  // 29.5 จาก dataLBM[8] 
            $current_v = $dataLBM[9];  // 0.1 จาก dataLBM[9]
        } else {
            $batt_vcell = null;
            $batt1 = null;
            $batt2 = null;
            $batt3 = null;
            $batt4 = null;
            $batt5 = null;
            $batt6 = null;
            $batt_temperature = null;
            $lbm_temp = null;
            $current_v = null;
        }

        // Show SQL query for debugging
        dbg("SQL Query", "INSERT INTO ups_data columns count: 28");
        
        // ลบส่วนที่ซ้ำและจัดระเบียบตัวแปร
        
        // กำหนดค่า LBM_status จากข้อมูลที่ได้รับ
        if ($commLBM) {
            $lbm_temp_status = $dataLBM[0];  // เก็บค่า 00000200 จาก LBM
        } else {
            $lbm_temp_status = 'err';  // มีข้อผิดพลาด
        }

        // บันทึก log ก่อนเข้า database
        dbg("Values for Database", sprintf(
            "UPS Status: [%s], LBM Status: [%s]",
            $ups_raw_status,
            $lbm_temp_status
        ));

        // เตรียม SQL statement ตามลำดับที่ต้องการ
        $sql = "INSERT INTO ups_data (
            `ups_id`, `signal`, `RH`, `last_signal_updated`, 
            `input_voltage`, `input_freq_hz`, `input_fault_v`, 
            `output_i_percent`, `output_voltage`, `batt_v_per_cell`, 
            `ups_temp`, `UPS_status`, `LBM_status`, 
            `batt_1`, `batt_2`, `batt_3`, `batt_4`, `batt_5`, `batt_6`, 
            `batt_temp`, `env_temp`, `LBM_ampTemp`, 
            `avg_voltage`, `sum_batt`, `current_voltage`, 
            `nb_status_id`, `ups_status_id`, `lbm_status_id`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        // แสดงข้อมูลที่จะบันทึกก่อน bind
        dbg("Data Mapping", sprintf(
            "UPS: [%s,%s,%s,%s,%s,%s,%s,%s] LBM: [%s,%s,%s,%s,%s,%s,%s,%s,%s,%s]",
            $input_voltage, $input_freq, $input_fault, $output_percent, $output_voltage, $batt_vcell, $ups_temperature, $ups_raw_status,
            $lbm_temp_status, $batt1, $batt2, $batt3, $batt4, $batt5, $batt6, $batt_temperature, $lbm_temp, $current_v
        ));
        
        // แสดงความแตกต่างระหว่าง Raw Status และ Calculated Status
        dbg("Status Comparison", sprintf(
            "UPS: Raw=%s, Calculated=%s | LBM: Raw=%s, Calculated=%s | NB: Calculated=%s",
            $ups_raw_status, $ups_status, $lbm_temp_status, $lbm_status, $nb_status
        ));
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssssssssssssssssssssss",
            $ups_id,          // 1. ups_id
            $rssi,           // 2. signal (data[0] = 0)
            $humid,          // 3. RH (data[2] = 42.60)
            $date,           // 4. last_signal_updated
            $input_voltage,  // 5. input_voltage (dataUPS[0] = 233.7)
            $input_freq,     // 6. input_freq_hz (dataUPS[1] = 233.1)
            $input_fault,    // 7. input_fault_v (dataUPS[2] = 220.0)
            $output_percent, // 8. output_i_percent (dataUPS[3] = 000)
            $output_voltage, // 9. output_voltage (dataUPS[4] = 50.1)
            $batt_vcell,     // 10. batt_v_per_cell (dataUPS[5] = 2.25)
            $ups_temperature, // 11. ups_temp (dataUPS[6] = 28.0)
            $ups_raw_status, // 12. UPS_status (dataUPS[7] = 00000001) - RAW STATUS
            $lbm_temp_status, // 13. LBM_status (dataLBM[0] = 00000200)
            $batt1,          // 14. batt_1 (dataLBM[1] = 0.33)
            $batt2,          // 15. batt_2 (dataLBM[2] = 0.33)
            $batt3,          // 16. batt_3 (dataLBM[3] = 0.33)
            $batt4,          // 17. batt_4 (dataLBM[4] = 0.33)
            $batt5,          // 18. batt_5 (dataLBM[5] = 0.33)
            $batt6,          // 19. batt_6 (dataLBM[6] = 0.33)
            $batt_temperature, // 20. batt_temp (dataLBM[7] = -50.0)
            $temp,           // 21. env_temp (data[1] = 30.09)
            $lbm_temp,       // 22. LBM_ampTemp (dataLBM[8] = 29.5) - แก้ไขให้ใช้ lbm_temp
            $AVGdata,        // 23. avg_voltage (calculated)
            $SUMdata,        // 24. sum_batt (calculated)
            $current_v,      // 25. current_voltage (dataLBM[9] = 0.1)
            $nb_status,      // 26. nb_status_id (calculated 011/000/010)
            $ups_status,     // 27. ups_status_id (calculated 011/000/010) - NOT RAW
            $lbm_status      // 28. lbm_status_id (calculated 011/000/010)
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
