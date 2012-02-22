<?php
/**
 * Created by JetBrains PhpStorm.
 * Author: Yurko Fedoriv <yurko.fedoriv@gmail.com>
 * Date: 2/14/12
 * Time: 4:17 PM
 */
namespace gearman;
/**
 * Gearman client class. Aggregates \GearmanWorker object
 */
class Worker extends Connection
{
    const API_CLASS = 'GearmanWorker';


    /**
     * Registers a function name with the job server and specifies a callback corresponding to that function.
     * @param string $function The name of a function to register with the job server
     * @param callback $callback A callback that gets called when a job for the registered function name is submitted
     */
    public function addFunction($function, $callback){
        $this->getInstance()->addFunction($function, $callback);
    }

    /**
     * Unregisters a function name with the job servers ensuring that no more jobs (for that function) are sent to this worker.
     * @param string $function The name of a function to register with the job server
     */
    public function unregister($function){
        $this->getInstance()->unregister($function);
    }

    /**
     * Implements working loop. Will pause if connection to server fails.
     * @param callback $callback Callback to call on each iteration.
     */
    public function work($callback = null) {
        /** @var $instance \GearmanWorker */
        $instance = $this->getInstance();
        while (true) {
            $instance->work();
            if(!($instance->returnCode() == GEARMAN_SUCCESS || $instance->returnCode() == GEARMAN_TIMEOUT)){
                \Yii::log("Worker failed:  {$instance->error()} with code {$instance->returnCode()}", \CLogger::LEVEL_ERROR, 'gearman.worker');
                \Yii::log("Waiting...", \CLogger::LEVEL_INFO, 'gearman.worker');
                sleep(self::RECONNECT_TIMEOUT);
            }
            if(is_callable($callback)){
                call_user_func($callback);
            }
        }
    }
}
