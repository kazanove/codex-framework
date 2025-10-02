<?php

declare(strict_types=1);

namespace CodeX\Providers;

use CodeX\Debug\Bar;
use CodeX\Http\Request;
use CodeX\Provider;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

class Debug extends Provider
{
    public function register(): void
    {
        $debugEnabled = $this->application->config['app']['debug'] ?? false;
        $logPath = $this->application->config['app']['log_path'] ?? dirname(__DIR__, 2) . '/storage/logs/app.log';

        // Регистрируем основной Debug-утилиту
        $this->application->container->singleton(\CodeX\Debug::class, function () use ($debugEnabled, $logPath) {
            // Получаем основной логгер (он может быть файловым или буферизованным — решит boot())
            $logger = $this->application->container->get(LoggerInterface::class);
            return new \CodeX\Debug($debugEnabled, $logPath, $logger);
        });

        // Регистрируем Debug Bar
        $this->application->container->singleton(Bar::class, function () use ($debugEnabled) {
            return new Bar($debugEnabled);
        });
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function boot(): void
    {
        $debugEnabled = $this->application->config['app']['debug'] ?? false;
        if (!$debugEnabled) {
            return;
        }

        // Получаем экземпляры
        $debugBar = $this->application->container->get(Bar::class);
        $container = $this->application->container;

        // Создаём буферизованный логгер для отладочной панели
        $logPath = $this->application->config['app']['log_path'] ?? dirname(__DIR__, 2) . '/storage/logs/app.log';
        $debugLogger = new \CodeX\Logger('debug_bar', $logPath, LogLevel::DEBUG, buffered: true);

        // Подменяем основной логгер на буферизованный (только для текущего запроса)
        $container->instance(LoggerInterface::class, $debugLogger);

        // === Сбор данных запроса ===
        try {
            $request = $container->make(Request::class);
            $requestData = ['method' => $request->getMethod(), 'uri' => $request->getRequestUri(), 'path' => $request->getPathInfo(), 'get' => $request->getQueryParams(), 'post' => $request->getParsedBody(), 'cookies' => $request->getCookieParams(), 'session' => $_SESSION ?? [], 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A', 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',];
            $debugBar->addData('request_data', $requestData);
        } catch (Throwable $e) {
            $debugBar->addData('request_data', ['error' => 'Failed to resolve request: ' . $e->getMessage()]);
        }

        // === Данные контейнера ===
        if (method_exists($container, 'getBindings')) {
            $debugBar->addData('container_bindings', $container->getBindings());
            $debugBar->addData('container_instances', $container->getInstances());
        }

        // === Время старта ===
        $debugBar->addData('start_time', defined('CODEX_START') ? CODEX_START : microtime(true));

        // === Внедрение в ответ при завершении ===
        $this->application->onShutdown(function () use ($debugBar, $debugLogger, $container) {
            // Передаём собранные логи в панель
            $debugBar->addData('logs', $debugLogger->getRecords());

            try {
                $response = $container->make(\CodeX\Http\Response::class);
                $debugBar->injectToResponse($response);
            } catch (Throwable $e) {
                // Игнорируем ошибки при внедрении (например, если ответ уже отправлен)
            }
        });
    }
}