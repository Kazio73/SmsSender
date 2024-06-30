<?php

namespace Kazio\SmsSender\Interfaces;


// serial.php - Matt Layher, 3/13/13
// PHP class utilizing Direct IO to interact with a RS232 serial port
//
// changelog
//
// 3/14/13 MDL:
//	- bugfixes and optimizations
// 3/13/13 MDL:
//	- initial commit

class SmsSerialIO implements SmsInterface
{
    // CONSTANTS - - - - - - - - - - - - - - - - - - - -

    // Default read/write length
    const DEFAULT_LENGTH = 1024;

    // Default wait time after write (in microseconds)
    const DEFAULT_WAIT = 200000;

    // Direct IO attribute defaults
    const DEFAULT_BAUD = 9600;
    const DEFAULT_BITS = 8;
    const DEFAULT_STOP = 1;
    const DEFAULT_PARITY = 0;


    // FLAGS

    const O_RDWR = 2;
    const O_NOCTTY = 256;
    const O_NONBLOCK = 4;

    // STATIC VARIABLES - - - - - - - - - - - - - - - -

    // Verbosity
    private static $verbose = 1;

    // Valid Direct IO (DIO) options
    private static $OPTIONS = array(
        "baud" => array(50, 75, 110, 134, 150, 200, 300, 600, 1200, 1800, 2400, 4800, 9600, 19200, 38400, 57600, 115200),
        "bits" => array(5, 6, 7, 8),
        "stop" => array(1, 2),
        "parity" => array(0, 1, 2),
    );

    // INSTANCE VARIABLES - - - - - - - - - - - - - - -

    // Instance variables
    private $device;
    private $options;

    // Serial connection
    private $serial;

    // Previous command
    private $command;

    // Valid output
    private $valid;

    // PUBLIC PROPERTIES - - - - - - - - - - - - - - - -

    // device:
    //  - get: device
    //	- set: device (validated by file_exists(), created if not present)
    public function get_device()
    {
        return $this->device;
    }

    public function set_device($device)
    {
        if (file_exists($device)) {
            $this->device = $device;
            return true;
        }

        return false;
    }

    // options:
    //  - get: options array
    //	- set: options array (validated by is_array() and keys checked)
    public function get_options()
    {
        return $this->options;
    }

    public function set_options($options)
    {
        if (is_array($options)) {
            // Check for valid DIO attribute options
            foreach ($options as $key => $value) {
                // Validate option name
                if (!array_key_exists($key, self::$OPTIONS)) {
                    trigger_error("Invalid PHP Direct IO option specified '" . $key . "'", E_USER_WARNING);
                    return false;
                }

                // Validate option value
                if (!in_array($value, self::$OPTIONS[$key])) {
                    trigger_error("Invalid PHP Direct IO value specified for " . $key . " '" . $value . "'", E_USER_WARNING);
                    return false;
                }
            }

            // If all checks pass, set options
            $this->options = $options;
            dio_tcsetattr($this->serial, $options);
            return true;
        }

        return false;
    }

    // CONSTRUCTOR/DESTRUCTOR - - - - - - - - - - - - -

    // Construct serial object using specified device with specified flags (02 = O_RDWR)
//    public function __construct($device, $flags = 02)
    public function __construct($device, $flags = 02)
    {
        // Attempt to set device...
        if (!$this->set_device($device)) {
            throw new \Exception("Unable to set device for serial connection");
        }

        // Check if Direct IO extension installed
        if (!function_exists("dio_open")) {
            throw new \Exception("PHP Direct IO is not installed, cannot open serial connection!");
        }

        // Create direct IO file handle with specified flags
        // Combine the flags using bitwise OR
        $flags = self::O_RDWR | self::O_NOCTTY | self::O_NONBLOCK;
        $this->serial = dio_open($device, $flags);

        // Set synchronous IO
        dio_fcntl($this->serial, F_SETFL, O_SYNC);

        // Set options default
        $options = array(
            "baud" => self::DEFAULT_BAUD,
            "bits" => self::DEFAULT_BITS,
            "stop" => self::DEFAULT_STOP,
            "parity" => self::DEFAULT_PARITY,
        );
        $this->set_options($options);

        $this->valid = array(
            'OK',
            'ERROR',
            '+CPIN: SIM PIN',
            '+CPIN: READY',
            '> AT+CMGF=0',
            '> AT+CSCS=?',
            '>',
            "+CMGS:"
        );
    }

    // Close connection on destruct
    public function __destruct()
    {
        if (isset($this->serial)) {
            $this->deviceClose();
        }
    }

    // PUBLIC METHODS - - - - - - - - - - - - - - - - -

    // Close connection to serial port
    public function deviceClose()
    {
        if (isset($this->serial)) {
            dio_close($this->serial);
            unset($this->serial);
            return true;
        }
        trigger_error("Unable to close the device", E_USER_ERROR);
        return false;
    }

    // Read data from serial port
    public function readPort($length = self::DEFAULT_LENGTH)
    {
        $timeout = 10;
        $buffer = [];
        $endTime = time() + $timeout;

        while (time() < $endTime) {
            $bytes = dio_read($this->serial, $length);
            $bytes = $this->cleanRespond($bytes);

           count($bytes) === 2 ? $separator = " " : $separator = "";

            if ($bytes !== false) {
                $buffer = array_merge($buffer, $bytes);
            }
            // Check if the expected response is in the buffer
            foreach ($this->valid as $valid) {
                foreach ($bytes as $byte) {
                    if (stripos($byte, $valid) !== false) {
                        return implode(separator: $separator, array: $buffer);
                    };
                }
            }
            // Check if an error response is in the buffer
            if (in_array("ERROR", $buffer)) {
                return "ERROR";
            }

            usleep(1000000); // Sleep for 100ms to prevent busy waiting
        }
        return "TIMEOUT";
    }

// Write data to serial port
    public function sendMessage($data, $length = self::DEFAULT_LENGTH, $wait = self::DEFAULT_WAIT)
    {
        $bytes = dio_write($this->serial, $data);
        $this->setCommand($data);
        usleep($wait);
        return $bytes;
    }

    public function deviceOpen()
    {
        return true;
    }

    public function setValidOutputs($validOutputs)
    {
        $this->valid = $validOutputs;
    }

    private function cleanRespond($bytes)
    {
        $arr = preg_split('/\r\n|\r|\n/', $bytes);
        // Step 3: Remove empty values from the array
        $arr = array_filter($arr, function ($value) {
            return $value !== '' && $value !== $this->command;
        });
        $this->command = "";
        return $arr;
    }

    private function setCommand($command)
    {
        $this->command = str_replace(array("\n", "\r"), '', $command);
    }
}

