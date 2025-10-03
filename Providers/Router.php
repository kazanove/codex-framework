<?php

declare(strict_types=1);

namespace CodeX\Providers;

use CodeX\Provider;
use ReflectionException;

class Router extends Provider
{
    public function register(): void
    {
        $this->application->container->singleton(\CodeX\Router::class, function () {
            return new \CodeX\Router($this->application);
        });
    }

    public function boot(): void
    {
        $router = $this->application->container->get(\CodeX\Router::class);
        // Загружаем маршруты
        $this->loadRoutes($router);

        // Диспетчеризация
        $this->application->container->call([$router, 'dispatcher']);
    }

    private function loadRoutes(\CodeX\Router $router): void
    {
        $routingDir = $this->application->dirApp . 'config' . DIRECTORY_SEPARATOR . 'routing' . DIRECTORY_SEPARATOR;

        if (!is_dir($routingDir)) {
            return; // Нет директории маршрутизации
        }

        $files = ['web.php', 'api.php']; // Поддержка нескольких файлов

        foreach ($files as $file) {
            $path = $routingDir . $file;
            if (file_exists($path)) {
                $routerInstance = $router; // Для замыкания
                include $path;
            }
        }
    }
}