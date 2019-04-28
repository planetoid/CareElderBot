<?php
/**
 * Copyright 2016 LINE Corporation
 *
 * LINE Corporation licenses this file to you under the Apache License,
 * version 2.0 (the "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at:
 *
 *   https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */



namespace CareElderBot;

use TheIconic\Tracking\GoogleAnalytics\Analytics;

/*
 * This polyfill of hash_equals() is a modified edition of https://github.com/indigophp/hash-compat/tree/43a19f42093a0cd2d11874dff9d891027fc42214
 *
 * Copyright (c) 2015 Indigo Development Team
 * Released under the MIT license
 * https://github.com/indigophp/hash-compat/blob/43a19f42093a0cd2d11874dff9d891027fc42214/LICENSE
 */
if (!function_exists('hash_equals')) {
    defined('USE_MB_STRING') or define('USE_MB_STRING', function_exists('mb_strlen'));

    function hash_equals($knownString, $userString)
    {
        $strlen = function ($string) {
            if (USE_MB_STRING) {
                return mb_strlen($string, '8bit');
            }

            return strlen($string);
        };

        // Compare string lengths
        if (($length = $strlen($knownString)) !== $strlen($userString)) {
            return false;
        }

        $diff = 0;

        // Calculate differences
        for ($i = 0; $i < $length; $i++) {
            $diff |= ord($knownString[$i]) ^ ord($userString[$i]);
        }
        return $diff === 0;
    }
}


class CareElderBot
{
    private $channel_token;
    private $channel_secret;
    private $base_url;
    private $google_sheet_url;
    private $google_app_script_url;
    private $show_debug_message = false;
    private $replyToken = null;
    private $ga_protocol_version;
    private $ga_tracking_id;

    public function __construct($init_config)
    {
        $this->channel_token = $init_config->channel_token;
        $this->channel_secret = $init_config->channel_secret;
        $this->base_url = $init_config->base_url;
        $this->google_sheet_url = $init_config->google_sheet_url;
        $this->google_app_script_url = $init_config->google_app_script_url;
        $this->show_debug_message = $init_config->show_debug_message;

        // GA
        $this->ga_protocol_version = $init_config->ga_protocol_version;
        $this->ga_tracking_id = $init_config->ga_tracking_id;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->{$name};
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->{$name} = $value;
    }

    public function parseEvents()
    {
        $debug = false;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            error_log('Method not allowed');
            exit();
        }

        $entityBody = file_get_contents('php://input');
        if($debug){
            error_log('Returned result: ' . print_r($entityBody, true));
        }

        if (strlen($entityBody) === 0) {
            http_response_code(400);
            error_log('Missing request body');
            exit();
        }

        if (!hash_equals($this->sign($entityBody), $_SERVER['HTTP_X_LINE_SIGNATURE'])) {
            http_response_code(400);
            error_log('Invalid signature value');
            exit();
        }

