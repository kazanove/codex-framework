<?php
declare(strict_types=1);
return[
    'providers'=>[
        \CodeX\Providers\Container::class,
        \CodeX\Providers\Request::class,
        \CodeX\Providers\Response::class,
        \CodeX\Providers\Logger::class,
        \CodeX\Providers\Debug::class,
        \CodeX\Providers\Router::class,
    ],
    'app' => [
        'debug' => true,
        'log_path' => dirname(__DIR__) . '/storage/logs/app.log',
        'debug_log_path' => __DIR__ . '/storage/logs/debug',
    ],
    'shutdown'=>[
        [\CodeX\Http\Response::class, 'send'],
    ],
    'middleware' => [
        \CodeX\Middleware\Cors::class,
    ],
    'error_pages' => [
        '404' => static function (\CodeX\Http\Request $request) {
            return '<h1>Страница не найдена</h1><p>URL: ' . htmlspecialchars($request->getPathInfo()) . '</p>';
        },
        // Можно также указать:
        // '404' => [App\Controllers\ErrorController::class, 'notFound'],
        // '404' => 'App\Controllers\ErrorController@notFound',
        // '404' => __DIR__ . '/views/errors/404.html',
    ],
];