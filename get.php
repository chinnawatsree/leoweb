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

$SUMdata = 0 ;
$AVGdata = 0 ; 

date_default_timezone_set("Asia/Bangkok");

function dbg($label, $val="") {
    $log_filename = 'get_php_log.txt';
    $entry = "[".date('Y-m-d H:i:s')."] $label";
    if ($val !== "") $entry .= " | $val";
    $entry .= "\n";
    file_put_contents($log_filename, $entry, FILE_APPEND);
}

if((isset($_GET['submit']))&&(isset($_GET['id']))&&(isset($_GET['data']))) { 
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
        $link = $conn;
        $SN_safe = mysqli_real_escape_string($link, $SN);

        dbg("Connected to database successfully");

        // ========== หา ups_id จาก ups_devices ==========
        $sql = "SELECT ups_id FROM ups_devices WHERE NBserial='$SN_safe'";
        dbg("Query ups_devices by NBserial", $sql);
        $ups_id = "";
        if($result = mysqli_query($link, $sql)){
            if($row = mysqli_fetch_assoc($result)){
                $ups_id = $row['ups_id'];
                dbg("Fetched ups_id from ups_devices", $ups_id);
            } else {
                dbg("No matching NBserial in ups_devices", $SN_safe);
            }
            mysqli_free_result($result);
        } else {
            dbg("Query error ups_devices", mysqli_error($link));
        }

        // ========== ตรวจสอบ ups_id ==========
        if($ups_id == ""){
            dbg("Empty ups_id", "NBserial=$SN_safe");
            echo "ACKERR: Unknown NBserial '$SN_safe'. Please add to ups_devices and link ups_id.";
            exit;
        }

        // ========== INSERT ข้อมูลลง ups_data ==========
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
            ".($commUPS ? "'".$dataUPS[0]."'" : "NULL").",
            ".($commUPS ? "'".$dataUPS[1]."'" : "NULL").",
            ".($commUPS ? "'".$dataUPS[2]."'" : "NULL").",
            ".($commUPS ? "'".$dataUPS[3]."'" : "NULL").",
            ".($commUPS ? "'".$dataUPS[4]."'" : "NULL").",
            ".($commUPS ? "'".$dataUPS[6]."'" : "NULL").",
            ".($commLBM ? "'".$dataLBM[0]."'" : "NULL").",
            ".($commLBM ? "'".$dataLBM[1]."'" : "NULL").",
            ".($commLBM ? "'".$dataLBM[2]."'" : "NULL").",
            ".($commLBM ? "'".$dataLBM[3]."'" : "NULL").",
            ".($commLBM ? "'".$dataLBM[4]."'" : "NULL").",
            ".($commLBM ? "'".$dataLBM[5]."'" : "NULL").",
            ".($commLBM ? "'".$dataLBM[6]."'" : "NULL").",
            ".($commLBM ? "'".$dataLBM[7]."'" : "NULL").",
            ".($commLBM ? "'".$dataLBM[8]."'" : "NULL").",
            '$AVGdata',
            '$SUMdata',
            ".($commLBM ? "'".$dataLBM[9]."'" : "NULL").",
            ".($commLBM ? "'".$dataLBM[0]."'" : "NULL").",
            '1'
        )";

        if(mysqli_query($link, $sql)) {
            if(mysqli_affected_rows($link)>0){
                dbg("Data inserted successfully");
            } else {
                dbg("Insert executed", "No rows affected");
            }
        } else {
            dbg("Insert Error", mysqli_error($link)." | SQL=".$sql);
        }

        mysqli_close($link);
    }
}
?>
