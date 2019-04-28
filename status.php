<?php

/**
 * Created by PhpStorm.
 * User: planetoid
 * Date: 2019-01-30
 * Time: 21:07
 */

require_once( dirname(__FILE__) . '/vendor/linecorp/line-bot-sdk/line-bot-sdk-tiny/LINEBotTiny.php');
require_once( dirname(__FILE__) . '/config.php');

$channel_access_token = getenv('LINEBOT_CHANNEL_TOKEN');
$channel_secret = getenv('LINEBOT_CHANNEL_SECRET');

try {
    // 建立Client from LINEBotTiny
    $client = new LINEBotTiny($channel_access_token, $channel_secret);

} catch (Exception $e) {
    //echo 'Caught exception: ',  $e->getMessage(), "\n";
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    error_log("Caught exception: " . $e->getMessage());
}