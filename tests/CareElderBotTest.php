<?php
/**
 * Created by PhpStorm.
 * User: planetoid
 * Date: 15/3/16
 * Time: 下午3:33
 */

use PHPUnit\Framework\TestCase;
use CareElderBot;

require_once __DIR__ . '/../load.php';

class CareElderBotTest extends TestCase {

    private $myClass;

    protected function setUp(): void
    {
        $init_config = (object) [
            'channel_token'  => '',
            'channel_secret' => '',
            'verify_url'  => '',
            'base_url'  => '',
            'google_sheet_url'  => '',
            'google_app_script_url'  => '',
            'show_debug_message'  => true,
            'image_placeholder'  => "",
            'image_thumbnail_placeholder'  => "",
            'ga_protocol_version'  => "",
            'ga_tracking_id'  => "",
        ];
        $this->myClass = new CareElderBot\CareElderBot($init_config);
    }

    protected function tearDown(): void
    {
        $this->myClass = null;
        //echo __FUNCTION__ . ' called' . "\r\n<br />";
    }


    public function test_getMockMessage(){
        $message = array(
            'type' => 'text',
            'id' => 123,
            'text' => 'test:echo boston we have a problem'
        );
        $expected_result = array(
            array(
                'type' => 'text',
                'text' => "boston we have a problem",
            )
        );
        $actual_result = $this->myClass->getMockMessage($message);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);

    }

    public function test_checkReplyMessage(){

        $this->myClass->show_debug_message = false;
        //$this->myClass->show_debug_message = true;

        $message = array
        (
            "replyToken" => "00000000000000000000000000000000",
            "messages" => array
                (
                    0 => Array
                        (
                            "type" => "text",
                            "text" => "抱歉，沒有找到符合的資料。也歡迎你動手貢獻～"
                        )

                )
        );
        $expected_result = array
        (
            0 => Array
            (
                "type" => "text",
                "text" => "抱歉，沒有找到符合的資料。也歡迎你動手貢獻～"
            )

        );
        $actual_result = $this->myClass->checkReplyMessage($message);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);

        $message = array
        (
            //"replyToken" => "00000000000000000000000000000000",
            "messages" => array
            (
                0 => Array
                (
                    "type" => "text",
                    "text" => "抱歉，沒有找到符合的資料。也歡迎你動手貢獻～"
                )

            )
        );
        $expected_result = Array
        (
            0 => Array
            (
                "type" => "text",
                "text" => "the replyToken element is not exists"
            )
        );
        $actual_result = $this->myClass->checkReplyMessage($message);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);

        $message = array
        (
            "replyToken" => "00000000000000000000000000000000",
        );
        $expected_result = Array
        (
            0 => Array
            (
                "type" => "text",
                "text" => "the messages element is not exists"
            )
        );
        $actual_result = $this->myClass->checkReplyMessage($message);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);

        // max of message count is 5
        $message = array
        (
            "replyToken" => "00000000000000000000000000000000",
            "messages" => array
            (
                0 => Array
                (
                    "type" => "text",
                    "text" => "抱歉，沒有找到符合的資料。也歡迎你動手貢獻～"
                ),
                1 => Array
                (
                    "type" => "text",
                    "text" => "抱歉，沒有找到符合的資料。也歡迎你動手貢獻～"
                ),
                2 => Array
                (
                    "type" => "text",
                    "text" => "抱歉，沒有找到符合的資料。也歡迎你動手貢獻～"
                ),
                3 => Array
                (
                    "type" => "text",
                    "text" => "抱歉，沒有找到符合的資料。也歡迎你動手貢獻～"
                ),
                4 => Array
                (
                    "type" => "text",
                    "text" => "抱歉，沒有找到符合的資料。也歡迎你動手貢獻～"
                ),
                5 => Array
                (
                    "type" => "text",
                    "text" => "抱歉，沒有找到符合的資料。也歡迎你動手貢獻～"
                )

            )
        );
        $expected_result = Array
        (
            0 => Array
            (
                "type" => "text",
                "text" => "max of messages element is 5"
            )
        );
        $actual_result = $this->myClass->checkReplyMessage($message);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);

        $message = null;
        $expected_result = Array
        (
            0 => Array
            (
                "type" => "text",
                "text" => '$message is not array'
            )
        );
        $actual_result = $this->myClass->checkReplyMessage($message);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);



        // Carousel template https://developers.line.biz/en/reference/messaging-api/#carousel
        // message: must be non-empty text
        $json = <<<EOT

                {
                    "type": "template",
                    "altText": "不支援顯示樣板",
                    "template": {
                        "type": "carousel",
                        "imageAspectRatio": "rectangle",
                        "imageSize": "contain",
                        "columns": [
                            {
                                "thumbnailImageUrl": "https://i.imgur.com/hUjEE3Nh.jpg",
                                "imageBackgroundColor": "#a8e8fb",
                                "title": "更粗的標題",
                                "text": "第一組標題",
                                "defaultAction": {
                                    "type": "message",
                                    "label": "點到圖片或標題",
                                    "text": "0"
                                },
                                "actions": [
                                    {
                                        "type": "message",
                                        "label": "第一個按鈕",
                                        "text": "1"
                                    },
                                    {
                                        "type": "message",
                                        "label": "第二個按鈕",
                                        "text": "2"
                                    },
                                    {
                                        "type": "message",
                                        "label": "第三個按鈕",
                                        "text": "3"
                                    }
                                ]
                            },
                            {
                                "thumbnailImageUrl": "https://i.imgur.com/eoZd524h.jpg",
                                "imageBackgroundColor": "#a8e8fb",
                                "title": "更粗的標題",
                                "text": "第二組標題",
                                "defaultAction": {
                                    "type": "message",
                                    "label": "點到圖片或標題",
                                    "text": "0"
                                },
                                "actions": [
                                    {
                                        "type": "message",
                                        "label": "第一個按鈕",
                                        "text": "1"
                                    },
                                    {
                                        "type": "message",
                                        "label": "第二個按鈕",
                                        "text": "2"
                                    },
                                    {
                                    "type": "message",
                                        "label": "第三個按鈕",
                                        "text": "3"
                                    }
                                ]
                            }
                        ]
                    }
                }

