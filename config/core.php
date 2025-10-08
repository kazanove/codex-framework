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
    'database'=>[
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'host' => 'MySQL-8.4',
                'port' => '3306',
                'database' => 'codex',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => $_ENV['DB_DATABASE'] ?? ROOT . 'database/database.sqlite',
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'port' => '5432',
                'database' => 'codex',
                'username' => 'postgres',
                'password' => '',
                'charset' => 'utf8',
            ],
        ],

    ]
];