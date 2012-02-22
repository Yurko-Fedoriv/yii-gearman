<?php
/**
 * Created by JetBrains PhpStorm.
 * Author: Yurko Fedoriv <yurko.fedoriv@gmail.com>
 * Date: 2/14/12
 * Time: 5:23 PM
 */
namespace gearman;

/**
 * Base class for all gearman works, which actually implement worker functionality.
 * Children class names should have "Work" suffix to be recognised as work class.
 *
 * @property array $tasks            Array in which keys contain gearman function aliases and values realising methods for this work object
 * @property-read Job $currentJob Currently handled Job object
 */
abstract class BaseWork extends \CComponent
{
    /**
     * @var string Name of the current Work
     */
    public $id;

    /**
     * @var Job Stores currently handled job object. Is set when work receives task.
     * @see BaseWork::getCurrentJob();
     */
    private $_currentJob;

    /**
     * @var array
     * @see LBaseWork::getTasks()
     */
    private $_tasks;

    /**
     * @var Module reference to German Module
     */
    protected $_gearmanModule;

    private $_class;

    private $_reflections = array();

    /**
     * Constructor.
     *
     * @param string $id Name of current work
     */
    public function __construct($id) {
        $this->id = strtolower($id);
    }

    /**
     * Initialises object. Children classes should call it, if override this method.
     *
     * @return void
     */
    public function init() {
        $this->_gearmanModule = \Yii::app()->getModule('gearman');
        $this->_class = get_class($this);
    }


    /**
         * Logs message defining category.
         *
         * @param string $msg Message to log
         * @param string $level Message level
         *
         * @return void
         */

    public function log($msg, $level = \CLogger::LEVEL_INFO) {
        \Yii::log($msg, $level, $this->getCurrentJob() ? $this->getCurrentJob()->function : "gearman.{$this->id}");
    }

    /**
     * Main method. Attached as gearman function to worker.
     * Unserializes GearmanJob workload and routes calls to actual implementation methods
     * Catches exceptions. Declines job if it was passed to worker too many times (means error in internal implementation, or unhandable input data causing fatal errors).
     * Sends callback jobs
     *
     * @param \GearmanJob $gearmanJob
     *
     * @return string Value returned by task, or job handle
     * @throws WorkException
     */
    public function execute($gearmanJob) {

        $startTime = microtime(true);

        $job = new Job($gearmanJob);

        \Yii::app()->setLogPrefix($job->getHandle(), $job->logPrefix);

        $this->_currentJob = $job;
        $jobHandle = $job->getHandle();

        $returnData = null;
        try {
            $method = $this->mapTask($job->function);

            //check if $job should be rejected because of too many handling retries.
            /** @var $cache MemCache */
            $cache = $this->_gearmanModule->getComponent('cache');

            $times = $cache->get($jobHandle);
            if ($times === false) {
                $cache->set($jobHandle, 1);
            }
            elseif ($times <= 5) {
                $cache->increment($jobHandle);
            }
            else {
                throw new WorkException("Job <{$jobHandle}> canceled. Too many retries.");
            }

            $this->log('Received task');

            //calls realisation method with workload data
            $returnData = $this->call($method, $job->params);
            $cache->delete($jobHandle);
            $this->log('Finished. Job took: ' . round(microtime(true) - $startTime, 3) . ' seconds.');

            $job->done();
        }
        catch (JobRefused $e) { //Job execution runtime may decide that job shouldn't be executed. This also disables callback.
            $this->log('Job refused' . ($e->getMessage() ? " Reason: {$e->getMessage()}." : ''));
            return $jobHandle;
        }
        catch (\Exception $e) { //handling of any exceptions appeared. Marks work as failed, and logs exception. Worker will continue it's normal runtime.
            $job->failed($e);
            $this->log($e->getMessage(), \CLogger::LEVEL_ERROR);
            \Yii::app()->onException(new \CExceptionEvent($this, $e));
        }

        if ($job->callback) {
            try {
                $callback = $job->callback;
                if (is_array($callback)) {
                    $function = array_shift($callback);
                    $params = $callback;
                    array_unshift($params, $job->getStatusInfo());
                }
                else {
                    $function = $callback;
                    $params = array($job->getStatusInfo());
                }

                $params[count($params)] = $returnData;

                $callbackJob = new Job($params, $function, null, $job->logPrefix);
                $callbackJob->send();
            } catch (\Exception $e) {
                $this->log("Failed to send callback for job {$job->getHandle()}: {$e->getMessage()}");
            }
        }

        \Yii::app()->setLogPrefix();
        $this->_currentJob = null;
        return $returnData === null ? $jobHandle : $this->encodeReturnData($returnData);
    }

    /**
     * Method that encodes task result before returning it to gearman
     * @param mixed $data data to encode
     * @return string
     */
    protected function encodeReturnData($data){
       return base64_encode(\CJSON::encode($data));
    }

