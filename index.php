<?php

use CareElderBot;
use TheIconic\Tracking\GoogleAnalytics\Analytics;

header("Content-Type:text/html; charset=utf-8");

// config
$path = __DIR__ . '/config.php';
if(file_exists($path)){
    $init_config = require($path);
}

if(!isset($init_config)){
    echo '[Error] $init_config is not defined!';
    return null;
}

// 建立 Client
$path = __DIR__ . '/load.php';
if(file_exists($path)){
    require_once __DIR__ . '/load.php';
}
$client = new CareElderBot\CareElderBot($init_config);

$debug = false;
//$debug = true;

// 取得事件
foreach ($client->parseEvents() as $event) {

    if($event['source']['type'] === 'user'){
        $user_id = $event['source']['userId'];
    }else{
        $user_id = null;
    }

    $ga_protocol_version = $client->ga_protocol_version;
    $ga_tracking_id = $client->ga_tracking_id;
    $time_start = microtime_float();

    // Instantiate the Analytics object
    // optionally pass TRUE in the constructor if you want to connect using HTTPS
    $analytics = new Analytics(true);

    // Build the GA hit using the Analytics class methods
    // they should Autocomplete if you use a PHP IDE
    $analytics
        ->setProtocolVersion($ga_protocol_version)
        ->setTrackingId($ga_tracking_id)
        ->setClientId($user_id)
    ;

    $analytics
        ->setDocumentPath('/');

    // When you finish bulding the payload send a hit (such as an pageview or event)
    $analytics->sendPageview();


    $analytics->setEventCategory('UserInput')
        ->setEventAction('MessageType')
        ->setEventLabel($event['type'])
        ->sendEvent();


    switch ($event['type']) {
        case 'message':
            // 讀入訊息
            $message = $event['message'];

            if($debug){
                error_log('$event: ' . print_r($event, true));
                error_log('message: ' . print_r($message, true));
            }

            $reply_content = $client->getReplyContent($message);
            $client->__set('replyToken', $event['replyToken']);
            if($debug){
                error_log("reply_content: " . print_r($reply_content, true));
                error_log("replyToken: " . print_r($event['replyToken'], true));
            }

            switch ($message['type']) {
                case 'text':

                    $analytics->setEventCategory('Article')
                        ->setEventAction('Search')
                        ->setEventLabel($message['text'])
                        ->sendEvent();

                    $time_end = microtime_float();
                    $time = $time_end - $time_start;
                    $time = $time * 1000000;
                    $time = (int) $time;
                    //echo '$time (ms): ' . $time . PHP_EOL;

                    $analytics->setEventCategory('Performance')
                        ->setEventAction('ResponseTimeMicroSeconds')
                        ->setEventLabel($message['text'])
                        ->setEventValue($time)
                        ->sendEvent();

                    // 回覆訊息
                    $client->replyMessage(array(
                        'replyToken' => $event['replyToken'],
                        'messages' => $reply_content,
                    ));
                    break;

                case 'sticker':
                    // 回覆訊息
                    $reply_content = array();
                    $reply_content[] = array(
                        'type' => 'text',
                        'text' => '不好意思，機器人程式目前看不懂貼圖～',
                    );
                    $reply_content[] = array(
                        'type' => 'sticker',
                        'packageId' => "1",
                        'stickerId' => "1"
                    );
                    $client->replyMessage(array(
                        'replyToken' => $event['replyToken'],
                        'messages' => $reply_content,
                    ));
                    break;

                case 'image':
                    // 回覆訊息
                    //$message = $client->saveMessageContent($message["id"]);
                    $reply_content = array();
                    $reply_content[] = array(
                        'type' => 'text',
                        'text' => '如果你想要投稿圖片，請點選網址投搞： https://goo.gl/forms/RV43Ni8B1beaSu0w2',
                    );
                    $client->replyMessage(array(
                        'replyToken' => $event['replyToken'],
                        'messages' => $reply_content,
                    ));
                    break;

                default:
                    /*
                    $reply_content = array();
                    $reply_content[] = array(
                        'type' => 'text',
                        'text' => "不支援的訊息類型: " . $message['type'],
                    );
                    $client->replyMessage(array(
                        'replyToken' => $event['replyToken'],
                        'messages' => $reply_content,
                    ));
                    */
                    error_log("Un-supported message type: " . $message['type']);
                    break;
            }
            break;

        case 'postback':
            // 回覆訊息
            $save_result = $client->savePostbackEvent($event['postback']['data'], $user_id);

            //error_log('$event: ' . print_r($event, true));
            //error_log('$save_result: ' . print_r($save_result, true));

            $reply_content = array();
            $reply_content[] = array(
                'type' => 'text',
                'text' => '謝謝你，已經收到你的寶貴意見～'
            );
            $client->replyMessage(array(
                'replyToken' => $event['replyToken'],
                'messages' => $reply_content,
            ));
            break;

        default:
            // 回覆訊息
            /*
            $reply_content = array();
            $reply_content[] = array(
                'type' => 'text',
                'text' => "不支援的事件類型: " . $event['type'],
            );
            $client->replyMessage(array(
                'replyToken' => $event['replyToken'],
                'messages' => $reply_content,
            ));
            */
            error_log("Un-supported event type: " . $event['type']);
            break;
    }
};
