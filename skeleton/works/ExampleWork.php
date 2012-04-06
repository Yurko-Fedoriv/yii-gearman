<?php
/**
 * @author Yurko Fedoriv <yurko.fedoriv@gmail.com>
 */
/**
 * Class to demonstrate example implementation of work to be performed with worker application
 */
class ExampleWork extends \gearman\BaseWork
{
    /**
     * Example task. You will see dump of received params
     *
     * @param mixed $param1 Some input param for task
     * @param mixed $param2 Some other input param for task
     *
     * @return array
     */
    public function taskDo($param1, $param2) {
        echo '===RUNNING MAIN WORK===', PHP_EOL;
        var_dump(func_get_args());

        return array('key' => 'value');
    }

    /**
     * Example callback task. You will see dump of received params
     *
     * @param array $status  Info about job whose callback this is. Passed automatically by BaseWork
     * @param mixed $param1  Param passed by client when callback of job was defined
     * @param mixed $param2  Other param passed by client when callback of job was defined
     * @param mixed $result  Result of previous job. Passed automatically by BaseWork
     */
    public function taskCallback($status, $param1, $param2, $result) {
        echo '===RUNNING CALLBACK===', PHP_EOL;
        var_dump(func_get_args());
    }

}
