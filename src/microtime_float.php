<?php
/**
 * Created by PhpStorm.
 * User: planetoid
 * Date: 2019-04-01
 * Time: 22:42
 */

// https://www.php.net/manual/en/function.microtime.php
/**
 * Simple function to replicate PHP 5 behaviour
 */
function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}
