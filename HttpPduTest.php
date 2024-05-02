<?php
namespace Kazio\SmsSender;
require 'vendor/autoload.php';

//$numbers =['501790152','501513526','500000011','500000001', '604411466','698232095','508553310','513769157','608683626','501790152','501513526','500000011','500000001', '604411466','698232095','508553310','513769157','608683626','501790152','501513526','500000011','500000001','604411466','698232095','508553310','513769157','608683626','501790152' ];
//$numbers =['501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152','501790152'];
$numbers =['501790152'];


$time_start = microtime(true);

$content = [
    'smsc' => '+48602951111'
    ];


     $i = 1;
foreach ( $numbers as $number) {

    $content['number'] = $number;
    $content['text'] = "Ruszyło :) {$i} Monsz napisał.";
    $sms = new HttpSMS($content);
    $result = $sms->sendHttpPduSms();
    echo ('Result number: ' . $number. ' -> ' . $result. "\n");
    unset ($sms);
    $i++;
    }
$time_end = microtime(true);
 echo ('Execution script time: '. round(($time_end - $time_start)/60, 2) . ' sec');