    /**
     * Wrapper for smart calling task methods
     * @param string $method Method of current instance to call
     * @param array $params Params to be passed to method. Supports param name or param position indexed array
     * @return mixed Method return value
     * @throws WorkException
     */
    protected function call($method, $params) {
        $reflection = $this->getReflectionMethod($method);

        $callParams = array();

        foreach ($reflection['params'] as $number => $parameter) {
            /** @var $parameter \ReflectionParameter */
            if (isset($params[$parameter->name])) {
                $callParams[$parameter->name] = $params[$parameter->name];
            }
            elseif (isset($params[$number])) {
                $callParams[$parameter->name] = $params[$number];
            }
            elseif ($parameter->isOptional()) {
                $callParams[$parameter->name] = $parameter->getDefaultValue();
            }
            else {
                throw new WorkException("Missing parameter {$parameter->name} in workload.");
            }
            if ($parameter->isArray() && !is_array($callParams[$parameter->name])
                && is_null($callParams[$parameter->name])
            ) {
                throw new WorkException("Parameter {$parameter->name} should be array, but {$callParams[$parameter->name]} was provided.");
            }
        }

        $info = array();
        foreach ($callParams as $name => $value) {
            if (is_array($value)) {
                $value = 'Array';
            }
            $info[] = "\$$name=$value";
        }

        $this->log("Invoking {$this->_class}::$method(" . implode(', ', $info) . ')');

        return $reflection['method']->invokeArgs($this, $callParams);
    }

    /**
     * Method to retrieve reflection information for current instance method. Caches data in memory for multiple usages
     * @param string $method Method name of current instance
     * @return array Array containig 'method' key with method reflection and 'params' key with array reflections of all method params.
     */
    public function getReflectionMethod($method) {
        if (!isset($this->_reflections[$method])) {
            $reflection = new \ReflectionMethod($this, $method);
            $this->_reflections[$method] = array(
                'method' => $reflection,
                'params' => $reflection->getParameters(),
            );
        }
        return $this->_reflections[$method];
    }

    /**
     * Returns method name associated with gearman function
     * @param string $function gearman function to map
     * @return string method name of current instance associated with gearman function
     * @throws WorkException if mapping is possible
     */
    public function mapTask($function) {
        $tasks = $this->getTasks();
        if (!isset($tasks[$function])) {
            throw new WorkException("[$function] does not exist in [{$this->_class}] task map");
        }
        if (!method_exists($this, $tasks[$function])) {
            throw new WorkException(
                "[{$this->_class}::{$tasks[$function]}] is not implemented. [$function] cannot be mapped");
        }

        return $tasks[$function];
    }

    /**
     * GETTER
     * Returns currently handled Job object
     *
     * @return Job
     */
    public function getCurrentJob() {
        return $this->_currentJob;
    }

    /**
     * GETTER
     * @return array Current task map
     */
    public function getTasks() {
        if ($this->_tasks === null) {
            $this->_tasks = $this->tasks();
        }
        return $this->_tasks;
    }

    /**
     * SETTER
     * @param array $value <gearman function> => <class method> map
     * @return bool
     */
    public function setTasks(array $value){
        if(!$value){
            return false;
        }

        foreach($value as $function => &$method){
            if(method_exists($this, $method)){
                continue;
            }
            elseif(method_exists($this, "task$method")){
                $method = "task$method";
            }
            else{
                throw new WorkException("Cannot map function $function to undefined method $method");
            }
        }

        $this->_tasks = $value;
        return true;
    }


    /**
     * Defines default <german function> => <class method> map
     * May be overriden in children classes to hardcode gearman function => work method association
     * By default scans all class methods with prefix 'task' and associates them with gearman function retrieved from {@link formatFunctionName()} call
     *
     * @return array <german function> => <class method> map
     */
    public function tasks() {
        $taskMethods = array_filter(
            get_class_methods($this),
            function($method) { return $method != 'tasks' && stripos($method, 'task') === 0; }
        );

        $map = array();
        foreach ($taskMethods as $method) {
            $map[$this->formatFunctionName($method)] = $method;
        }
        return $map;
    }

    /**
     * Default implementatin for generation geearman function names. uses [application id].[work id].[task name] pattern
     * @param string $method Work method name to generate gearman function name for
     * @return string Gearman function name
     */
    public function formatFunctionName($method) {
        return strtolower(
            implode(
                '.', array(
                    \Yii::app()->name,
                    $this->id,
                    preg_replace('/task(.*)/i', '\1', $method)
                )
            )
        );
    }


    /**
     * Register worker for handling gearman functions
     * @param string $function gearman function to regiter for
     * @param string $method current work method name to be mapped to function
     */
    public function register($function, $method) {
        $tasks = $this->getTasks();
        if (!isset($tasks[$function])) {
            $this->_tasks[$function] = $method;
        }
        elseif ($tasks[$function] != $method) {
            $this->unregister($function);
            $this->_tasks[$function] = $method;
        }

        $this->_gearmanModule->worker->addFunction($function, array($this, 'execute'));


        $reflection = $this->getReflectionMethod($method);
        $params = array();
        foreach ($reflection['params'] as $parameter) {
            /** @var $parameter \ReflectionParameter  */
            $param = '$' . $parameter->getName();
            if ($parameter->isOptional()) {
                $param = "[$param]";
            }
            $params[] = $param;
        }
        $this->log(
            "Registered: $function => " . $this->_class . '::' . $method . '('
                . implode(', ', $params) . ')'
        );
    }

    /**
     * Unregisters worker from gearman function
     * @param string $function geramn function
     */
    public function unregister($function) {
        $this->_gearmanModule->worker->unregister($function);
        unset($this->_tasks[$function]);
        $this->log("Unregistered $function");
    }

    /**
     * Registers worker to handle all functions mapped to current work
     */
    public function registerAll() {
        foreach ($this->getTasks() as $function => $method) {
            $this->register($function, $method);
        }
    }
}


/**
 * If job implementation throws this exception, this means that job is totally refused and callback wont be raised.
 */
class JobRefused extends Exception
{

}

/**
 * Exception class used to identify work internal errors
 */
class WorkException extends Exception
{
}
