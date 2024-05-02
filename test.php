<?php

namespace App;

require 'vendor/autoload.php';

use App\Main\Sms;
use App\Main\Sms_Dummy;

$pin = 1234;

$serial = new Sms_Dummy;

if (Sms::factory($serial)->insertPin($pin)
    ->sendSMS(555987654, "test Hi")) {
    echo "SMS sent\n";
} else {
    echo "SMS not Sent\n";
}