EOT;

        $json = trim($json);
        $message = array
        (
            "replyToken" => "00000000000000000000000000000000",
            "messages" => array(
                0 => json_decode($json, true)
            )
        );
        $expected_result = array(
            0 => json_decode($json, true)
        );
        $actual_result = $this->myClass->checkReplyMessage($message);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);


        $json = <<<EOT

                {
                    "type": "template",
                    "altText": "不支援顯示樣板",
                    "template": {
                        "type": "carousel",
                        "imageAspectRatio": "rectangle",
                        "imageSize": "contain",
                        "columns": [
                            {
                                "thumbnailImageUrl": "https://i.imgur.com/hUjEE3Nh.jpg",
                                "imageBackgroundColor": "#a8e8fb",
                                "title": "更粗的標題",
                                "text": "",
                                "defaultAction": {
                                    "type": "message",
                                    "label": "點到圖片或標題",
                                    "text": "0"
                                },
                                "actions": [
                                    {
                                        "type": "message",
                                        "label": "第一個按鈕",
                                        "text": "1"
                                    },
                                    {
                                        "type": "message",
                                        "label": "第二個按鈕",
                                        "text": "2"
                                    },
                                    {
                                        "type": "message",
                                        "label": "第三個按鈕",
                                        "text": "3"
                                    }
                                ]
                            },
                            {
                                "thumbnailImageUrl": "https://i.imgur.com/eoZd524h.jpg",
                                "imageBackgroundColor": "#a8e8fb",
                                "title": "更粗的標題",
                                "text": "第二組標題",
                                "defaultAction": {
                                    "type": "message",
                                    "label": "點到圖片或標題",
                                    "text": "0"
                                },
                                "actions": [
                                    {
                                        "type": "message",
                                        "label": "第一個按鈕",
                                        "text": "1"
                                    },
                                    {
                                        "type": "message",
                                        "label": "第二個按鈕",
                                        "text": "2"
                                    },
                                    {
                                    "type": "message",
                                        "label": "第三個按鈕",
                                        "text": "3"
                                    }
                                ]
                            }
                        ]
                    }
                }

