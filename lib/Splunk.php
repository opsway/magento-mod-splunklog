<?php
/**
* Sends statistics to the Splunk over UDP
*
* @todo Need implemented "delay send" feature
* @category Tools
* @package SplunkLog
* @author Alexandr Vronskiy <alvro@opsway.com>
* @license New BSD License
*/
class Splunk
{
    /**
     * It constant for relation Splunk_port <=> format string send to Splunk
     * It calc as Default port from magento config + value const
     */
    const FORMAT_PORT_DEFAULT = 0;
    const FORMAT_PORT_INT_STRING = -1;

    /**
    * @var array $config
    */
    protected static $config;

    /**
     * For each separate splunk port needed defferent format messages to send
     *
     * @var array $_formatters
     */
    protected static $_formatters = array(self::FORMAT_PORT_DEFAULT => Opsway_Log_Formatter_Splunk::DEFAULT_FORMAT,
                                        self::FORMAT_PORT_INT_STRING => '%metric% %value% %info% %timestamp%');

    /**
     * For collect timers metric
     *
     * @var array $_timings
     */
    protected static $_timings = array();

    protected static $sampledData = array();

    /**
     * For different port hold open separate model log (sockets)
     *
     * @var Zend_Log[] $_loggers
     */
    protected static $_loggers = array();

    public static function log($data, $metric, $format = self::FORMAT_PORT_DEFAULT )
    {
        if (!self::$config){
            if (!Mage::getConfig()) {
                return false;
            }
            try {
                $config['enabled'] = (bool) Mage::getStoreConfig('splunk/log/active');
                $config['host'] = Mage::getStoreConfig('splunk/log/host');
                $config['port'] = (int) Mage::getStoreConfig('splunk/log/default_port');

            } catch (Exception $e) {
                $config['enabled'] = false;
            }
            self::init($config);
        }
        if (!is_array($data)){
            $data = array("value" => $data);
        }

        if (!self::$config['enabled']){
            return false;
        }

        try {

            if (!isset(self::$_loggers[$format])) {
                $writer = new Opsway_Log_Writter_Splunk(self::$config['host'], self::$config['port']+$format, self::$_formatters[$format]);
                self::$_loggers[$format] = new Zend_Log($writer);
            }

            $metric = self::$config["namespace"] . '.' . $metric;
            self::$_loggers[$format]->log($metric,Zend_Log::INFO,$data);

        } catch (Opsway_Log_Exception $e){
            Mage::logException($e);
            return false;
        }

        return true;
    }

    /**
* Pass in configuration, supply defaults if necessary.
*
* @param array $config
*
* @return void
* @throws InvalidArgumentException When 'enabled' is missing.
*/
    public static function init(array $config)
    {
        if (!isset($config['enabled'])) {
            throw new InvalidArgumentException("Config must contain 'enabled' flag.");
        }
        if ($config['enabled'] === true) {
            if (!isset($config['port']) || empty($config['port'])) {
                $config['port'] = 8125;
            }
            if (!isset($config['host']) || empty($config['host'])) {
                $config['host'] = '127.0.0.1';
            }
            /**
             * @todo It will be implemented in future
             */
            if (!isset($config['delay_send']) || empty($config['delay_send'])) {
                $config['delay_send'] = false;
            }

            if (!isset($config['namespace']) || empty($config['namespace'])) {
                $config['namespace'] = @file_get_contents("/etc/sensu/plugins/hostname") . '.magento';
            }

        }

        if ($config['delay_send']){
            register_shutdown_function(function(){ Splunk::sendData(); });
        }
        self::$config = $config;
    }

    /**
     * Getting all key data for this specific format
     *
     * @param $format
     *
     * @return array
     */
    public static function getRequiredKeysDataByFormat($format){
        $keys = explode(" ",str_ireplace("%", "", $format));
        $autoKeysForExclude = array('metric','timestamp');
        return array_diff($keys, $autoKeysForExclude);
    }

    /**
    * starts the timing for a key
    *
    * @param string $key
    *
    * @return void
    */
    public static function timerStart($key)
    {
        self::$_timings[$key] = gettimeofday(true);
    }

    /**
    * ends the timing for a key and sends it to statsd
    *
    * @param string $key
    *
    * @return void
    */
    public static function timerStop($key)
    {
        $end = gettimeofday(true);

        if (array_key_exists($key, self::$_timings)) {
            $timing = ($end - self::$_timings[$key]) * 1000;
            self::timing($key, round($timing), 1);
            unset(self::$_timings[$key]);
        }
    }

    /**
    * Log timing information
    *
    * @param string $stats The metric to in log timing info for.
    * @param float $time The ellapsed time (ms) to log
    *
    * @return boolean
    */
    public static function timing($stat, $time)
    {
        return self::log(array("value" => "$time"), $stat);
    }

    /**
    * Increments one or more stats counters
    * @todo Not needed in future
    * @deprecated
    * @param string|array $stats The metric(s) to increment.
    * @return boolean
    **/
    public static function increment($stats)
    {
        return self::log(array("value" => 1), $stats);
    }

    /**
    * Decrements one or more stats counters.
    * @todo Not needed in future
    * @deprecated
    * @param string|array $stats The metric(s) to decrement.
    * @return boolean
    **/
    public static function decrement($stats)
    {
        return self::log(array("value" => -1), $stats);
    }

    /*
    * Squirt the metrics
    * @todo to refator
    *
    * @param array $data
    * @param float|1 $sampleRate
    *
    * @return boolean
    */
    public static function send(array $data, $sampleRate=1)
    {
        if (self::$config['enabled'] !== true) {
            return false;
        }

        // sampling
        $sampledData = array();

        if ($sampleRate < 1) {
            foreach ($data as $stat => $value) {
                if ((mt_rand() / mt_getrandmax()) <= $sampleRate) {
                    $sampledData[$stat] = "$value";
                }
            }
        } else {
            $sampledData = $data;
        }

        if (empty($sampledData)) {
            return false;
        }

        if (self::$config["delay_send"]){
            self::$sampledData += $sampledData;
        } else {
            self::sendData($sampledData);
        }

        return true;
    }

    /**
     * Send on Splunk server over UDP
     *
     * @param array $sampledData
     *
     * @return bool
     */
    public static function sendData($sampledData = array()){
        if (count($sampledData) == 0){
            $sampledData = self::$sampledData;
        }
        //@todo It will be help?
    }
}