<?php
date_default_timezone_set('Asia/Bangkok');
$type = $_POST['type-select'];
$Table_name = $_POST['table-name'];
$Key_data = $_POST['key'];
$Value_data = $_POST['value'];
$Format_data = $_POST['table-format'];

function checkAndConvertToInterval($data) {
    $checker_numeric = str_replace("'","",$data);
    // ใช้ฟังก์ชัน is_numeric เพื่อตรวจสอบว่าข้อมูลเป็นเลขทศนิยมหรือไม่
    if (is_numeric($checker_numeric) && strpos($checker_numeric, '.') !== false) {
        $number = explode(".",$checker_numeric);
        if(count($number) > 1){
            if(strlen($number[0]) > 1){
                if(substr($number[0], 0) !== 0){
                    
                    return "'".$number[0].".".$number[1]."'";
                }else{
                    return $number[0].".".$number[1];
                }
            }else{
                return $number[0].".".$number[1];
            }
        }else{
            return $checker_numeric; // ถ้าข้อมูลเป็นเลขทศนิยม ให้คืนค่าเป็น integer
        }
    } else {
        return $data; // ในกรณีอื่น ให้คืนค่าเดิม
    }
}

function countDigitsBeforeDecimal($number) {
    // หาตำแหน่งของจุด (.)
    $dot_position = strpos($number, '.');

    // ตรวจสอบว่ามีจุด (.) ในสตริงหรือไม่
    if ($dot_position !== false) {
        // หาจำนวนตัวเลขที่อยู่หน้าจุด
        $num_before_dot = $dot_position;
        
        // ดึงตัวเลขที่อยู่หน้าจุดออกมา
        $digits = substr($number, 0, $dot_position);
        
        // ส่งผลลัพธ์ออกมาในรูปแบบของอาร์เรย์
        return array($num_before_dot, $digits);
    } else {
        // ถ้าไม่มีจุด (.) ในสตริง ให้คืนค่า null
        return null;
    }
}

function checkDataType($data) {
    // ตรวจสอบว่าข้อมูลเป็นตัวเลขและไม่มี leading zero
    if (is_numeric($data) && strpos($data, '0') !== 0) {
        list($num_count, $firt_number) = countDigitsBeforeDecimal($data);
        if($num_count > 1){
            return (float)$data; // ถ้าข้อมูลเป็นตัวเลขและไม่มี leading zero ให้คืนค่าเป็น Interval (integer)
        }elseif($num_count == 1 && $firt_number !== 0){
            return (float)$data;
        }else{
            return (int)$data;
        }
    } else {
        if((string)$data == "0"){
            return (float)$data; // ในกรณีอื่น ๆ ให้คืนค่าเป็น string ที่อยู่ภายใน ' '
        }else{
            return "'" . (string)$data . "'";
        }
    }
}
function Check_ADv($value){
    $output = preg_replace('/@v\d+/', "''", $value);
    return $output;
}

function processString($input) {
    // ตรวจสอบว่ามีเครื่องหมาย ' หรือไม่
    if (strpos($input, "'") !== false) {
        // ลบเครื่องหมาย ' ออก
        $input_setter = str_replace("'", "", $input);
        
        // ลองแปลงเป็น float
        if(is_numeric($input_setter)){
            $float_value = floatval($input_setter);
        }else{
            return $input;
        }
        // ตรวจสอบว่าสามารถเปลี่ยนเป็น float ได้หรือไม่
        // และตรวจสอบว่าตัวแรกไม่เป็น 0
        if (is_float($float_value) && $float_value != 0 && $input_setter[0] != '0') {
            return $float_value;
        } else {
            // ถ้าไม่สามารถเปลี่ยนเป็น float ได้หรือตัวแรกเป็น 0 ให้คืนค่าเดิม
            return $input;
        }
    } else {
        // ถ้าไม่มีเครื่องหมาย ' ในข้อมูลให้คืนค่าเดิม
        return $input;
    }
}

function null_removal($data){
    if(strpos($data, "@v") !== null){
        $output = preg_replace('/@v(\d+)/', "''", $data);
        return $output;
    }else{
        return $data;
    }
}

function processData($data, $replace, $k_replace) {
    // แทนที่ @k ด้วยค่าใน $k_replace
    $data = str_replace('@k', $k_replace, $data);
    // แทนค่าใน @v{ตัวเลข} ด้วยค่าใน $replace
    $data = preg_replace_callback('/@v(\d+)/', function($matches) use ($replace) {
        $index = (int)$matches[1]-1;
        return isset($replace[$index]) ? ($replace[$index]) : $matches[0];
    }, $data);

    return $data;
}