        $data = json_decode($entityBody, true);
        if (!isset($data['events'])) {
            http_response_code(400);
            error_log('Invalid request body: missing events property');
            exit();
        }
        return $data['events'];
    }

    /**
     * @param array $message
     * @return bool|null
     */
    public function checkReplyMessage($message = null){

        $debug = $this->show_debug_message;

        if($debug){
            echo '#### ' . __FUNCTION__ . PHP_EOL;
            echo '$message: ' . print_r($message, true) . PHP_EOL;
        }

        $error = "";
        if(!is_array($message)){
            $error = '$message is not array';
            error_log(__FUNCTION__ . ' ' . $error);
            return array(
                array(
                    'type' => 'text',
                    'text' => $error
                )
            );
        }

        if( is_array($message)
            && !array_key_exists("replyToken", $message)
        ){
            $error = 'the replyToken element is not exists';
            error_log(__FUNCTION__ . ' '  . $error);
            return array(
                array(
                    'type' => 'text',
                    'text' => $error
                )
            );
        }

        if(!array_key_exists("messages", $message)){
            $error = 'the messages element is not exists';
            error_log(__FUNCTION__ . ' '  . $error);
            return array(
                array(
                    'type' => 'text',
                    'text' => $error
                )
            );
        }

        if(array_key_exists("messages", $message)
            && !is_array($message["messages"])
        ){
            $error = 'the messages element is not array';
            error_log(__FUNCTION__ . ' '  . $error);
            return array(
                array(
                    'type' => 'text',
                    'text' => $error
                )
            );
        }

        // Size must be between 1 and 5
        if(array_key_exists("messages", $message)
            && is_array($message["messages"])
            && count($message["messages"]) > 5
        ){
            $error = 'max of messages element is 5';
            error_log(__FUNCTION__ . ' '  . $error);
            return array(
                array(
                    'type' => 'text',
                    'text' => $error
                )
            );
        }

        // Carousel template https://developers.line.biz/en/reference/messaging-api/#carousel
        // message: must be non-empty text
        if(array_key_exists('messages', $message)
        ){
            foreach ($message['messages'] AS $single_message){

                if($debug){
                    echo '$single_message: ' . print_r($single_message, true) . PHP_EOL;
                }

                if(array_key_exists('type', $single_message)
                    && $single_message['type'] === 'template'
                    && array_key_exists('template', $single_message)
                    && array_key_exists('type', $single_message['template'])
                    && $single_message['template']['type'] === 'carousel'
                    && array_key_exists('columns', $single_message['template'])
                ){
                    $reply_message_columns = $single_message['template']['columns'];
                    if($debug){
                        echo '$reply_message_columns: ' . print_r($reply_message_columns, true) . PHP_EOL;
                    }
                    foreach ($reply_message_columns AS $reply_message_column){
                        if(array_key_exists('text', $reply_message_column)
                            && trim($reply_message_column['text']) === ''
                        ){
                            $error = 'must be non-empty text';
                            error_log(__FUNCTION__ . ' '  . $error);
                            return array(
                                array(
                                    'type' => 'text',
                                    'text' => $error
                                )
                            );
                        }
                    }
                }

            }


        }


        // do nothing
        return $message["messages"];
    }

    /**
     * This function checkJson() is a modified edition of https://php.net/manual/en/function.json-last-error.php
     * @copyright Copyright (c) 2001-2019 The PHP Group
     * @param string $json
     * @return bool|null
     */
    function checkJson($json = ''){
        if(!is_array(json_decode($json, true))){
            $error = __FUNCTION__ . PHP_EOL .
                'JSON is not well-formatted! Error: ' . PHP_EOL ;


            switch (json_last_error()) {
                case JSON_ERROR_NONE:
                    $error .= 'No errors';
                    break;
                case JSON_ERROR_DEPTH:
                    $error .= 'Maximum stack depth exceeded';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $error .= 'Underflow or the modes mismatch';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $error .= 'Unexpected control character found';
                    break;
                case JSON_ERROR_SYNTAX:
                    $error .= 'Syntax error, malformed JSON';
                    break;
                case JSON_ERROR_UTF8:
                    $error .= 'Malformed UTF-8 characters, possibly incorrectly encoded';
                    break;
                default:
                    $error .= 'Unknown error';
                    break;
            }

            $error .= PHP_EOL . 'Content of JSON:' . PHP_EOL .
                $json;

            error_log($error);
            return null;
        }

        return true;
    }


    /**
     * @param $message
     * @return null
     */
    public function replyMessage($message)
    {
        $debug = false;
        //$debug = true;

        // overwrite original message if any error was happened
        $messages = $this->checkReplyMessage($message);

        $this->sendReplyMessageApi(array(
            "replyToken" => $message["replyToken"],
            "messages" => $messages
        ));
    }

    /**
     * Copyright 2016 LINE Corporation
     *
     * LINE Corporation licenses this file to you under the Apache License,
     * version 2.0 (the "License"); you may not use this file except in compliance
     * with the License. You may obtain a copy of the License at:
     *
     *   https://www.apache.org/licenses/LICENSE-2.0
     *
     * Unless required by applicable law or agreed to in writing, software
     * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
     * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
     * License for the specific language governing permissions and limitations
     * under the License.
     */
    private function sign($body)
    {
        $hash = hash_hmac('sha256', $body, $this->channel_secret, true);
        $signature = base64_encode($hash);
        return $signature;
    }


    /**
     * @param array $message
     * @return array|null
     */
    public function getReplyContent($message = array()){

        //global $debug;
        $debug = false;
        //$debug = true;
        //$debug = $this->show_debug_message;

        if($debug){
            error_log('$message: ' . print_r($message, true));
        }

        // 將Google表單轉成JSON資料
        $all_articles = $this->getAllArticlesFromGoogle();
        if(is_null($all_articles)){
            return array(
                array(
                    'type' => 'text',
                    'text' => '抱歉，文章資料庫發生故障，請稍後重試、或與我們聯繫～'
                )
            );
        }

        $mock_message = $this->getMockMessage($message);
        if(!is_null($mock_message)){
            return $mock_message;
        }

        $user_text = $message['text'];
        $user_text = trim($user_text);

        if($debug){
            error_log('$message: ' . print_r($message, true));
            error_log('user_text: ' . print_r($user_text, true));
        }

        switch ($user_text) {
            case (preg_match('/^(mode\:all\s)/iu', $user_text) ? true : false) :
            case (preg_match('/^(mode\:keyword\s)/iu', $user_text) ? true : false) :
                $reply_message = $this->searchArticlesFromGoogle($all_articles, $message);
                break;
            // Carousel template https://developers.line.biz/en/reference/messaging-api/#carousel
            case (preg_match('/^(mode\:carousel\s)/iu', $user_text) ? true : false) :
                $reply_message = $this->searchArticlesCarouselFromGoogle($all_articles, $message);
                break;
            // Image Carousel template
            case (preg_match('/^(mode\:image_carousel\s)/iu', $user_text) ? true : false) :
                $reply_message = $this->searchArticlesImageCarouselFromGoogle($all_articles, $message);
                break;
            case (preg_match('/^(顯示關鍵字.*第\d+筆的搜尋結果 ...)$/iu', $user_text) ? true : false) :
                $reply_message = $this->getArticleKnownKeywordRowNumber($all_articles, $message);
                break;
            // Carousel template
            default:
                $reply_message = $this->searchArticlesCarouselFromGoogle($all_articles, $message);
        }

        if($debug){
            error_log('$reply_message: ' . print_r($reply_message, true));
        }

        if(!is_null($reply_message)){
            return $reply_message;
        }

        return array(
            array(
                'type' => 'text',
                'text' => '抱歉，沒有找到符合的資料。也歡迎你動手貢獻～'
            )
        );
    }

    /**
     * @param string $text
     * @param int $character_limit
     * @return string
     */
    public function getTruncatedString($text = '', $character_limit = 0){
        $character_limit--;
        return mb_substr($text, 0, $character_limit, 'UTF-8');
    }

    /**
     * @param string $previous_message
     * @return mixed|null
     */
    public function getVoteButtonsMessage($previous_message = ''){

        $json = <<<EOT

                {
                    "type": "template",
                    "altText": "以上這則訊息長輩是否有反應？",
                    "template": {
                        "type": "buttons",
                        "text": "以上這則訊息長輩是否有反應？",
                        "actions": [
                            {
                                "type":"postback",
                                "label":"是",
                                "data":"act=vote&val=1&pre={$previous_message}"
                            },
                            {
                                "type":"postback",
                                "label":"否",
                                "data":"act=vote&val=0&pre={$previous_message}"
                            }
                        ]
                    }
                }

EOT;

        $json = trim($json);
        $check_json = $this->checkJson($json);
        if(is_null($check_json)){
            return null;
        }

        return json_decode($json, true);
    }

    /**
     * @param array $messages
     * @return string
     */
    public function getPreviousMessage($messages = array()){
        $output = array();

        foreach ($messages AS $message){
            switch ($message['type']) {
                case 'image':
                    $output[] = $message['originalContentUrl'];
                    break;
                case 'text':
                    $tmp_text = $message['text'];
                    // fix json error: Unexpected control character found
                    $tmp_text = str_replace(array("\r\n", "\n", "\r"), '', $tmp_text);

                    // replace multiple spaces with a single space
                    $tmp_text = preg_replace('/\s+/', ' ', $tmp_text);

                    $output[] = $tmp_text;
                    break;
            }
        }
        $known_length = strlen('act=vote&val=0&pre=');
        $length = 290 - $known_length; // Max: 300 characters
        $tmp_output = implode(';', $output);
        return $this->getTruncatedString($tmp_output, $length);
    }

    /**
     * @param null $image_size
     * @return string|null
     */
    function getRandomImageUrl($image_size = null){
        //global $debug;
        $debug = false;

        $image_id = $this->getRandomImgurId();
        $image_url = "https://i.imgur.com/{$image_id}.jpg";
        $image_url = $this->getImgurUrl($image_url, $image_size);

        return $image_url;

    }

    /**
     * @return array
     */
    function getRandomImgurId(){
        $debug = false;
        //$debug = true;

        $image_id_list = array(
            // https://stocksnap.io/search/cat CC0 license
            "L4i2C7d", "IhJ1X4y", "MeDxcPH", "CnQPkSt", "PvE5j7Q",
            "AWPg5yd", "ypPCzop",

            // https://stocksnap.io/search/sea CC0 license
            "hUjEE3N",

            // https://stocksnap.io/search/beach CC0 license
            "eoZd524",

            // https://www.youtube.com/watch?v=7X8II6J-6mU CC0 license
            "crUeZea"
        );
        $num_list = range(0, count($image_id_list));
        $random = array_rand($num_list);

        if($debug){
            error_log(__FUNCTION__ );
            error_log("random: " . print_r($random, true));
            error_log("image_id_list: " . print_r($image_id_list, true));
        }


        return $image_id_list[$random];
    }

    /**
     * @param int $count_of_msg
     * @return array|false|mixed|string|null
     */
    public function getRandomText($count_of_msg = 0){
        $content = file_get_contents("http://more.handlino.com/sentences.json?n={$count_of_msg}");
        $content = json_decode($content, true);
        if(!array_key_exists("sentences", $content)){
            error_log(__FUNCTION__ . ' the sentences element is not exists: ');
            return null;
        }
        if(array_key_exists('sentences', $content)){
            $content = $content['sentences'];
        }

        /*$content = file_get_contents("https://loripsum.net/api/{$count_of_msg}/medium/decorate/");
        $content = explode(PHP_EOL, $content);
        */
        $content = array_filter($content);
        return $content;
    }

    /**
     * @param int $count_of_msg
     * @return array
     */
    public function getMockImageMessage($count_of_msg = 1){
        $debug = false;
        //$debug = true;

        if($debug){
            error_log(__FUNCTION__ );
        }

        $output = array();
        for ($x = 1; $x <= $count_of_msg; $x++) {

            $image_id = $this->getRandomImgurId();
            $output[] = array(
                'type' => 'image',
                'originalContentUrl' => "https://i.imgur.com/{$image_id}.jpg", //"圖片網址",
                'previewImageUrl' => "https://i.imgur.com/{$image_id}b.jpg" //"縮圖網址"
            );
        }

        if($debug){
            error_log('image_id: ' . print_r($image_id, true));
            error_log('output: ' . print_r($output, true));
        }

        return $output;

    }

    /**
     * @return array|mixed
     */
    public function getAllArticlesFromGoogle(){

        $google_sheet_url = $this->google_sheet_url;

        // for test purpose
        if(trim($google_sheet_url) === ''){
            return null;
        }

        $json = file_get_contents($google_sheet_url);
        if(!is_array(json_decode($json, true))){
            error_log('[Error] the returned content is not well-formatted json: ' . print_r($json, true));
            return null;
        }
        return json_decode($json, true);
    }


    /**
     * @param array $all_articles
     * @param array $message
     * @return mixed
     */
    public function getArticleKnownKeywordRowNumber($all_articles = array(), $message = array()){
        $debug = false;
        //$debug = true;

        if($debug){
            error_log('#### ' . __FUNCTION__);
            error_log('$message: ' . print_r($message, true));
        }

        $user_text = $message['text'];
        if($debug){
            error_log('original $user_text: ' . print_r($user_text, true));
        }

        list($user_text, $row_count) = $this->getKeywordInfo($user_text);

        if($debug){
            error_log('removed $user_text: ' . print_r($user_text, true));
            error_log('$row_count: ' . print_r($row_count, true));
        }

        $cache_path = __DIR__ . '/../cache/' . json_encode($user_text) . '.json';

        if(!file_exists($cache_path)){
            error_log('$cache_path of given keyword ' . $user_text. ' is not saved: ' . print_r($cache_path, true));
        }

        if(file_exists($cache_path)){
            $file_content = file_get_contents($cache_path);
            $json = json_decode($file_content, true);
            if($debug){
                error_log('$json: ' . print_r($json, true));
                error_log('$json[$row_count]: ' . print_r($json[$row_count], true));
            }


            $reply_message = $json[$row_count];

            $previous_message = $this->getPreviousMessage($reply_message);
            $reply_message[] = $this->getVoteButtonsMessage($previous_message);

            return $reply_message;
        }
    }


    /**
     * @param array $all_articles
     * @param array $message
     * @return array
     */
    public function searchArticlesFromGoogle($all_articles = array(), $message = array()){

        $debug = $this->show_debug_message;
        $user_text = $message['text'];

        preg_match("/^(mode\:all)/iu", $user_text, $matches);
        if(isset($matches[1])){
            // search keyword & content of text
            $user_text = preg_replace("/^(mode\:all)/iu", '', $user_text);
            $user_text = trim($user_text);
            $search_mode = 'all';
        }else{
            $search_mode = 'keyword';
        }

        if($debug){
            error_log('$message: ' . print_r($message, true));
            error_log('user_text: ' . print_r($user_text, true));
            error_log('search_mode: ' . print_r($search_mode, true));
        }

        // 資料起始從feed.entry
        $reply_message = array();

        $reply_message[] = array(
            'type' => 'text',
            'text' => '你想要找「'.$message['text'].'」請稍後 ...',
        );

        $count_of_matched_articles = 0;
        $matched_articles_result = array();
        foreach ($all_articles['feed']['entry'] as $item) {
            // 將keywords欄位依,切成陣列
            $keywords = explode(',', $item['gsx$關鍵字逗號間隔']['$t']);
            $keywords = array_filter($keywords);
            $keywords = array_map('trim', $keywords);;

            switch ($search_mode) {
                case "all":
                    $full_text = $item['gsx$關鍵字逗號間隔']['$t'] . " " . $item['gsx$文字訊息1']['$t'] . " " . $item['gsx$文字訊息2']['$t'];
                    preg_match("/(" . $user_text . ")/iu", $full_text, $matches);
                    if(isset($matches[1])){

                        if(strlen(trim($item['gsx$文字訊息1']['$t'])) > 0){
                            $reply_message[] = array(
                                'type' => 'text',
                                'text' => trim($item['gsx$文字訊息1']['$t']),
                            );
                        }

                        if(strlen(trim($item['gsx$文字訊息2']['$t'])) > 0){
                            $reply_message[] = array(
                                'type' => 'text',
                                'text' => trim($item['gsx$文字訊息2']['$t']),
                            );
                        }

                        if(strlen(trim($item['gsx$圖片訊息圖片網址']['$t'])) > 0
                            && strlen(trim($item['gsx$圖片訊息縮圖網址']['$t'])) > 0
                        ){
                            $reply_message[] = array(
                                "type" => "image",
                                "originalContentUrl" => trim($item['gsx$圖片訊息圖片網址']['$t']), //"圖片網址",
                                "previewImageUrl" => trim($item['gsx$圖片訊息縮圖網址']['$t']) //"縮圖網址"
                            );
                        }

                        return $reply_message;
                    }
                    break;

                case 'keyword':

                    // 以關鍵字比對文字內容，符合的話馬上回傳
                    foreach ($keywords as $keyword) {
                        preg_match("/(" . $keyword . ")/iu", $user_text, $matches);
                        if(isset($matches[1])){

                            if(trim($item['gsx$文字訊息1']['$t']) !== ''){
                                $reply_message[] = array(
                                    'type' => 'text',
                                    'text' => trim($item['gsx$文字訊息1']['$t']),
                                );
                            }

                            if(trim($item['gsx$文字訊息2']['$t']) !== ''){
                                $reply_message[] = array(
                                    'type' => 'text',
                                    'text' => trim($item['gsx$文字訊息2']['$t']),
                                );
                            }

                            if(trim($item['gsx$圖片訊息圖片網址']['$t']) !== ''
                                && trim($item['gsx$圖片訊息縮圖網址']['$t']) !== ''
                            ){
                                $reply_message[] = array(
                                    "type" => "image",
                                    "originalContentUrl" => trim($item['gsx$圖片訊息圖片網址']['$t']), //"圖片網址",
                                    "previewImageUrl" => trim($item['gsx$圖片訊息縮圖網址']['$t']) //"縮圖網址"
                                );
                            }


                            $previous_message = $this->getPreviousMessage($reply_message);
                            $reply_message[] = $this->getVoteButtonsMessage($previous_message);

                            return $reply_message;
                        }
                    }
                    break;
            }

        }

        return null;
    }


    /**
     * @param array $all_articles
     * @param array $message
     * @return array|null
     */
    public function searchArticlesCarouselFromGoogle($all_articles = array(), $message = array()){
        //$debug = true;
        $debug = $this->show_debug_message;

        $user_text = $message['text'];
        $user_text = preg_replace("/^(mode\:carousel\s)/i", '', $user_text);
        $user_text = trim($user_text);

        if($debug){
            error_log('#### ' . __FUNCTION__);
            error_log('$message: ' . print_r($message, true));
            error_log('user_text: ' . print_r($user_text, true));
            error_log('count of entries: ' . count($all_articles['feed']['entry']));
        }

        // 資料起始從feed.entry
        $number_of_matched_articles = 1;
        $matched_articles_result = array();
        $reply_message_columns = array();
        foreach ($all_articles['feed']['entry'] as $item) {
            // 以關鍵字比對文字內容，符合的話累積最多 10 則後回傳

            preg_match("/(" . $user_text . ")/iu", $item['gsx$關鍵字逗號間隔']['$t'], $matches);
            if(isset($matches[1])) {

                if($debug){
                    //error_log('$item: ' . print_r($item, true));
                    error_log('$item: ' . print_r($item, true));
                    error_log('$matches: ' . print_r($matches, true));
                }

                // avoid empty message
                $not_empty_message = false;
                $message_body = array();
                $message_body[] = trim($item['gsx$文字訊息1']['$t']);
                $message_body[] = trim($item['gsx$文字訊息2']['$t']);
                $message_body[] = trim($item['gsx$圖片訊息圖片網址']['$t']);
                $message_string = implode('', $message_body);
                if (trim($message_string) !== '') {
                    $not_empty_message = true;
                }

                if ($not_empty_message) {
                    $tmp_text = '';
                    if (trim($item['gsx$文字訊息1']['$t']) !== '') {
                        $matched_articles_result[$number_of_matched_articles][] = array(
                            'type' => 'text',
                            'text' => trim($item['gsx$文字訊息1']['$t']),
                        );
                        $tmp_text .= trim($item['gsx$文字訊息1']['$t']);
                    }

                    if (trim($item['gsx$文字訊息2']['$t']) !== '') {
                        $matched_articles_result[$number_of_matched_articles][] = array(
                            'type' => 'text',
                            'text' => trim($item['gsx$文字訊息2']['$t']),
                        );
                        $tmp_text .= trim($item['gsx$文字訊息2']['$t']);
                    }

                    $tmp_text = preg_replace('/\R+/iu', ' ', $tmp_text);
                    $tmp_text = $number_of_matched_articles . ') ' . trim($tmp_text);

                    // Max: 60 characters (message with an image or title)
                    $tmp_text = $this->getTruncatedString($tmp_text, 60);

                    if (trim($item['gsx$圖片訊息圖片網址']['$t']) !== ''
                        && preg_match('/(imgur\.com)/i', trim($item['gsx$圖片訊息圖片網址']['$t']))
                        && trim($item['gsx$圖片訊息縮圖網址']['$t']) !== ''
                        && preg_match('/(imgur\.com)/i', trim($item['gsx$圖片訊息縮圖網址']['$t']))
                    ) {
                        $matched_articles_result[$number_of_matched_articles][] = array(
                            'type' => 'image',
                            'originalContentUrl' => trim($item['gsx$圖片訊息圖片網址']['$t']), //"圖片網址",
                            'previewImageUrl' => trim($item['gsx$圖片訊息縮圖網址']['$t']) //"縮圖網址"
                        );

                        $reply_message_column = array(
                            'thumbnailImageUrl' => trim($item['gsx$圖片訊息圖片網址']['$t']), //"圖片網址",
                            'imageBackgroundColor' => '#a8e8fb',
                            'text' => $tmp_text,
                            'defaultAction' => array(
                                'type' => 'message',
                                'text' => '顯示關鍵字「' . $message['text'] . '」第' . $number_of_matched_articles . '筆的搜尋結果 ...'
                            ),
                            'actions' => array(
                                0 => array(
                                    'type' => 'message',
                                    'label' => '選定該訊息',
                                    'text' => '顯示關鍵字「' . $message['text'] . '」第' . $number_of_matched_articles . '筆的搜尋結果 ...'
                                )

                            )
                        );
                    } else {
                        $reply_message_column = array(
                            'thumbnailImageUrl' => $this->getImagePlaceholder(), //"圖片網址",
                            'imageBackgroundColor' => '#a8e8fb',
                            'text' => $tmp_text,
                            'defaultAction' => array(
                                'type' => 'message',
                                'text' => '顯示關鍵字「' . $message['text'] . '」第' . $number_of_matched_articles . '筆的搜尋結果 ...'
                            ),
                            'actions' => array(
                                0 => array(
                                    'type' => 'message',
                                    'label' => '選定該訊息',
                                    'text' => '顯示關鍵字「' . $message['text'] . '」第' . $number_of_matched_articles . '筆的搜尋結果 ...'
                                )
                            )
                        );
                    }

                    $reply_message_columns[] = $reply_message_column;
                    if($debug){
                        error_log('$reply_message_column: ' . print_r($reply_message_column, true));
                    }


                    if ($number_of_matched_articles === 10) {

                        $cache_path = __DIR__ . '/../cache/' . json_encode($user_text) . '.json';
                        if(file_exists($cache_path)){
                            rename($cache_path, $cache_path . '.bak');
                        }

                        file_put_contents($cache_path, json_encode($matched_articles_result));

                        if(!file_exists($cache_path)){
                            error_log('$cache_path of given keyword ' . $user_text. ' is not saved: ' . print_r($cache_path, true));
                        }



                        $reply_message_columns = json_encode($reply_message_columns);
                        $json = <<<EOT

                            {
                                "type": "template",
                                "altText": "搜尋結果不支援在桌面電腦顯示",
                                "template": {
                                    "type": "carousel",
                                    "columns": {$reply_message_columns}
                                }
                            }

EOT;


                        $json = trim($json);
                        $check_json = $this->checkJson($json);
                        if (is_null($check_json)) {
                            return null;
                        }

                        $reply_message = array();
                        $reply_message[] = array(
                            'type' => 'text',
                            'text' => '你想要找「' . $message['text'] . '」請稍後 ...',
                        );
                        $reply_message[] = json_decode($json, true);
                        return $reply_message;
                    }

                    $number_of_matched_articles++;

                    //$previous_message = $this->getPreviousMessage($reply_message);
                    //$reply_message[] = $this->getVoteButtonsMessage($previous_message);
                }

            }


        }

        if(count($reply_message_columns) > 0){

            $cache_path = __DIR__ . '/../cache/' . json_encode($user_text) . '.json';
            if(file_exists($cache_path)){
                rename($cache_path, $cache_path . '.bak');
            }

            file_put_contents($cache_path, json_encode($matched_articles_result));

            if(!file_exists($cache_path)){
                error_log('$cache_path of given keyword ' . $user_text. ' is not saved: ' . print_r($cache_path, true));
            }

            $reply_message_columns = json_encode($reply_message_columns);

            if($debug){
                error_log('$reply_message_columns: ' . print_r($reply_message_columns, true));
            }

            $json = <<<EOT

                {
                    "type": "template",
                    "altText": "不支援顯示樣板",
                    "template": {
                        "type": "carousel",
                        "columns": {$reply_message_columns}
                    }
                }

EOT;


            $json = trim($json);
            $check_json = $this->checkJson($json);
            if(is_null($check_json)){
                return null;
            }

            $reply_message = array();
            $reply_message[] = array(
                'type' => 'text',
                'text' => '你想要找「'.$message['text'].'」請稍後 ...',
            );
            $reply_message[] = json_decode($json, true);
            return $reply_message;
        }

        return null;
    }

    /**
     * @param array $all_articles
     * @param array $message
     * @return array|null
     */
    public function searchArticlesImageCarouselFromGoogle($all_articles = array(), $message = array()){
        //$debug = true;
        $debug = $this->show_debug_message;

        $user_text = $message['text'];
        $user_text = preg_replace("/^(mode\:image_carousel\s)/i", '', $user_text);
        $user_text = trim($user_text);

        if($debug){
            error_log('#### ' . __FUNCTION__);
            error_log('$message: ' . print_r($message, true));
            error_log('user_text: ' . print_r($user_text, true));
            error_log('count of entries: ' . count($all_articles['feed']['entry']));
        }

        // 資料起始從feed.entry
        $number_of_matched_articles = 1;
        $matched_articles_result = array();
        $reply_message_columns = array();
        foreach ($all_articles['feed']['entry'] as $item) {
            // 以關鍵字比對文字內容，符合的話累積最多 10 則後回傳

            preg_match("/(" . $user_text . ")/iu", $item['gsx$關鍵字逗號間隔']['$t'], $matches);
            if(isset($matches[1])) {

                if($debug){
                    //error_log('$item: ' . print_r($item, true));
                    error_log('$item: ' . print_r($item, true));
                    error_log('$matches: ' . print_r($matches, true));
                }

                // avoid empty message
                $not_empty_message = false;
                $message_body = array();
                $message_body[] = trim($item['gsx$文字訊息1']['$t']);
                $message_body[] = trim($item['gsx$文字訊息2']['$t']);
                $message_body[] = trim($item['gsx$圖片訊息圖片網址']['$t']);
                $message_string = implode('', $message_body);
                if (trim($message_string) !== '') {
                    $not_empty_message = true;
                }

                if ($not_empty_message) {
                    if (trim($item['gsx$文字訊息1']['$t']) !== '') {
                        $matched_articles_result[$number_of_matched_articles][] = array(
                            'type' => 'text',
                            'text' => trim($item['gsx$文字訊息1']['$t']),
                        );
                    }

                    if (trim($item['gsx$文字訊息2']['$t']) !== '') {
                        $matched_articles_result[$number_of_matched_articles][] = array(
                            'type' => 'text',
                            'text' => trim($item['gsx$文字訊息2']['$t']),
                        );
                    }

                    if (trim($item['gsx$圖片訊息圖片網址']['$t']) !== ''
                        && preg_match('/(imgur\.com)/i', trim($item['gsx$圖片訊息圖片網址']['$t']))
                        && trim($item['gsx$圖片訊息縮圖網址']['$t']) !== ''
                        && preg_match('/(imgur\.com)/i', trim($item['gsx$圖片訊息縮圖網址']['$t']))
                    ) {
                        $matched_articles_result[$number_of_matched_articles][] = array(
                            'type' => 'image',
                            'originalContentUrl' => trim($item['gsx$圖片訊息圖片網址']['$t']), //"圖片網址",
                            'previewImageUrl' => trim($item['gsx$圖片訊息縮圖網址']['$t']) //"縮圖網址"
                        );

                        $reply_message_column = array(
                            'imageUrl' => trim($item['gsx$圖片訊息圖片網址']['$t']), //"圖片網址",
                            'action' => array(
                                'type' => 'message',
                                'label' => '選定該訊息',
                                'text' => '顯示關鍵字「' . $message['text'] . '」第' . $number_of_matched_articles . '筆的搜尋結果 ...'
                            )
                        );
                    } else {
                        $reply_message_column = array(
                            'imageUrl' => $this->getImagePlaceholder(), //"圖片網址",
                            'action' => array(
                                'type' => 'message',
                                'label' => '選定該訊息',
                                'text' => '顯示關鍵字「' . $message['text'] . '」第' . $number_of_matched_articles . '筆的搜尋結果 ...'
                            )
                        );
                    }

                    $reply_message_columns[] = $reply_message_column;


                    if ($number_of_matched_articles === 10) {

                        $cache_path = __DIR__ . '/../cache/' . json_encode($user_text) . '.json';
                        if(file_exists($cache_path)){
                            rename($cache_path, $cache_path . '.bak');
                        }

                        file_put_contents($cache_path, json_encode($matched_articles_result));

                        if(!file_exists($cache_path)){
                            error_log('$cache_path of given keyword ' . $user_text. ' is not saved: ' . print_r($cache_path, true));
                        }



                        $reply_message_columns = json_encode($reply_message_columns);
                        $json = <<<EOT

                                        {
                                            "type": "template",
                                            "altText": "不支援顯示樣板",
                                            "template": {
                                                "type": "image_carousel",
                                                "columns": {$reply_message_columns}
                                            }
                                        }

EOT;


                        $json = trim($json);
                        $check_json = $this->checkJson($json);
                        if (is_null($check_json)) {
                            return null;
                        }

                        $reply_message = array();
                        $reply_message[] = array(
                            'type' => 'text',
                            'text' => '你想要找「' . $message['text'] . '」請稍後 ...',
                        );
                        $reply_message[] = json_decode($json, true);
                        return $reply_message;
                    }

                    $number_of_matched_articles++;

                    //$previous_message = $this->getPreviousMessage($reply_message);
                    //$reply_message[] = $this->getVoteButtonsMessage($previous_message);
                }

            }


        }

        if(count($reply_message_columns) > 0){

            $cache_path = __DIR__ . '/../cache/' . json_encode($user_text) . '.json';
            if(file_exists($cache_path)){
                rename($cache_path, $cache_path . '.bak');
            }

            file_put_contents($cache_path, json_encode($matched_articles_result));

            if(!file_exists($cache_path)){
                error_log('$cache_path of given keyword ' . $user_text. ' is not saved: ' . print_r($cache_path, true));
            }

            $reply_message_columns = json_encode($reply_message_columns);
            $json = <<<EOT

                {
                    "type": "template",
                    "altText": "不支援顯示樣板",
                    "template": {
                        "type": "image_carousel",
                        "columns": {$reply_message_columns}
                    }
                }

EOT;


            $json = trim($json);
            $check_json = $this->checkJson($json);
            if(is_null($check_json)){
                return null;
            }

            $reply_message = array();
            $reply_message[] = array(
                'type' => 'text',
                'text' => '你想要找「'.$message['text'].'」請稍後 ...',
            );
            $reply_message[] = json_decode($json, true);
            return $reply_message;
        }

        return null;
    }

    /**
     * @param string $file_path
     * @return string|null
     * @license Attribution-ShareAlike 3.0 Unported (CC BY-SA 3.0) https://creativecommons.org/licenses/by-sa/3.0/
     * @see https://stackoverflow.com/questions/3312607/php-binary-image-data-checking-the-image-type
     */
    function getFileFormatFromFileContent($file_path = ""){
        $data = file_get_contents($file_path);

        $binary_check = "\xFF\xD8\xFF";
        if ( substr( $data, 0, strlen($binary_check) ) === $binary_check ) {
            return "jpg";
        }

        $binary_check = "GIF";
        if ( substr( $data, 0, strlen($binary_check) ) === $binary_check ) {
            return "gif";
        }

        $binary_check = "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a";
        if ( substr( $data, 0, strlen($binary_check) ) === $binary_check ) {
            return "png";
        }

        return null;
    }


    /**
     * @return mixed
     */
    public function getImagePlaceholder(){
        $debug = false;

        $image_url_list = array(
            'https://i.imgur.com/vkLDIAUh.png', // 一起傳送你的關心
            'https://i.imgur.com/5V8FdiM.jpg' // line channel profile image
        );
        $max_num = count($image_url_list) - 1;
        $num_list = range(0, $max_num);
        $random = array_rand($num_list);

        if($debug){
            echo 'random: ' . print_r($random, true) . PHP_EOL;
            echo '$image_url_list: ' . print_r($image_url_list, true) . PHP_EOL;
        }


        return $image_url_list[$random];
    }

    /**
     * @param string $url
     * @param null $image_size
     * @return string|null
     */
    function getImgurUrl($url = "", $image_size = null){

        if(strlen(trim($url)) == 0){
            return null;
        }

        $url_info = parse_url($url);
        if($url_info["host"] != "i.imgur.com"){
            return null;
        }

        $path = $url_info["path"]; // e.g. /crUeZea.jpg
        $image_id = preg_replace("/^(\/)/", "", $path); // e.g. crUeZea.jpg
        $image_id = preg_replace("/(\.jpg)$/", "", $image_id); // e.g. /crUeZea


        // Image thumbnails https://api.imgur.com/models/image
        switch ($image_size) {
            case "huge": // 1024x1024 & Keeps Image Proportions: true
                return "https://i.imgur.com/{$image_id}h.jpg";
                break;
            case "large": //640x640 & Keeps Image Proportions: true
                return "https://i.imgur.com/{$image_id}l.jpg";
                break;
            case "medium": // 320x320 & Keeps Image Proportions: true
                return "https://i.imgur.com/{$image_id}m.jpg";
                break;
            case "small": // 160x160 & Keeps Image Proportions: true
                return "https://i.imgur.com/{$image_id}t.jpg";
                break;
            case "big": // 160x160 & Keeps Image Proportions: false
                return "https://i.imgur.com/{$image_id}b.jpg";
                break;
            default:
                return $url;
        }
    }

    /**
     * @param string $user_text
     * @return string
     */
    public function getKeywordInfo($user_text = ''){

        $row_count = 1;
        preg_match('/第(\d+)筆的搜尋結果/iu', $user_text, $matches);
        if(isset($matches[1])){
            $row_count = $matches[1];
        }

        preg_match('/「([^「|」]+)」/u', $user_text, $matches);
        var_dump($matches);
        if(isset($matches[1])){
            $user_text = $matches[1];
            $user_text = trim($user_text);
        }

        $user_text = preg_replace("/^(mode\:carousel)/iu", '', $user_text);
        $user_text = trim($user_text);

        return array($user_text, $row_count);
    }

    /**
     * @param array $message
     * @return array|null
     */
    public function getMockMessage($message = array()){
        //global $debug;
        //$debug = false;
        $debug = true;

        $user_text = $message['text'];

        switch ($user_text) {
            // random text message
            case 'test:img':
            case 'test:image':
            case (preg_match('/^test\:(\d)image/', $user_text, $matches) ? true : false):
                $count_of_msg = 1;
                if(isset($matches[1])){
                    $count_of_msg = $matches[1];
                }
                return $this->getMockImageMessage($count_of_msg);
                break;

            case 'test:image_postback':

                $count_of_msg = 1;
                $reply_message = $this->getMockImageMessage($count_of_msg);

                $previous_message = $this->getPreviousMessage($reply_message);
                $reply_message[] = $this->getVoteButtonsMessage($previous_message);

                return $reply_message;
                break;

            case 'test:video':
                return array(
                     array(
                        'type' => 'video',
                        'originalContentUrl' => $this->base_url . 'sample_files/lorem.mp4',
                        'previewImageUrl' => 'https://i.imgur.com/WEeAmenh.jpg'
                     )
                );
                break;

            case "test:imagemap":
                return array(
                    array(
                        'type' => 'text',
                        'text' => '影像地圖測試，點選圖右方回傳文字、點選左方打開網址'
                    ),
                    array(
                        'type' => 'imagemap',
                        'baseUrl' => $this->getRandomImageUrl(),
                        "altText" => "在不支援顯示影像地圖的地方顯示的文字",
                        "baseSize" => array(
                                    "height" => 1040,
                                    "width" => 1040
                                    ),
                        "actions" => array(
                            0 => array(
                                "type" => "uri",
                                "linkUri" => "https://www.google.com",
                                "label" => "https://www.google.com",
                                "area" => array(
                                    "x" => 0,
                                    "y" => 0,
                                    "width" => 520,
                                    "height" => 1040
                                )
                            ),
                            1 => array(
                                "type" => "message",
                                "text" => "傳送文字",
                                "label" => $this->base_url,
                                "area" => array(
                                    "x" => 520,
                                    "y" => 0,
                                    "width" => 520,
                                    "height" => 1040
                                )
                            )
                        )
                    )
                );
                break;

            case 'test:confirm':
                $json = <<<EOT

                {
                    "type": "template",
                    "altText": "不支援顯示樣板",
                    "template": {
                        "type": "confirm",
                        "text": "標題文字",
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
                            }
                        ]
                    }
                }

