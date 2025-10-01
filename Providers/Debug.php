<?php
declare(strict_types=1);

namespace CodeX\Providers;

use CodeX\Provider;
use Psr\Log\LoggerInterface;
use ReflectionException;

class Debug extends Provider
{
    public function register(): void
    {
        $debugEnabled = $this->application->config['app']['debug'] ?? false;
        $logPath = $this->application->config['app']['debug_log_path'] ?? null;

        // Регистрируем Debug
        $this->application->container->singleton(\CodeX\Debug::class, function () use ($debugEnabled, $logPath) {
            $logger = $this->application->container->get(LoggerInterface::class);
            return new \CodeX\Debug($debugEnabled, $logPath, $logger);
        });

        // Регистрируем DebugBar
        $this->application->container->singleton(\CodeX\Debug\Bar::class, function () use ($debugEnabled) {
            return new \CodeX\Debug\Bar($debugEnabled);
        });
    }

    /**
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $debugEnabled = $this->application->config['app']['debug'] ?? false;
        if (!$debugEnabled) {
            return;
        }

        $debugBar = $this->application->container->make(\CodeX\Debug\Bar::class);

        // Запоминаем время старта
        $debugBar->addData('start_time', microtime(true));

        // Перехватываем Response для внедрения панели
        $this->application->onShutdown(function () use ($debugBar) {
            $response = $this->application->container->make(\CodeX\Http\Response::class);
            $request = $this->application->container->make(\CodeX\Http\Request::class);

            $debugBar->addData('request', $request);
            $debugBar->injectToResponse($response);
        });
    }
}