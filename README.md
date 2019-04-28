CareElderBot
 
「你今天關心長輩了嗎」專案程式碼

## install

安裝多個套件
```
composer install
```

或更新套件
```
composer update
```

逐一安裝套件

```
$ composer require linecorp/line-bot-sdk
$ composer require slim/slim
$ composer require monolog/monolog
$ composer require theiconic/php-ga-measurement-protocol
```
* line/line-bot-sdk-php: SDK of the LINE Messaging API for PHP https://github.com/line/line-bot-sdk-php
* Slim Framework - Slim Framework http://www.slimframework.com/
* theiconic/php-ga-measurement-protocol: Send data to Google Analytics from the server using PHP. Implements GA measurement protocol. https://github.com/theiconic/php-ga-measurement-protocol


## 環境設定

### PHP 環境設定

* turn on `allow_url_fopen=1`
* 啟用 `curl` 套件
* PHP 版本 7.2 (與 phpunit 有關，[line/line\-bot\-sdk\-php: SDK of the LINE Messaging API for PHP](https://github.com/line/line-bot-sdk-php) 需求條件可以較低 PHP 版本)
* 檔案 `config.example.php` 另存成 `config.php`

### 訊息資料庫 Google sheet

* Google sheet 欄位順序不重要，但是欄位名稱重要，跟程式碼 `/src/CareElderBot.php` function `getReplyContent` 有關係。

參考資料
* 林壽山的 LINE 機器人範例 [使用Azure+PHP+Google試算表建置LINE@回應機器人](https://superlevin.tw/%E4%BD%BF%E7%94%A8azurephpgoogle%E8%A9%A6%E7%AE%97%E8%A1%A8%E5%BB%BA%E7%BD%AEline%E5%9B%9E%E6%87%89%E6%A9%9F%E5%99%A8%E4%BA%BA/)

### Google App Script + 訊息投票回傳 Google sheet 環境設定

* Google Apps Script 環境設定參考 [用 Google Apps Script 操作試算表 \(1\)製作資料庫 \+ 寫入資料＠WFU BLOG](https://www.wfublog.com/2017/01/google-apps-script-spreadsheet-write-data.html)
* Google sheet 欄位順序重要，跟程式碼 `/google_app_script/程式碼.gs` 有關係。程式碼不會看欄位名稱，所以欄位名稱不重要。欄位順序：

```
時間	訊息內容	user_id	action_name	action_value	註解
```

參考資料
* [Quotas for Google Services  \|  Apps Script  \|  Google Developers](https://developers.google.com/apps-script/guides/services/quotas): `URL Fetch calls	20,000 / day`
* [Sheets Tips \- Google Sheets Tips and Tricks \| G Suite Tips](https://gsuitetips.com/tips/sheets/google-spreadsheet-limitations/): `Up to 5 million cells for spreadsheets`

### LINE 相關設定

* <https://admin-official.line.me/> 帳號設定 -> Messaging API設定
  * 選擇 Provider List
* <https://developers.line.biz/console/> 
  * 複製 `Channel secret`, `Channel access token (long-lived)` 到 `config.php`
  * 啟用 `Use webhooks=Enables `
  * `Webhook URL Requires SSL` 設定網址
  * `Auto-reply messages=Disabled` 不然會出現 `很抱歉，這個帳號沒有辦法對用戶個別回覆 ...`


切換到 Messaging API 顯示的訊息
```
請注意，開始使用API後，將無法復原至使用前的狀態，且無法使用以下功能。

・1對1聊天
・LINE@應用程式
若要透過API收發訊息，請前往LINE Developers設定Channel。
```

Auto-reply messages 預設罐頭訊息
```
感謝您傳送訊息給我！(blush)

很抱歉，這個帳號沒有辦法對用戶個別回覆。(hm)

敬請期待下次的訊息內容！(shiny)
```

## troubleshooting

### line bot 已讀不回

可能原因

* 程式壞掉，可到伺服器內看 `error_log` 查原因
* token 相關議題 `error_log` 顯示如下訊息

```php
PHP Warning:  file_get_contents(https://api.line.me/v2/bot/message/reply): failed to open stream: HTTP request failed! HTTP/1.0 400 Bad Request
 in /path/to/vendor/linecorp/line-bot-sdk/line-bot-sdk-tiny/LINEBotTiny.php on line 111
Request failed: 
```

### PHP Warning:  file_get_contents(): https:// wrapper is disabled in the server configuration by allow_url_fopen=0 in /home/path/to/hello.php on line xx

Solution: Cpanel -> MultiPHP INI Editor -> turn on `allow_url_fopen=1`

### error_log 顯示 Method not allowed、LINE 開發者網頁 Webhook URL 無法驗證通過

錯誤狀態:

瀏覽器看錯誤網址會直接看到 `HTTP ERROR 405` 。到伺服器內看 `error_log` 也是 `Method not allowed` 但是瀏覽器不會直接寫出來。

成功狀態:

* 使用 `postman` 協定要使用 `POST` 去呼叫 `http://bot.website.com/callback`
* 觀察回傳 http status code 是 200

瀏覽器看網頁 `http://bot.website.com/callback` 是

```html
Method not allowed
Method not allowed. Must be one of: POST
```

### LINE 開發者網頁 Webhook URL 無法驗證顯示 The webhook returned an invalid HTTP status code. (The expected status code is 200.)

錯誤狀態:

伺服器 `error_log` 是 `HTTP/1.0 401 Unauthorized`

```bash
PHP Warning:  file_get_contents(https://api.line.me/v2/bot/message/reply): failed to open stream: HTTP request failed! HTTP/1.0 401 Unauthorized
 in /path/to/line-bot/script.php on line xxx
Request failed: 
```

### API 回傳 Error 400: message May not be empty

錯誤狀態:
```json
{"message":"The request body has 1 error(s)","details":[{"message":"May not be empty","property":"messages"}]}
```

解決方式: 回傳的 `messages` 欄位值不能是空值 

```php
$client->replyMessage(array(
                        'replyToken' => $event['replyToken'],
                        'messages' => $reply_content,
                    ));
```

檢查 `$reply_content` 或使用 [JSON Formatter & Validator](https://jsonformatter.curiousconcept.com/) 檢查輸入的 `json` 是否有效 e.g. `Strings should be wrapped in double quotes.`

### Invalid signature value

檢查程式碼中 channel access token, channel secret 的設定

## License

程式碼如果沒有特別註明，則使用 [MIT License](https://opensource.org/licenses/MIT).

影片檔案授權
* `sample_files/lorem.mp4` [Lorem Ipsum Video \- YouTube](https://www.youtube.com/watch?v=7X8II6J-6mU) License: Creative Commons 0