<?php

namespace Kazio\SmsSender\Main;

use Kazio\SmsSender\Interfaces\SmsInterface;


class SmsDummy implements SmsInterface
{
    private $_validOutputs = array();

    public function deviceOpen()
    {
    }

    public function deviceClose()
    {
    }

    public function sendMessage($msg)
    {
    }

    public function readPort()
    {
        return array("OK", array());
    }

    public function setValidOutputs($validOutputs) :void
    {
        $this->_validOutputs = $validOutputs;
    }
}