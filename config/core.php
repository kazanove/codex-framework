<?php
declare(strict_types=1);
return[
    'providers'=>[
        \CodeX\Providers\Container::class,
        \CodeX\Providers\Request::class,
        \CodeX\Providers\Response::class,
        \CodeX\Providers\Logger::class,
        \CodeX\Providers\Debug::class,
    ],
    'app' => [
        'debug' => true,
        'log_path' => dirname(__DIR__) . '/storage/logs/app.log',
    ],
    'shutdown'=>[
        [\CodeX\Debug\Bar::class, 'injectToResponse'],
        [\CodeX\Http\Response::class, 'send'],
    ],
    'middleware' => [
        \CodeX\Middleware\Cors::class,
    ],
];