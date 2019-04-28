<?php

require_once (dirname(__FILE__) . "/config.php");

$linebot_verify_url = getenv('LINEBOT_VERIFY_URL');
$access_token = getenv('LINEBOT_CHANNEL_TOKEN');

// CURL
$ch = curl_init($linebot_verify_url);


curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Authorization: Bearer ' . $access_token ]);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

$result = curl_exec($ch);
curl_close($ch);

//error_log('Returned result: ' . print_r($result, true));

// Echo
echo print_r($result);