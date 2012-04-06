<?php
/**
 * @author Yurko Fedoriv <yurko.fedoriv@gmail.com>
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
     *
     * @param string $function   The name of a function to register with the job server
     * @param callback $callback A callback that gets called when a job for the registered function name is submitted
     */
    public function addFunction($function, $callback) {
        $this->getInstance()->addFunction($function, $callback);
    }

    /**
     * Unregisters a function name with the job servers ensuring that no more jobs (for that function) are sent to this worker.
     *
     * @param string $function The name of a function to register with the job server
     */
    public function unregister($function) {
        $this->getInstance()->unregister($function);
    }

    /**
     * Implements working loop. Will pause if connection to server fails.
     *
     * @param callback $callback Callback to call on each iteration.
     */
    public function work($callback = null) {
        /** @var $instance \GearmanWorker */
        $instance = $this->getInstance();
        while (true) {
            $e = null;
            $message = null;
            try {
                $instance->work();
            }
            catch (\GearmanException $e) {

                $message = (object)array('error' => $e->getMessage(), 'code' => $e->getCode());
            }

            if (!($instance->returnCode() == GEARMAN_SUCCESS || $instance->returnCode() == GEARMAN_TIMEOUT)
                && $e === null
            ) {
                $message = (object)array('error' => $instance->error(), 'code' => $instance->returnCode());
            }

            if ($message) {
                \Yii::log("Worker failed:  {$message->error} with code {$message->code}", \CLogger::LEVEL_ERROR, 'gearman.worker');
                \Yii::log("Waiting...", \CLogger::LEVEL_INFO, 'gearman.worker');
                sleep(self::RECONNECT_TIMEOUT);
            }
            if (is_callable($callback)) {
                call_user_func($callback);
            }
        }
    }
}
