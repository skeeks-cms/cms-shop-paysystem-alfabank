<?php
return [
    'components' => [
        'shop' => [
            'paysystemHandlers' => [
                'alfabank' => [
                    'class' => \skeeks\cms\shop\alfabank\AlfabankPaysystemHandler::class
                ]
            ],
        ],

        'log' => [
            'targets' => [
                [
                    'class'      => 'yii\log\FileTarget',
                    'levels'     => ['info', 'warning', 'error'],
                    'logVars'    => [],
                    'categories' => [\skeeks\cms\shop\alfabank\AlfabankPaysystemHandler::class, \skeeks\cms\shop\alfabank\controllers\AlfabankController::class],
                    'logFile'    => '@runtime/logs/alfabank-info.log',
                ],

                [
                    'class'      => 'yii\log\FileTarget',
                    'levels'     => ['error'],
                    'logVars'    => [],
                    'categories' => [\skeeks\cms\shop\alfabank\AlfabankPaysystemHandler::class, \skeeks\cms\shop\alfabank\controllers\AlfabankController::class],
                    'logFile'    => '@runtime/logs/alfabank-errors.log',
                ],
            ],
        ],
    ],
];