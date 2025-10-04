<?php
declare(strict_types=1);
return[
    'providers'=>[
        \CodeX\Providers\Container::class,
        \CodeX\Providers\Request::class,
        \CodeX\Providers\Response::class,
        \CodeX\Providers\Logger::class,
        \CodeX\Providers\Debug::class,
        \CodeX\Providers\LogViewer::class,
        \CodeX\Providers\Router::class,
        \CodeX\Providers\View::class,
        \CodeX\Providers\Auth::class,
    ],
    'app' => [
        'debug' => false,
    ],
    'shutdown'=>[
        [\CodeX\Http\Response::class, 'send'],
    ],
];