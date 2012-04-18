<?php
/**
 * @author Yurko Fedoriv <yurko.fedoriv@gmail.com>
 */
namespace gearman;
/**
 * Tweaked \CConsoleApplication class to better fit for gearman worker oriented application.
 *
 * @property string $instanceId Property used to exclusively identify application instance. Used in logging.
 * @property string $logPrefix  If \gearman\LogFilter is used, this would be prepended to all logs. May be set on runtime. Accepts array on assigment.
 */
class Application extends \CConsoleApplication
{
    /**
     * @var string
     * @see Application::$instanceId
     */
    private $_instanceId;

    /**
     * @var string
     * @see Application::$logPrefix
     */
    private $_logPrefix;

    /**
     * Additional application initialization.
     * Replaces error handler with error-to-exception converter.
     * Configures logger to immediately dump logs to routes as they go, which is essential for long-running application.
     *
     * @throws \ErrorException
     * @return void
     */
    protected function init() {
        parent::init();
        set_error_handler(
        //converts error to exxception. ignores GearmanWorker::work() spam warnings, used to be in old lib versions.
            function($code, $message, $file = null, $line = null) {
                if ($code & error_reporting()) {
                    if (strpos($message, 'GearmanWorker::work():') === 0) {
                        return true;
                    }
                    throw new \ErrorException($message, $code, $code, $file, $line);
                }
                return true;
            }
        );
        \Yii::getLogger()->autoFlush = 1;
        \Yii::getLogger()->autoDump = true;
    }


    /**
     * GETTER
     * Default value includes host name and process pid.
     *
     * @see Application::$instanceId
     * @return string
     */
    public function getInstanceId() {
        if ($this->_instanceId === null) {
            $this->_instanceId = sprintf('%s:%d', gethostname(), getmypid());
        }
        return $this->_instanceId;
    }

    /**
     * SETTER
     *
     * @see Application::$instanceId
     *
     * @param string $value vallue to be assigned
     *
     * @return void
     */
    public function setInstanceId($value) {
        $this->_instanceId = $value;
    }

    /**
     * GETTER
     *By default calls setter {@link Application::setLogPrefix()}
     *
     * @see Application::$logPrefix
     * @return string
     */
    public function getLogPrefix() {
        if ($this->_logPrefix === null) {
            $this->setLogPrefix();
        }
        return $this->_logPrefix;
    }

    /**
     * SETTER
     *
     * @see Application::$logPrefix
     *
     * Expects array as first argument, or get it from all params passed.
     * Prepends each value rounded with brackets
     * Always preped instance id
     * return void
     */
    public function setLogPrefix() {
        $prefix = "[{$this->getInstanceId()}]";
        $data = func_get_args();
        if ($data && is_array($data[0])) {
            $data = $data[0];
        }
        $data = array_filter($data);
        if ($data) {
            foreach (
                $data as &$item
            ) {
                $prefix .= " [$item]";
            }
        }
        $this->_logPrefix = $prefix;
    }

    /**
     * Shortcut to gearman module
     *
     * @return Module
     */
    public function getGearman() {
        return $this->getModule('gearman');
    }

    /**
     * Shortcut to cache component included in gearman module.
     *
     * @return \CCache|MemCache
     */
    public function getCache() {
        return parent::getCache() ?: $this->getGearman()->getComponent('cache');
    }

}
