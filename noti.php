<?php
date_default_timezone_set('Asia/Bangkok');
function noti($content,$file){
    $timezone = new DateTimeZone('Asia/Bangkok');
    $date = new DateTime('now', $timezone);
    $curl = curl_init();
    curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://discord.com/api/webhooks/1279391710211735572/n91433hkjklym2Dl_B5SIF08g26Oc87mRtkbhThpAtYshMjXtk85OsWHMrHEI0-x-TyW',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => array('content' => $content,'username' => Strval(date("d/m/Y H:i:s")), 'file' => new CURLFILE($file)),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
}

