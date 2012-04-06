<?php
/**
 * @author Yurko Fedoriv <yurko.fedoriv@gmail.com>
 */
namespace gearman;
/**
 * Class to be used for sending and handling gearman jobs.
 */
class Job extends \CComponent
{
    const STATUS_IN_PROGRESS = 'inProgress';
    const STATUS_DONE = 'done';
    const STATUS_FAILED = 'failed';


    /**
     * @var array Params passed to Job
     */
    public $params;

    /**
     * @var string|array|null Log prefix value to be attached to logs while performing this job
     */
    public $logPrefix;

    /**
     * @var null|array Callback gearman function to send job to after task completion. First item must be name of function, other params will be treated as additional params in callback
     */
    public $callback;

    /**
     * @var string Gearman function this Job instance is used for
     */
    public $function;

    /**
     * @var string Which priority to use when sending the job
     */
    public $priority = Client::PRIORITY_NORMAL;

    /**
     * @var bool Whether to perform job in background mode
     */
    public $background = true;

    /**
     * @var string Unique id of the job
     */
    public $unique;

    /**
     * @var string Status of the job
     */
    private $_status;

    /**
     * @var int Last modified timestamp. Updated when status changes
     */
    private $_lastModified;

    /**
     * @var \Exception Exception object catched during job
     */
    private $_exception;

    /**
     * @var \GearmanJob If instance created from \GearmanJob instance, this is reference to it.
     */
    private $_gearmanJob;

    /**
     * @var string Handle of current job if present
     */
    private $_handle;

    /**
     * Constructor
     *
     * @param array|\GearmanJob $params
     * @param string $function Gearman function to associate instance with. May be passed in params array as 'function' key
     * @param array $callback  Callback info. May ba passed in params as 'callback' key
     * @param mixed $logPrefix Log prefix to used while job will be performed. May be passed in params array as 'logPrefix' key
     */
    function __construct($params, $function = null, $callback = null, $logPrefix = null) {
        if ($params instanceof \GearmanJob) {
            $this->_gearmanJob = $params;
            $this->_handle = $this->_gearmanJob->handle();

            $params = $this->decode($params->workload());
            $function = $this->_gearmanJob->functionName();

        }
        if (isset($params['logPrefix'])) {
            $logPrefix = $params['logPrefix'];
            unset($params['logPrefix']);
        }
        if (isset($params['callback'])) {
            $callback = $params['callback'];
            unset($params['callback']);
        }
        if (isset($params['params'])) {
            $params = $params['params'];
        }

        $this->params = $params;
        $this->logPrefix = $logPrefix;
        $this->callback = $callback;
        $this->function = $function;

        $this->_lastModified = time();
    }

    /**
     * @return null|string Get handle of current Job.
     */
    public function getHandle() {
        return $this->_handle;
    }

    /**
     * @return string
     */
    function __toString() {
        return $this->encode();
    }

    /**
     * @return string Encoded workload to be sent to gearman job server
     */
    public function encode() {
        return base64_encode(
            \CJSON::encode(
                array(
                    'params' => $this->params,
                    'logPrefix' => $this->logPrefix,
                    'callback' => $this->callback,
                )
            )
        );
    }

    /**
     * Decodes workload
     *
     * @param string $workload Workload recieved from gearman job server to decode
     *
     * @return mixed
     */
    public function decode($workload) {
        return \CJSON::decode(base64_decode($workload));
    }

    /**
     * Sends job to gearman job server
     *
     * @return string Result of sending job.
     */
    public function send() {
        $result = $this->getClient()->send(
            $this->function,
            $this->encode(),
            $this->priority,
            $this->background,
            $this->unique
        );

        if ($this->background) {
            $this->_handle = $result;
        }

        return $result;
    }

    /**
     * Shortcut to gearman client instance
     *
     * @return Client
     */
    public function getClient() {
        return \Yii::app()->getModule('gearman')->getComponent('client');
    }


    /**
     * Modifies status to done state
     *
     * @return void
     */
    public function done() {
        $this->_status = self::STATUS_DONE;
        $this->_lastModified = time();
    }

    /**
     * Modifies status to failed state
     *
     * @param \Exception $e Exception describing why job was failed
     *
     * @return void
     */
    public function failed(\Exception $e = null) {
        $this->_status = self::STATUS_FAILED;
        $this->_exception = $e;
        $this->_lastModified = time();
    }

    /**
     * GETTER
     *
     * @return string Status message
     */
    public function getStatus() {
        return $this->_status;
    }

    /**
     * @return null|string Class of exception thrown during task performing
     */
    public function getExceptionClass() {
        return is_object($this->_exception) ? get_class($this->_exception) : null;
    }

    /**
     * @return null|string Formatted string of error occurred.
     */
    public function getFailMassage() {
        if ($this->_exception) {
            return '[' . date('Y/m/d H:i:s', $this->_lastModified) . '] [' . $this->function . '] '
                . $this->_exception->getMessage();
        }
        else {
            return null;
        }
    }

    /**
     * GETTER
     *
     * @return array Full status info. Used to send to callback tasks
     */
    public function getStatusInfo() {
        return array(
            'function' => $this->function,
            'jobHandle' => $this->getHandle(),
            'status' => $this->_status,
            'exception' => $this->_exception
                ?
                array(
                    'class' => $this->getExceptionClass(),
                    'message' => $this->_exception->getMessage(),
                )
                :
                false,
            'lastModified' => $this->_lastModified,
        );
    }
}