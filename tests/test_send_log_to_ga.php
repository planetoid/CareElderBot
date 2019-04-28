<?php
/**
 * Created by PhpStorm.
 * User: planetoid
 * Date: 2019-04-01
 * Time: 21:07
 * reference: https://github.com/theiconic/php-ga-measurement-protocol
 */

use CareElderBot;
use TheIconic\Tracking\GoogleAnalytics\Analytics;

$test_prefix = 'Test';

require_once __DIR__ . '/../load.php';

// config
$path = __DIR__ . '/../config.php';
if(file_exists($path)){
    $init_config = require($path);
}

if(!isset($init_config)){
    echo '[Error] $init_config is not defined!';
    return null;
}

//----
$client = new CareElderBot\CareElderBot($init_config);
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
    ->setClientId('12345678')
;


$analytics
    ->setDocumentPath('/');

// When you finish bulding the payload send a hit (such as an pageview or event)
$analytics->sendPageview();



$dummy_user_behavior = array();
$dummy_user_behavior[] = array(
    'eventCategory' => $test_prefix.'UserInput',
    'eventAction' => 'MessageType',
    'eventLabel' => 'text',
);

$dummy_user_behavior[] = array(
    'eventCategory' => $test_prefix.'UserInput',
    'eventAction' => 'ArticleSearch',
    'eventLabel' => 'ArticleFound',
);

$rand = rand(1, 10);
$dummy_user_behavior[] = array(
    'eventCategory' => $test_prefix.'Article',
    'eventAction' => 'Search',
    'eventLabel' => '關鍵字(' . $rand .')',
    'eventValue' => $rand,
);


$dummy_user_behavior[] = array(
    'eventCategory' => $test_prefix.'UserInput',
    'eventAction' => 'ArticleSearch',
    'eventLabel' => 'ArticleNotFound',
);

$dummy_user_behavior[] = array(
    'eventCategory' => $test_prefix.'Article',
    'eventAction' => 'Search',
    'eventLabel' => '關鍵字 (N/A)',
    'eventValue' => 0,
);

$dummy_user_behavior[] = array(
    'eventCategory' => $test_prefix.'Article',
    'eventAction' => 'Selected',
    'eventLabel' => '文章摘要 (略)',
);

$rand_vote = rand(0, 1);
$dummy_user_behavior[] = array(
    'eventCategory' => $test_prefix.'UserInput',
    'eventAction' => 'Feedback-Vote',
    'eventLabel' => '文章摘要 (略)',
    'eventValue' => $rand_vote,
);

foreach ($dummy_user_behavior AS $user_behavior){
    if(array_key_exists('eventCategory', $user_behavior)){
        $analytics->setEventCategory($user_behavior['eventCategory']);
    }

    if(array_key_exists('eventAction', $user_behavior)){
        $analytics->setEventAction($user_behavior['eventAction']);
    }

    if(array_key_exists('eventLabel', $user_behavior)){
        $analytics->setEventLabel($user_behavior['eventLabel']);
    }

    if(array_key_exists('eventValue', $user_behavior)){
        $analytics->setEventValue($user_behavior['eventValue']);
    }

    $analytics->sendEvent();

    // randomly sleep between 500000-3000000 microseconds, which means 0.5 ~ 3 seconds.
    usleep(rand(500000,3000000));
}


$time_end = microtime_float();
$time = $time_end - $time_start;
$time = $time * 1000000;
$time = (int) $time;
echo '$time (ms): ' . $time . PHP_EOL;

$rand = rand(1, 10);
$analytics->setEventCategory($test_prefix.'Performance')
    ->setEventAction('ResponseTimeMicroSeconds')
    ->setEventLabel('關鍵字(' . $rand .')')
    ->setEventValue($time)
    ->sendEvent();

echo 'tests were completed' . PHP_EOL;

// When you finish bulding the payload send a hit (such as an pageview or event)
//$analytics->sendPageview();

