<?php
/**
 * @author Yurko Fedoriv <yurko.fedoriv@gmail.com>
 */
/**
 * Example of sending job to worker
 */
class ExampleCommand extends CConsoleCommand
{
    public function actionIndex($param1 = 'value1', $param2 = 'value2') {
        $job = new \gearman\Job(
            array( //params for the task. Order does not matter.
                'param2' => $param2,
                'param1' => $param1,
            ),
            'skeleton.example.do', //gearman function (queue) to send job to
            array( //[OPTIONAL] callback definition.
                'skeleton.example.callback',
                'param2' => $param2,
                'param1' => $param1,
            ),
            'EXAMPLE' //[OPTIONAL] this value will be prefixed to log while performing this task
        );

        $job->send();
    }

}