EOT;

                $json = trim($json);
                $reply_message = array();
                $reply_message[] = json_decode($json, true);
                return $reply_message;
                break;

            case 'test:button':
                $json = <<<EOT

                {
                    "type": "template",
                    "altText": "不支援顯示樣板",
                    "template": {
                        "type": "buttons",
                        "text": "標題文字",
                        "actions": [
                            {
                                "type": "message",
                                "label": "第1個按鈕",
                                "text": "1"
                            }
                        ]
                    }
                }

EOT;

                $json = trim($json);
                $check_json = $this->checkJson($json);
                if(is_null($check_json)){
                    return null;
                }

                $reply_message = array();
                $reply_message[] = json_decode($json, true);
                return $reply_message;
                break;


            case "test:buttons":
                $json = <<<EOT

                {
                    "type": "template",
                    "altText": "不支援顯示樣板",
                    "template": {
                        "type": "buttons",
                        "text": "標題文字",
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
                            },
                            {
                                "type": "message",
                                "label": "第四個按鈕",
                                "text": "4"
                            }
                        ]
                    }
                }

EOT;

                $json = trim($json);
                $check_json = $this->checkJson($json);
                if(is_null($check_json)){
                    return null;
                }

                $reply_message = array();
                $reply_message[] = json_decode($json, true);
                return $reply_message;
                break;

            case "test:buttons_img":
                // cc0 licensed photo
                // https://stocksnap.io/photo/ELADKTWHHU
                $json = <<<EOT

                {
                    "type": "template",
                    "altText": "不支援顯示樣板",
                    "template": {
                        "type": "buttons",
                        "imageAspectRatio": "rectangle",
                        "imageSize": "contain",
                        "thumbnailImageUrl": "https://i.imgur.com/hUjEE3Nh.jpg",
                        "imageBackgroundColor": "#a8e8fb",
                        "title": "更粗的標題",
                        "text": "標題文字",
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
                            },
                            {
                                "type": "message",
                                "label": "第四個按鈕",
                                "text": "4"
                            }
                        ]
                    }
                }

