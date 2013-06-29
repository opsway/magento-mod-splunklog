<?php
/**
 * Base observer model
 *
 * @category Opsway
 * @package  Opsway_SplunkLog
 * @author Alexandr Vronskiy <alvro@opsway.com>
 */
class Opsway_SplunkLog_Model_Observer
{

    /**
     *
     * @param Varien_Event_Observer $observer
     */
    public function loggingByDefault(Varien_Event_Observer $observer)
    {
        $metric = $observer->getEvent()->getMetric();
        $data = array('value' => $observer->getEvent()->getValue());
        Splunk::log($data,$metric);
    }

    /**
     *
     * @param Varien_Event_Observer $observer
     */
    public function loggingByValueAndInfo(Varien_Event_Observer $observer)
    {
        $dataEvent = $observer->getEvent()->getData();

        if (!isset($dataEvent['metric'])) return;

        $data = array();
        foreach (Splunk::getRequiredKeysDataByFormat(Splunk::FORMAT_PORT_INT_STRING) as $key){
            if (!isset($dataEvent[$key])) continue;
            $data[$key] = $dataEvent[$key];
        }

        Splunk::log($data,$dataEvent['metric'],Splunk::FORMAT_PORT_INT_STRING);
    }
}
