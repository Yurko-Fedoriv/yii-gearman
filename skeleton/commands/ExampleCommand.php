<?php
/**
 * Created by JetBrains PhpStorm.
 * Author: Yurko Fedoriv <yurko.fedoriv@gmail.com>
 * Date: 2/22/12
 * Time: 6:17 PM
 */

/**
 * Example of sending job to worker
 */
class ExampleCommand extends CConsoleCommand
{
    public function actionIndex($param1 = 'value1', $param2 = 'value2'){
        $job = new \gearman\Job(
            array( //params for the task. Order does not matter.
                'param2' => $param2,
                'param1' => $param1,
            ),
            'sceleton.example.do', //gearman function (queue) to send job to
            array(  //callback definition
                'sceleton.example.callback',
                'param2' => $param2,
                'param1' => $param1,
            ),
            'EXAMPLE' //this value will be prefixed to log while performing this task
        );

        $job->send();
    }

}