EOT;

                $json = trim($json);
                $check_json = $this->checkJson($json);
                if(is_null($check_json)){
                    return null;
                }

                $reply_message = array();
                $reply_message[] = json_decode($json, true);
                return $reply_message;
                break;

            case 'test:buttons_postback':
                $json = <<<EOT

                {
                    "type": "template",
                    "altText": "以上這則訊息長輩是否有反應？",
                    "template": {
                        "type": "buttons",
                        "thumbnailImageUrl": "https://i.imgur.com/hUjEE3Nh.jpg",
                        "imageAspectRatio": "square",
                        "text": "以上這則訊息長輩是否有反應？",
                        "actions": [
                            {
                                "type":"postback",
                                "label":"是",
                                "data":"vote=1&itemid=1"
                            },
                            {
                                "type":"postback",
                                "label":"否",
                                "data":"vote=0&itemid=2"
                            }
                        ]
                    }
                }

EOT;

                $json = trim($json);
                $check_json = $this->checkJson($json);
                if(is_null($check_json)){
                    return null;
                }

                $reply_message = array();
                $reply_message[] = json_decode($json, true);
                return $reply_message;
                break;

            // Carousel template https://developers.line.biz/en/reference/messaging-api/#carousel
            case 'test:carousel':
                // cc0 licensed photo
                // https://stocksnap.io/photo/ELADKTWHHU
                // https://stocksnap.io/photo/YUMTDKMJ27
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
                $check_json = $this->checkJson($json);
                if(is_null($check_json)){
                    return null;
                }

                $reply_message = array();
                $reply_message[] = json_decode($json, true);
                return $reply_message;
                break;

            case (preg_match('/(test\:carousel)(\d+)/', $user_text, $matches) ? true : false):

                $count_of_msg = 10;
                if(isset($matches[1])){
                    $count_of_msg = $matches[2];
                }

                $text_content_list = $this->getRandomText($count_of_msg);

                $reply_message_columns = array();
                $show_image_or_not = true;
                for ($index = 0; $index < $count_of_msg; $index++) {

                    $number_of_matched_articles = $index + 1;

                    $tmp = array();

                    if($show_image_or_not){
                        $tmp['thumbnailImageUrl'] = $this->getRandomImageUrl('huge');
                        $tmp['imageBackgroundColor'] = '#a8e8fb';
                        //$tmp['title'] = '標題' . $number_of_matched_articles;
                    }

                    $tmp_text = $text_content_list[$index];
                    $tmp_text = trim($tmp_text);
                    switch ($show_image_or_not) {
                        case false:
                            //Max: 120 characters (no image or title)
                            $tmp_text = $this->getTruncatedString($tmp_text, 120);
                            break;
                        default:
                            // Max: 60 characters (message with an image or title)
                            $tmp_text = $this->getTruncatedString($tmp_text, 60);
                    }
                    $tmp['text'] = $tmp_text;

                    // 點到圖或文字
                    $tmp['defaultAction'] = array(
                        'type' => 'message',
                        //'label' => '選定該訊息',
                        'text' => '顯示關鍵字「' . $message['text'] . '」第' . $number_of_matched_articles . '筆的搜尋結果 ...'

                    );


                    $tmp['actions'] = array(
                        0 => array(
                            'type' => 'message',
                            'label' => '選定該訊息',
                            'text' => '顯示關鍵字「' . $message['text'] . '」第' . $number_of_matched_articles . '筆的搜尋結果 ...'
                        )
                    );

                    $reply_message_columns[] = $tmp;

                }


                $reply_message_columns = json_encode($reply_message_columns);

                $json = <<<EOT

                {
                    "type": "template",
                    "altText": "不支援顯示樣板",
                    "template": {
                        "type": "carousel",
                        "columns": $reply_message_columns
                    }
                }

