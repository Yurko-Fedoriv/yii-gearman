<?php
//You should change path to where lib was checked out in real application.
//For example ROOT . '/extensions/yii-gearman'
Yii::setPathOfAlias('gearman', ROOT . '/../');
return array(
    'name' => 'skeleton',
    'basePath' => ROOT,

    'preload' => array('log'),

    'commandMap' => array(
        'worker' => array('class' => '\gearman\WorkerCommand'),
    ),
    'modules' => array(
        'gearman' => array('class' => '\gearman\Module'),
    ),

    'components' => array(
        'log' => array(
            'class' => 'CLogRouter',
            'routes' => array(
                array(
                    'filter' => '\gearman\LogFilter',
                    'class' => 'CFileLogRoute',
                    'levels' => 'error, warning, info',
                ),
            ),
        ),
    ),
);