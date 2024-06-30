<?php

namespace Kazio\SmsSender\Main;

use Exception;
use Kazio\SmsSender\Interfaces\SmsHttp;
use Kazio\SmsSender\Interfaces\SmsSerialIO;
use Kazio\SmsSender\Interfaces\SmsInterface;

/**
 * GSM Modem AT Send/receive
 * Adapter is loader via dependency injection
 *
 * THIS PROGRAM COMES WITH ABSOLUTELY NO WARANTIES !
 * USE IT AT YOUR OWN RISKS !
 *
 * @author Gonzalo Ayuso <gonzalo123@gmail.com>
 * @copyright under GPL 2 licence
 */
class Sms
{
    private $_serial;
    private $_debug;
    protected $_pinOK = true;
    protected $openAT = false;

    const EXCEPTION_PIN_ERROR = 1;
    const EXCEPTION_NO_PIN = 2;
    const EXCEPTION_SERVICE_NOT_IMPLEMENTED = 3;

    /**
     * Factory. Creates new instance. Dependency injections with the type os Modem
     * valid serial resources:
     *   SmsSerial: GSM modem connected via seria interface
     *   SmsHttp: GSM modem connected via seria/ethernet converter
     *   Sms_Dummy: Mock for testing
     *
     * @param SmsInterface; $serial
     * @param Boolean $debug
     * @return Sms
     */
    public static function factory($serial, $debug = false)
    {
        if (!($serial instanceof SmsSerialIO ||
            $serial instanceof SmsHttp ||
            $serial instanceof SmsDummy
        )) {
            throw new Exception("NOT IMPLEMENTED", self::EXCEPTION_SERVICE_NOT_IMPLEMENTED);
        }

        $serial->setValidOutputs([
            'OK',
            'ERROR',
            '+CPIN: SIM PIN',
            '+CPIN: READY',
            '> AT+CMGF=0',
            '> AT+CSCS=?',
            '> ',
            '>',
            "+CMGS:"
        ]
        );

        return new self($serial, $debug);
    }

    protected function __construct($serial, $debug = false)
    {
        $this->_serial = $serial;
        $this->_debug = $debug;
    }

    private function readPort()
    {
      return $this->_serial->readPort();
    }

    private function sendMessage($msg)
    {
        $this->_serial->sendMessage($msg);
    }

    private function deviceOpen()
    {
        $this->_serial->deviceOpen();
        $this->setOpenAT(true);
    }

    private function deviceClose()
    {
        $this->_serial->deviceClose();
        $this->setOpenAT(false);
    }

