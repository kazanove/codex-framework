<?php
declare(strict_types=1);

namespace CodeX\Providers;

use CodeX\Provider;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Logger extends Provider
{
    public function register(): void
    {
        $debug = $this->application->config['app']['debug'] ?? false;
        $logPath = $this->application->config['app']['log_path'] ?? dirname(__DIR__) . '/storage/logs/';

        $this->application->container->singleton(LoggerInterface::class, function () use ($debug, $logPath) {
            $minLevel = $debug ? LogLevel::DEBUG : LogLevel::WARNING;
            return new \CodeX\Logger('app', $logPath, $minLevel);
        });

        // Отдельный канал для отладки
        $this->application->container->singleton('logger.debug', function () use ($logPath) {
            return new \CodeX\Logger('debug', $logPath, LogLevel::DEBUG);
        });

        // Отдельный канал для ошибок
        $this->application->container->singleton('logger.error', function () use ($logPath) {
            return new \CodeX\Logger('error', $logPath, LogLevel::ERROR);
        });
    }
}