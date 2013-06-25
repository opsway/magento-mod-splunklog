<?php
/**
* Socket writer to Splunk
*
* @category Tools
* @package SplunkLog
* @author Alexandr Vronskiy <alvro@opsway.com>
* @license New BSD License
*/
class Opsway_Log_Writter_Splunk extends Zend_Log_Writer_Abstract
{
    /**
      * Holds the PHP stream to log to.
      * @var null|stream
      */
     protected $_socket = null;

     /**
      * Class Constructor
      *
      * @param  host     host to open as a socket
      * @param  port
      * @param formatString
      */
     public function __construct($host, $port, $formatString = null)
     {
         if (!isset($host) || !isset($port)) {
             throw new Opsway_Log_Exception("Do not set the required parameters: host, port");
         }

         if (! $this->_socket = @fsockopen("udp://$host", $port, $errno, $errstr)) {
             $msg = "Socket for udp://\"$host\":$port address cannot be opened with $errno error: \"$errstr\"";
             throw new Opsway_Log_Exception($msg);
         }

         $this->_formatter = new Opsway_Log_Formatter_Splunk($formatString);
     }

     /**
      * Create a new instance of Zend_Log_Writer_Mock
      *
      * @param  array|Zend_Config $config
      * @return Zend_Log_Writer_Mock
      * @throws Zend_Log_Exception
      */
     static public function factory($config)
     {
         $config = self::_parseConfig($config);
         $config = array_merge(array(
             'host' => null,
             'port'   => null,
             'formatString' => null
         ), $config);

         return new self(
             $config['host'],
             $config['port'],
             $config['formatString']
         );
     }

     /**
      * Close the stream resource.
      *
      * @return void
      */
     public function shutdown()
     {
         if (is_resource($this->_socket)) {
             fclose($this->_socket);
         }
     }

     /**
      * Write a message to the log.
      *
      * @param  array  $event  event data
      * @return void
      */
     protected function _write($event)
     {
         $line = $this->_formatter->format($event);

         if (false === @fwrite($this->_socket, $line)) {
             throw new Opsway_Log_Exception("Unable to write to socket: {$line}");
         }
     }
}