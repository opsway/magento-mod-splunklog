<?php
/**
* Log formatter for Splunk
*
* @category Tools
* @package SplunkLog
* @author Alexandr Vronskiy <alvro@opsway.com>
* @license New BSD License
*/
class Opsway_Log_Formatter_Splunk extends Zend_Log_Formatter_Simple
{
    const DEFAULT_FORMAT = '%metric% %value% %timestamp%';

    /**
     * Class constructor
     *
     * @param  null|string  $format  Format specifier for log messages
     * @throws Zend_Log_Exception
     */
    public function __construct($format = null)
    {
        if ($format === null) {
            $format = self::DEFAULT_FORMAT . PHP_EOL;
        }

        if (! is_string($format)) {
            throw new Opsway_Log_Exception('Format must be a string');
        }

        $this->_format = $format;
    }

    public function format($event)
    {
        $event['metric'] = $event['message'];
        unset($event['message']);
        return parent::format($event);
    }
}