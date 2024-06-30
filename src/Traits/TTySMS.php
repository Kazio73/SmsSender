<?php

namespace Kazio\SmsSender\Traits;

use Kazio\SmsSender\Main\Sms;
use Kazio\SmsSender\Interfaces\SmsSerialIO;

class TTySMS
{
    use generateSmsPdu;

    protected string $serialEthernetConverterIP;
    protected string $serialEthernetConverterPort;
    protected string $pin;

    public function __construct(array $sms)
    {
        $this->setParams($sms);
        $this->serial = '/dev/ttyUSB3';
        $this->pin = '1234';
        $this->options = array(
            "baud" => 115200,
            "bits" => 8,
            "stop" => 1,
            "parity" => 0,
        );
    }

    public function sendTTyPduSms() {

        $pdu = $this->generateSmsPdu();

        $pin ='1234';

        $serial = new SmsSerialIO($this->serial);
        $serial->set_options($this->options);

        try {
        if (Sms::factory($serial, true)
            ->sendSmsPdu($pdu)) {
         return "SMS sent\n";
        } else {
           return "SMS not Sent\n";
        }

        } catch (\Exception $e) {
            switch ($e->getCode()) {
                case Sms::EXCEPTION_NO_PIN:
                    echo "PIN Not set\n";
                    break;
                case Sms::EXCEPTION_PIN_ERROR:
                    echo "PIN Incorrect\n";
                    break;
                case Sms::EXCEPTION_SERVICE_NOT_IMPLEMENTED:
                    echo "Service Not implemented\n";
                    break;
                default:
                    echo $e->getMessage();
            }
        }

    }

}

