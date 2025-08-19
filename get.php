<?php
	$tablename = "";
	$PEAcode = "PEA";

    $NormalID = 0;
    $CommErrID = 2;
    $HighAmbientID = 3;
    $UpsCommErrID = 5;
    $UpsUtilityFailID = 6;
    $UpsLowBattID = 7;
    $UpsFailID = 8;
    $UpsShutdownID = 9;
    $UpsHighLoadID = 10;
    $UpsHighTempID = 11;
    $LbmCommErrID = 13;
    $LbmHighTempID = 14;
    $LbmSupplyFailID = 15;

    $NormalSERV = "Normal";
    $CommErrSERV = "Comm Error";
    $HighAmbientSERV = "Minor";
    $UpsCommErrSERV = "Comm Error";
    $UpsUtilityFailSERV = "Major";
    $UpsLowBattSERV = "Minor";
    $UpsFailSERV = "Minor";
    $UpsShutdownSERV = "Minor";
    $UpsHighLoadSERV = "Minor";
    $UpsHighTempSERV = "Minor";
    $LbmCommErrSERV = "Comm Error";
    $LbmHighTempSERV = "Minor";
    $LbmSupplyFailSERV = "Minor";

    $HighAmbient = 45;
    $UpsHighLoad = 85;
    $UpsHighTemp = 50;

	date_default_timezone_set("Asia/Bangkok");
	if((isset($_GET['submit']))&&(isset($_GET['id']))&&(isset($_GET['data']))) { 
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
			$SUMdata=0;
			for($i=1;$i<(count($dataLBM)-3);$i++){
				$SUMdata=$SUMdata+$dataLBM[$i];
			}
			$AVGdata=number_format(($SUMdata/(count($dataLBM)-4)),2);
		}
		else {
			$sqlLBM = "'err',null,null,null,null,null,null,null,null,null";
			$commLBM = 0;
		}

		require "db_config.php";
		if ($conn) {
			$link = $conn; // ใช้ตัวแปร $link แทน $conn เพื่อไม่ต้องแก้โค้ดทั้งหมด

			// ตรวจสอบเวลาอัพเดทล่าสุดของอุปกรณ์นี้
			$sql = "SELECT TIMESTAMPDIFF(MINUTE, MAX(last_signal_updated), NOW()) as minutes_since_update 
					FROM ups_data 
					WHERE ups_id = (SELECT ups_id FROM sitedetail WHERE NBserial='$SN')";
			
			if($result = mysqli_query($link, $sql)) {
				if($row = mysqli_fetch_assoc($result)) {
					$minutes_since_update = $row['minutes_since_update'];
					if($minutes_since_update > 10) {
						error_log("Warning: Device $SN hasn't updated for $minutes_since_update minutes", 0);
						
						// สร้างไฟล์ log แยกสำหรับอุปกรณ์ที่ไม่อัพเดท
						$log_message = date('Y-m-d H:i:s') . " - Device: $SN, Last update: $minutes_since_update minutes ago\n";
						file_put_contents('get_log.txt', $log_message, FILE_APPEND);
					}
				}
				mysqli_free_result($result);
			}
			// Enable error logging
			error_log("Connected to database successfully", 0);
			
			$sql = "select ID from sitedetail where NBserial='$SN'";
            if($result = mysqli_query($link, $sql)){
                if($row = mysqli_fetch_assoc($result)){
                    $tablename = $row['ID'];
                    error_log("Found tablename: " . $tablename, 0);
                } else {
                    error_log("No matching NBserial found: " . $SN, 0);
                }
                mysqli_free_result($result);
            } else {
                error_log("SQL Error: " . mysqli_error($link), 0);
            }


			// ตรวจสอบ UPS ID จาก NBserial
			$sql = "SELECT ups_id FROM sitedetail WHERE NBserial='$SN'";
			$ups_id = "";
			if($result = mysqli_query($link, $sql)){
				if($row = mysqli_fetch_assoc($result)){
					$ups_id = $row['ups_id'];
				}
				mysqli_free_result($result);
			}

			$sql = "INSERT INTO ups_data (
				`ups_id`, 
				`signal`, 
				`env_temp`,
				`RH`, 
				`last_signal_updated`,
				`input_voltage`,
				`input_freq_hz`,
				`input_fault_v`,
				`output_i_percent`,
				`output_voltage`,
				`ups_temp`,
				`batt_temp`,
				`batt_1`,
				`batt_2`,
				`batt_3`,
				`batt_4`,
				`batt_5`,
				`batt_6`,
				`batt_v_per_cell`,
				`LBM_ampTemp`,
				`avg_voltage`,
				`sum_batt`,
				`current_voltage`,
				`LBM_status`,
				`event_id`
			) VALUES (
				'$ups_id',
				'$rssi',
				'$temp',
				'$humid',
				'$date',
				".($commUPS ? $dataUPS[0] : "NULL").",
				".($commUPS ? $dataUPS[1] : "NULL").",
				".($commUPS ? $dataUPS[2] : "NULL").",
				".($commUPS ? $dataUPS[3] : "NULL").",
				".($commUPS ? $dataUPS[4] : "NULL").",
				".($commUPS ? $dataUPS[6] : "NULL").",
				".($commLBM ? $dataLBM[0] : "NULL").",
				".($commLBM ? $dataLBM[1] : "NULL").",
				".($commLBM ? $dataLBM[2] : "NULL").",
				".($commLBM ? $dataLBM[3] : "NULL").",
				".($commLBM ? $dataLBM[4] : "NULL").",
				".($commLBM ? $dataLBM[5] : "NULL").",
				".($commLBM ? $dataLBM[6] : "NULL").",
				".($commLBM ? $dataLBM[7] : "NULL").",
				".($commLBM ? $dataLBM[8] : "NULL").",
				'$AVGdata',
				'$SUMdata',
				".($commLBM ? $dataLBM[9] : "NULL").",
				'".$dataLBM[0]."',
				'1'
			)";
			
			if(mysqli_query($link, $sql)) {
				if(mysqli_affected_rows($link)>0){
					error_log("Data inserted successfully", 0);
				} else {
					error_log("No rows affected", 0);
				}
			} else {
				error_log("Insert Error: " . mysqli_error($link) . "\nSQL: " . $sql, 0);
			}

			$status = $NormalSERV;
			if ($commUPS == 0) {
				$status = $UpsCommErrSERV;
				goto updateSiteDetail;
			}
			if ($commLBM == 0) {
				$status = $LbmCommErrSERV;
				goto updateSiteDetail;
			}

			$upsStat = hexdec($dataUPS[7]);
			if ($upsStat & hexdec("0080")) {    //bit 7
				$status = $UpsUtilityFailSERV;
				goto updateSiteDetail;
			}
			if ($upsStat & hexdec("0040")) {    //bit 6
				$status = $UpsLowBattSERV;
				goto updateSiteDetail;
			}
			if ($upsStat & hexdec("0010")) {    //bit 4
				$status = $UpsFailSERV;
				goto updateSiteDetail;
			}
			if ($upsStat & hexdec("0002")) {    //bit 1
				$status = $UpsShutdownSERV;
				goto updateSiteDetail;
			}
			if ($dataUPS[3] > $UpsHighLoad) {
				$status = $UpsHighLoadSERV;
				goto updateSiteDetail;
			}
			if ($dataUPS[6] > $UpsHighTemp) {
				$status = $UpsHighTempSERV;
				goto updateSiteDetail;
			}

			$lbmStat = hexdec($dataLBM[0]);
			if ($lbmStat & hexdec("0100")) {    //bit 9
				$status = $LbmHighTempSERV;
				goto updateSiteDetail;
			}
			if ($lbmStat & hexdec("0040")) {    //bit 6
				$status = $LbmSupplyFailSERV;
				goto updateSiteDetail;
			}

		updateSiteDetail:
			// ค้นหา event_id ที่เหมาะสมจากสถานะ
			$event_id = 1; // default เป็น Normal
			if ($status == $CommErrSERV) $event_id = 2;
			else if ($status == $HighAmbientSERV) $event_id = 3;
			else if ($status == $UpsCommErrSERV) $event_id = 5;
			else if ($status == $UpsUtilityFailSERV) $event_id = 6;
			else if ($status == $UpsLowBattSERV) $event_id = 7;
			else if ($status == $UpsFailSERV) $event_id = 8;
			else if ($status == $UpsShutdownSERV) $event_id = 9;
			else if ($status == $UpsHighLoadSERV) $event_id = 10;
			else if ($status == $UpsHighTempSERV) $event_id = 11;
			else if ($status == $LbmCommErrSERV) $event_id = 14;
			else if ($status == $LbmHighTempSERV) $event_id = 12;
			else if ($status == $LbmSupplyFailSERV) $event_id = 15;

			// อัพเดทสถานะและ event_id ในตาราง ups_data
			$sql = "UPDATE ups_data SET event_id = $event_id WHERE ups_id = '$ups_id' AND last_signal_updated = '$date'";
			mysqli_query($link, $sql);

			// อัพเดทสถานะใน sitedetail
			$sql = "UPDATE sitedetail SET status='$status', lastupdate='$date' WHERE NBserial='$SN'";
			mysqli_query($link, $sql);
			if(mysqli_affected_rows($link)>0){
				// อัพเดทสำเร็จ
			}
			mysqli_close($link);
		}
	}
?> 
    
