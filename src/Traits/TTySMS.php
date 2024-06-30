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
        $this->serial = '/dev/ttyUSB2';
        $this->pin = '1234';
        $this->options = array(
            "baud" => 115200,
            "bits" => 8,
            "stop" => 1,
            "parity" => 0,
        );
    }

    public function sendTTyPduSms()
    {
        $pdu = $this->generateSmsPdu();

        $serial = new SmsSerialIO($this->serial);
        $serial->set_options($this->options);

        try {
            $sms = (Sms::factory($serial, true));
            if ($sms->sendSmsPdu($pdu)) {
                return "SMS Sent";
            } else {
                return "Sent Error";
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

