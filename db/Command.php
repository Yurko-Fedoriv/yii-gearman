<?php
/**
 * @author Yurko Fedoriv <yurko.fedoriv@gmail.com>
 */
namespace gearman\db;
/**
 * Command extends \CDbCommand with reconnection behavior. If statement fails with code code 2006 reconnecting to database server will be performed.
 * NOTE: intended for MYSQL server.
 *
 * @throws \CDbException
 *
 */
class Command extends \CDbCommand
{
    const RECONNECT_TIMEOUT = 5;
    const RECONNECT_ATTEMPTS = 10;

    /**
     * Executes the SQL statement.
     * This method is meant only for executing non-query SQL statement.
     * No result set will be returned.
     *
     * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
     *                      to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
     *                      them in this way can improve the performance. Note that if you pass parameters in this way,
     *                      you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
     *                      binding methods and  the input parameters this way can improve the performance.
     *                      This parameter has been available since version 1.0.10.
     *
     * @return integer number of rows affected by the execution.
     * @throws \CException execution failed
     */
    public function execute($params = array()) {
        return $this->connectionHandler(array($this, 'parent::execute'), func_get_args());
    }

    private function connectionHandler($invoke, $args) {
        for ($i = 0; $i < self::RECONNECT_ATTEMPTS; $i++) {
            try {
                return call_user_func_array($invoke, $args);
            }
            catch (\CDbException $e) {
                if ($e->errorInfo[1] >= 2000 && $e->errorInfo[1] <= 2013) {
                    \Yii::log("Lost connection to MYSQL server. <[{$e->errorInfo[1]}] {$e->errorInfo[2]}>. Reconnecting...", \CLogger::LEVEL_WARNING, 'gearman.db.Command');
                    $this->cancel();
                    sleep(self::RECONNECT_TIMEOUT);
                    $this->getConnection()->setActive(false);
                    $this->getConnection()->setActive(true);
                }
                else {
                    throw $e;
                }
            }
        }
        throw $e;
    }

    /**
     * Executes the SQL statement and returns query result.
     * This method is for executing an SQL query that returns result set.
     *
     * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
     *                      to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
     *                      them in this way can improve the performance. Note that if you pass parameters in this way,
     *                      you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
     *                      binding methods and  the input parameters this way can improve the performance.
     *                      This parameter has been available since version 1.0.10.
     *
     * @return \CDbDataReader the reader object for fetching the query result
     * @throws \CException execution failed
     */
    public function query($params = array()) {
        return $this->connectionHandler(array($this, 'parent::query'), func_get_args());
    }

    /**
     * Executes the SQL statement and returns all rows.
     *
     * @param boolean $fetchAssociative whether each row should be returned as an associated array with
     *                                  column names as the keys or the array keys are column indexes (0-based).
     * @param array $params             input parameters (name=>value) for the SQL execution. This is an alternative
     *                                  to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
     *                                  them in this way can improve the performance. Note that if you pass parameters in this way,
     *                                  you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
     *                                  binding methods and  the input parameters this way can improve the performance.
     *                                  This parameter has been available since version 1.0.10.
     *
     * @return array all rows of the query result. Each array element is an array representing a row.
     * An empty array is returned if the query results in nothing.
     * @throws \CException execution failed
     */
    public function queryAll($fetchAssociative = true, $params = array()) {
        return $this->connectionHandler(array($this, 'parent::queryAll'), func_get_args());
    }

    /**
     * Executes the SQL statement and returns the first row of the result.
     * This is a convenient method of {@link query} when only the first row of data is needed.
     *
     * @param boolean $fetchAssociative whether the row should be returned as an associated array with
     *                                  column names as the keys or the array keys are column indexes (0-based).
     * @param array $params             input parameters (name=>value) for the SQL execution. This is an alternative
     *                                  to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
     *                                  them in this way can improve the performance. Note that if you pass parameters in this way,
     *                                  you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
     *                                  binding methods and  the input parameters this way can improve the performance.
     *                                  This parameter has been available since version 1.0.10.
     *
     * @return mixed the first row (in terms of an array) of the query result, false if no result.
     * @throws \CException execution failed
     */
    public function queryRow($fetchAssociative = true, $params = array()) {
        return $this->connectionHandler(array($this, 'parent::queryRow'), func_get_args());
    }

    /**
     * Executes the SQL statement and returns the value of the first column in the first row of data.
     * This is a convenient method of {@link query} when only a single scalar
     * value is needed (e.g. obtaining the count of the records).
     *
     * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
     *                      to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
     *                      them in this way can improve the performance. Note that if you pass parameters in this way,
     *                      you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
     *                      binding methods and  the input parameters this way can improve the performance.
     *                      This parameter has been available since version 1.0.10.
     *
     * @return mixed the value of the first column in the first row of the query result. False is returned if there is no value.
     * @throws \CException execution failed
     */
    public function queryScalar($params = array()) {
        return $this->connectionHandler(array($this, 'parent::queryScalar'), func_get_args());
    }

    /**
     * Executes the SQL statement and returns the first column of the result.
     * This is a convenient method of {@link query} when only the first column of data is needed.
     * Note, the column returned will contain the first element in each row of result.
     *
     * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
     *                      to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
     *                      them in this way can improve the performance. Note that if you pass parameters in this way,
     *                      you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
     *                      binding methods and  the input parameters this way can improve the performance.
     *                      This parameter has been available since version 1.0.10.
     *
     * @return array the first column of the query result. Empty array if no result.
     * @throws \CException execution failed
     */
    public function queryColumn($params = array()) {
        return $this->connectionHandler(array($this, 'parent::queryColumn'), func_get_args());
    }
}
