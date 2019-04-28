<?php
// config
$init_config = include __DIR__ . '/../config.php';

require_once __DIR__ . '/../load.php';
$client = new CareElderBot($init_config);


if(isset($_GET["name"])
    && isset($_GET["mime_format"])
){
    echo $client->displayMessageContent($_GET["name"], $_GET["mime_format"]);
    return null;
}

if(isset($_GET["id"])){
    $binary_result = $client->getMessageContentApi($_GET["id"]);
    //var_dump($result);

    // checking the type of content
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $file_format = $finfo->buffer($binary_result);


    switch ($file_format) {
        case "image/jpeg":
            $tmp_file_name = "files/" . $_GET["id"] . ".jpg";
            file_put_contents($tmp_file_name, $binary_result);
            if(!file_exists($tmp_file_name)){
                echo '[Err] file not saved';
                return null;
            }
            $html = <<<EOT

<img src ="{$tmp_file_name}" />

EOT;
            echo $html;
            break;
        case "image/png":
            $tmp_file_name = "files/" . $_GET["id"] . ".png";
            file_put_contents($tmp_file_name, $binary_result);
            if(!file_exists($tmp_file_name)){
                echo '[Err] file not saved';
                return null;
            }
            $html = <<<EOT

<img src ="{$tmp_file_name}" />

EOT;
            echo $html;
        case "image/gif":
            $tmp_file_name = "files/" . $_GET["id"] . ".gif";
            file_put_contents($tmp_file_name, $binary_result);
            if(!file_exists($tmp_file_name)){
                echo '[Err] file not saved';
                return null;
            }
            $html = <<<EOT

<img src ="{$tmp_file_name}" />

EOT;
            echo $html;
        default:
            echo "檔案類型: $file_format";
            $tmp_file_name = "files/" . $_GET["id"] . "";
            file_put_contents($tmp_file_name, $binary_result);
            if(!file_exists($tmp_file_name)){
                echo '[Err] file not saved';
                return null;
            }
            $html = <<<EOT

<a href ="{$tmp_file_name}">連結按右鍵 另存檔案 (需自行修改附檔名)</a>

EOT;
            echo $html;
            break;
    }
    return null;
}


echo "[Err] missing required parameter";
