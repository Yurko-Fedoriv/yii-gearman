<?php
/**
 * Created by JetBrains PhpStorm.
 * Author: Yurko Fedoriv <yurko.fedoriv@gmail.com>
 * Date: 2/22/12
 * Time: 5:58 PM
 */

/**
 * Class to demonstrate example implementation of work to be performed with worker application
 */
class ExampleWork extends \gearman\BaseWork
{
    /**
     * @param mixed $param1 Some input param for task
     * @param mixed $param2 Some other input param for task
     * @return array
     */
    public function taskDo($param1, $param2){
        echo '===RUNNING MAIN WORK===', PHP_EOL;
        var_dump(func_get_args());

        return array('key' => 'value');
    }

    /**
     * @param array $status Info about job whose callback this is. Always passed as first parameter in callback tsaks
     * @param mixed $param1 Param passed by client when callback of job was defined
     * @param mixed $param2 Other param passed by client when callback of job was defined
     * @param mixed $result  Always passed as last attribute in callback tasks
     */
    public function taskCallback($status, $param1, $param2, $result){
        echo '===RUNNING CALLBACK===', PHP_EOL;
        var_dump(func_get_args());
    }

}
