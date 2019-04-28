<?php

$path_list = array();
$path_list['config.php'] = __DIR__ . '/config.php';
$path_list['vendor/autoload.php'] = __DIR__ . '/vendor/autoload.php';
$path_list['src/CareElderBot.php'] = __DIR__ . '/src/CareElderBot.php';
$path_list['src/microtime_float.php'] = __DIR__ . '/src/microtime_float.php';

foreach ($path_list AS $file_name => $file_path){
    if(!file_exists($file_path)){
        echo '[Error] missing file: ' . $file_name . PHP_EOL;
        return null;
    }else{
        require_once $file_path;
    }
}

?>