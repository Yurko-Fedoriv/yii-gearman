<?php
/**
 * @author Yurko Fedoriv <yurko.fedoriv@gmail.com>
 */
namespace gearman;
/**
 * Application module that contains all gearman related components.
 * Provides default config for all of them. Components config may be overrided in config file. e.g.
 * modules => array('gearman' => array('class' => '\gearman\Module', 'components' => array('client' => array('servers' => '172.16.1.111:4730'))))
 *
 * Components:
 *
 * @property Client $client
 * @property Worker $worker
 * @property Server $server
 * @property MemCache  $cache
 */

class Module extends \CModule
{

    /**
     * @var WorkerCommand Currently running worker command instance.
     */
    public $command;

    /**
     * @var string Server to which all components should connect by default
     */
    public $host = '127.0.0.1';


    /**
     * Initailizes component, also merges runtime configuration with default one.
     *
     * @return void
     */
    public function init() {
        parent::init();
        $this->setComponents(\CMap::mergeArray($this->defaultComponentsConfig(), $this->getComponents(false)), false);
    }


    /**
     * Provides default configuration for gearman components. Uses host property
     *
     * @return array
     */
    protected function defaultComponentsConfig() {
        return array(
            'client' => array(
                'class' => '\gearman\Client',
                'servers' => $this->host . ':4730'
            ),
            'worker' => array(
                'class' => '\gearman\Worker',
                'servers' => $this->host . ':4730',
                'options' => array(GEARMAN_WORKER_GRAB_UNIQ),
            ),
            'server' => array(
                'class' => '\gearman\Server',
                'server' => $this->host . ':4730'
            ),
            'cache' => array(
                'class' => '\gearman\MemCache',
                'keyPrefix' => 'gearman',
                'servers' => array(
                    array(
                        'host' => $this->host,
                        'port' => 11211,
                    ),
                )
            )
        );
    }
}
