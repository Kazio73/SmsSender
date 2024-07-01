<?php

namespace Kazio\SmsSender\Traits;

use Kazio\SmsSender\Main\SmsPduMaker;
use Kazio\SmsSender\Main\SmsMessage as SMS;

trait generateSmsPdu
{
    private string $number;
    private string $smsc;
    private string $text;

    /**
     * This method is preparing all params
     * @return void
     */
    protected function setParams(array $params)
    {
        $this->number = $params['number'] ?: '';
        $this->smsc = $params['smsc'] ?: '+48602951111';
        $this->text = $params['text'] ?: '';

        $this->cutText();
        $this->checkPhone();
    }

    /**
     * This method is cutting this text to 70 chars.
     * @return void
     */
    protected function cutText()
    {
        $this->text = substr($this->text, 0, 70);
    }

    /**
     * This method add +48 before phone number and sanitize number from spaces chars (ascii 32)
     * @return void
     */
    protected function checkPhone(): void
    {
        if (str_contains($this->number, " "))
        {
            $this->number = str_replace(" ", "", $this->number);
        }
        if (!str_contains($this->number, '+48')) {
            $this->number = '+48' . $this->number;
        }
    }

    /**
     * Method prepare PDU for sending as sms. Text is cut there to 70 chars.
     * Default SMSC it's TMobile Poland
     * @param array $params ['number', 'smsc','text]
     * @return array|string  success ['length','content'}, error message
     */
    public function generateSmsPdu()
    {

        try {
            $message = new SMS;
            $message->setReceiver($this->number);
            $message->setSMSC($this->smsc);
            $message->setText($this->text);
            $message->setSender('PTTK');

            $something = new SmsPduMaker();

            return $something->generatePDU($message);
        } catch (\Exception $exception) {

            return $exception->getMessage();
        }
    }

}