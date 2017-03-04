<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 27.01.17
 * Time: 11:26
 */

use rollun\actionrender\Factory\ActionRenderAbstractFactory;
use rollun\actionrender\Factory\MiddlewarePipeAbstractFactory;
use rollun\actionrender\Renderer\ResponseRendererAbstractFactory;
use rollun\actionrender\Factory\LazyLoadDirectAbstractFactory;
use rollun\callback\Callback\Interruptor\Factory\AbstractInterruptorAbstractFactory;
use rollun\callback\Callback\Interruptor\Factory\AbstractMultiplexerFactory;
use rollun\callback\Callback\Interruptor\Factory\CronMultiplexerFactory;
use rollun\callback\Callback\Interruptor\Factory\MultiplexerAbstractFactory;
use rollun\callback\Callback\Interruptor\Factory\TickerAbstractFactory;
use rollun\callback\Example;

return [
    'dependencies' => [
        'invokables' => [
            'httpCallback' =>
                \rollun\callback\Middleware\HttpInterruptorAction::class,
        ],
        'abstract_factories' => [
            \rollun\callback\Callback\Interruptor\Factory\MultiplexerAbstractFactory::class,
            \rollun\callback\Callback\Interruptor\Factory\TickerAbstractFactory::class,
            \rollun\actionrender\Factory\LazyLoadDirectAbstractFactory::class,
        ]
    ],

    AbstractInterruptorAbstractFactory::KEY => [
        'sec_multiplexer' => [
            MultiplexerAbstractFactory::KEY_CLASS => Example\CronSecMultiplexer::class,
        ],
        'min_multiplexer' => [
            MultiplexerAbstractFactory::KEY_CLASS => Example\CronMinMultiplexer::class,
            MultiplexerAbstractFactory::KEY_INTERRUPTERS_SERVICE => [
                'cron_sec_ticker'
            ]
        ],
        'cron_sec_ticker' => [
            TickerAbstractFactory::KEY_CLASS => \rollun\callback\Callback\Interruptor\Ticker::class,
            TickerAbstractFactory::KEY_WRAPPER_CLASS => \rollun\callback\Callback\Interruptor\Process::class,
            TickerAbstractFactory::KEY_CALLBACK => 'sec_multiplexer',
        ],
        'cron' => [
            TickerAbstractFactory::KEY_CLASS => \rollun\callback\Callback\Interruptor\Ticker::class,
            TickerAbstractFactory::KEY_WRAPPER_CLASS => \rollun\callback\Callback\Interruptor\Process::class,
            TickerAbstractFactory::KEY_CALLBACK => 'min_multiplexer',
            TickerAbstractFactory::KEY_TICKS_COUNT => 1,
        ]
    ],

    ResponseRendererAbstractFactory::KEY_RESPONSE_RENDERER => [
        'webhookJsonRender' => [
            ResponseRendererAbstractFactory::KEY_ACCEPT_TYPE_PATTERN => [
                //pattern => middleware-Service-Name
                '/application\/json/' => \rollun\actionrender\Renderer\Json\JsonRendererAction::class,
            ]
        ]
    ],

    ActionRenderAbstractFactory::KEY_AR => [
        'webhookActionRender' => [
            ActionRenderAbstractFactory::KEY_AR_MIDDLEWARE => [
                ActionRenderAbstractFactory::KEY_ACTION_MIDDLEWARE_SERVICE => 'webhookLazyLoad',
                ActionRenderAbstractFactory::KEY_RENDER_MIDDLEWARE_SERVICE => 'webhookJsonRender'
            ]
        ]
    ],

    LazyLoadDirectAbstractFactory::KEY_LAZY_LOAD => [
        'webhookLazyLoad' => [
            LazyLoadDirectAbstractFactory::KEY_DIRECT_FACTORY =>
                \rollun\callback\Middleware\Factory\InterruptorDirectFactory::class
        ]
    ],
];
