<?php

namespace src\Interfaces;

interface SmsInterface
{
    public function deviceOpen();

    public function deviceClose();

    public function sendMessage($msg);

    public function readPort();

    public function setValidOutputs($validOutputs);
}