    /**
     * Delete selected id from SMS SIM
     *
     * @param unknown_type $id
     * @return unknown
     */
    public function deleteSms($id)
    {
        $this->deviceOpen();
        $this->sendMessage("AT+CMGD={$id}\r");
        $out = $this->readPort();
        $this->deviceClose();
        if ($out == 'OK') {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Sends a SMS to a selected recipient with PDU method
     * @param Array $params
     *
     * @return Boolean
     */
    public function sendSmsPdu(array $params)
    {
       // echo("Sleep starting.\n"); // TEST
       // sleep(4);
       // echo("Sleep ending\n"); // TEST
        $this->deviceOpen();
        if ($this->openAT === true) {
            $this->sendMessage("AT+CMGF=0\r");
            $out = $this->readPort();
            if ($out === '> AT+CMGF=0' || $out === '>') {
                $end = chr('26');
                $this->sendMessage($end);
                $this->sendMessage("AT+CMGF=0\r");
                $out = $this->readPort();
            } else if ($out === 'OK') {
                $this->sendMessage("AT+CMGS={$params['length']}\r");
                $out = $this->readPort();
            } else {
                return false;
            }
            if ($out === '>') {
                $this->sendMessage("{$params['content']}");
                $this->sendMessage($end = chr('26'));
                $out = $this->readPort();
            }
        }
        $this->deviceClose();
        if (stripos($out, "OK") !== false) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Sends a SMS to a selected tlfn
     * @param Integer $tlfn
     * @param String $text
     * @param String $type
     * @return Boolean
     */
    public function sendSMS($tlfn, $text, $type = 'text')
    {
        if ($this->_pinOK) {
            $this->deviceOpen();
            if ($this->openAT === true) {
                $this->sendMessage("AT+CMGF=0\r");
                $out = $this->readPort();
                if ($out === '> AT+CMGF=0' || $out === '>' || $out === '> AT+CSCS=?') {
                    $end = chr('26');
                    $this->sendMessage($end);
                    $this->sendMessage("AT+CMGF=0\r");
                    $out = $this->readPort();
                } else if ($out == 'OK') {
                    $this->sendMessage("AT+CMGF=1\r");
                    $out = $this->readPort();
                } else {
                    return false;
                }
                if ($out == 'OK') {
                    $this->sendMessage("AT+CMGS=\"{$tlfn}\"\r");
                    $out = $this->readPort();
                } else {
                    return false;
                }
                if ($out == '>') {
                    $end = chr('26');
                    $this->sendMessage("{$text}");
                    $this->sendMessage($end);
                    $out = $this->readPort();
                } else {
                    return false;
                }
            } else {
                $this->sendMessage("AT+CMGF=0\rAT+CMGF=1\rAT+CMGS=\"{$tlfn}\"\r{$text}" . chr(26));
            }

            $this->deviceClose();
            if ($out == 'OK') {
                return true;
            } else {
                return false;
            }
        } else {
            throw new Exception("Please insert the PIN", self::EXCEPTION_NO_PIN);
        }
    }

    public function isPinOk()
    {
        return $this->_pinOK;
    }

    /**
     * Inserts the pin number.
     * first checks if PIN is set. If it's set nothing happens
     * @param Integer $pin
     * @return Sms
     */
    public
    function insertPin($pin)
    {
        $this->deviceOpen();

        $this->sendMessage("AT+CPIN?\r");
        $out = $this->readPort();
        $this->deviceClose();

        if ($out == "+CPIN: SIM PIN") {
            $this->deviceOpen();
            if (is_null($pin) || $pin == '') {
                throw new Exception("PIN ERROR", self::EXCEPTION_PIN_ERROR);
            }
            $this->sendMessage("AT+CPIN={$pin}\r");
            $out = $this->readPort();
            $this->deviceClose();
            // I don't know why but I need to wait a few seconds until
            // start sending SMS. Only after the first PIN
            sleep(2);
        }

        switch ($out) {
            case "+CPIN: READY":
            case "OK":
                $this->_pinOK = true;
                break;
        }

        if ($this->_pinOK === true) {
            return $this;
        } else {
            throw new Exception("PIN ERROR ({$out})", self::EXCEPTION_PIN_ERROR);
        }
    }

    const ALL = "ALL";
    const UNREAD = "REC UNREAD";

    /**
     * Read Inbox
     *
     * @param string $mode ALL | UNREAD
     * @return array
     */
    public
    function readInbox($mode = self::ALL)
    {
        $inbox = $return = [];

        if ($this->_pinOK) {
            $this->deviceOpen();
            $this->sendMessage("AT+CMGF=1\r");
            $out = $this->readPort();
            if ($out == 'OK') {
                $this->sendMessage("AT+CMGL=\"{$mode}\"\r");
                $inbox = $this->readPort(true);
            }
            $this->deviceClose();
            if (count($inbox) > 2) {
                array_pop($inbox);
                array_pop($inbox);
                $arr = explode("+CMGL:", implode("\n", $inbox));

                for ($i = 1; $i < count($arr); $i++) {
                    $arrItem = explode("\n", $arr[$i], 2);

                    // Header
                    $headArr = explode(",", $arrItem[0]);

                    $fromTlfn = str_replace('"', null, $headArr[2]);
                    $id = $headArr[0];
                    $date = $headArr[4];
                    $hour = $headArr[5];

                    // txt
                    $txt = $arrItem[1];

                    $return[] = array('id' => $id, 'tlfn' => $fromTlfn, 'msg' => $txt, 'date' => $date, 'hour' => $hour);
                }
            }
            return $return;
        } else {
            throw new Exception("Please insert the PIN", self::EXCEPTION_NO_PIN);
        }
    }

    public
    function setOpenAT($openAT)
    {
        $this->openAT = $openAT;
    }
}