EOT;

                // "imageAspectRatio": "rectangle",
                // "imageSize": "contain",

                if($debug){
                    $log_message = '$json: ' . print_r($json, true) . PHP_EOL;
                    error_log(__FUNCTION__ . ' :' . $log_message);
                }

                $json = trim($json);
                $check_json = $this->checkJson($json);
                if(is_null($check_json)){
                    return null;
                }

                $reply_message = array();
                $reply_message[] = json_decode($json, true);

                if($debug){
                    $log_message = '$json: ' . print_r(json_encode($reply_message), true) . PHP_EOL;
                    error_log(__FUNCTION__ . ' :' . $log_message);
                }

                return $reply_message;
                break;

            case 'test:custom':

                $json = <<<EOT

                {
                    "type": "template",
                    "altText": "不支援顯示樣板",
                    "template": {
                        "type": "carousel",
                        "columns": [
                            {
                                "text": "第一組標題",
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
                                "text": "第二組標題",
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
                $check_json = $this->checkJson($json);
                if(is_null($check_json)){
                    return null;
                }

                $reply_message = array();
                $reply_message[] = json_decode($json, true);
                return $reply_message;
                break;

            case "test:image_carousel":
                // cc0 licensed photo
                // https://stocksnap.io/photo/ELADKTWHHU
                // https://stocksnap.io/photo/YUMTDKMJ27
                $json = <<<EOT

                {
                    "type": "template",
                    "altText": "不支援顯示樣板",
                    "template": {
                        "type": "image_carousel",
                        "columns": [
                            {
                                "imageUrl": "https://i.imgur.com/hUjEE3Nh.jpg",
                                "action": {
                                    "type": "message",
                                    "label": "第1張圖",
                                    "text": "1"
                                }
                            },
                            {
                                "imageUrl": "https://i.imgur.com/eoZd524h.jpg",
                                "action": {
                                    "type": "message",
                                    "label": "第2張圖",
                                    "text": "2"
                                }
                            },
                            {
                                "imageUrl": "https://i.imgur.com/hUjEE3Nh.jpg",
                                "action": {
                                    "type": "postback",
                                    "label": "第3張圖",
                                    "data": "action=buy&itemid=3"
                                }
                            },
                            {
                                "imageUrl": "https://i.imgur.com/eoZd524h.jpg",
                                "action": {
                                    "type": "message",
                                    "label": "第4張圖",
                                    "text": "4"
                                }
                            },{
                                "imageUrl": "https://i.imgur.com/hUjEE3Nh.jpg",
                                "action": {
                                    "type": "message",
                                    "label": "第5張圖",
                                    "text": "5"
                                }
                            },
                            {
                                "imageUrl": "https://i.imgur.com/eoZd524h.jpg",
                                "action": {
                                    "type": "message",
                                    "label": "第6張圖",
                                    "text": "6"
                                }
                            },
                            {
                                "imageUrl": "https://i.imgur.com/hUjEE3Nh.jpg",
                                "action": {
                                    "type": "message",
                                    "label": "第7張圖",
                                    "text": "7"
                                }
                            },
                            {
                                "imageUrl": "https://i.imgur.com/eoZd524h.jpg",
                                "action": {
                                    "type": "message",
                                    "label": "第8張圖",
                                    "text": "8"
                                }
                            },
                            {
                                "imageUrl": "https://i.imgur.com/hUjEE3Nh.jpg",
                                "action": {
                                    "type": "message",
                                    "label": "第9張圖",
                                    "text": "9"
                                }
                            },
                            {
                                "imageUrl": "https://i.imgur.com/eoZd524h.jpg",
                                "action": {
                                    "type": "message",
                                    "label": "第10張圖",
                                    "text": "10"
                                }
                            }
                        ]
                    }
                }

EOT;


                $json = trim($json);
                $check_json = $this->checkJson($json);
                if(is_null($check_json)){
                    return null;
                }

                $reply_message = array();
                $reply_message[] = json_decode($json, true);
                return $reply_message;
                break;

            case (preg_match('/(test\:echo)(.*)/', $user_text, $matches) ? true : false):
                $output_text = $user_text;
                if(isset($matches[2])){
                    $output_text = trim($matches[2]);
                }
                if($debug){
                    error_log(__FUNCTION__ . ' $matches: ' . print_r($matches, true));
                }

                $reply_message = array();
                $reply_message[] = array(
                    'type' => 'text',
                    'text' => $output_text,
                );
                return $reply_message;
                break;

            // random text message
            case (preg_match('/^test\:(\d)msg$/', $user_text, $matches) ? true : false):
                $count_of_msg = 5;
                if(isset($matches[1])){
                    $count_of_msg = $matches[1];
                }

                $content = $this->getRandomText($count_of_msg);
                $reply_message = array();
                foreach ($content AS $row){
                    $reply_message[] = array(
                        'type' => 'text',
                        'text' => $row,
                    );
                }
                return $reply_message;
                break;

            // random text & image message
            case (preg_match('/^test\:(\d)mixed$/', $user_text, $matches) ? true : false):
                $count_of_total_msg = 5;
                if(isset($matches[1])){
                    $count_of_total_msg = $matches[1];
                }

                $count_of_text_msg = rand(1, $count_of_total_msg - 1);
                $count_of_image_msg = $count_of_total_msg - $count_of_text_msg;

                $text_content = $this->getRandomText($count_of_text_msg);
                $reply_message = array();
                foreach ($text_content AS $row){
                    $reply_message[] = array(
                        'type' => 'text',
                        'text' => $row,
                    );
                }

                for ($x = 1; $x <= $count_of_image_msg; $x++) {

                    $image_id = $this->getRandomImgurId();
                    $reply_message[] = array(
                        'type' => 'image',
                        'originalContentUrl' => "https://i.imgur.com/{$image_id}.jpg", //"圖片網址",
                        'previewImageUrl' => "https://i.imgur.com/{$image_id}b.jpg" //"縮圖網址"
                    );
                }

                return $reply_message;
                break;

            // random text, image message & post back
            case (preg_match('/^test\:(\d)postback$/', $user_text, $matches) ? true : false):
            case (preg_match('/^test\:(\d)mixed_postback$/', $user_text, $matches) ? true : false):
                $count_of_total_msg = 4;
                if(isset($matches[1])){
                    $count_of_total_msg = $matches[1];
                }

                $count_of_text_msg = rand(1, $count_of_total_msg - 1);
                $count_of_image_msg = $count_of_total_msg - $count_of_text_msg;

                $text_content = $this->getRandomText($count_of_text_msg);
                $reply_message = array();
                foreach ($text_content AS $row){
                    $reply_message[] = array(
                        'type' => 'text',
                        'text' => $row,
                    );
                }

                for ($x = 1; $x <= $count_of_image_msg; $x++) {

                    $image_id = $this->getRandomImgurId();
                    $reply_message[] = array(
                        'type' => 'image',
                        'originalContentUrl' => "https://i.imgur.com/{$image_id}.jpg", //"圖片網址",
                        'previewImageUrl' => "https://i.imgur.com/{$image_id}b.jpg" //"縮圖網址"
                    );
                }

                $previous_message = $this->getPreviousMessage($reply_message);
                $reply_message[] = $this->getVoteButtonsMessage($previous_message);

                return $reply_message;
                break;
        }

        return null;
    }

    /**
     * @param $message
     * @param string $approach
     */
    public function sendReplyMessageApi($message, $approach = 'curl'){
        //global $debug;
        $debug = false;
        $is_error_returned = false;

        switch ($approach) {
            case 'file_get_contents':
                $header = array(
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->channel_token,
                );

                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => implode("\r\n", $header),
                        'content' => json_encode($message),
                    ],
                ]);
                if($debug){
                    $log_message = '$header: ' . print_r($header, true) . PHP_EOL;
                    $log_message .= '$message: ' . print_r($message, true) . PHP_EOL;
                    $log_message .= '$context: ' . print_r($context, true) . PHP_EOL;
                    error_log(__FUNCTION__ . ' :' . $log_message);
                }

                try {
                    $response = file_get_contents('https://api.line.me/v2/bot/message/reply', false, $context);
                } catch (Exception $e) {
                    error_log('Caught exception: ' . $e->getMessage());

                }

                if($debug){
                    error_log('$response: ' . $response);
                    error_log('$http_response_header: ' . print_r($http_response_header, true));
                }
                if (strpos($http_response_header[0], '200') === false) {
                    $is_error_returned = true;

                }
                break;
            case 'curl':
            default:
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.line.me/v2/bot/message/reply');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->channel_token
                ]);
                $response = curl_exec($ch);
                $response_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if($debug){
                    error_log('$response: ' . $response);
                    error_log('$response_http_code: ' . $response_http_code);
                }
                if (strpos($response_http_code, '200') === false) {
                    $is_error_returned = true;
                }
        }


        if($is_error_returned){
            http_response_code(500);
            error_log('Request failed: ' . $response);
            error_log('json_encode($message): ' . json_encode($message));

            /*
            $reply_message = array();
            $reply_message[] = array(
                'type' => 'text',
                'text' => 'Request failed: ' . $response,
            );

            $replyToken = $this->__get('replyToken');
            if(is_null($replyToken)){
                exit('$replyToken is null');
            }

            // 回覆訊息
            $this->replyMessage(array(
                'replyToken' => $replyToken,
                'messages' => $reply_message,
            ));
            */
        }

    }

    /**
     * @param string $message_id
     * @return false|string
     * @see Messaging API reference: Gets image, video, and audio data sent by users. https://developers.line.biz/en/reference/messaging-api/#get-content
     */
    function getMessageContentApi($message_id = ""){
        //global $debug;
        $debug = false;

        if(strlen(trim($message_id)) == 0){
            $output = array(
                "result" => "error",
                "message" => '$message_id should not be empty!'
            );
            return json_encode($output);
        }

        $header = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->channel_token,
        );

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $header)
            ],
        ]);
        if($debug){
            $log_message = '$header: ' . print_r($header, true) . PHP_EOL;
            $log_message .= '$message_id: ' . print_r($message_id, true) . PHP_EOL;
            $log_message .= '$context: ' . print_r($context, true) . PHP_EOL;
            error_log(__FUNCTION__ . ' :' . $log_message);
        }

        try {
            $response = file_get_contents("https://api.line.me/v2/bot/message/{$message_id}/content", false, $context);
            if($debug){
                error_log('$response: ' . $response);
                error_log('$http_response_header: ' . print_r($http_response_header, true));
            }

            return $response;
        } catch (Exception $e) {
            error_log('Caught exception: ' . $e->getMessage());

        }

        if (strpos($http_response_header[0], '200') === false) {
            http_response_code(500);
            error_log('Request failed: ' . $response);
        }
    }

    /**
     * @param string $message_id
     * @return string|null
     */
    function saveMessageContent($message_id = ""){

        $debug = false;
        //$debug = true;

        $message_id = trim($message_id);

        $binary_result = $this->getMessageContentApi($message_id);
        //var_dump($result);

        // checking the type of content
        // MIME types - HTTP | MDN
        // https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_format = $finfo->buffer($binary_result);

        if($debug){
            $log_message = '$message_id: ' . $message_id . PHP_EOL;
            $log_message .= '$mime_format from buffer: ' . $mime_format . PHP_EOL;
            //$log_message .= '$mime_format from file content: ' . $mime_format_from_file_content . PHP_EOL;
            error_log(__FUNCTION__ . ' :' . PHP_EOL . $log_message);
        }

        $file_extension = null;
        switch ($mime_format) {
            case "image/jpeg":
                $file_extension = ".jpg";
                break;
            case "image/png":
                $file_extension = ".png";
                break;
            case "image/gif":
                $file_extension = ".gif";
                break;
            default:
                // do nothing
        }

        switch ($mime_format) {
            case "image/jpeg":
            case "image/png":
            case "image/gif":
                $tmp_file_name = "files/{$message_id}{$file_extension}";
                file_put_contents($tmp_file_name, $binary_result);
                $url = $this->base_url . "get_message_content.php?name={$message_id}&mime_format={$mime_format}";
                if(!file_exists($tmp_file_name)){
                    $output = "圖片儲存失敗，請反應給管理者「訊息代號: {$message_id}{$file_extension}」！";
                    return $output;

                }
                return "圖片已經儲存，線上預覽: $url";
                break;

            default:
                echo "檔案類型不是圖片: $mime_format";
                $tmp_file_name = "files/{$message_id}";
                file_put_contents($tmp_file_name, $binary_result);
                $url = $this->base_url . "get_message_content.php?name={$message_id}&mime_format={$mime_format}";
                if(!file_exists($tmp_file_name)){
                    $output = "檔案儲存失敗，請反應給管理者「訊息代號: {$message_id}」！";
                    return $output;
                }
                return "檔案已經儲存，線上預覽: $url";
                break;
        }
    }

    /**
     * @param string $postback
     * @param string $user_id
     * @return bool|string
     */
    public function savePostbackEvent($postback = '', $user_id = ''){
        parse_str($postback, $output);

        $url = $this->google_app_script_url;

        $post_fields = array(
            'method' => 'write',
            'message' => $output['pre'],
            'user_id' => $user_id,
            'action_name' => $output['act'],
            'action_value' => $output['val'],
            'notes' => ''
        );
        $post_fields  = http_build_query($post_fields);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    /**
     * @param string $file_name
     * @param string $mime_format
     * @return string|null
     */
    function displayMessageContent($file_name = "", $mime_format = ""){

        // filter illegal file name
        preg_match("/(\.)/i", $file_name, $matches);
        if(isset($matches[1])){
            return "找不到這個檔案 (1)，請跟管理者反應這個連結問題";
        }

        $file_extension = null;
        switch ($mime_format) {
            case "image/jpeg": // e.g. 9330978510392
                $file_extension = ".jpg";
                break;
            case "image/png":
                $file_extension = ".png";
                break;
            case "image/gif":
                $file_extension = ".gif";
                break;
            default:
                // do nothing
        }

        if(!is_null($file_extension)){
            $file_path = dirname(__FILE__) . "/../files/{$file_name}{$file_extension}";
            $url = $this->base_url . "files/{$file_name}{$file_extension}";
        }else{
            $file_path = dirname(__FILE__) . "/../files/{$file_name}";
            $url = $this->base_url . "../files/{$file_name}";
        }

        if(!file_exists($file_path)){
            return "找不到這個檔案 (2)，請跟管理者反應這個連結問題";
        }


        if(is_null($file_extension)){
            $html = <<<EOT

檔案類型不是圖片: $mime_format <br />
<a href ="{$url}">下載檔案 (需自行修改附檔名)</a>

EOT;
            return $html;
        }else{
            $html = <<<EOT

<img src ="{$url}" />

EOT;
            return $html;
        }

    }
}