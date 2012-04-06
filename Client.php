<?php
/**
 * @author Yurko Fedoriv <yurko.fedoriv@gmail.com>
 */
namespace gearman;
/**
 * Gearman client class. Aggregates \GearmanClient object
 */
class Client extends Connection
{
    const API_CLASS = 'GearmanClient';


    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_LOW = 'low';

    const RECONNECT_ATTEMPTS = 10;

    /**
     * Run a task in the background
     * Translates calls to particular method by priority
     *
     * @param string $functionName A registered function the worker is to execute
     * @param string $workload     Data to be processed
     * @param string $priority     Priority the task should be performed with. May be one of 'normal', 'low' or 'high'
     * @param bool $background     Whether job should be performed in background mode
     * @param null $unique         A unique ID used to identify a particular task
     *
     * @return string The job handle for the submitted task or task result for foregroung jobs
     */
    public function send(
        $functionName, $workload = '', $priority = self::PRIORITY_NORMAL, $background = true, $unique = null
    ) {
        $args = array($functionName, $workload);
        if ($unique !== null) {
            $args[] = $unique;
        }

        switch ($priority) {
        case self::PRIORITY_LOW:
            $doMethod = 'doLow';
            break;
        case self::PRIORITY_HIGH:
            $doMethod = 'doHigh';
            break;
        default:
            $doMethod = !$background && method_exists($this->getInstance(), 'doNormal') ? 'doNormal' : 'do';
            break;
        }

        if ($background) {
            $doMethod .= 'Background';
        }

        for ($i = 0; $i < self::RECONNECT_ATTEMPTS; $i++) {
            try {
                return call_user_func_array(array($this->getInstance(), $doMethod), $args);
            }
            catch (\Exception $e) {
                \Yii::log("Job submission failed: {$e->getMessage()}. Reconnecting", \CLogger::LEVEL_WARNING, 'gearman.client');
                sleep(self::RECONNECT_TIMEOUT);
                $this->connect();
            }

        }
        throw $e;
    }
}
