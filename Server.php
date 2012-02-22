<?php
/**
 * Created by JetBrains PhpStorm.
 * Author: Yurko Fedoriv <yurko.fedoriv@gmail.com>
 * Date: 2/15/12
 * Time: 5:35 PM
 */

namespace gearman;
/**
 * Component which allows to connect to gearman server and retrieve its' status information.
 *
 * @author dima
 * @author Yurko
 */
class Server extends \CApplicationComponent
{
    const COMMAND_STATUS = 'status';
    const COMMAND_WORKERS = 'workers';
    const COMMAND_VERSION = 'version';

    public $host = '127.0.0.1';
    public $port = 4730;
    public $timeout = 5;

    private $_socket;

    public function __destruct() {
        $this->disconnect();
    }

    public function setServer($value) {
        list($this->host, $this->port) = explode(':', $value);
    }

    protected function connect() {
        if (!$this->_socket) {
            $errno = $errstr = null;
            $this->_socket = @pfsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

            if ($errno) {
                throw new \Exception("$errno : $errstr");
            }

            stream_set_timeout($this->_socket, $this->timeout);
        }

        return $this->_socket;
    }

    protected function disconnect() {
        @fclose($this->_socket);
    }

    /**
     * Sends a command to gearman server,
     * returns array of strings or single string if $single == true
     *
     * @param string $command
     * @param bool $single
     *
     * @return string | array
     */
    protected function sendCommand($command, $single = false) {
        $this->connect();

        $command = $command . "\n";
        fwrite($this->_socket, $command, strlen($command));

        if ($single) {
            return trim(fgets($this->_socket));
        }

        $data = array();
        while ('.' != $out = trim(fgets($this->_socket))) {
            $data[] = $out;
        }

        return $data;
    }

    /**
     * Returns gearman status in format:
     *
     * array(
     *     array(function, total, running, available workers),
     *     ...
     * )
     *
     * @return array
     */
    public function getStatus() {
        $res = array();
        foreach ($this->sendCommand(self::COMMAND_STATUS) as $row) {
            $res[] = sscanf($row, "%s\t%u\t%u\t%u");
        }
        return $res;
    }

    public function printFormattedStatus() {
        $status = $this->getStatus();

        $len = @max(array_map('strlen', arrayRow($status, 0)));
        $format = "%-{$len}s : %-10s %-10s %-10s";

        echo  PHP_EOL, sprintf($format, 'Function', 'Enqueued', 'Running', 'Workers'), PHP_EOL, PHP_EOL;
        foreach ($status as $row) {
            echo vsprintf($format, $row), PHP_EOL;
        }
    }

    /**
     * Returns gearman workers list in format:
     *
     * array(
     *     array(file descriptor, ip address, client id, function),
     *     ...
     * )
     *
     * @return array
     */
    public function getWorkers() {
        $res = array();
        foreach ($this->sendCommand(self::COMMAND_WORKERS) as $row) {
            $res[] = sscanf($row, "%u %s %s : %s");
        }
        return $res;
    }

    /**
     * @return string
     */
    public function getVersion() {
        return $this->sendCommand(self::COMMAND_VERSION, true);
    }

}

/**
 * @param array $array Input array
 * @param int $row Get single row of multidimensional array
 * @return array
 */
function arrayRow(Array $array, $row) {
    return array_reduce(
        $array,
        function($v, $w) use ($row) {
            array_push($v, $w[$row]);
            return $v;
        },
        array()
    );
}