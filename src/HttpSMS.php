<?php

namespace src;

use src\Main\Sms;
use src\Interfaces\SmsHttp;
use src\Traits\generateSmsPdu;

class HttpSMS
{
    use generateSmsPdu;

    protected string $serialEthernetConverterIP;
    protected string $serialEthernetConverterPort;
    protected string $pin;

    public function __construct(array $sms)
    {
        $this->setParams($sms);
        $this->serialEthernetConverterIP =  '192.168.2.91';
        $this->serialEthernetConverterPort =  '5050';
        $this->pin = '1234';
    }

    public function sendHttpPduSms() {


        $pdu = $this->generateSmsPdu();


        try {
       $sms = Sms::factory(new SmsHttp($this->serialEthernetConverterIP, $this->serialEthernetConverterPort),true,);

              if ($sms->sendSmsPdu($pdu)) {
                echo "SMS Sent\n";
                return true;
            } else {
                echo "Sent Error\n";
                return false;
            }

            // Now read inbox part for next thing
             /*  foreach ($sms->readInbox() as $in) {
                   echo"tlfn: {$in['tlfn']} date: {$in['date']} {$in['hour']}\n{$in['msg']}\n";

                   // now delete sms
                   if ($sms->deleteSms($in['id'])) {
                       echo "SMS Deleted\n";
                   }
               }*/


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

