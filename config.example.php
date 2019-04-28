<?php
/**
 * Created by PhpStorm.
 * User: planetoid
 * Date: 2019-01-28
 * Time: 23:29
 */

// https://developers.line.biz/ -> Channel Settings -> Channel access token (long-lived)
$channel_token = "";

// https://developers.line.biz/ -> Channel Settings -> Channel secret
$channel_secret = "";

$linebot_verify_url = 'https://api.line.me/v2/oauth/verify';

$base_url = "";
$google_sheet_url = "";
$google_tracking_id = "";
$google_app_script_url = '';

// GA
$ga_protocol_version = '1';
$ga_tracking_id = "UA-XXXX-X";

putenv("LINEBOT_CHANNEL_TOKEN={$channel_token}");
putenv("LINEBOT_CHANNEL_SECRET={$channel_secret}");

putenv("LINEBOT_VERIFY_URL={$linebot_verify_url}");
putenv("BASE_URL={$base_url}");
putenv("GOOGLE_SHEET_API={$google_sheet_url}");
putenv("GOOGLE_APP_SCRIPT_API={$google_app_script_url}");

return (object) [
    'channel_token'  => $channel_token,
    'channel_secret' => $channel_secret,
    'verify_url'  => $linebot_verify_url,
    'base_url'  => $base_url,
    'google_sheet_url'  => $google_sheet_url,
    'google_app_script_url'  => $google_app_script_url,

    // GA
    'ga_protocol_version'  => $ga_protocol_version,
    'ga_tracking_id'  => $ga_tracking_id,
];