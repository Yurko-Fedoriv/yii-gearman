<?php
/**
 * @author Yurko Fedoriv <yurko.fedoriv@gmail.com>
 */
namespace gearman\db;
/**
 * Connection extends \CDbConnection with reconnection behavior. If connection fails with codes 2000-2013 reconnecting to database server will be performed until succeeded or max attempts is reached..
 * NOTE: intended for MYSQL server.
 *
 * @throws \CDbException
 *
 */
class Connection extends \CDbConnection
{
    const RECONNECT_TIMEOUT = 5;
    const RECONNECT_ATTEMPTS = 10;

    /**
     * Creates a command for execution.
     *
     * @param mixed $query the DB query to be executed. This can be either a string representing a SQL statement,
     *                     or an array representing different fragments of a SQL statement. Please refer to {@link CDbCommand::__construct}
     *                     for more details about how to pass an array as the query. If this parameter is not given,
     *                     you will have to call query builder methods of {@link CDbCommand} to build the DB query.
     *
     * @return Command the DB command
     */
    public function createCommand($query = null) {
        $this->setActive(true);
        return new Command($this, $query);
    }

    /**
     * Open or close the DB connection. If connection fails, retries until success.
     *
     * @param boolean $value whether to open or close DB connection
     *
     * @throws \CException if connection fails
     */
    public function setActive($value) {
        for ($i = 0; $i < self::RECONNECT_ATTEMPTS; $i++) {
            try {
                parent::setActive($value);
                return;
            }
            catch (\CDbException $eConnect) {
                if ($eConnect->getCode() >= 2000 && $eConnect->getCode() <= 2013) {
                    \Yii::log("Connecting failed. <[{$eConnect->getCode()}] {$eConnect->getMessage()}>. Waiting for retry...", \CLogger::LEVEL_WARNING, 'gearman.db.Connection');
                    sleep(self::RECONNECT_TIMEOUT);
                }
                else {
                    throw $eConnect;
                }
            }
        }

        throw $eConnect;
    }
}
