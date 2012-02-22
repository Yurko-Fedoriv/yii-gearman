<?php
/**
 * Created by JetBrains PhpStorm.
 * Author: Yurko Fedoriv <yurko.fedoriv@gmail.com>
 * Date: 2/14/12
 * Time: 4:59 PM
 */
namespace gearman;
/**
 * Base class for Client and Worker classes. Handles connection to gearman job server
 */
abstract class Connection extends \CApplicationComponent
{
    const RECONNECT_TIMEOUT = 5;
    /**
     * @var \GearmanClient|\GearmanWorker wrapped instance
     * @see LBaseGearman::getInstance()
     */
    protected $_instance;

    /**
     * A comma-separated list of gearman servers, each server specified in the format 'host:port'.
     *
     * @var string
     */
    public $servers = '127.0.0.1:4730';

    /**
     * @var Array of gearman options to be applied to wrapped instance
     */
    public $options;

    /**
     * @var int Value will be passed to wrapped GearmanClient(Worker)::setTimeout();
     */
    public $timeout = 1000;

    /**
     * Initializes the component..
     */
    public function init() {
        parent::init();
        $this->connect();
    }

    /**
     * Creates GearmanWorker / GearmanClient instance and connection to gearman job server
     */
    public function connect() {
        $class = static::API_CLASS;
        $this->_instance = new $class();
        $this->_instance->addServers($this->servers);
        $this->_instance->setTimeout($this->timeout);
        if (!is_null($this->options)) {
            $options = is_array($this->options) ? $this->options : array($this->options);
            foreach ($options as &$option) {
                $this->_instance->addOptions($option);
            }
        }
    }

    /**
     * Getter for GearmanClient or GearmanServer wrapped instance
     *
     * @return \GearmanClient|\GearmanWorker wrapped instance
     */
    public function getInstance() {
        return $this->_instance;
    }

}