function removeConsecutiveNonBreakingSpaces($input) {
    // ใช้ str_replace เพื่อลบ `&nbsp;` ที่ติดกันด้วย `&nbsp;` อันเดียว
    $output = preg_replace('/( +)/', "", $input);
    return $output;
}

function replaceVar($input) {
    $output = preg_replace('/=\'\'/', "=#valuenull", $input); 
    //$output = preg_replace('/=\{\}/', "=#valuenull", $output);
    $output = preg_replace('/=\[\]/', "=#valuenull", $output); 
    $output = preg_replace('/=\[\[\]\]/', "=#valuenull", $output);
    $output = preg_replace('/=\[\'\'\]/', "=#valuenull", $output);
    //$output = preg_replace('/=\{\'\'\}/', "=#valuenull", $output);
    $output = preg_replace('/=,/', "=#valuenull,", $output); 
    $output = preg_replace('/\w+=#valuenull/', "#varnull=#valuenull", $output);
    $output = preg_replace('/\[\'\w+\'\]=#valuenull/', "#varnull=#valuenull", $output); 
    $output = preg_replace('/\[\'\d+\'\]=#valuenull/', "#varnull=#valuenull", $output); 
    $output = preg_replace('/\[\d+\]=#valuenull/', "#varnull=#valuenull", $output); 
    $output = preg_replace('/\w+=,/', "#null", $output); 
    $output = preg_replace('/\w+=}/', "#null}", $output);
    $output = preg_replace('/#varnull=#valuenull/', "#null", $output);
    //$output = preg_replace('/\w+=\{\}/', "#null", $output);
    $output = preg_replace('/,#null/', "", $output);
    $output = preg_replace('/#null/', "", $output);
    $output = preg_replace('/{,/', "{", $output);
    return ($output);
}

function getFirstDigit($number) {
    // ใช้ preg_match เพื่อค้นหาตัวเลขแรกที่ไม่ใช่ 0 หรือจุด (.)
    if (preg_match('/[1-9]/', $number, $matches)) {
        // คืนค่าตัวเลขแรกที่พบ
        return $matches[0];
    } else {
        // ถ้าไม่พบตัวเลขที่ไม่ใช่ 0 หรือจุด (.) ในสตริง ให้คืนค่า 0
        return 0;
    }
}


function FixFloat($data){
    if(strpos("'",$data) !== null){
        $data_fix = str_replace("'","",$data);
        if(is_numeric($data_fix)){
            if(getFirstDigit($data_fix) !== 0){
                return $data;
            }
        }else{
            return $data;
        }
    }else{
        return $data;
    }
}

function logVisitorData($file,$msg) {
    $date = date("d/m/Y H:i:s");
    $ip = $_SERVER['REMOTE_ADDR'];
    $data = "$date [$ip] {$msg}\n";
    // เปิดไฟล์เพื่อเพิ่มข้อมูล
    $fileContents = file_get_contents($file);
    if ($handle = fopen($file, 'w+')) {
        // เขียนข้อมูลลงในไฟล์
        fwrite($handle, $fileContents.$data);
        // ปิดไฟล์
        fclose($handle);
    } else {
        echo "ไม่สามารถเปิดไฟล์ได้";
    }
}

if($type == "var"){
    $Key_array = explode("\r\n",$Key_data);
    $Value_array = explode("\r\n",$Value_data);
    echo "9001\n";
    //echo("-- Generate by www.voxtolua.free.nf -- \n\n");
    foreach($Key_array as $key => $line) {
        $int_checker = processString(checkAndConvertToInterval(checkDataType($Value_array[$key])));
        echo($line." = $int_checker"."\n");
    }
    logVisitorData("formatting_logs.txt","Variable (type)");
}elseif($type == "tab"){
    $Key_array = explode("\r\n",$Key_data);
    $Value_array = explode("\r\n",$Value_data);
    echo "9001\n";
    //echo("-- Generate by www.voxtolua.free.nf -- \n\n");
    echo($Table_name." = {");
    foreach($Value_array as $key => $line) {
        $value_inline = explode("	",$Value_array[$key]);
        $result = null_removal(processData(removeConsecutiveNonBreakingSpaces($Format_data), $value_inline, $Key_array[$key]));
        $result = str_replace('@n', intval($key)+1, $result);
        echo("\n\t".replaceVar(replaceVar(replaceVar(($result)))).",");
    }
    logVisitorData("formatting_logs.txt","Table (type) [$Format_data]");
    echo("\n}");
}else{
    echo "9002";
}




?>