EOT;

        $json = trim($json);
        $message = array
        (
            "replyToken" => "00000000000000000000000000000000",
            "messages" => array(
                0 => json_decode($json, true)
            )
        );
        $expected_result = Array
        (
            0 => Array
            (
                "type" => "text",
                "text" => 'must be non-empty text'
            )
        );
        $actual_result = $this->myClass->checkReplyMessage($message);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);


    }

    function test_getImgurUrl(){
        // specify the image size
        $url = "https://i.imgur.com/crUeZea.jpg";
        $image_size = "big";
        $expected_result = "https://i.imgur.com/crUeZeab.jpg";
        $actual_result = $this->myClass->getImgurUrl($url, $image_size);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);

        // not specify the image size
        $url = "https://i.imgur.com/crUeZea.jpg";
        $image_size = null;
        $expected_result = "https://i.imgur.com/crUeZea.jpg";
        $actual_result = $this->myClass->getImgurUrl($url, $image_size);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);

        // illegal input
        $url = " ";
        $expected_result = null;
        $actual_result = $this->myClass->getImgurUrl($url);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);

        $url = "https://www.google.com";
        $expected_result = null;
        $actual_result = $this->myClass->getImgurUrl($url);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);
    }

    function test_getImagePlaceholder(){
        $expected_list = array(
            'https://i.imgur.com/vkLDIAUh.png', // 一起傳送你的關心
            'https://i.imgur.com/5V8FdiM.jpg' // line channel profile image
        );
        $actual_result = $this->myClass->getImagePlaceholder();
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertContains($actual_result, $expected_list);
    }


    function test_checkJson(){
        $json = <<<EOT

{"type":"template"}

EOT;

        $json = trim($json);
        $expected_result = true;
        $actual_result = $this->myClass->checkJson($json);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);

        // INVALID JSON: Strings should be wrapped in double quotes
        $json = <<<EOT

{"type":'template'}

EOT;

        $json = trim($json);
        $expected_result = null;
        $actual_result = $this->myClass->checkJson($json);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);
    }


    function test_getReplyContent(){
        // empty google sheet url
        $expected_result = array(
            array(
                'type' => 'text',
                'text' => '抱歉，文章資料庫發生故障，請稍後重試、或與我們聯繫～'
            )
        );
        $actual_result = $this->myClass->getReplyContent();
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);
    }

    function test_getKeywordInfo(){
        $user_text = '顯示關鍵字「mode:carousel 假新聞」第8筆的搜尋結果 ...';
        $expected_result = array('假新聞', 8);
        $actual_result = $this->myClass->getKeywordInfo($user_text);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);
    }

    function test_getRandomImageUrl(){
        $expected_result = true;
        $image_size = 'huge';
        $actual_result = $this->myClass->getRandomImageUrl($image_size);

        //Show filename without file extension
        $file_name = basename($actual_result);

        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, strlen($file_name) > 3);
    }

    function test_getTruncatedString(){
        $text = '顯示關鍵字第1筆的搜尋結果 ...';
        $expected_result = '顯示關鍵字第';
        $chacacter_limit = 7;
        $actual_result = $this->myClass->getTruncatedString($text, $chacacter_limit);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);

        $text = '顯示關鍵字第1筆的搜尋結果 ...';
        $expected_result = '顯示關';
        $chacacter_limit = 4;
        $actual_result = $this->myClass->getTruncatedString($text, $chacacter_limit);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);

    }

    function test_set_and_get(){
        $config_name = 'replyToken';
        $config_value = 'xxx';
        $this->myClass->__set($config_name, $config_value);
        $expected_result ='xxx';
        $actual_result = $this->myClass->__get($config_name);
        //echo '$actual_result:' . print_r($actual_result, true) . PHP_EOL;
        $this->assertEquals($expected_result, $actual_result);
    }
}
