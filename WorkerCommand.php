<?php
/**
 * @author Yurko Fedoriv <yurko.fedoriv@gmail.com>
 */
namespace gearman;
use \Yii;

/**
 * Command that implements worker apllication.
 */
class WorkerCommand extends \CConsoleCommand
{
    /**
     * @var int Determines in seconds how long worker instance should exists before automatic exiting.
     */
    public $lifetime;

    /**
     * @var BaseWork[] Collection of attached work objects
     */
    public $works = array();

    /**
     * @var int Stores timestamp when current instance will safely die to be restarted.
     * @see WorkerCommand::getWorkerEndTime()
     */
    private $_workerEndTime;

    /**
     * @var bool Whether worker may automatically die in such state. Can be used to prevent terminating from attached work.
     */
    public $allowDie = true;

    /**
     * @var bool Whether worker should die. Used by signal handler.
     */
    public $forceDie = false;


    /**
     * Inits object.
     * Sets needed import.
     * Attaches signal handler
     *
     * @return void
     */
    public function init() {
        \Yii::app()->getGearman()->command = $this;
        parent::init();

        \Yii::import('application.works.*');

        $signals = array(
            SIGTERM => 'SIGTERM',
            SIGHUP => 'SIGHUP',
            SIGINT => 'SIGINT'
        );

        $self = $this;
        $signalHandler = function($sigNo) use ($signals, $self) {
            $self->log("I got {$signals[$sigNo]} and will exit");
            $self->forceDie = true;
        };

        foreach ($signals as $signal => $signalName) {
            pcntl_signal($signal, $signalHandler);
        }
    }

    /**
     * Realisation of worker. Connects to gearman server,attaches job functions,
     *
     * @param array $args Work names to be attached to worker and named params to be passed to works
     *
     * @return void
     */
    public function run(array $args) {
        list($works, $options) = $this->resolveRequest($args);

        if (!$works) {
            echo 'Please Provide at least one implemented work name to be handled by worker.', PHP_EOL;
            Yii::app()->end();
        }
        foreach ($works as $workName) {
            $this->getWork($workName)->setOptions($options)->registerAll();
        }

        $this->getWorker()->work(array($this, 'iterate'));
    }

    protected function resolveRequest($args) {
        $options = array(); // named parameters
        $works = array(); // works
        foreach ($args as $arg) {
            if (preg_match('/^--([\w\-]+)(=(.*))?$/', $arg, $matches)) { // an option
                $name = $matches[1];
                if (strpos($name, '-') !== false) {
                    $nameParts = array_filter(explode('-', $name));
                    $name = array_shift($nameParts);
                    foreach ($nameParts as $namePart) {
                        $name .= ucfirst($namePart);
                    }
                }
                $value = isset($matches[3]) ? $matches[3] : true;
                if (isset($options[$name])) {
                    if (!is_array($options[$name])) {
                        $options[$name] = array($options[$name]);
                    }
                    $options[$name][] = $value;
                }
                else {
                    $options[$name] = $value;
                }
            }
            else {
                $works[] = $arg;
            }
        }

        return array($works, $options);
    }

    /**
     * Creates work instance based on it's name.
     *
     * @param string  $name
     *
     * @return BaseWork
     */
    public function getWork($name) {
        $name = preg_replace('/^(\w+)Work$/', '\1', $name);

        foreach (array_keys($this->works) as $id) {
            if (strtolower($name) == strtolower($id)) {
                $name = $id;
            }
        }

        $className = (preg_match('/[a-z0-9]/', $name) ? ucfirst($name) : $name) . 'Work';

        if (isset($this->works[$name])) {
            if (!is_object($this->works[$name])) {
                if (is_array($this->works[$name]) && !isset($this->works[$name]['class'])) {
                    $this->works[$name]['class'] = $className;
                }
                $this->works[$name] = Yii::createComponent($this->works[$name], $name);
                $this->works[$name]->init();
            }
        }
        else {
            $this->works[$name] = Yii::createComponent($className, $name);
            $this->works[$name]->init();
        }
        return $this->works[$name];
    }

    /**
     * Getter for Worker component.
     *
     * @return Worker
     */
    public function getWorker() {
        if (!Yii::app()->hasModule('gearman')) {
            throw new WorkerCommandException('Module of class \gearman\Module must be attached to the application with name gearman');
        }
        return Yii::app()->getModule('gearman')->getComponent('worker');
    }

    /**
     * Getter for workerEndTime property.
     * Timestamp when current instance will safely die to be restarted.
     *
     * @see WorkerCommand::$_workerEndTime
     * @return float unix timestamp corresponding to current worker instance death time. False if end time is not configured.
     */
    public function getWorkerEndTime() {
        if ($this->_workerEndTime === null) {
            if ($this->lifetime) {
                $delta = intval($this->lifetime * 0.1);
                $this->_workerEndTime = YII_BEGIN_TIME + $this->lifetime + mt_rand(-$delta, $delta);
                $this->log('I will die at ' . date('Y-m-d H:i:s', $this->_workerEndTime));
            }
            else {
                $this->_workerEndTime = false;
            }
        }
        return $this->_workerEndTime;
    }

    /**
     * Ends worker instance if max lifetime is reached.
     *
     * @return void
     */
    public function iterate() {
        pcntl_signal_dispatch();
        if ($this->forceDie
            || ($this->allowDie && $this->getWorkerEndTime() != false && $this->getWorkerEndTime() < time())
        ) {
            $this->log('I worked enough. Shutting down.');
            Yii::app()->end();
        }
    }

    /**
     * Logs message with apropriate category
     *
     * @param string $msg   Message to be logged
     * @param string $level [Optional] Defaults to 'info'
     *
     * @return void
     */
    public function log($msg, $level = \CLogger::LEVEL_INFO) {
        Yii::log($msg, $level, "gearman.command");
    }
}

/**
 * Exception thrown by WorkerCommand.
 */
class WorkerCommandException extends Exception
{

}