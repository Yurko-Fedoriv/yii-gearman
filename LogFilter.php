<?php
/**
 * @author Yurko Fedoriv <yurko.fedoriv@gmail.com>
 */
namespace gearman;
class LogFilter extends \CComponent
{
    /**
     * Filters the given log messages.
     * Adds Application::$logPrefix value to each log.
     *
     * @param array $logs the log messages
     *
     * @return array
     */
    public function filter(&$logs) {
        $prefix = \Yii::app()->getLogPrefix();
        if (is_array($logs) && $prefix) {
            foreach ($logs as &$log) {
                $log[0] = $prefix . ' ' . $log[0];
            }
        }
        return $logs;
    }

}
